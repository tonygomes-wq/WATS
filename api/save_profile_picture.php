<?php
/**
 * API para salvar foto de perfil do contato localmente
 * Baixa a foto do WhatsApp e salva na pasta uploads/profile_pictures
 */

session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

// Verificar se está logado
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Não autorizado']);
    exit;
}

/**
 * Baixa e salva a foto de perfil de um contato
 * @param string $phone Número do telefone (identificador único)
 * @param string $imageUrl URL da imagem do WhatsApp
 * @return array Resultado da operação
 */
function saveProfilePicture($phone, $imageUrl) {
    // Limpar número do telefone para usar como nome do arquivo
    $cleanPhone = preg_replace('/[^0-9]/', '', $phone);
    
    if (empty($cleanPhone)) {
        return ['success' => false, 'error' => 'Telefone inválido'];
    }
    
    // Diretório de destino
    $uploadDir = __DIR__ . '/../uploads/profile_pictures/';
    
    // Criar diretório se não existir
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // Nome do arquivo
    $filename = $cleanPhone . '.jpg';
    $filepath = $uploadDir . $filename;
    
    // Se a URL está vazia ou inválida, retornar caminho padrão
    if (empty($imageUrl) || !filter_var($imageUrl, FILTER_VALIDATE_URL)) {
        return [
            'success' => false, 
            'error' => 'URL inválida',
            'local_path' => null
        ];
    }
    
    // Verificar se já existe uma foto recente (menos de 24 horas)
    if (file_exists($filepath)) {
        $fileAge = time() - filemtime($filepath);
        if ($fileAge < 86400) { // 24 horas em segundos
            return [
                'success' => true,
                'message' => 'Foto já existe e está atualizada',
                'local_path' => '/uploads/profile_pictures/' . $filename,
                'cached' => true
            ];
        }
    }
    
    // Tentar baixar a imagem
    $context = stream_context_create([
        'http' => [
            'timeout' => 10,
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false
        ]
    ]);
    
    $imageData = @file_get_contents($imageUrl, false, $context);
    
    if ($imageData === false) {
        return [
            'success' => false,
            'error' => 'Não foi possível baixar a imagem',
            'local_path' => file_exists($filepath) ? '/uploads/profile_pictures/' . $filename : null
        ];
    }
    
    // Verificar se é uma imagem válida
    $imageInfo = @getimagesizefromstring($imageData);
    if ($imageInfo === false) {
        return [
            'success' => false,
            'error' => 'Dados não são uma imagem válida',
            'local_path' => file_exists($filepath) ? '/uploads/profile_pictures/' . $filename : null
        ];
    }
    
    // Salvar a imagem
    if (file_put_contents($filepath, $imageData) !== false) {
        return [
            'success' => true,
            'message' => 'Foto salva com sucesso',
            'local_path' => '/uploads/profile_pictures/' . $filename,
            'cached' => false
        ];
    }
    
    return [
        'success' => false,
        'error' => 'Erro ao salvar arquivo',
        'local_path' => null
    ];
}

/**
 * Retorna o caminho local da foto de perfil se existir
 * @param string $phone Número do telefone
 * @return string|null Caminho local ou null
 */
function getLocalProfilePicture($phone) {
    $cleanPhone = preg_replace('/[^0-9]/', '', $phone);
    $filename = $cleanPhone . '.jpg';
    $filepath = __DIR__ . '/../uploads/profile_pictures/' . $filename;
    
    if (file_exists($filepath)) {
        return '/uploads/profile_pictures/' . $filename . '?v=' . filemtime($filepath);
    }
    
    return null;
}

// Processar requisição
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $phone = $input['phone'] ?? '';
    $imageUrl = $input['image_url'] ?? '';
    
    if (empty($phone)) {
        echo json_encode(['success' => false, 'error' => 'Telefone é obrigatório']);
        exit;
    }
    
    $result = saveProfilePicture($phone, $imageUrl);
    echo json_encode($result);
    
} elseif ($method === 'GET') {
    // Buscar foto local
    $phone = $_GET['phone'] ?? '';
    
    if (empty($phone)) {
        echo json_encode(['success' => false, 'error' => 'Telefone é obrigatório']);
        exit;
    }
    
    $localPath = getLocalProfilePicture($phone);
    
    echo json_encode([
        'success' => $localPath !== null,
        'local_path' => $localPath
    ]);
    
} else {
    echo json_encode(['success' => false, 'error' => 'Método não suportado']);
}
