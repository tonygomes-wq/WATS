<?php
session_start();
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Não autenticado']);
    exit;
}

$user_id = $_SESSION['user_id'];
$userType = $_SESSION['user_type'] ?? 'user';
$phone = $_POST['phone'] ?? '';

if (empty($phone)) {
    echo json_encode(['success' => false, 'error' => 'Telefone não informado']);
    exit;
}

// Determinar o owner_id para cache (supervisor se for atendente)
$cacheOwnerId = $user_id;
if ($userType === 'attendant') {
    $stmt = $pdo->prepare("SELECT supervisor_id FROM supervisor_users WHERE id = ?");
    $stmt->execute([$user_id]);
    $attendant = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($attendant && $attendant['supervisor_id']) {
        $cacheOwnerId = $attendant['supervisor_id'];
    }
}

// Verificar se já existe no cache (e não é muito antigo - 7 dias)
try {
    $stmt = $pdo->prepare("
        SELECT profile_picture_url, status, last_checked_at 
        FROM profile_pictures_cache 
        WHERE phone = ? AND user_id = ? 
        AND last_checked_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
    ");
    $stmt->execute([$phone, $cacheOwnerId]);
    $cached = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($cached) {
        if ($cached['status'] === 'found' && !empty($cached['profile_picture_url'])) {
            echo json_encode([
                'success' => true,
                'profile_picture_url' => $cached['profile_picture_url'],
                'phone' => $phone,
                'from_cache' => true
            ]);
            exit;
        } elseif ($cached['status'] === 'not_found') {
            // Já verificamos e não tem foto
            echo json_encode([
                'success' => false,
                'error' => 'Foto não disponível (cache)',
                'phone' => $phone,
                'from_cache' => true
            ]);
            exit;
        }
    }
} catch (Exception $e) {
    // Tabela pode não existir ainda, continuar normalmente
}

try {
    // Verificar se é atendente (supervisor_users) ou usuário normal
    $userType = $_SESSION['user_type'] ?? 'user';
    $instance = null;
    $token = null;
    
    if ($userType === 'attendant') {
        // Atendente: buscar dados do supervisor/admin associado
        $stmt = $pdo->prepare("SELECT supervisor_id FROM supervisor_users WHERE id = ?");
        $stmt->execute([$user_id]);
        $attendant = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($attendant && $attendant['supervisor_id']) {
            $stmt = $pdo->prepare("SELECT evolution_instance, evolution_token FROM users WHERE id = ?");
            $stmt->execute([$attendant['supervisor_id']]);
            $supervisor = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($supervisor) {
                $instance = $supervisor['evolution_instance'];
                $token = $supervisor['evolution_token'];
            }
        }
    } else {
        // Usuário normal: buscar seus próprios dados
        $stmt = $pdo->prepare("SELECT evolution_instance, evolution_token FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            $instance = $user['evolution_instance'];
            $token = $user['evolution_token'];
        }
    }
    
    if (empty($instance) || empty($token)) {
        echo json_encode(['success' => false, 'error' => 'Instância não configurada']);
        exit;
    }
    
    // Limpar número (remover caracteres especiais)
    $cleanPhone = preg_replace('/[^0-9]/', '', $phone);
    
    // Adicionar código do país se não tiver (Brasil = 55)
    if (strlen($cleanPhone) <= 11 && !str_starts_with($cleanPhone, '55')) {
        $cleanPhone = '55' . $cleanPhone;
    }
    
    // Buscar foto de perfil na Evolution API
    require_once __DIR__ . '/../config/database.php';
    $url = EVOLUTION_API_URL . "/chat/fetchProfilePictureUrl/$instance";
    
    $data = [
        'number' => $cleanPhone
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'apikey: ' . $token
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($httpCode === 200 && !empty($response)) {
        $result = json_decode($response, true);
        
        if (isset($result['profilePictureUrl']) && !empty($result['profilePictureUrl'])) {
            $pictureUrl = $result['profilePictureUrl'];
            
            // Salvar no cache
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO profile_pictures_cache (phone, user_id, profile_picture_url, status, last_checked_at)
                    VALUES (?, ?, ?, 'found', NOW())
                    ON DUPLICATE KEY UPDATE 
                        profile_picture_url = VALUES(profile_picture_url),
                        status = 'found',
                        last_checked_at = NOW()
                ");
                $stmt->execute([$phone, $cacheOwnerId, $pictureUrl]);
            } catch (Exception $e) {
                // Tabela pode não existir, ignorar
            }
            
            // Atualizar também na tabela contacts (compatibilidade)
            $stmt = $pdo->prepare("
                UPDATE contacts 
                SET profile_picture_url = ?, 
                    profile_picture_updated_at = NOW() 
                WHERE phone = ? AND user_id = ?
            ");
            $stmt->execute([$pictureUrl, $phone, $cacheOwnerId]);
            
            echo json_encode([
                'success' => true,
                'profile_picture_url' => $pictureUrl,
                'phone' => $phone
            ]);
        } else {
            // Foto não disponível - salvar no cache para não tentar novamente
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO profile_pictures_cache (phone, user_id, profile_picture_url, status, last_checked_at)
                    VALUES (?, ?, NULL, 'not_found', NOW())
                    ON DUPLICATE KEY UPDATE 
                        status = 'not_found',
                        last_checked_at = NOW()
                ");
                $stmt->execute([$phone, $cacheOwnerId]);
            } catch (Exception $e) {
                // Tabela pode não existir, ignorar
            }
            
            echo json_encode([
                'success' => false,
                'error' => 'Foto não disponível',
                'phone' => $phone
            ]);
        }
    } else {
        // Erro na API - salvar como erro para tentar novamente depois
        try {
            $stmt = $pdo->prepare("
                INSERT INTO profile_pictures_cache (phone, user_id, status, last_checked_at)
                VALUES (?, ?, 'error', NOW())
                ON DUPLICATE KEY UPDATE 
                    status = 'error',
                    last_checked_at = NOW()
            ");
            $stmt->execute([$phone, $cacheOwnerId]);
        } catch (Exception $e) {
            // Ignorar
        }
        
        echo json_encode([
            'success' => false,
            'error' => 'Erro ao buscar foto: ' . ($curlError ?: "HTTP $httpCode"),
            'phone' => $phone
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'phone' => $phone
    ]);
}
?>
