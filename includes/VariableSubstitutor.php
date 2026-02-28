<?php

/**
 * VariableSubstitutor
 * 
 * Componente responsável por substituir variáveis de contexto em textos.
 * Suporta variáveis no formato {{variable_name}} com matching case-insensitive.
 * 
 * Variáveis suportadas:
 * - {{contact_name}}: Nome do contato
 * - {{contact_phone}}: Telefone do contato
 * - {{message}}: Mensagem recebida
 * - {{ai_response}}: Resposta gerada pela IA
 * - {{timestamp}}: Data/hora atual formatada
 * - {{conversation_id}}: ID da conversa
 * - {{history}}: Histórico recente de mensagens
 * - {{channel}}: Canal da conversa (whatsapp, teams, instagram)
 */
class VariableSubstitutor
{
    /**
     * Substitui variáveis em texto
     * 
     * Processa texto contendo variáveis no formato {{variable_name}} e substitui
     * pelos valores correspondentes do contexto. Variáveis não encontradas são
     * substituídas por string vazia.
     * 
     * @param string $text Texto com variáveis no formato {{variable_name}}
     * @param array $context Contexto com valores das variáveis
     * @return string Texto com variáveis substituídas
     */
    public static function substitute(string $text, array $context): string
    {
        // Regex para encontrar variáveis no formato {{variable_name}}
        // Captura o nome da variável entre {{ e }}
        $pattern = '/\{\{([a-zA-Z_][a-zA-Z0-9_]*)\}\}/';
        
        // Substitui cada variável encontrada
        $result = preg_replace_callback($pattern, function($matches) use ($context) {
            $variableName = $matches[1]; // Nome da variável capturada
            
            // Busca case-insensitive no contexto
            $value = self::findContextValue($variableName, $context);
            
            // Se não encontrou, retorna string vazia
            if ($value === null) {
                return '';
            }
            
            // Converte arrays e objetos para string
            if (is_array($value)) {
                return self::formatArrayValue($value);
            }
            
            // Converte outros tipos para string
            return (string) $value;
        }, $text);
        
        return $result;
    }
    
    /**
     * Busca valor no contexto de forma case-insensitive
     * 
     * @param string $variableName Nome da variável
     * @param array $context Contexto com valores
     * @return mixed|null Valor encontrado ou null
     */
    private static function findContextValue(string $variableName, array $context)
    {
        // Primeiro tenta busca exata (mais rápido)
        if (array_key_exists($variableName, $context)) {
            return $context[$variableName];
        }
        
        // Busca case-insensitive
        $lowerName = strtolower($variableName);
        foreach ($context as $key => $value) {
            if (strtolower($key) === $lowerName) {
                return $value;
            }
        }
        
        return null;
    }
    
    /**
     * Formata valor array para string
     * 
     * @param array $value Array a ser formatado
     * @return string Representação em string
     */
    private static function formatArrayValue(array $value): string
    {
        // Se é array de mensagens de histórico, formata especialmente
        if (self::isHistoryArray($value)) {
            return self::formatHistory($value);
        }
        
        // Para outros arrays, converte para JSON
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    
    /**
     * Verifica se array é histórico de mensagens
     * 
     * @param array $value Array a verificar
     * @return bool
     */
    private static function isHistoryArray(array $value): bool
    {
        if (empty($value)) {
            return false;
        }
        
        // Verifica se primeiro elemento tem estrutura de mensagem
        $first = reset($value);
        return is_array($first) && 
               (isset($first['role']) || isset($first['text']) || isset($first['message']));
    }
    
    /**
     * Formata histórico de mensagens para string legível
     * 
     * @param array $history Array de mensagens
     * @return string Histórico formatado
     */
    private static function formatHistory(array $history): string
    {
        $lines = [];
        
        foreach ($history as $message) {
            $role = $message['role'] ?? 'user';
            $text = $message['text'] ?? $message['message'] ?? '';
            
            $roleLabel = $role === 'user' ? 'Usuário' : 'Assistente';
            $lines[] = "{$roleLabel}: {$text}";
        }
        
        return implode("\n", $lines);
    }
    
    /**
     * Extrai variáveis de um texto
     * 
     * @param string $text Texto a analisar
     * @return array Lista de nomes de variáveis encontradas
     */
    public static function extractVariables(string $text): array
    {
        $pattern = '/\{\{([a-zA-Z_][a-zA-Z0-9_]*)\}\}/';
        preg_match_all($pattern, $text, $matches);
        
        // Retorna lista única de variáveis
        return array_unique($matches[1]);
    }
    
    /**
     * Valida se todas as variáveis estão disponíveis no contexto
     * 
     * @param string $text Texto com variáveis
     * @param array $context Contexto com valores
     * @return bool True se todas as variáveis estão disponíveis
     */
    public static function validateVariables(string $text, array $context): bool
    {
        $variables = self::extractVariables($text);
        
        foreach ($variables as $variable) {
            if (self::findContextValue($variable, $context) === null) {
                return false;
            }
        }
        
        return true;
    }
}
