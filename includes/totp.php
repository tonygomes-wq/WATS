<?php
/**
 * Classe para gerenciar TOTP (Time-based One-Time Password)
 * Compatível com Google Authenticator e Microsoft Authenticator
 */
class TOTP {
    
    /**
     * Gerar um secret aleatório para 2FA
     */
    public static function generateSecret($length = 32) {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = '';
        for ($i = 0; $i < $length; $i++) {
            $secret .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $secret;
    }
    
    /**
     * Gerar QR Code URL para configuração no app authenticator
     */
    public static function getQRCodeUrl($secret, $label, $issuer = 'WATS') {
        $label = urlencode($label);
        $issuer = urlencode($issuer);
        $secret = str_replace(' ', '', $secret);
        
        $otpauth = "otpauth://totp/{$label}?secret={$secret}&issuer={$issuer}";
        
        // Tentar múltiplas APIs de QR Code como fallback
        $qrApis = [
            "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=" . urlencode($otpauth),
            "https://chart.googleapis.com/chart?chs=200x200&chld=M|0&cht=qr&chl=" . urlencode($otpauth),
            "https://quickchart.io/qr?text=" . urlencode($otpauth) . "&size=200"
        ];
        
        // Retornar a primeira API (QR Server é mais confiável)
        return $qrApis[0];
    }
    
    /**
     * Gerar múltiplas URLs de QR Code para fallback
     */
    public static function getQRCodeUrls($secret, $label, $issuer = 'WATS') {
        $label = urlencode($label);
        $issuer = urlencode($issuer);
        $secret = str_replace(' ', '', $secret);
        
        $otpauth = "otpauth://totp/{$label}?secret={$secret}&issuer={$issuer}";
        
        return [
            'qrserver' => "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=" . urlencode($otpauth),
            'google' => "https://chart.googleapis.com/chart?chs=200x200&chld=M|0&cht=qr&chl=" . urlencode($otpauth),
            'quickchart' => "https://quickchart.io/qr?text=" . urlencode($otpauth) . "&size=200",
            'otpauth' => $otpauth
        ];
    }
    
    /**
     * Verificar código TOTP
     */
    public static function verifyCode($secret, $code, $window = 1) {
        $secret = str_replace(' ', '', strtoupper($secret));
        $code = str_pad($code, 6, '0', STR_PAD_LEFT);
        
        $currentTime = time();
        $timeStep = 30; // 30 segundos por step (padrão TOTP)
        
        // Verificar código atual e códigos adjacentes (para compensar diferença de relógio)
        for ($i = -$window; $i <= $window; $i++) {
            $timestamp = $currentTime + ($i * $timeStep);
            $calculatedCode = self::generateCode($secret, $timestamp);
            
            if (hash_equals($calculatedCode, $code)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Gerar código TOTP para um timestamp específico
     */
    private static function generateCode($secret, $timestamp) {
        $timeStep = 30;
        $counter = floor($timestamp / $timeStep);
        
        // Decodificar secret base32
        $secretBinary = self::base32Decode($secret);
        
        // Converter counter para binary (8 bytes, big endian)
        $counterBinary = pack('N*', 0) . pack('N*', $counter);
        
        // Gerar HMAC-SHA1
        $hash = hash_hmac('sha1', $counterBinary, $secretBinary, true);
        
        // Extrair código de 6 dígitos
        $offset = ord($hash[19]) & 0xf;
        $code = (
            ((ord($hash[$offset + 0]) & 0x7f) << 24) |
            ((ord($hash[$offset + 1]) & 0xff) << 16) |
            ((ord($hash[$offset + 2]) & 0xff) << 8) |
            (ord($hash[$offset + 3]) & 0xff)
        ) % 1000000;
        
        return str_pad($code, 6, '0', STR_PAD_LEFT);
    }
    
    /**
     * Decodificar string base32
     */
    private static function base32Decode($input) {
        $input = strtoupper($input);
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $output = '';
        $v = 0;
        $vbits = 0;
        
        for ($i = 0; $i < strlen($input); $i++) {
            $char = $input[$i];
            if ($char === '=') break;
            
            $pos = strpos($alphabet, $char);
            if ($pos === false) continue;
            
            $v = ($v << 5) | $pos;
            $vbits += 5;
            
            if ($vbits >= 8) {
                $output .= chr(($v >> ($vbits - 8)) & 0xff);
                $vbits -= 8;
            }
        }
        
        return $output;
    }
    
    /**
     * Gerar códigos de backup (para caso o usuário perca o dispositivo)
     */
    public static function generateBackupCodes($count = 10) {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $codes[] = sprintf('%04d-%04d', random_int(1000, 9999), random_int(1000, 9999));
        }
        return $codes;
    }
    
    /**
     * Verificar código de backup
     */
    public static function verifyBackupCode($storedCodes, $inputCode) {
        $inputCode = strtoupper(trim($inputCode));
        
        if (is_string($storedCodes)) {
            $storedCodes = json_decode($storedCodes, true) ?: [];
        }
        
        $key = array_search($inputCode, $storedCodes);
        if ($key !== false) {
            // Remover código usado
            unset($storedCodes[$key]);
            return [
                'valid' => true,
                'remaining_codes' => array_values($storedCodes)
            ];
        }
        
        return [
            'valid' => false,
            'remaining_codes' => $storedCodes
        ];
    }
}
?>
