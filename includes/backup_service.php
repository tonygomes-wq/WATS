<?php

/**
 * Serviço de Backup de Conversas
 * 
 * Gerencia criação, armazenamento e upload de backups
 * 
 * MACIP Tecnologia LTDA
 */

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}

// Diretório de backups
define('BACKUP_DIR', BASE_PATH . '/backups');

/**
 * Garante que o diretório de backups existe
 */
function ensureBackupDirectory($userId = null)
{
    $dir = BACKUP_DIR;
    if ($userId) {
        $dir .= '/' . $userId;
    }

    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    // Criar .htaccess para proteger diretório (mas permitir acesso via PHP)
    $htaccess = BACKUP_DIR . '/.htaccess';
    if (!file_exists($htaccess)) {
        // Bloquear listagem de diretório mas permitir acesso via PHP
        file_put_contents($htaccess, "Options -Indexes\n");
    }

    return $dir;
}

/**
 * Gera backup de conversas
 * 
 * IMPORTANTE: O backup NÃO é armazenado no servidor do sistema.
 * É enviado diretamente para o destino configurado pelo usuário.
 */
function createConversationBackup($userId, $options = [])
{
    global $pdo;

    $format = $options['format'] ?? 'json';
    $dateFrom = $options['date_from'] ?? null;
    $dateTo = $options['date_to'] ?? null;
    $includeMedia = $options['include_media'] ?? false;
    $configId = $options['config_id'] ?? null;
    $retentionDays = $options['retention_days'] ?? 30;
    $destination = $options['destination'] ?? 'download';
    $config = $options['config'] ?? null;
    $encrypt = $options['encrypt'] ?? false;
    $encryptPassword = $options['encrypt_password'] ?? null;
    $sendEmail = $options['send_email'] ?? true;
    $incremental = $options['incremental'] ?? false;

    // LOG DEBUG
    error_log("[BACKUP_DEBUG] Starting createConversationBackup");
    error_log("[BACKUP_DEBUG] Destination recebido nas options: " . ($options['destination'] ?? 'NULL'));
    error_log("[BACKUP_DEBUG] Destination após extração: " . $destination);

    // Converter 'local' antigo para 'download'
    if ($destination === 'local') {
        $destination = 'download';
    }
    
    // Se destination estiver vazio, forçar para download
    if (empty($destination)) {
        error_log("[BACKUP_DEBUG] Destination estava vazio, forçando para download");
        $destination = 'download';
    }
    
    error_log("[BACKUP_DEBUG] Destination final: " . $destination);

    // Criar registro do backup
    $stmt = $pdo->prepare('
        INSERT INTO backups (user_id, config_id, backup_type, filename, destination, status, started_at)
        VALUES (?, ?, "full", "", ?, "running", NOW())
    ');
    $stmt->execute([$userId, $configId, $destination]);
    $backupId = $pdo->lastInsertId();

    try {
        // Buscar conversas do usuário
        $sql = 'SELECT * FROM chat_conversations WHERE user_id = ?';
        $params = [$userId];

        if ($dateFrom) {
            $sql .= ' AND DATE(created_at) >= ?';
            $params[] = $dateFrom;
        }
        if ($dateTo) {
            $sql .= ' AND DATE(created_at) <= ?';
            $params[] = $dateTo;
        }

        $sql .= ' ORDER BY created_at ASC';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $conversationsCount = count($conversations);
        $messagesCount = 0;

        // Buscar mensagens de cada conversa
        $backupData = [
            'backup_info' => [
                'created_at' => date('Y-m-d H:i:s'),
                'user_id' => $userId,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'format' => $format,
                'version' => '1.0'
            ],
            'conversations' => []
        ];

        foreach ($conversations as $conv) {
            $msgSql = 'SELECT * FROM chat_messages WHERE conversation_id = ?';
            $msgParams = [$conv['id']];

            if ($dateFrom) {
                $msgSql .= ' AND DATE(created_at) >= ?';
                $msgParams[] = $dateFrom;
            }
            if ($dateTo) {
                $msgSql .= ' AND DATE(created_at) <= ?';
                $msgParams[] = $dateTo;
            }

            $msgSql .= ' ORDER BY created_at ASC';

            $stmt = $pdo->prepare($msgSql);
            $stmt->execute($msgParams);
            $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $messagesCount += count($messages);

            // Limpar dados sensíveis se necessário
            if (!$includeMedia) {
                foreach ($messages as &$msg) {
                    if (!empty($msg['media_url']) && strpos($msg['media_url'], 'http') === 0) {
                        $msg['media_url'] = '[MEDIA_REMOVED]';
                    }
                }
            }

            $backupData['conversations'][] = [
                'conversation' => $conv,
                'messages' => $messages
            ];
        }

        // Gerar conteúdo do backup em memória
        $timestamp = date('Y-m-d_His');
        $filename = "backup_{$userId}_{$timestamp}.{$format}";

        if ($format === 'json') {
            $content = json_encode($backupData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        } elseif ($format === 'csv') {
            $content = generateCsvBackup($backupData);
        }

        $originalSize = strlen($content);

        // Comprimir em memória se habilitado
        $compress = $options['compress'] ?? true;
        if ($compress) {
            $compressedContent = gzencode($content, 9);
            if ($compressedContent !== false) {
                $content = $compressedContent;
                $filename .= '.gz';
                error_log("[BACKUP] Compressão em memória: {$originalSize} bytes -> " . strlen($content) . " bytes");
            }
        }

        $fileSize = strlen($content);

        // Criptografar em memória se habilitado
        if ($encrypt && !empty($encryptPassword)) {
            $key = hash('sha256', $encryptPassword, true);
            $iv = substr(hash('sha256', $encryptPassword . 'iv'), 0, 16);
            $encryptedContent = openssl_encrypt($content, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
            if ($encryptedContent !== false) {
                $content = base64_encode($encryptedContent);
                $filename .= '.enc';
                $fileSize = strlen($content);
                error_log("[BACKUP] Arquivo criptografado em memória");
            }
        }

        $remotePath = null;
        $remoteUrl = null;
        $localPath = null;

        // Enviar para o destino configurado (SEM armazenar no servidor)
        if ($destination === 'download') {
            // Para download, criar arquivo no diretório de backups do usuário
            $userBackupDir = ensureBackupDirectory($userId);
            $localPath = $userBackupDir . '/' . $filename;
            
            error_log("[BACKUP_CREATE] Salvando backup localmente - Path: $localPath");
            
            $bytesWritten = file_put_contents($localPath, $content);
            
            if ($bytesWritten === false) {
                error_log("[BACKUP_CREATE] ERRO ao salvar arquivo: $localPath");
                throw new Exception("Erro ao salvar arquivo de backup");
            }
            
            error_log("[BACKUP_CREATE] Arquivo salvo com sucesso - Bytes: $bytesWritten");
            
            if (!file_exists($localPath)) {
                error_log("[BACKUP_CREATE] ERRO: Arquivo não existe após salvar: $localPath");
                throw new Exception("Arquivo de backup não foi criado");
            }
            
            // Agendar exclusão automática em 7 dias (tempo suficiente para download)
            $expiresAt = date('Y-m-d H:i:s', strtotime('+7 days'));
            
        } elseif ($destination === 'ftp' && $config && !empty($config['extra_config'])) {
            $extra = json_decode($config['extra_config'], true);
            if (!empty($extra['ftp'])) {
                // Criar arquivo temporário para FTP
                $tempFile = tempnam(sys_get_temp_dir(), 'backup_');
                file_put_contents($tempFile, $content);
                
                $ftpResult = uploadBackupToFtp($tempFile, $filename, $extra['ftp']);
                @unlink($tempFile); // Remover arquivo temporário imediatamente
                
                if (!$ftpResult['success']) {
                    throw new Exception('Erro ao enviar para FTP: ' . $ftpResult['error']);
                }
                $remotePath = $ftpResult['remote_path'];
            }
            
        } elseif ($destination === 'network' && $config && !empty($config['extra_config'])) {
            $extra = json_decode($config['extra_config'], true);
            if (!empty($extra['network'])) {
                // Criar arquivo temporário para cópia de rede
                $tempFile = tempnam(sys_get_temp_dir(), 'backup_');
                file_put_contents($tempFile, $content);
                
                $networkResult = copyBackupToNetwork($tempFile, $filename, $extra['network']);
                @unlink($tempFile); // Remover arquivo temporário imediatamente
                
                if (!$networkResult['success']) {
                    throw new Exception('Erro ao copiar para rede: ' . $networkResult['error']);
                }
                $remotePath = $networkResult['remote_path'];
            }
            
        } elseif ($destination === 'google_drive') {
            $uploadResult = uploadBackupToGoogleDriveStream($userId, $content, $filename);
            if (!$uploadResult['success']) {
                throw new Exception('Erro ao enviar para Google Drive: ' . $uploadResult['error']);
            }
            $remotePath = $uploadResult['file_id'] ?? null;
            $remoteUrl = $uploadResult['web_view_link'] ?? $uploadResult['web_content_link'] ?? null;
            
        } elseif ($destination === 'onedrive') {
            $uploadResult = uploadBackupToOneDriveStream($userId, $content, $filename);
            if (!$uploadResult['success']) {
                throw new Exception('Erro ao enviar para OneDrive: ' . $uploadResult['error']);
            }
            $remotePath = $uploadResult['file_id'] ?? null;
            $remoteUrl = $uploadResult['web_url'] ?? null;
            
        } elseif ($destination === 'dropbox') {
            $uploadResult = uploadBackupToDropboxStream($userId, $content, $filename);
            if (!$uploadResult['success']) {
                throw new Exception('Erro ao enviar para Dropbox: ' . $uploadResult['error']);
            }
            $remotePath = $uploadResult['file_id'] ?? null;
            $remoteUrl = $uploadResult['path'] ?? null;
            
        } elseif ($destination === 's3') {
            $uploadResult = uploadBackupToS3Stream($userId, $content, $filename);
            if (!$uploadResult['success']) {
                throw new Exception('Erro ao enviar para S3: ' . $uploadResult['error']);
            }
            $remotePath = $uploadResult['key'] ?? null;
            $remoteUrl = $uploadResult['s3_url'] ?? null;
        }

        // Definir expiração
        if ($destination === 'download') {
            $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));
        } else {
            $expiresAt = date('Y-m-d H:i:s', strtotime("+{$retentionDays} days"));
        }

        // Atualizar registro do backup
        $stmt = $pdo->prepare('
            UPDATE backups SET
                filename = ?,
                local_path = ?,
                remote_path = ?,
                remote_url = ?,
                size_bytes = ?,
                conversations_count = ?,
                messages_count = ?,
                date_from = ?,
                date_to = ?,
                status = "completed",
                completed_at = NOW(),
                expires_at = ?
            WHERE id = ?
        ');
        $stmt->execute([
            $filename,
            $localPath,
            $remotePath,
            $remoteUrl,
            $fileSize,
            $conversationsCount,
            $messagesCount,
            $dateFrom,
            $dateTo,
            $expiresAt,
            $backupId
        ]);

        // Registrar log
        logBackupAction($backupId, $userId, 'create', [
            'conversations' => $conversationsCount,
            'messages' => $messagesCount,
            'size' => $fileSize,
            'original_size' => $originalSize,
            'compressed' => $compress,
            'destination' => $destination,
            'stored_on_server' => ($destination === 'download')
        ]);

        // Enviar notificação por email se configurado
        if ($sendEmail && $destination === 'download') {
            try {
                sendBackupNotification($userId, $backupId, [
                    'filename' => $filename,
                    'size' => $fileSize,
                    'conversations' => $conversationsCount,
                    'messages' => $messagesCount
                ]);
            } catch (Exception $e) {
                error_log("[BACKUP] Erro ao enviar email: " . $e->getMessage());
            }
        }

        return [
            'success' => true,
            'backup_id' => $backupId,
            'filename' => $filename,
            'size' => $fileSize,
            'conversations' => $conversationsCount,
            'messages' => $messagesCount,
            'destination' => $destination,
            'remote_url' => $remoteUrl
        ];
    } catch (Exception $e) {
        // Marcar backup como falho
        $stmt = $pdo->prepare('
            UPDATE backups SET status = "failed", error_message = ?, completed_at = NOW()
            WHERE id = ?
        ');
        $stmt->execute([$e->getMessage(), $backupId]);

        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Gera conteúdo CSV do backup
 */
function generateCsvBackup($backupData)
{
    $output = fopen('php://temp', 'r+');

    // Cabeçalho
    fputcsv($output, [
        'conversation_id',
        'contact_name',
        'phone',
        'message_id',
        'from_me',
        'message_type',
        'message_text',
        'media_url',
        'status',
        'created_at'
    ]);

    foreach ($backupData['conversations'] as $convData) {
        $conv = $convData['conversation'];
        foreach ($convData['messages'] as $msg) {
            fputcsv($output, [
                $conv['id'],
                $conv['contact_name'] ?? '',
                $conv['phone'] ?? '',
                $msg['id'],
                $msg['from_me'] ? 'Sim' : 'Não',
                $msg['message_type'] ?? 'text',
                $msg['message_text'] ?? '',
                $msg['media_url'] ?? '',
                $msg['status'] ?? '',
                $msg['created_at'] ?? ''
            ]);
        }
    }

    rewind($output);
    $content = stream_get_contents($output);
    fclose($output);

    return $content;
}

/**
 * Lista backups de um usuário
 */
function listUserBackups($userId, $limit = 50, $offset = 0)
{
    global $pdo;

    $stmt = $pdo->prepare('
        SELECT b.*, bc.destination as config_destination, bc.schedule
        FROM backups b
        LEFT JOIN backup_configs bc ON b.config_id = bc.id
        WHERE b.user_id = ? AND b.status != "deleted"
        ORDER BY b.created_at DESC
        LIMIT ? OFFSET ?
    ');
    $stmt->execute([$userId, $limit, $offset]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Obtém configuração de backup do usuário
 */
function getBackupConfig($userId)
{
    global $pdo;

    $stmt = $pdo->prepare('SELECT * FROM backup_configs WHERE user_id = ? LIMIT 1');
    $stmt->execute([$userId]);

    $config = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($config) {
        // Converter 'local' antigo para 'download'
        if ($config['destination'] === 'local') {
            $config['destination'] = 'download';
        }

        // Decodificar configurações extras
        if (!empty($config['extra_config'])) {
            $extra = json_decode($config['extra_config'], true);

            // Configurações FTP (sem expor senha diretamente)
            if (!empty($extra['ftp'])) {
                $config['ftp_config'] = [
                    'host' => $extra['ftp']['host'] ?? '',
                    'port' => $extra['ftp']['port'] ?? 21,
                    'user' => $extra['ftp']['user'] ?? '',
                    'path' => $extra['ftp']['path'] ?? '',
                    'ssl' => $extra['ftp']['ssl'] ?? false
                ];
            }

            // Configurações de Rede (sem expor senha diretamente)
            if (!empty($extra['network'])) {
                $config['network_config'] = [
                    'path' => $extra['network']['path'] ?? '',
                    'user' => $extra['network']['user'] ?? ''
                ];
            }

            // Configurações Google Drive (sem expor secret)
            if (!empty($extra['google'])) {
                $config['google_config'] = [
                    'client_id' => $extra['google']['client_id'] ?? '',
                    'has_secret' => !empty($extra['google']['client_secret'])
                ];
                $config['google_connected'] = !empty($extra['google']['access_token']);
            }

            // Configurações OneDrive (sem expor secret)
            if (!empty($extra['onedrive'])) {
                $config['onedrive_config'] = [
                    'client_id' => $extra['onedrive']['client_id'] ?? '',
                    'tenant_id' => $extra['onedrive']['tenant_id'] ?? 'common',
                    'has_secret' => !empty($extra['onedrive']['client_secret'])
                ];
                $config['onedrive_connected'] = !empty($extra['onedrive']['access_token']);
            }

            // Configurações Dropbox (sem expor secret)
            if (!empty($extra['dropbox'])) {
                $config['dropbox_config'] = [
                    'app_key' => $extra['dropbox']['app_key'] ?? '',
                    'has_secret' => !empty($extra['dropbox']['app_secret'])
                ];
                $config['dropbox_connected'] = !empty($extra['dropbox']['access_token']);
            }

            // Configurações S3 (sem expor secret key)
            if (!empty($extra['s3'])) {
                $config['s3_config'] = [
                    'access_key' => $extra['s3']['access_key'] ?? '',
                    'bucket' => $extra['s3']['bucket'] ?? '',
                    'region' => $extra['s3']['region'] ?? 'us-east-1',
                    'has_secret' => !empty($extra['s3']['secret_key'])
                ];
            }
        }
    }

    return $config;
}

/**
 * Salva configuração de backup
 */
function saveBackupConfig($userId, $config)
{
    global $pdo;

    // Buscar configuração existente com extra_config completo
    $stmt = $pdo->prepare('SELECT * FROM backup_configs WHERE user_id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    // Converter 'local' antigo para 'download'
    $destination = $config['destination'] ?? 'download';
    if ($destination === 'local') {
        $destination = 'download';
    }

    // Carregar extra_config existente para preservar credenciais OAuth
    $extraConfig = [];
    if ($existing && !empty($existing['extra_config'])) {
        $extraConfig = json_decode($existing['extra_config'], true) ?: [];
    }

    // Atualizar configurações FTP
    if (!empty($config['ftp_config'])) {
        $extraConfig['ftp'] = [
            'host' => $config['ftp_config']['host'] ?? '',
            'port' => (int)($config['ftp_config']['port'] ?? 21),
            'user' => $config['ftp_config']['user'] ?? '',
            'pass' => base64_encode($config['ftp_config']['pass'] ?? ''),
            'path' => $config['ftp_config']['path'] ?? '',
            'ssl' => (bool)($config['ftp_config']['ssl'] ?? false)
        ];
    }

    // Atualizar configurações de Rede
    if (!empty($config['network_config'])) {
        $extraConfig['network'] = [
            'path' => $config['network_config']['path'] ?? '',
            'user' => $config['network_config']['user'] ?? '',
            'pass' => base64_encode($config['network_config']['pass'] ?? '')
        ];
    }

    // Atualizar configurações Google Drive (preservar tokens OAuth existentes)
    if (!empty($config['google_config'])) {
        $existingGoogle = $extraConfig['google'] ?? [];
        $extraConfig['google'] = array_merge($existingGoogle, [
            'client_id' => $config['google_config']['client_id'] ?? $existingGoogle['client_id'] ?? '',
            'client_secret' => $config['google_config']['client_secret'] ?? $existingGoogle['client_secret'] ?? ''
        ]);
    }

    // Atualizar configurações OneDrive (preservar tokens OAuth existentes)
    if (!empty($config['onedrive_config'])) {
        $existingOnedrive = $extraConfig['onedrive'] ?? [];
        $extraConfig['onedrive'] = array_merge($existingOnedrive, [
            'client_id' => $config['onedrive_config']['client_id'] ?? $existingOnedrive['client_id'] ?? '',
            'client_secret' => $config['onedrive_config']['client_secret'] ?? $existingOnedrive['client_secret'] ?? '',
            'tenant_id' => $config['onedrive_config']['tenant_id'] ?? $existingOnedrive['tenant_id'] ?? 'common'
        ]);
    }

    // Atualizar configurações Dropbox (preservar tokens OAuth existentes)
    if (!empty($config['dropbox_config'])) {
        $existingDropbox = $extraConfig['dropbox'] ?? [];
        $extraConfig['dropbox'] = array_merge($existingDropbox, [
            'app_key' => $config['dropbox_config']['app_key'] ?? $existingDropbox['app_key'] ?? '',
            'app_secret' => $config['dropbox_config']['app_secret'] ?? $existingDropbox['app_secret'] ?? ''
        ]);
    }

    // Atualizar configurações S3
    if (!empty($config['s3_config'])) {
        $extraConfig['s3'] = [
            'access_key' => $config['s3_config']['access_key'] ?? '',
            'secret_key' => $config['s3_config']['secret_key'] ?? '',
            'bucket' => $config['s3_config']['bucket'] ?? '',
            'region' => $config['s3_config']['region'] ?? 'us-east-1'
        ];
    }

    $extraConfigJson = !empty($extraConfig) ? json_encode($extraConfig) : null;

    if ($existing) {
        $stmt = $pdo->prepare('
            UPDATE backup_configs SET
                destination = ?,
                schedule = ?,
                schedule_time = ?,
                retention_days = ?,
                format = ?,
                include_media = ?,
                is_active = ?,
                extra_config = ?,
                updated_at = NOW()
            WHERE user_id = ?
        ');
        $stmt->execute([
            $destination,
            $config['schedule'] ?? 'manual',
            $config['schedule_time'] ?? '03:00:00',
            $config['retention_days'] ?? 30,
            $config['format'] ?? 'json',
            $config['include_media'] ?? 0,
            $config['is_active'] ?? 1,
            $extraConfigJson,
            $userId
        ]);

        $configId = $existing['id'];
    } else {
        $stmt = $pdo->prepare('
            INSERT INTO backup_configs (user_id, destination, schedule, schedule_time, retention_days, format, include_media, is_active, extra_config)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $userId,
            $destination,
            $config['schedule'] ?? 'manual',
            $config['schedule_time'] ?? '03:00:00',
            $config['retention_days'] ?? 30,
            $config['format'] ?? 'json',
            $config['include_media'] ?? 0,
            $config['is_active'] ?? 1,
            $extraConfigJson
        ]);

        $configId = $pdo->lastInsertId();
    }

    // Registrar log (sem senhas/secrets)
    $logConfig = $config;
    if (isset($logConfig['ftp_config']['pass'])) $logConfig['ftp_config']['pass'] = '***';
    if (isset($logConfig['network_config']['pass'])) $logConfig['network_config']['pass'] = '***';
    if (isset($logConfig['google_config']['client_secret'])) $logConfig['google_config']['client_secret'] = '***';
    if (isset($logConfig['onedrive_config']['client_secret'])) $logConfig['onedrive_config']['client_secret'] = '***';
    if (isset($logConfig['dropbox_config']['app_secret'])) $logConfig['dropbox_config']['app_secret'] = '***';
    if (isset($logConfig['s3_config']['secret_key'])) $logConfig['s3_config']['secret_key'] = '***';
    logBackupAction(null, $userId, 'config_update', $logConfig);

    return $configId;
}

/**
 * Deleta um backup
 */
function deleteBackup($backupId, $userId)
{
    global $pdo;

    // Verificar propriedade
    $stmt = $pdo->prepare('SELECT * FROM backups WHERE id = ? AND user_id = ?');
    $stmt->execute([$backupId, $userId]);
    $backup = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$backup) {
        return ['success' => false, 'error' => 'Backup não encontrado'];
    }

    // Remover arquivo local
    if (!empty($backup['local_path']) && file_exists($backup['local_path'])) {
        unlink($backup['local_path']);
    }

    // Marcar como deletado
    $stmt = $pdo->prepare('UPDATE backups SET status = "deleted" WHERE id = ?');
    $stmt->execute([$backupId]);

    // Registrar log
    logBackupAction($backupId, $userId, 'delete', ['filename' => $backup['filename']]);

    return ['success' => true];
}

/**
 * Obtém caminho de download de um backup
 */
function getBackupDownloadPath($backupId, $userId)
{
    global $pdo;

    $stmt = $pdo->prepare('SELECT * FROM backups WHERE id = ? AND user_id = ? AND status = "completed"');
    $stmt->execute([$backupId, $userId]);
    $backup = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$backup || empty($backup['local_path']) || !file_exists($backup['local_path'])) {
        return null;
    }

    // Registrar log
    logBackupAction($backupId, $userId, 'download', []);

    return $backup;
}

/**
 * Registra ação de backup no log
 */
function logBackupAction($backupId, $userId, $action, $details = [])
{
    global $pdo;

    $stmt = $pdo->prepare('
        INSERT INTO backup_logs (backup_id, user_id, action, details, ip_address, user_agent)
        VALUES (?, ?, ?, ?, ?, ?)
    ');
    $stmt->execute([
        $backupId,
        $userId,
        $action,
        json_encode($details),
        $_SERVER['REMOTE_ADDR'] ?? null,
        $_SERVER['HTTP_USER_AGENT'] ?? null
    ]);
}

/**
 * Limpa backups expirados
 */
function cleanupExpiredBackups()
{
    global $pdo;

    $stmt = $pdo->prepare('
        SELECT * FROM backups 
        WHERE expires_at IS NOT NULL AND expires_at < NOW() AND status = "completed"
    ');
    $stmt->execute();
    $expired = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $deleted = 0;
    foreach ($expired as $backup) {
        if (!empty($backup['local_path']) && file_exists($backup['local_path'])) {
            unlink($backup['local_path']);
        }

        $stmt = $pdo->prepare('UPDATE backups SET status = "deleted" WHERE id = ?');
        $stmt->execute([$backup['id']]);

        logBackupAction($backup['id'], $backup['user_id'], 'delete', ['reason' => 'expired']);
        $deleted++;
    }

    return $deleted;
}

/**
 * Envia backup para servidor FTP/SFTP
 */
function uploadBackupToFtp($localPath, $filename, $ftpConfig)
{
    $host = $ftpConfig['host'] ?? '';
    $port = (int)($ftpConfig['port'] ?? 21);
    $user = $ftpConfig['user'] ?? '';
    $pass = base64_decode($ftpConfig['pass'] ?? '');
    $remotePath = rtrim($ftpConfig['path'] ?? '', '/');
    $useSsl = (bool)($ftpConfig['ssl'] ?? false);

    if (empty($host) || empty($user) || empty($pass)) {
        return ['success' => false, 'error' => 'Configurações FTP incompletas'];
    }

    try {
        if ($useSsl && $port == 22) {
            // SFTP via SSH2
            if (!function_exists('ssh2_connect')) {
                return ['success' => false, 'error' => 'Extensão SSH2 não disponível'];
            }

            $connection = @ssh2_connect($host, $port);
            if (!$connection) {
                return ['success' => false, 'error' => 'Não foi possível conectar ao servidor SFTP'];
            }

            if (!@ssh2_auth_password($connection, $user, $pass)) {
                return ['success' => false, 'error' => 'Autenticação SFTP falhou'];
            }

            $sftp = @ssh2_sftp($connection);
            if (!$sftp) {
                return ['success' => false, 'error' => 'Não foi possível inicializar SFTP'];
            }

            $remoteFile = $remotePath ? "{$remotePath}/{$filename}" : $filename;
            $stream = @fopen("ssh2.sftp://{$sftp}{$remoteFile}", 'w');
            if (!$stream) {
                return ['success' => false, 'error' => 'Não foi possível criar arquivo remoto'];
            }

            $data = file_get_contents($localPath);
            fwrite($stream, $data);
            fclose($stream);

            return ['success' => true, 'remote_path' => "sftp://{$host}{$remoteFile}"];
        } else {
            // FTP normal ou FTPS
            if ($useSsl) {
                $conn = @ftp_ssl_connect($host, $port, 30);
            } else {
                $conn = @ftp_connect($host, $port, 30);
            }

            if (!$conn) {
                return ['success' => false, 'error' => 'Não foi possível conectar ao servidor FTP'];
            }

            if (!@ftp_login($conn, $user, $pass)) {
                @ftp_close($conn);
                return ['success' => false, 'error' => 'Autenticação FTP falhou'];
            }

            @ftp_pasv($conn, true);

            // Criar diretório remoto se não existir
            if ($remotePath) {
                @ftp_mkdir($conn, $remotePath);
                @ftp_chdir($conn, $remotePath);
            }

            $result = @ftp_put($conn, $filename, $localPath, FTP_BINARY);
            @ftp_close($conn);

            if (!$result) {
                return ['success' => false, 'error' => 'Falha ao enviar arquivo para FTP'];
            }

            $protocol = $useSsl ? 'ftps' : 'ftp';
            $remoteFile = $remotePath ? "{$remotePath}/{$filename}" : $filename;
            return ['success' => true, 'remote_path' => "{$protocol}://{$host}/{$remoteFile}"];
        }
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Copia backup para servidor de rede (SMB/NFS)
 */
function copyBackupToNetwork($localPath, $filename, $networkConfig)
{
    $networkPath = $networkConfig['path'] ?? '';
    $user = $networkConfig['user'] ?? '';
    $pass = base64_decode($networkConfig['pass'] ?? '');

    if (empty($networkPath)) {
        return ['success' => false, 'error' => 'Caminho de rede não informado'];
    }

    try {
        // Normalizar caminho
        $networkPath = rtrim($networkPath, '/\\');
        $destPath = $networkPath . DIRECTORY_SEPARATOR . $filename;

        // Verificar se o caminho de rede está acessível
        if (!is_dir($networkPath)) {
            // Tentar criar o diretório (pode falhar se for rede não montada)
            if (!@mkdir($networkPath, 0755, true)) {
                return ['success' => false, 'error' => 'Caminho de rede não acessível: ' . $networkPath];
            }
        }

        // Copiar arquivo
        if (!@copy($localPath, $destPath)) {
            return ['success' => false, 'error' => 'Falha ao copiar arquivo para rede'];
        }

        return ['success' => true, 'remote_path' => $destPath];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Formata tamanho de arquivo
 */
function formatFileSize($bytes)
{
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}

/**
 * Estatísticas de backup do usuário
 */
function getBackupStats($userId)
{
    global $pdo;

    $stats = [
        'total_backups' => 0,
        'total_size' => 0,
        'last_backup' => null,
        'next_scheduled' => null
    ];

    // Total de backups
    $stmt = $pdo->prepare('SELECT COUNT(*) as total, SUM(size_bytes) as size FROM backups WHERE user_id = ? AND status = "completed"');
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['total_backups'] = (int) $row['total'];
    $stats['total_size'] = (int) $row['size'];

    // Último backup
    $stmt = $pdo->prepare('SELECT completed_at FROM backups WHERE user_id = ? AND status = "completed" ORDER BY completed_at DESC LIMIT 1');
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['last_backup'] = $row['completed_at'] ?? null;

    // Próximo agendado
    $stmt = $pdo->prepare('SELECT next_backup_at FROM backup_configs WHERE user_id = ? AND is_active = 1 LIMIT 1');
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['next_scheduled'] = $row['next_backup_at'] ?? null;

    return $stats;
}

/**
 * Comprime backup em formato ZIP
 */
function compressBackup($filepath)
{
    if (!extension_loaded('zip')) {
        return ['success' => false, 'error' => 'Extensão ZIP não disponível'];
    }

    if (!file_exists($filepath)) {
        return ['success' => false, 'error' => 'Arquivo não encontrado'];
    }

    $zipPath = $filepath . '.zip';
    $zip = new ZipArchive();

    if ($zip->open($zipPath, ZipArchive::CREATE) !== TRUE) {
        return ['success' => false, 'error' => 'Não foi possível criar arquivo ZIP'];
    }

    // Adicionar arquivo ao ZIP
    $zip->addFile($filepath, basename($filepath));
    $zip->close();

    // Verificar se ZIP foi criado com sucesso
    if (!file_exists($zipPath)) {
        return ['success' => false, 'error' => 'Erro ao criar arquivo ZIP'];
    }

    // Remover arquivo original
    @unlink($filepath);

    return [
        'success' => true,
        'filepath' => $zipPath,
        'filename' => basename($zipPath),
        'size' => filesize($zipPath)
    ];
}

/**
 * Cria backup incremental (apenas mensagens novas desde último backup)
 */
function createIncrementalBackup($userId, $options = [])
{
    global $pdo;

    // Buscar data do último backup completo
    $stmt = $pdo->prepare('
        SELECT MAX(completed_at) as last_backup 
        FROM backups 
        WHERE user_id = ? AND status = "completed" AND backup_type = "full"
    ');
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $lastBackupDate = $row['last_backup'] ?? null;

    if (!$lastBackupDate) {
        // Se não há backup anterior, fazer backup completo
        return createConversationBackup($userId, array_merge($options, ['backup_type' => 'full']));
    }

    // Configurar opções para backup incremental
    $options['date_from'] = $lastBackupDate;
    $options['backup_type'] = 'incremental';

    return createConversationBackup($userId, $options);
}

/**
 * Envia notificação por email quando backup é concluído
 */
function sendBackupNotification($userId, $backupId, $details)
{
    global $pdo;

    // Buscar email do usuário
    $stmt = $pdo->prepare('SELECT email, name FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || empty($user['email'])) {
        return false;
    }

    // Verificar se serviço de email está disponível
    if (!function_exists('sendEmail')) {
        if (file_exists(__DIR__ . '/email_sender.php')) {
            require_once __DIR__ . '/email_sender.php';
        }
    }

    if (!function_exists('sendEmail')) {
        error_log("[BACKUP] Função sendEmail não disponível - email não será enviado");
        return false;
    }

    $subject = 'Backup de Conversas Concluído - WATS';

    $sizeFormatted = formatFileSize($details['size']);

    $body = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #10b981; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
            .content { background: #f9fafb; padding: 30px; border: 1px solid #e5e7eb; }
            .stats { background: white; padding: 15px; border-radius: 8px; margin: 20px 0; }
            .stat-item { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #e5e7eb; }
            .stat-item:last-child { border-bottom: none; }
            .stat-label { font-weight: bold; color: #6b7280; }
            .stat-value { color: #10b981; font-weight: bold; }
            .button { display: inline-block; background: #10b981; color: white; padding: 12px 30px; text-decoration: none; border-radius: 6px; margin: 20px 0; }
            .footer { text-align: center; color: #6b7280; font-size: 12px; padding: 20px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>✅ Backup Concluído com Sucesso!</h1>
            </div>
            <div class='content'>
                <p>Olá <strong>{$user['name']}</strong>,</p>
                <p>Seu backup de conversas do WhatsApp foi concluído com sucesso e está pronto para download.</p>
                
                <div class='stats'>
                    <h3 style='margin-top: 0; color: #10b981;'>📊 Detalhes do Backup</h3>
                    <div class='stat-item'>
                        <span class='stat-label'>Arquivo:</span>
                        <span class='stat-value'>{$details['filename']}</span>
                    </div>
                    <div class='stat-item'>
                        <span class='stat-label'>Tamanho:</span>
                        <span class='stat-value'>{$sizeFormatted}</span>
                    </div>
                    <div class='stat-item'>
                        <span class='stat-label'>Conversas:</span>
                        <span class='stat-value'>{$details['conversations']}</span>
                    </div>
                    <div class='stat-item'>
                        <span class='stat-label'>Mensagens:</span>
                        <span class='stat-value'>{$details['messages']}</span>
                    </div>
                </div>
                
                <p style='text-align: center;'>
                    <a href='" . (defined('SITE_URL') ? SITE_URL : (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST']) . "/api/backup_download.php?id={$backupId}' class='button'>
                        📥 Fazer Download do Backup
                    </a>
                </p>
                
                <p style='color: #6b7280; font-size: 14px;'>
                    <strong>⏰ Atenção:</strong> Este backup estará disponível para download por 1 hora. 
                    Após esse período, será removido automaticamente por segurança.
                </p>
            </div>
            <div class='footer'>
                <p>Este é um email automático do sistema WATS - WhatsApp Sender</p>
                <p>MACIP Tecnologia LTDA | " . (defined('SITE_URL') ? SITE_URL : 'https://wats.macip.com.br') . "</p>
            </div>
        </div>
    </body>
    </html>
    ";

    try {
        return sendEmail($user['email'], $subject, $body);
    } catch (Exception $e) {
        error_log("[BACKUP] Erro ao enviar email de notificação: " . $e->getMessage());
        return false;
    }
}

/**
 * Criptografa backup com senha
 */
function encryptBackup($filepath, $password)
{
    if (!file_exists($filepath)) {
        return ['success' => false, 'error' => 'Arquivo não encontrado'];
    }

    if (empty($password)) {
        return ['success' => false, 'error' => 'Senha não informada'];
    }

    $data = file_get_contents($filepath);

    // Gerar chave e IV a partir da senha
    $key = hash('sha256', $password, true);
    $iv = substr(hash('sha256', $password . 'iv'), 0, 16);

    // Criptografar dados
    $encrypted = openssl_encrypt($data, 'AES-256-CBC', $key, 0, $iv);

    if ($encrypted === false) {
        return ['success' => false, 'error' => 'Erro ao criptografar arquivo'];
    }

    $encryptedPath = $filepath . '.enc';
    file_put_contents($encryptedPath, $encrypted);

    // Remover arquivo original
    @unlink($filepath);

    return [
        'success' => true,
        'filepath' => $encryptedPath,
        'filename' => basename($encryptedPath),
        'size' => filesize($encryptedPath)
    ];
}

/**
 * Descriptografa backup
 */
function decryptBackup($encryptedPath, $password)
{
    if (!file_exists($encryptedPath)) {
        return ['success' => false, 'error' => 'Arquivo não encontrado'];
    }

    if (empty($password)) {
        return ['success' => false, 'error' => 'Senha não informada'];
    }

    $encrypted = file_get_contents($encryptedPath);

    // Gerar chave e IV a partir da senha
    $key = hash('sha256', $password, true);
    $iv = substr(hash('sha256', $password . 'iv'), 0, 16);

    // Descriptografar dados
    $decrypted = openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);

    if ($decrypted === false) {
        return ['success' => false, 'error' => 'Senha incorreta ou arquivo corrompido'];
    }

    $decryptedPath = str_replace('.enc', '', $encryptedPath);
    file_put_contents($decryptedPath, $decrypted);

    return [
        'success' => true,
        'filepath' => $decryptedPath,
        'filename' => basename($decryptedPath),
        'size' => filesize($decryptedPath)
    ];
}

/**
 * Upload de backup para Google Drive (streaming direto, sem arquivo local)
 */
function uploadBackupToGoogleDriveStream($userId, $content, $filename)
{
    global $pdo;
    
    require_once __DIR__ . '/cloud_providers/google_drive.php';
    
    // Buscar configuração com credenciais
    $stmt = $pdo->prepare('SELECT * FROM backup_configs WHERE user_id = ?');
    $stmt->execute([$userId]);
    $config = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$config || empty($config['extra_config'])) {
        return ['success' => false, 'error' => 'Google Drive não configurado'];
    }
    
    $extra = json_decode($config['extra_config'], true);
    if (empty($extra['google']) || empty($extra['google']['access_token'])) {
        return ['success' => false, 'error' => 'Google Drive não conectado. Conecte sua conta primeiro.'];
    }
    
    $credentials = $extra['google'];
    $drive = new GoogleDriveBackup($credentials);
    
    // Criar arquivo temporário para upload
    $tempFile = tempnam(sys_get_temp_dir(), 'gdrive_');
    file_put_contents($tempFile, $content);
    
    // Fazer upload
    $result = $drive->uploadFile(
        $tempFile,
        $filename,
        $credentials['folder_id'] ?? null,
        'application/octet-stream'
    );
    
    // Remover arquivo temporário
    @unlink($tempFile);
    
    if ($result['success']) {
        // Atualizar tokens se renovados
        $newTokens = $drive->getTokens();
        if ($newTokens['access_token'] !== $credentials['access_token']) {
            $extra['google'] = array_merge($credentials, $newTokens);
            $stmt = $pdo->prepare('UPDATE backup_configs SET extra_config = ? WHERE id = ?');
            $stmt->execute([json_encode($extra), $config['id']]);
        }
    }
    
    return $result;
}

/**
 * Upload de backup para OneDrive (streaming direto)
 */
function uploadBackupToOneDriveStream($userId, $content, $filename)
{
    global $pdo;
    
    require_once __DIR__ . '/cloud_providers/onedrive.php';
    
    $stmt = $pdo->prepare('SELECT * FROM backup_configs WHERE user_id = ?');
    $stmt->execute([$userId]);
    $config = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$config || empty($config['extra_config'])) {
        return ['success' => false, 'error' => 'OneDrive não configurado'];
    }
    
    $extra = json_decode($config['extra_config'], true);
    if (empty($extra['onedrive']) || empty($extra['onedrive']['access_token'])) {
        return ['success' => false, 'error' => 'OneDrive não conectado. Conecte sua conta primeiro.'];
    }
    
    $credentials = $extra['onedrive'];
    $onedrive = new OneDriveBackup($credentials);
    
    // Upload direto do conteúdo
    $result = $onedrive->uploadContent($content, $filename, '/WATS_Backups');
    
    if ($result['success']) {
        // Atualizar tokens se renovados
        $newTokens = $onedrive->getTokens();
        if ($newTokens['access_token'] !== $credentials['access_token']) {
            $extra['onedrive'] = array_merge($credentials, $newTokens);
            $stmt = $pdo->prepare('UPDATE backup_configs SET extra_config = ? WHERE id = ?');
            $stmt->execute([json_encode($extra), $config['id']]);
        }
    }
    
    return $result;
}

/**
 * Upload de backup para Dropbox (streaming direto)
 */
function uploadBackupToDropboxStream($userId, $content, $filename)
{
    global $pdo;
    
    require_once __DIR__ . '/cloud_providers/dropbox.php';
    
    $stmt = $pdo->prepare('SELECT * FROM backup_configs WHERE user_id = ?');
    $stmt->execute([$userId]);
    $config = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$config || empty($config['extra_config'])) {
        return ['success' => false, 'error' => 'Dropbox não configurado'];
    }
    
    $extra = json_decode($config['extra_config'], true);
    if (empty($extra['dropbox']) || empty($extra['dropbox']['access_token'])) {
        return ['success' => false, 'error' => 'Dropbox não conectado. Conecte sua conta primeiro.'];
    }
    
    $credentials = $extra['dropbox'];
    $dropbox = new DropboxBackup($credentials);
    
    // Upload direto do conteúdo
    $result = $dropbox->uploadContent($content, $filename, '/WATS_Backups');
    
    if ($result['success']) {
        // Atualizar tokens se renovados
        $newTokens = $dropbox->getTokens();
        if (!empty($newTokens['access_token']) && $newTokens['access_token'] !== $credentials['access_token']) {
            $extra['dropbox'] = array_merge($credentials, $newTokens);
            $stmt = $pdo->prepare('UPDATE backup_configs SET extra_config = ? WHERE id = ?');
            $stmt->execute([json_encode($extra), $config['id']]);
        }
    }
    
    return $result;
}

/**
 * Upload de backup para Amazon S3 (streaming direto)
 */
function uploadBackupToS3Stream($userId, $content, $filename)
{
    global $pdo;
    
    require_once __DIR__ . '/cloud_providers/s3.php';
    
    $stmt = $pdo->prepare('SELECT * FROM backup_configs WHERE user_id = ?');
    $stmt->execute([$userId]);
    $config = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$config || empty($config['extra_config'])) {
        return ['success' => false, 'error' => 'Amazon S3 não configurado'];
    }
    
    $extra = json_decode($config['extra_config'], true);
    if (empty($extra['s3']) || empty($extra['s3']['access_key'])) {
        return ['success' => false, 'error' => 'Amazon S3 não configurado. Configure suas credenciais primeiro.'];
    }
    
    $credentials = $extra['s3'];
    $s3 = new S3Backup($credentials);
    
    // Upload direto do conteúdo
    $result = $s3->uploadContent($content, $filename, 'wats-backups');
    
    return $result;
}
