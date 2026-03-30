<?php
/**
 * API: Baixar Fotos de Perfil
 * Baixa fotos de perfil dos contatos da Evolution API
 */

header('Content-Type: application/json');

session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Não autenticado']);
    exit;
}

$userId = $_SESSION['user_id'];

// Buscar configuração do usuário
$stmt = $pdo->prepare("
    SELECT evolution_instance, evolution_token, evolution_api_url
    FROM users 
    WHERE id = ?
");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$instance = $user['evolution_instance'] ?? '';
$token = $user['evolution_token'] ?? '';
$apiUrl = !empty($user['evolution_api_url']) ? $user['evolution_api_url'] : EVOLUTION_API_URL;

if (empty($instance) || empty($token)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Instância não configurada']);
    exit;
}

try {
    // Buscar contatos sem foto ou com foto antiga
    $stmt = $pdo->prepare("
        SELECT DISTINCT c.id, c.phone, c.name
        FROM contacts c
        INNER JOIN chat_conversations cc ON c.phone = cc.phone
        WHERE c.user_id = ?
        AND (
            c.profile_picture_url IS NULL 
            OR c.profile_picture_updated_at IS NULL
            OR c.profile_picture_updated_at < DATE_SUB(NOW(), INTERVAL 7 DAY)
        )
        ORDER BY cc.last_message_time DESC
        LIMIT 50
    ");
    $stmt->execute([$userId]);
    $contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $success = 0;
    $failed = 0;
    
    foreach ($contacts as $contact) {
        $phone = preg_replace('/[^0-9]/', '', $contact['phone']);
        
        try {
            // Buscar foto na Evolution API
            $url = rtrim($apiUrl, '/') . "/chat/fetchProfilePictureUrl/{$instance}";
            $payload = json_encode(['number' => $phone]);
            
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'apikey: ' . $token
                ],
                CURLOPT_TIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => false
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode === 200 && $response) {
                $data = json_decode($response, true);
                $picUrl = $data['profilePictureUrl'] ?? null;
                
                if ($picUrl) {
                    // Baixar e salvar localmente
                    $cleanPhone = preg_replace('/[^0-9]/', '', $phone);
                    $content = @file_get_contents($picUrl);
                    
                    if ($content && strlen($content) > 100) {
                        $dir = __DIR__ . "/../uploads/profile_pictures/";
                        if (!is_dir($dir)) {
                            mkdir($dir, 0755, true);
                        }
                        
                        $filename = $cleanPhone . ".jpg";
                        $filepath = $dir . $filename;
                        
                        if (file_put_contents($filepath, $content)) {
                            $localUrl = "/uploads/profile_pictures/$filename";
                            
                            // Atualizar banco
                            $stmt = $pdo->prepare("
                                UPDATE contacts 
                                SET profile_picture_url = ?, profile_picture_updated_at = NOW() 
                                WHERE id = ?
                            ");
                            $stmt->execute([$localUrl, $contact['id']]);
                            
                            $success++;
                        } else {
                            $failed++;
                        }
                    } else {
                        $failed++;
                    }
                } else {
                    $failed++;
                }
            } else {
                $failed++;
            }
        } catch (Exception $e) {
            $failed++;
        }
        
        // Pequeno delay para não sobrecarregar a API
        usleep(200000); // 200ms
    }
    
    echo json_encode([
        'success' => true,
        'success_count' => $success,
        'failed_count' => $failed,
        'total' => count($contacts)
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
