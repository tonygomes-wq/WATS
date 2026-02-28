<?php
/**
 * Visualizador de Logs do Webhook
 * Mostra as últimas requisições recebidas pelo webhook
 */

if (!isset($_SESSION)) {
    session_start();
}

require_once '../config/database.php';

// Verificar autenticação
if (!isset($_SESSION['user_id'])) {
    die('Acesso negado. Faça login primeiro.');
}

$userId = $_SESSION['user_id'];

// Criar tabela de logs se não existir
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS webhook_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            event_type VARCHAR(100),
            payload TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_created (created_at)
        )
    ");
} catch (Exception $e) {
    // Tabela já existe
}

// Obter últimos 50 logs
$stmt = $pdo->query("
    SELECT * FROM webhook_logs 
    ORDER BY created_at DESC 
    LIMIT 50
");
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logs do Webhook - WATS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100 p-8">
    <div class="max-w-6xl mx-auto">
        <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
            <h1 class="text-2xl font-bold mb-4 text-gray-800">
                <i class="fas fa-file-alt text-blue-500 mr-2"></i>
                Logs do Webhook
            </h1>
            <p class="text-gray-600 mb-4">
                Últimas 50 requisições recebidas pelo webhook da Evolution API
            </p>
            <button onclick="location.reload()" 
                    class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded">
                <i class="fas fa-sync-alt mr-2"></i>
                Atualizar
            </button>
        </div>

        <?php if (empty($logs)): ?>
            <div class="bg-yellow-50 border-l-4 border-yellow-500 p-6 rounded">
                <div class="flex items-center gap-2 text-yellow-800">
                    <i class="fas fa-exclamation-triangle text-xl"></i>
                    <span class="font-semibold">Nenhum log encontrado</span>
                </div>
                <p class="text-yellow-700 mt-2">
                    O webhook não recebeu nenhuma requisição ainda. Isso pode indicar que:
                </p>
                <ul class="list-disc list-inside text-yellow-700 mt-2 ml-4">
                    <li>A Evolution API não foi reiniciada após alterar o .env</li>
                    <li>O webhook não está configurado corretamente</li>
                    <li>A URL do webhook está incorreta</li>
                </ul>
            </div>
        <?php else: ?>
            <div class="space-y-4">
                <?php foreach ($logs as $log): ?>
                    <div class="bg-white rounded-lg shadow p-4">
                        <div class="flex items-center justify-between mb-2">
                            <span class="font-semibold text-lg text-gray-800">
                                <?= htmlspecialchars($log['event_type']) ?>
                            </span>
                            <span class="text-sm text-gray-500">
                                <?= date('d/m/Y H:i:s', strtotime($log['created_at'])) ?>
                            </span>
                        </div>
                        <details class="mt-2">
                            <summary class="cursor-pointer text-blue-600 hover:text-blue-800">
                                Ver payload completo
                            </summary>
                            <pre class="mt-2 bg-gray-50 p-4 rounded text-xs overflow-x-auto"><?= htmlspecialchars(json_encode(json_decode($log['payload']), JSON_PRETTY_PRINT)) ?></pre>
                        </details>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
