<?php
/**
 * Media Storage Manager
 * 
 * Gerencia o armazenamento local de arquivos de mídia.
 * 
 * @package MediaHandlers
 * @version 1.0
 * @since 2026-01-29
 */

class MediaStorageManager {
    
    /**
     * Diretório base para uploads
     */
    const BASE_UPLOAD_DIR = 'uploads';
    
    /**
     * Salvar arquivo localmente
     * 
     * Salva o arquivo no sistema de arquivos local e retorna
     * informações sobre o arquivo salvo.
     * 
     * @param array $file Dados do arquivo ($_FILES)
     * @param int $userId ID do usuário (supervisor se atendente)
     * @return array ['success' => bool, 'media_url' => string, 'local_path' => string, 'error' => string|null]
     * 
     * @example
     * $result = MediaStorageManager::save($_FILES['file'], 123);
     * if ($result['success']) {
     *     echo "Arquivo salvo em: " . $result['local_path'];
     *     echo "URL: " . $result['media_url'];
     * }
     */
    public static function save(array $file, int $userId): array {
        try {
            // Validar que o arquivo foi enviado corretamente
            if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
                return [
                    'success' => false,
                    'media_url' => null,
                    'local_path' => null,
                    'error' => 'Arquivo não foi enviado corretamente'
                ];
            }
            
            // Construir caminho absoluto para o diretório de uploads
            // Usar DOCUMENT_ROOT para garantir caminho correto
            $documentRoot = $_SERVER['DOCUMENT_ROOT'];
            $uploadDir = $documentRoot . '/' . self::BASE_UPLOAD_DIR;
            $userDir = $uploadDir . "/user_{$userId}/media/";
            
            // Log para debug
            error_log("[MediaStorageManager] Salvando arquivo:");
            error_log("  - Document Root: " . $documentRoot);
            error_log("  - Upload Dir: " . $uploadDir);
            error_log("  - User Dir: " . $userDir);
            
            // Criar diretório se não existir
            if (!is_dir($userDir)) {
                if (!mkdir($userDir, 0755, true)) {
                    return [
                        'success' => false,
                        'media_url' => null,
                        'local_path' => null,
                        'error' => 'Erro ao criar diretório de upload: ' . $userDir
                    ];
                }
                error_log("  - Diretório criado: " . $userDir);
            } else {
                error_log("  - Diretório já existe: " . $userDir);
            }
            
            // Gerar nome único para o arquivo
            $uniqueName = self::generateUniqueName($file['name']);
            
            // Construir caminhos
            $localPath = $userDir . $uniqueName;
            $mediaUrl = '/' . self::BASE_UPLOAD_DIR . "/user_{$userId}/media/" . $uniqueName;
            
            error_log("  - Nome único: " . $uniqueName);
            error_log("  - Local Path: " . $localPath);
            error_log("  - Media URL: " . $mediaUrl);
            
            // Mover arquivo para o diretório
            if (!move_uploaded_file($file['tmp_name'], $localPath)) {
                return [
                    'success' => false,
                    'media_url' => null,
                    'local_path' => null,
                    'error' => 'Erro ao mover arquivo para diretório de destino: ' . $localPath
                ];
            }
            
            error_log("  - Arquivo movido com sucesso!");
            error_log("  - Verificando existência: " . (file_exists($localPath) ? 'SIM' : 'NÃO'));
            
            // Retornar sucesso
            return [
                'success' => true,
                'media_url' => $mediaUrl,
                'local_path' => $localPath,
                'error' => null
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'media_url' => null,
                'local_path' => null,
                'error' => 'Erro ao salvar arquivo: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Gerar nome único para arquivo
     * 
     * Cria um nome único baseado em timestamp e uniqid para evitar
     * conflitos de nomes de arquivo.
     * 
     * @param string $originalName Nome original do arquivo
     * @return string Nome único gerado
     * 
     * @example
     * $uniqueName = MediaStorageManager::generateUniqueName('foto.jpg');
     * // Returns: "67890abc_1738195200.jpg"
     */
    private static function generateUniqueName(string $originalName): string {
        // Extrair extensão do arquivo original
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        
        // Gerar nome único: {uniqid}_{timestamp}.{extension}
        $uniqueId = uniqid();
        $timestamp = time();
        
        // Construir nome único
        if (!empty($extension)) {
            return "{$uniqueId}_{$timestamp}.{$extension}";
        } else {
            return "{$uniqueId}_{$timestamp}";
        }
    }
}
