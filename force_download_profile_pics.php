<?php
/**
 * FORÇAR DOWNLOAD DE FOTOS DE PERFIL
 * Baixa fotos de perfil de todos os contatos ativos
 */

session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

if (!isLoggedIn()) {
    die('Não autenticado. <a href="/login.php">Fazer login</a>');
}

$userId = $_SESSION['user_id'];

// Buscar configuração do usuário
$stmt = $pdo->prepare("
    SELECT 
        evolution_instance, 
        evolution_token, 
        evolution_api_url
    FROM users 
    WHERE id = ?
");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$instance = $user['evolution_instance'] ?? '';
$token = $user['evolution_token'] ?? '';
$apiUrl = !empty($user['evolution_api_url']) ? $user['evolution_api_url'] : EVOLUTION_API_URL;

$results = [];
$success = 0;
$failed = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['download'])) {
    // Buscar contatos sem foto ou com foto antiga (mais de 7 dias)
    $stmt = $pdo->prepare("
        SELECT DISTINCT c.id, c.phone, c.name, c.profile_picture_url, c.profile_picture_updated_at
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
                    $localUrl = saveProfilePicture($phone, $picUrl);
                    
                    if ($localUrl) {
                        // Atualizar banco
                        $stmt = $pdo->prepare("
                            UPDATE contacts 
                            SET profile_picture_url = ?, profile_picture_updated_at = NOW() 
                            WHERE id = ?
                        ");
                        $stmt->execute([$localUrl, $contact['id']]);
                        
                        $results[] = [
                            'phone' => $phone,
                            'name' => $contact['name'],
                            'status' => 'success',
                            'url' => $localUrl
                        ];
                        $success++;
                    } else {
                        $results[] = [
                            'phone' => $phone,
                            'name' => $contact['name'],
                            'status' => 'error',
                            'message' => 'Erro ao salvar foto localmente'
                        ];
                        $failed++;
                    }
                } else {
                    $results[] = [
                        'phone' => $phone,
                        'name' => $contact['name'],
                        'status' => 'not_found',
                        'message' => 'Contato sem foto de perfil'
                    ];
                    $failed++;
                }
            } else {
                $results[] = [
                    'phone' => $phone,
                    'name' => $contact['name'],
                    'status' => 'error',
                    'message' => "HTTP $httpCode"
                ];
                $failed++;
            }
        } catch (Exception $e) {
            $results[] = [
                'phone' => $phone,
                'name' => $contact['name'],
                'status' => 'error',
                'message' => $e->getMessage()
            ];
            $failed++;
        }
        
        // Pequeno delay para não sobrecarregar a API
        usleep(200000); // 200ms
    }
}

function saveProfilePicture($phone, $url) {
    try {
        $cleanPhone = preg_replace('/[^0-9]/', '', $phone);
        $content = @file_get_contents($url);
        
        if ($content && strlen($content) > 100) {
            $dir = __DIR__ . "/uploads/profile_pictures/";
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            
            $filename = $cleanPhone . ".jpg";
            $filepath = $dir . $filename;
            
            if (file_put_contents($filepath, $content)) {
                return "/uploads/profile_pictures/$filename";
            }
        }
    } catch (Exception $e) {
        error_log("Erro ao salvar foto: " . $e->getMessage());
    }
    return null;
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Download de Fotos de Perfil</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            border-radius: 16px;
            padding: 40px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        h1 {
            color: #2c3e50;
            margin-bottom: 30px;
        }
        .btn {
            display: inline-block;
            padding: 15px 30px;
            background: #27ae60;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            margin: 10px 5px;
        }
        .btn:hover {
            background: #229954;
        }
        .btn-secondary {
            background: #3498db;
        }
        .btn-secondary:hover {
            background: #2980b9;
        }
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 30px 0;
        }
        .stat-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 12px;
            text-align: center;
        }
        .stat-card h3 {
            font-size: 36px;
            margin-bottom: 10px;
        }
        .stat-card.success h3 {
            color: #27ae60;
        }
        .stat-card.error h3 {
            color: #e74c3c;
        }
        .results {
            margin-top: 30px;
            max-height: 500px;
            overflow-y: auto;
        }
        .result-item {
            padding: 15px;
            margin: 10px 0;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .result-item.success {
            background: #d4edda;
            border-left: 5px solid #28a745;
        }
        .result-item.error {
            background: #f8d7da;
            border-left: 5px solid #dc3545;
        }
        .result-item.not_found {
            background: #fff3cd;
            border-left: 5px solid #ffc107;
        }
        .result-item img {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
        }
        .info-box {
            background: #d1ecf1;
            border-left: 5px solid #17a2b8;
            color: #0c5460;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
        }
        .loading {
            text-align: center;
            padding: 40px;
            font-size: 18px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>📸 Download de Fotos de Perfil</h1>
        
        <div class="info-box">
            <strong>ℹ️ Informação:</strong><br><br>
            Este script irá baixar as fotos de perfil dos seus 50 contatos mais recentes que não possuem foto ou com foto antiga (mais de 7 dias).
        </div>
        
        <?php if (!empty($results)): ?>
            <div class="stats">
                <div class="stat-card success">
                    <h3><?php echo $success; ?></h3>
                    <p>Sucesso</p>
                </div>
                <div class="stat-card error">
                    <h3><?php echo $failed; ?></h3>
                    <p>Falhas</p>
                </div>
                <div class="stat-card">
                    <h3><?php echo count($results); ?></h3>
                    <p>Total</p>
                </div>
            </div>
            
            <div class="results">
                <?php foreach ($results as $result): ?>
                    <div class="result-item <?php echo $result['status']; ?>">
                        <?php if ($result['status'] === 'success'): ?>
                            <img src="<?php echo htmlspecialchars($result['url']); ?>" alt="Foto">
                        <?php endif; ?>
                        <div>
                            <strong><?php echo htmlspecialchars($result['name'] ?: $result['phone']); ?></strong><br>
                            <small><?php echo htmlspecialchars($result['phone']); ?></small>
                            <?php if (isset($result['message'])): ?>
                                <br><small><?php echo htmlspecialchars($result['message']); ?></small>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <form method="POST">
                <button type="submit" name="download" class="btn">
                    🔄 Baixar Mais Fotos
                </button>
            </form>
        <?php else: ?>
            <form method="POST" id="downloadForm">
                <button type="submit" name="download" class="btn">
                    📥 Iniciar Download
                </button>
            </form>
            
            <div id="loading" class="loading" style="display: none;">
                ⏳ Baixando fotos de perfil... Isso pode levar alguns minutos.
            </div>
        <?php endif; ?>
        
        <a href="/chat.php" class="btn btn-secondary">
            💬 Voltar para o Chat
        </a>
    </div>
    
    <script>
        document.getElementById('downloadForm')?.addEventListener('submit', function() {
            document.getElementById('loading').style.display = 'block';
        });
    </script>
</body>
</html>
