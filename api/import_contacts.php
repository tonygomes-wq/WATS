<?php
/**
 * API para Importar Contatos da Evolution API
 * Busca todos os contatos da instância e adiciona ao sistema
 */

session_start();
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

// Log de início
error_log("[IMPORT_CONTACTS] Iniciando importação de contatos");

if (!isset($_SESSION['user_id'])) {
    error_log("[IMPORT_CONTACTS] Erro: Usuário não autenticado");
    echo json_encode(['success' => false, 'error' => 'Não autenticado']);
    exit;
}

$user_id = $_SESSION['user_id'];
$userType = $_SESSION['user_type'] ?? 'user';
$isAttendant = ($userType === 'attendant');

error_log("[IMPORT_CONTACTS] User ID: $user_id, Type: $userType");

try {
    $instance = null;
    $token = null;
    $evolutionUrl = null;
    
    // Se for atendente, verificar se tem instância própria
    if ($isAttendant) {
        // Verificar se tabela attendant_instances existe
        $tableExists = false;
        try {
            $checkTable = $pdo->query("SHOW TABLES LIKE 'attendant_instances'");
            $tableExists = $checkTable->rowCount() > 0;
        } catch (Exception $e) {
            $tableExists = false;
        }
        
        if ($tableExists) {
            // Buscar instância do atendente
            $stmt = $pdo->prepare("
                SELECT ai.instance_name, ai.token, su.use_own_instance, u.evolution_token, u.evolution_api_url
                FROM supervisor_users su
                LEFT JOIN attendant_instances ai ON su.id = ai.attendant_id
                LEFT JOIN users u ON su.supervisor_id = u.id
                WHERE su.id = ?
            ");
            $stmt->execute([$user_id]);
            $attendantData = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Se atendente tem instância própria configurada
            if ($attendantData && ($attendantData['use_own_instance'] ?? 0) == 1 && !empty($attendantData['instance_name'])) {
                $instance = $attendantData['instance_name'];
                $token = $attendantData['token'] ?? $attendantData['evolution_token'];
                $evolutionUrl = $attendantData['evolution_api_url'] ?? EVOLUTION_API_URL;
                error_log("[IMPORT_CONTACTS] Usando instância do atendente: $instance");
            }
        }
    }
    
    // Se não é atendente ou atendente não tem instância própria, usar do supervisor/usuário
    if (empty($instance)) {
        if ($isAttendant) {
            // Atendente sem instância própria: usar do supervisor
            $stmt = $pdo->prepare("
                SELECT u.evolution_instance, u.evolution_token, u.evolution_api_url
                FROM supervisor_users su
                JOIN users u ON su.supervisor_id = u.id
                WHERE su.id = ?
            ");
            $stmt->execute([$user_id]);
        } else {
            // Usuário normal/supervisor
            $stmt = $pdo->prepare("SELECT evolution_instance, evolution_token, evolution_api_url FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
        }
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user || empty($user['evolution_instance']) || empty($user['evolution_token'])) {
            error_log("[IMPORT_CONTACTS] Erro: Instância não configurada para user_id: $user_id");
            echo json_encode(['success' => false, 'error' => 'Instância Evolution API não configurada. Configure em "Meus Números" primeiro.']);
            exit;
        }
        
        $instance = $user['evolution_instance'];
        $token = $user['evolution_token'];
        $evolutionUrl = $user['evolution_api_url'] ?? EVOLUTION_API_URL;
    }
    
    if (empty($instance) || empty($token)) {
        error_log("[IMPORT_CONTACTS] Erro: Instância ou token vazio");
        echo json_encode(['success' => false, 'error' => 'Instância Evolution API não configurada. Configure em "Meus Números" primeiro.']);
        exit;
    }
    
    // Garantir que a URL não tenha barra no final
    $evolutionUrl = rtrim($evolutionUrl, '/');
    
    error_log("[IMPORT_CONTACTS] Importando contatos da instância: $instance");
    error_log("[IMPORT_CONTACTS] Evolution URL: $evolutionUrl");
    
    // Lista de endpoints possíveis para buscar contatos (diferentes versões da Evolution API)
    // Formato: [endpoint, método HTTP, body]
    $endpoints = [
        // Evolution API v2 (POST)
        ["/chat/findContacts/$instance", 'POST', '{}'],
        ["/chat/fetchContacts/$instance", 'POST', '{}'],
        // Evolution API v1 (GET)
        ["/chat/findContacts/$instance", 'GET', null],
        ["/contact/findContacts/$instance", 'GET', null],
        ["/chat/contacts/$instance", 'GET', null],
        ["/contacts/all/$instance", 'GET', null],
        ["/contact/list/$instance", 'GET', null],
        // Tentar POST em outros endpoints
        ["/contact/findContacts/$instance", 'POST', '{}'],
        ["/chat/contacts/$instance", 'POST', '{}'],
    ];
    
    $response = null;
    $httpCode = 0;
    $usedEndpoint = '';
    $lastError = '';
    
    // Tentar cada endpoint até encontrar um que funcione
    foreach ($endpoints as $endpointConfig) {
        $endpoint = $endpointConfig[0];
        $method = $endpointConfig[1];
        $postData = $endpointConfig[2];
        
        $url = $evolutionUrl . $endpoint;
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'apikey: ' . $token
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        
        // Se for POST, configurar
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        error_log("[IMPORT_CONTACTS] Tentando $method $url -> HTTP $httpCode");
        
        if (!empty($curlError)) {
            $lastError = $curlError;
            error_log("[IMPORT_CONTACTS] Erro cURL: $curlError");
        }
        
        // Se funcionou, usar este endpoint
        if ($httpCode === 200 && !empty($response)) {
            $testData = json_decode($response, true);
            // Verificar se retornou um array de contatos
            if (is_array($testData) && (empty($testData) || isset($testData[0]))) {
                $usedEndpoint = "$method $endpoint";
                error_log("[IMPORT_CONTACTS] ✓ Endpoint funcionando: $usedEndpoint");
                break;
            } else {
                error_log("[IMPORT_CONTACTS] Resposta não é array de contatos: " . substr($response, 0, 200));
            }
        }
    }
    
    // Se nenhum endpoint funcionou
    if ($httpCode !== 200 || empty($response)) {
        $errorMsg = "Não foi possível conectar à Evolution API. ";
        
        if ($httpCode === 401 || $httpCode === 403) {
            $errorMsg .= "Token de autenticação inválido. Verifique a configuração em 'Meus Números'.";
        } elseif ($httpCode === 404) {
            $errorMsg .= "Instância '$instance' não encontrada na Evolution API.";
        } elseif ($httpCode === 0) {
            $errorMsg .= "Não foi possível conectar ao servidor. Verifique a URL: $evolutionUrl";
        } else {
            $errorMsg .= "Sua versão da Evolution API não possui endpoint para listar contatos. Use a captura automática via webhook quando mensagens chegarem.";
        }
        
        if (!empty($lastError)) {
            $errorMsg .= " Erro: $lastError";
        }
        
        error_log("[IMPORT_CONTACTS] Erro final: $errorMsg (HTTP $httpCode)");
        
        echo json_encode([
            'success' => false,
            'error' => $errorMsg,
            'debug' => [
                'http_code' => $httpCode,
                'url' => $evolutionUrl,
                'instance' => $instance,
                'curl_error' => $lastError
            ]
        ]);
        exit;
    }
    
    $data = json_decode($response, true);
    
    if (!isset($data) || !is_array($data)) {
        error_log("[IMPORT_CONTACTS] Resposta inválida da API: " . substr($response, 0, 500));
        echo json_encode([
            'success' => false,
            'error' => 'Resposta inválida da Evolution API. Verifique os logs.'
        ]);
        exit;
    }
    
    error_log("[IMPORT_CONTACTS] Total de contatos recebidos: " . count($data));
    
    // Contadores
    $imported = 0;
    $updated = 0;
    $skipped = 0;
    $errors = [];
    
    // Processar cada contato
    foreach ($data as $contact) {
        try {
            // Evolution API v2: usar remoteJid para o telefone
            // Pular grupos (terminam em @g.us)
            $remoteJid = $contact['remoteJid'] ?? $contact['id'] ?? null;
            
            if (empty($remoteJid)) {
                $skipped++;
                continue;
            }
            
            // Pular grupos (formato: xxxxx@g.us)
            if (strpos($remoteJid, '@g.us') !== false) {
                $skipped++;
                continue;
            }
            
            // Pular se não for contato individual
            $isGroup = $contact['isGroup'] ?? false;
            $type = $contact['type'] ?? 'contact';
            if ($isGroup || $type === 'group') {
                $skipped++;
                continue;
            }
            
            // Extrair informações do contato
            $name = $contact['pushName'] ?? $contact['name'] ?? null;
            $profilePicUrl = $contact['profilePicUrl'] ?? $contact['profilePictureUrl'] ?? null;
            
            // Limpar número (remover @s.whatsapp.net)
            $cleanPhone = str_replace('@s.whatsapp.net', '', $remoteJid);
            $cleanPhone = preg_replace('/[^0-9]/', '', $cleanPhone);
            
            // Pular números muito curtos ou muito longos
            if (strlen($cleanPhone) < 10 || strlen($cleanPhone) > 15) {
                $skipped++;
                continue;
            }
            
            // PROTEÇÃO ANTI-DUPLICAÇÃO: Verificar se contato já existe
            // Busca por telefone E user_id para garantir que não duplica
            $stmt = $pdo->prepare("SELECT id, name, profile_picture_url FROM contacts WHERE phone = ? AND user_id = ?");
            $stmt->execute([$cleanPhone, $user_id]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existing) {
                // CONTATO JÁ EXISTE - Decidir se atualiza ou ignora
                
                $needsUpdate = false;
                $updateFields = [];
                $updateValues = [];
                
                // Verificar se nome mudou
                if (!empty($name) && $name !== $existing['name']) {
                    $updateFields[] = "name = ?";
                    $updateValues[] = $name;
                    $needsUpdate = true;
                }
                
                // Verificar se foto mudou ou não tinha foto
                if (!empty($profilePicUrl) && $profilePicUrl !== $existing['profile_picture_url']) {
                    $updateFields[] = "profile_picture_url = ?";
                    $updateValues[] = $profilePicUrl;
                    $updateFields[] = "profile_picture_updated_at = NOW()";
                    $needsUpdate = true;
                }
                
                if ($needsUpdate) {
                    // Atualizar apenas os campos que mudaram
                    $updateFields[] = "updated_at = NOW()";
                    $sql = "UPDATE contacts SET " . implode(", ", $updateFields) . " WHERE id = ?";
                    $updateValues[] = $existing['id'];
                    
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($updateValues);
                    $updated++;
                } else {
                    // Contato idêntico - ignorar (NÃO DUPLICA)
                    $skipped++;
                }
            } else {
                // CONTATO NOVO - Inserir
                $stmt = $pdo->prepare("
                    INSERT INTO contacts (
                        user_id,
                        phone,
                        name,
                        profile_picture_url,
                        profile_picture_updated_at,
                        created_at,
                        updated_at
                    ) VALUES (?, ?, ?, ?, NOW(), NOW(), NOW())
                ");
                
                $contactName = !empty($name) ? $name : $cleanPhone;
                $stmt->execute([
                    $user_id,
                    $cleanPhone,
                    $contactName,
                    $profilePicUrl
                ]);
                
                $imported++;
            }
            
        } catch (Exception $e) {
            $errors[] = "Erro ao processar {$cleanPhone}: " . $e->getMessage();
            error_log("[IMPORT_CONTACTS] Erro ao processar contato: " . $e->getMessage());
        }
    }
    
    error_log("[IMPORT_CONTACTS] Importação concluída: $imported novos, $updated atualizados, $skipped ignorados");
    
    echo json_encode([
        'success' => true,
        'total_contacts' => count($data),
        'imported' => $imported,
        'updated' => $updated,
        'skipped' => $skipped,
        'errors' => $errors,
        'message' => "Importação concluída! $imported novos, $updated atualizados, $skipped ignorados.",
        'endpoint_used' => $usedEndpoint
    ]);
    
} catch (Exception $e) {
    error_log("[IMPORT_CONTACTS] Exceção: " . $e->getMessage());
    error_log("[IMPORT_CONTACTS] Stack trace: " . $e->getTraceAsString());
    
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'trace' => APP_DEBUG ? $e->getTraceAsString() : null
    ]);
}
?>
