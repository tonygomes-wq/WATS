<?php
/**
 * File Validator
 * 
 * Valida tipo e tamanho de arquivos de mídia antes do upload.
 * 
 * @package MediaHandlers
 * @version 1.0
 * @since 2026-01-29
 */

class FileValidator {
    
    /**
     * Tamanhos máximos por tipo de mídia (em bytes)
     */
    const MAX_SIZE_IMAGE = 5242880;      // 5MB
    const MAX_SIZE_AUDIO = 16777216;     // 16MB
    const MAX_SIZE_DOCUMENT = 104857600; // 100MB
    
    /**
     * MIME types permitidos por tipo de mídia
     */
    const ALLOWED_MIME_TYPES = [
        'image' => ['image/jpeg', 'image/png', 'image/gif'],
        'audio' => ['audio/mpeg', 'audio/ogg', 'audio/wav'],
        'document' => [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        ]
    ];
    
    /**
     * Validar arquivo
     * 
     * Verifica se o arquivo atende aos requisitos de tamanho e tipo
     * para o tipo de mídia especificado.
     * 
     * @param array $file Dados do $_FILES
     * @param string $mediaType 'image', 'audio' ou 'document'
     * @return array ['valid' => bool, 'error' => string|null]
     * 
     * @example
     * $result = FileValidator::validate($_FILES['file'], 'image');
     * if (!$result['valid']) {
     *     echo $result['error'];
     * }
     */
    public static function validate(array $file, string $mediaType): array {
        // 1. Verificar se o arquivo foi enviado corretamente
        if (!isset($file['error']) || !isset($file['tmp_name']) || !isset($file['size'])) {
            return [
                'valid' => false,
                'error' => 'Dados do arquivo inválidos'
            ];
        }
        
        // 2. Verificar erros de upload do PHP
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return [
                'valid' => false,
                'error' => self::getUploadErrorMessage($file['error'])
            ];
        }
        
        // 3. Verificar se o arquivo existe
        if (!file_exists($file['tmp_name'])) {
            return [
                'valid' => false,
                'error' => 'Arquivo não encontrado'
            ];
        }
        
        // 4. Validar tipo de mídia
        $mediaType = strtolower($mediaType);
        if (!in_array($mediaType, ['image', 'audio', 'document'])) {
            return [
                'valid' => false,
                'error' => 'Tipo de mídia inválido'
            ];
        }
        
        // 5. Validar tamanho do arquivo
        $maxSize = self::getMaxSize($mediaType);
        if ($file['size'] > $maxSize) {
            $maxSizeMB = round($maxSize / 1048576, 0); // Converter para MB
            return [
                'valid' => false,
                'error' => "Arquivo muito grande. Máximo: {$maxSizeMB}MB"
            ];
        }
        
        // 6. Validar MIME type usando mime_content_type (mais seguro que confiar no navegador)
        $mimeType = mime_content_type($file['tmp_name']);
        if (!$mimeType) {
            return [
                'valid' => false,
                'error' => 'Não foi possível determinar o tipo do arquivo'
            ];
        }
        
        // 7. Verificar se o MIME type é permitido para este tipo de mídia
        $allowedMimeTypes = self::ALLOWED_MIME_TYPES[$mediaType] ?? [];
        if (!in_array($mimeType, $allowedMimeTypes)) {
            return [
                'valid' => false,
                'error' => 'Tipo de arquivo não suportado'
            ];
        }
        
        // 8. Validar extensão do arquivo (segurança adicional)
        if (isset($file['name'])) {
            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowedExtensions = self::getAllowedExtensions($mediaType);
            
            if (!in_array($extension, $allowedExtensions)) {
                return [
                    'valid' => false,
                    'error' => 'Extensão de arquivo não permitida'
                ];
            }
        }
        
        // Arquivo válido!
        return [
            'valid' => true,
            'error' => null
        ];
    }
    
    /**
     * Obter tamanho máximo para tipo de mídia
     * 
     * @param string $mediaType Tipo de mídia
     * @return int Tamanho máximo em bytes
     */
    private static function getMaxSize(string $mediaType): int {
        switch ($mediaType) {
            case 'image':
                return self::MAX_SIZE_IMAGE;
            case 'audio':
                return self::MAX_SIZE_AUDIO;
            case 'document':
                return self::MAX_SIZE_DOCUMENT;
            default:
                return 0;
        }
    }
    
    /**
     * Obter extensões permitidas para tipo de mídia
     * 
     * @param string $mediaType Tipo de mídia
     * @return array Lista de extensões permitidas
     */
    private static function getAllowedExtensions(string $mediaType): array {
        switch ($mediaType) {
            case 'image':
                return ['jpg', 'jpeg', 'png', 'gif'];
            case 'audio':
                return ['mp3', 'ogg', 'wav'];
            case 'document':
                return ['pdf', 'doc', 'docx', 'xls', 'xlsx'];
            default:
                return [];
        }
    }
    
    /**
     * Obter mensagem de erro amigável para erros de upload do PHP
     * 
     * @param int $errorCode Código de erro do PHP
     * @return string Mensagem de erro
     */
    private static function getUploadErrorMessage(int $errorCode): string {
        switch ($errorCode) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return 'Arquivo muito grande';
            case UPLOAD_ERR_PARTIAL:
                return 'Upload incompleto. Tente novamente';
            case UPLOAD_ERR_NO_FILE:
                return 'Nenhum arquivo foi enviado';
            case UPLOAD_ERR_NO_TMP_DIR:
                return 'Erro no servidor: diretório temporário não encontrado';
            case UPLOAD_ERR_CANT_WRITE:
                return 'Erro no servidor: não foi possível salvar o arquivo';
            case UPLOAD_ERR_EXTENSION:
                return 'Upload bloqueado por extensão do PHP';
            default:
                return 'Erro ao fazer upload do arquivo';
        }
    }
}
