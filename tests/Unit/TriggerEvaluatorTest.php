<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

// Carregar a classe TriggerEvaluator
require_once __DIR__ . '/../../includes/TriggerEvaluator.php';

/**
 * Testes para TriggerEvaluator
 * 
 * Valida a avaliação de triggers, especialmente keyword triggers
 */
class TriggerEvaluatorTest extends TestCase
{
    private $pdo;
    private $evaluator;
    
    protected function setUp(): void
    {
        // Mock PDO para testes
        $this->pdo = $this->createMock(\PDO::class);
        $this->evaluator = new \TriggerEvaluator($this->pdo);
    }
    
    /**
     * Testa keyword matching case-insensitive
     * Validates: Requirements 2.2, 2.8
     */
    public function testKeywordMatchingCaseInsensitive(): void
    {
        $config = ['keywords' => ['olá', 'oi', 'bom dia']];
        
        // Testa com diferentes variações de case
        $this->assertTrue($this->evaluator->evaluate('keyword', $config, ['message' => 'Olá, tudo bem?']));
        $this->assertTrue($this->evaluator->evaluate('keyword', $config, ['message' => 'OLÁ']));
        $this->assertTrue($this->evaluator->evaluate('keyword', $config, ['message' => 'olá']));
        $this->assertTrue($this->evaluator->evaluate('keyword', $config, ['message' => 'OI, preciso de ajuda']));
        $this->assertTrue($this->evaluator->evaluate('keyword', $config, ['message' => 'Bom Dia!']));
    }
    
    /**
     * Testa suporte a múltiplas keywords
     * Validates: Requirements 2.2
     */
    public function testMultipleKeywords(): void
    {
        $config = ['keywords' => ['ajuda', 'suporte', 'problema']];
        
        $this->assertTrue($this->evaluator->evaluate('keyword', $config, ['message' => 'Preciso de ajuda']));
        $this->assertTrue($this->evaluator->evaluate('keyword', $config, ['message' => 'Tenho um problema']));
        $this->assertTrue($this->evaluator->evaluate('keyword', $config, ['message' => 'Suporte técnico']));
        $this->assertFalse($this->evaluator->evaluate('keyword', $config, ['message' => 'Olá, tudo bem?']));
    }
    
    /**
     * Testa keywords como string separada por vírgulas
     * Validates: Requirements 2.2
     */
    public function testKeywordsAsCommaSeparatedString(): void
    {
        $config = ['keywords' => 'olá, oi, bom dia'];
        
        $this->assertTrue($this->evaluator->evaluate('keyword', $config, ['message' => 'Olá!']));
        $this->assertTrue($this->evaluator->evaluate('keyword', $config, ['message' => 'oi']));
        $this->assertTrue($this->evaluator->evaluate('keyword', $config, ['message' => 'Bom dia']));
    }
    
    /**
     * Testa keywords com espaços extras
     * Validates: Requirements 2.2
     */
    public function testKeywordsWithExtraSpaces(): void
    {
        $config = ['keywords' => '  olá  ,  oi  ,  bom dia  '];
        
        $this->assertTrue($this->evaluator->evaluate('keyword', $config, ['message' => 'Olá!']));
        $this->assertTrue($this->evaluator->evaluate('keyword', $config, ['message' => 'oi']));
    }
    
    /**
     * Testa comportamento com keywords vazias
     * Validates: Requirements 2.2
     */
    public function testEmptyKeywords(): void
    {
        // Config sem keywords
        $this->assertFalse($this->evaluator->evaluate('keyword', [], ['message' => 'Olá']));
        
        // Config com keywords vazio
        $this->assertFalse($this->evaluator->evaluate('keyword', ['keywords' => []], ['message' => 'Olá']));
        
        // Config com string vazia
        $this->assertFalse($this->evaluator->evaluate('keyword', ['keywords' => ''], ['message' => 'Olá']));
    }
    
    /**
     * Testa comportamento com mensagem vazia
     * Validates: Requirements 2.2
     */
    public function testEmptyMessage(): void
    {
        $config = ['keywords' => ['olá', 'oi']];
        
        $this->assertFalse($this->evaluator->evaluate('keyword', $config, []));
        $this->assertFalse($this->evaluator->evaluate('keyword', $config, ['message' => '']));
    }
    
    /**
     * Testa matching parcial (keyword contida na mensagem)
     * Validates: Requirements 2.2
     */
    public function testPartialMatching(): void
    {
        $config = ['keywords' => ['ajuda']];
        
        // Keyword no início
        $this->assertTrue($this->evaluator->evaluate('keyword', $config, ['message' => 'ajuda por favor']));
        
        // Keyword no meio
        $this->assertTrue($this->evaluator->evaluate('keyword', $config, ['message' => 'preciso de ajuda urgente']));
        
        // Keyword no fim
        $this->assertTrue($this->evaluator->evaluate('keyword', $config, ['message' => 'preciso de ajuda']));
        
        // Keyword como parte de palavra maior
        $this->assertTrue($this->evaluator->evaluate('keyword', $config, ['message' => 'ajudando']));
    }
    
    /**
     * Testa suporte a caracteres UTF-8
     * Validates: Requirements 2.2, 2.8
     */
    public function testUTF8Support(): void
    {
        $config = ['keywords' => ['ação', 'informação', 'não']];
        
        $this->assertTrue($this->evaluator->evaluate('keyword', $config, ['message' => 'Preciso de uma ação']));
        $this->assertTrue($this->evaluator->evaluate('keyword', $config, ['message' => 'Mais informação']));
        $this->assertTrue($this->evaluator->evaluate('keyword', $config, ['message' => 'Não entendi']));
    }
    
    /**
     * Testa tipo de trigger inválido
     */
    public function testInvalidTriggerType(): void
    {
        $config = ['keywords' => ['olá']];
        
        $this->assertFalse($this->evaluator->evaluate('invalid_type', $config, ['message' => 'Olá']));
    }
    
    /**
     * Testa trigger manual (sempre retorna false)
     */
    public function testManualTrigger(): void
    {
        $config = [];
        
        $this->assertFalse($this->evaluator->evaluate('manual', $config, ['message' => 'Qualquer mensagem']));
    }
    
    /**
     * Testa first_message trigger - primeira mensagem com conversation_id
     * Validates: Requirements 2.3
     */
    public function testFirstMessageWithConversationId(): void
    {
        // Mock PDOStatement
        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->expects($this->once())
             ->method('execute')
             ->with($this->callback(function($params) {
                 return isset($params[':conversation_id']) && $params[':conversation_id'] === 123;
             }))
             ->willReturn(true);
        
        $stmt->expects($this->once())
             ->method('fetch')
             ->willReturn(['message_count' => 1]);
        
        // Mock PDO
        $this->pdo->expects($this->once())
                  ->method('prepare')
                  ->with($this->stringContains('WHERE cm.conversation_id = :conversation_id'))
                  ->willReturn($stmt);
        
        $config = [];
        $context = ['conversation_id' => 123];
        
        $this->assertTrue($this->evaluator->evaluate('first_message', $config, $context));
    }
    
    /**
     * Testa first_message trigger - não é primeira mensagem
     * Validates: Requirements 2.3
     */
    public function testNotFirstMessage(): void
    {
        // Mock PDOStatement
        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->expects($this->once())
             ->method('execute')
             ->willReturn(true);
        
        $stmt->expects($this->once())
             ->method('fetch')
             ->willReturn(['message_count' => 5]);
        
        // Mock PDO
        $this->pdo->expects($this->once())
                  ->method('prepare')
                  ->willReturn($stmt);
        
        $config = [];
        $context = ['conversation_id' => 123];
        
        $this->assertFalse($this->evaluator->evaluate('first_message', $config, $context));
    }
    
    /**
     * Testa first_message trigger com phone ao invés de conversation_id
     * Validates: Requirements 2.3
     */
    public function testFirstMessageWithPhone(): void
    {
        // Mock PDOStatement
        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->expects($this->once())
             ->method('execute')
             ->with($this->callback(function($params) {
                 return isset($params[':phone']) && $params[':phone'] === '5511999999999';
             }))
             ->willReturn(true);
        
        $stmt->expects($this->once())
             ->method('fetch')
             ->willReturn(['message_count' => 1]);
        
        // Mock PDO
        $this->pdo->expects($this->once())
                  ->method('prepare')
                  ->with($this->stringContains('WHERE cc.contact_number = :phone'))
                  ->willReturn($stmt);
        
        $config = [];
        $context = ['phone' => '5511999999999'];
        
        $this->assertTrue($this->evaluator->evaluate('first_message', $config, $context));
    }
    
    /**
     * Testa first_message trigger com window_seconds
     * Validates: Requirements 2.3
     */
    public function testFirstMessageWithWindowSeconds(): void
    {
        // Mock PDOStatement
        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->expects($this->once())
             ->method('execute')
             ->with($this->callback(function($params) {
                 return isset($params[':conversation_id']) && 
                        isset($params[':window_seconds']) && 
                        $params[':window_seconds'] === 600;
             }))
             ->willReturn(true);
        
        $stmt->expects($this->once())
             ->method('fetch')
             ->willReturn(['message_count' => 1]);
        
        // Mock PDO
        $this->pdo->expects($this->once())
                  ->method('prepare')
                  ->with($this->stringContains('DATE_SUB(NOW(), INTERVAL :window_seconds SECOND)'))
                  ->willReturn($stmt);
        
        $config = ['window_seconds' => 600];
        $context = ['conversation_id' => 123];
        
        $this->assertTrue($this->evaluator->evaluate('first_message', $config, $context));
    }
    
    /**
     * Testa first_message trigger sem conversation_id nem phone
     * Validates: Requirements 2.3
     */
    public function testFirstMessageWithoutConversationIdOrPhone(): void
    {
        $config = [];
        $context = ['message' => 'Olá'];
        
        $this->assertFalse($this->evaluator->evaluate('first_message', $config, $context));
    }
    
    /**
     * Testa first_message trigger com erro de banco de dados
     * Validates: Requirements 2.3
     */
    public function testFirstMessageDatabaseError(): void
    {
        // Mock PDO para lançar exceção
        $this->pdo->expects($this->once())
                  ->method('prepare')
                  ->willThrowException(new \PDOException('Database error'));
        
        $config = [];
        $context = ['conversation_id' => 123];
        
        $this->assertFalse($this->evaluator->evaluate('first_message', $config, $context));
    }
    
    /**
     * Testa no_response trigger - conversa sem resposta há mais tempo que o configurado
     * Validates: Requirements 2.5
     */
    public function testNoResponseTriggerExceedsThreshold(): void
    {
        $config = ['minutes' => 30];
        
        // Mock PDOStatement para retornar última mensagem de 45 minutos atrás
        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->expects($this->once())
             ->method('execute')
             ->with($this->callback(function($params) {
                 return isset($params[':conversation_id']) && $params[':conversation_id'] === 123;
             }))
             ->willReturn(true);
        
        // Última resposta foi há 45 minutos
        $lastResponseTime = date('Y-m-d H:i:s', time() - (45 * 60));
        $stmt->expects($this->once())
             ->method('fetch')
             ->willReturn(['created_at' => $lastResponseTime]);
        
        // Mock PDO
        $this->pdo->expects($this->once())
                  ->method('prepare')
                  ->with($this->stringContains("sender_type IN ('user', 'system')"))
                  ->willReturn($stmt);
        
        $context = ['conversation_id' => 123];
        
        // Deve retornar true pois 45 minutos > 30 minutos
        $this->assertTrue($this->evaluator->evaluate('no_response', $config, $context));
    }
    
    /**
     * Testa no_response trigger - conversa com resposta recente
     * Validates: Requirements 2.5
     */
    public function testNoResponseTriggerWithinThreshold(): void
    {
        $config = ['minutes' => 30];
        
        // Mock PDOStatement para retornar última mensagem de 15 minutos atrás
        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->expects($this->once())
             ->method('execute')
             ->willReturn(true);
        
        // Última resposta foi há 15 minutos
        $lastResponseTime = date('Y-m-d H:i:s', time() - (15 * 60));
        $stmt->expects($this->once())
             ->method('fetch')
             ->willReturn(['created_at' => $lastResponseTime]);
        
        // Mock PDO
        $this->pdo->expects($this->once())
                  ->method('prepare')
                  ->willReturn($stmt);
        
        $context = ['conversation_id' => 123];
        
        // Deve retornar false pois 15 minutos < 30 minutos
        $this->assertFalse($this->evaluator->evaluate('no_response', $config, $context));
    }
    
    /**
     * Testa no_response trigger - primeira mensagem do contato (sem resposta anterior)
     * Validates: Requirements 2.5
     */
    public function testNoResponseTriggerFirstMessage(): void
    {
        $config = ['minutes' => 30];
        
        // Mock PDOStatement para retornar nenhuma mensagem anterior
        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->expects($this->once())
             ->method('execute')
             ->willReturn(true);
        
        // Nenhuma mensagem anterior de atendente
        $stmt->expects($this->once())
             ->method('fetch')
             ->willReturn(false);
        
        // Mock PDO
        $this->pdo->expects($this->once())
                  ->method('prepare')
                  ->willReturn($stmt);
        
        $context = ['conversation_id' => 123];
        
        // Deve retornar true pois não há resposta anterior
        $this->assertTrue($this->evaluator->evaluate('no_response', $config, $context));
    }
    
    /**
     * Testa no_response trigger - exatamente no threshold
     * Validates: Requirements 2.5
     */
    public function testNoResponseTriggerExactThreshold(): void
    {
        $config = ['minutes' => 30];
        
        // Mock PDOStatement para retornar última mensagem de exatamente 30 minutos atrás
        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->expects($this->once())
             ->method('execute')
             ->willReturn(true);
        
        // Última resposta foi há exatamente 30 minutos
        $lastResponseTime = date('Y-m-d H:i:s', time() - (30 * 60));
        $stmt->expects($this->once())
             ->method('fetch')
             ->willReturn(['created_at' => $lastResponseTime]);
        
        // Mock PDO
        $this->pdo->expects($this->once())
                  ->method('prepare')
                  ->willReturn($stmt);
        
        $context = ['conversation_id' => 123];
        
        // Deve retornar false pois 30 minutos = 30 minutos (não é maior)
        $this->assertFalse($this->evaluator->evaluate('no_response', $config, $context));
    }
    
    /**
     * Testa no_response trigger com timestamp customizado no contexto
     * Validates: Requirements 2.5
     */
    public function testNoResponseTriggerWithCustomTimestamp(): void
    {
        $config = ['minutes' => 30];
        
        // Mock PDOStatement
        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->expects($this->once())
             ->method('execute')
             ->willReturn(true);
        
        // Última resposta foi às 10:00
        $lastResponseTime = '2024-01-15 10:00:00';
        $stmt->expects($this->once())
             ->method('fetch')
             ->willReturn(['created_at' => $lastResponseTime]);
        
        // Mock PDO
        $this->pdo->expects($this->once())
                  ->method('prepare')
                  ->willReturn($stmt);
        
        // Timestamp atual é 11:00 (60 minutos depois)
        $currentTimestamp = strtotime('2024-01-15 11:00:00');
        $context = [
            'conversation_id' => 123,
            'timestamp' => $currentTimestamp
        ];
        
        // Deve retornar true pois 60 minutos > 30 minutos
        $this->assertTrue($this->evaluator->evaluate('no_response', $config, $context));
    }
    
    /**
     * Testa no_response trigger sem conversation_id
     * Validates: Requirements 2.5
     */
    public function testNoResponseTriggerWithoutConversationId(): void
    {
        $config = ['minutes' => 30];
        $context = ['message' => 'Olá'];
        
        $this->assertFalse($this->evaluator->evaluate('no_response', $config, $context));
    }
    
    /**
     * Testa no_response trigger sem minutes na configuração
     * Validates: Requirements 2.5
     */
    public function testNoResponseTriggerWithoutMinutes(): void
    {
        $config = [];
        $context = ['conversation_id' => 123];
        
        $this->assertFalse($this->evaluator->evaluate('no_response', $config, $context));
    }
    
    /**
     * Testa no_response trigger com minutes inválido (não numérico)
     * Validates: Requirements 2.5
     */
    public function testNoResponseTriggerInvalidMinutes(): void
    {
        $config = ['minutes' => 'invalid'];
        $context = ['conversation_id' => 123];
        
        $this->assertFalse($this->evaluator->evaluate('no_response', $config, $context));
    }
    
    /**
     * Testa no_response trigger com minutes zero
     * Validates: Requirements 2.5
     */
    public function testNoResponseTriggerZeroMinutes(): void
    {
        $config = ['minutes' => 0];
        $context = ['conversation_id' => 123];
        
        $this->assertFalse($this->evaluator->evaluate('no_response', $config, $context));
    }
    
    /**
     * Testa no_response trigger com minutes negativo
     * Validates: Requirements 2.5
     */
    public function testNoResponseTriggerNegativeMinutes(): void
    {
        $config = ['minutes' => -10];
        $context = ['conversation_id' => 123];
        
        $this->assertFalse($this->evaluator->evaluate('no_response', $config, $context));
    }
    
    /**
     * Testa no_response trigger com erro de banco de dados
     * Validates: Requirements 2.5
     */
    public function testNoResponseTriggerDatabaseError(): void
    {
        $config = ['minutes' => 30];
        $context = ['conversation_id' => 123];
        
        // Mock PDO para lançar exceção
        $this->pdo->expects($this->once())
                  ->method('prepare')
                  ->willThrowException(new \PDOException('Database error'));
        
        $this->assertFalse($this->evaluator->evaluate('no_response', $config, $context));
    }
}

    /**
     * Testa off_hours trigger - horário normal (08:00 às 18:00)
     * Validates: Requirements 2.4
     */
    public function testOffHoursNormalBusinessHours(): void
    {
        $config = [
            'start' => '08:00',
            'end' => '18:00',
            'timezone' => 'America/Sao_Paulo'
        ];
        
        // Cria timestamps para diferentes horários
        $tz = new \DateTimeZone('America/Sao_Paulo');
        
        // 07:00 - Antes do horário (fora do horário)
        $dt = new \DateTime('2024-01-15 07:00:00', $tz);
        $context = ['timestamp' => $dt->getTimestamp()];
        $this->assertTrue($this->evaluator->evaluate('off_hours', $config, $context));
        
        // 08:00 - Início do horário (dentro do horário)
        $dt = new \DateTime('2024-01-15 08:00:00', $tz);
        $context = ['timestamp' => $dt->getTimestamp()];
        $this->assertFalse($this->evaluator->evaluate('off_hours', $config, $context));
        
        // 12:00 - Meio do horário (dentro do horário)
        $dt = new \DateTime('2024-01-15 12:00:00', $tz);
        $context = ['timestamp' => $dt->getTimestamp()];
        $this->assertFalse($this->evaluator->evaluate('off_hours', $config, $context));
        
        // 17:59 - Quase fim do horário (dentro do horário)
        $dt = new \DateTime('2024-01-15 17:59:00', $tz);
        $context = ['timestamp' => $dt->getTimestamp()];
        $this->assertFalse($this->evaluator->evaluate('off_hours', $config, $context));
        
        // 18:00 - Fim do horário (fora do horário)
        $dt = new \DateTime('2024-01-15 18:00:00', $tz);
        $context = ['timestamp' => $dt->getTimestamp()];
        $this->assertTrue($this->evaluator->evaluate('off_hours', $config, $context));
        
        // 19:00 - Depois do horário (fora do horário)
        $dt = new \DateTime('2024-01-15 19:00:00', $tz);
        $context = ['timestamp' => $dt->getTimestamp()];
        $this->assertTrue($this->evaluator->evaluate('off_hours', $config, $context));
    }
    
    /**
     * Testa off_hours trigger - horário overnight (18:00 às 08:00)
     * Validates: Requirements 2.4
     */
    public function testOffHoursOvernightHours(): void
    {
        $config = [
            'start' => '18:00',
            'end' => '08:00',
            'timezone' => 'America/Sao_Paulo'
        ];
        
        $tz = new \DateTimeZone('America/Sao_Paulo');
        
        // 07:00 - Antes do fim (dentro do horário overnight)
        $dt = new \DateTime('2024-01-15 07:00:00', $tz);
        $context = ['timestamp' => $dt->getTimestamp()];
        $this->assertFalse($this->evaluator->evaluate('off_hours', $config, $context));
        
        // 08:00 - Fim do horário overnight (fora do horário)
        $dt = new \DateTime('2024-01-15 08:00:00', $tz);
        $context = ['timestamp' => $dt->getTimestamp()];
        $this->assertTrue($this->evaluator->evaluate('off_hours', $config, $context));
        
        // 12:00 - Meio do dia (fora do horário)
        $dt = new \DateTime('2024-01-15 12:00:00', $tz);
        $context = ['timestamp' => $dt->getTimestamp()];
        $this->assertTrue($this->evaluator->evaluate('off_hours', $config, $context));
        
        // 17:59 - Quase início (fora do horário)
        $dt = new \DateTime('2024-01-15 17:59:00', $tz);
        $context = ['timestamp' => $dt->getTimestamp()];
        $this->assertTrue($this->evaluator->evaluate('off_hours', $config, $context));
        
        // 18:00 - Início do horário overnight (dentro do horário)
        $dt = new \DateTime('2024-01-15 18:00:00', $tz);
        $context = ['timestamp' => $dt->getTimestamp()];
        $this->assertFalse($this->evaluator->evaluate('off_hours', $config, $context));
        
        // 23:00 - Noite (dentro do horário overnight)
        $dt = new \DateTime('2024-01-15 23:00:00', $tz);
        $context = ['timestamp' => $dt->getTimestamp()];
        $this->assertFalse($this->evaluator->evaluate('off_hours', $config, $context));
        
        // 00:00 - Meia-noite (dentro do horário overnight)
        $dt = new \DateTime('2024-01-15 00:00:00', $tz);
        $context = ['timestamp' => $dt->getTimestamp()];
        $this->assertFalse($this->evaluator->evaluate('off_hours', $config, $context));
    }
    
    /**
     * Testa off_hours trigger com diferentes timezones
     * Validates: Requirements 2.4
     */
    public function testOffHoursDifferentTimezones(): void
    {
        $config = [
            'start' => '09:00',
            'end' => '17:00',
            'timezone' => 'America/New_York'
        ];
        
        $tz = new \DateTimeZone('America/New_York');
        
        // 08:00 NY - Fora do horário
        $dt = new \DateTime('2024-01-15 08:00:00', $tz);
        $context = ['timestamp' => $dt->getTimestamp()];
        $this->assertTrue($this->evaluator->evaluate('off_hours', $config, $context));
        
        // 10:00 NY - Dentro do horário
        $dt = new \DateTime('2024-01-15 10:00:00', $tz);
        $context = ['timestamp' => $dt->getTimestamp()];
        $this->assertFalse($this->evaluator->evaluate('off_hours', $config, $context));
    }
    
    /**
     * Testa off_hours trigger sem timezone (usa UTC como padrão)
     * Validates: Requirements 2.4
     */
    public function testOffHoursDefaultTimezone(): void
    {
        $config = [
            'start' => '09:00',
            'end' => '17:00'
            // timezone não especificado, deve usar UTC
        ];
        
        $tz = new \DateTimeZone('UTC');
        
        // 08:00 UTC - Fora do horário
        $dt = new \DateTime('2024-01-15 08:00:00', $tz);
        $context = ['timestamp' => $dt->getTimestamp()];
        $this->assertTrue($this->evaluator->evaluate('off_hours', $config, $context));
        
        // 10:00 UTC - Dentro do horário
        $dt = new \DateTime('2024-01-15 10:00:00', $tz);
        $context = ['timestamp' => $dt->getTimestamp()];
        $this->assertFalse($this->evaluator->evaluate('off_hours', $config, $context));
    }
    
    /**
     * Testa off_hours trigger sem timestamp (usa timestamp atual)
     * Validates: Requirements 2.4
     */
    public function testOffHoursWithoutTimestamp(): void
    {
        // Configura horário que certamente está fora (00:00 às 01:00)
        $config = [
            'start' => '00:00',
            'end' => '01:00',
            'timezone' => 'UTC'
        ];
        
        // Sem timestamp no contexto, deve usar timestamp atual
        $context = [];
        
        // Não podemos garantir o resultado pois depende do horário atual,
        // mas o método não deve lançar exceção
        $result = $this->evaluator->evaluate('off_hours', $config, $context);
        $this->assertIsBool($result);
    }
    
    /**
     * Testa off_hours trigger com configuração inválida - sem start
     * Validates: Requirements 2.4
     */
    public function testOffHoursMissingStart(): void
    {
        $config = [
            'end' => '18:00',
            'timezone' => 'America/Sao_Paulo'
        ];
        
        $context = ['timestamp' => time()];
        
        $this->assertFalse($this->evaluator->evaluate('off_hours', $config, $context));
    }
    
    /**
     * Testa off_hours trigger com configuração inválida - sem end
     * Validates: Requirements 2.4
     */
    public function testOffHoursMissingEnd(): void
    {
        $config = [
            'start' => '08:00',
            'timezone' => 'America/Sao_Paulo'
        ];
        
        $context = ['timestamp' => time()];
        
        $this->assertFalse($this->evaluator->evaluate('off_hours', $config, $context));
    }
    
    /**
     * Testa off_hours trigger com formato de horário inválido
     * Validates: Requirements 2.4
     */
    public function testOffHoursInvalidTimeFormat(): void
    {
        // Formato inválido - sem zero à esquerda
        $config = [
            'start' => '8:00',
            'end' => '18:00',
            'timezone' => 'America/Sao_Paulo'
        ];
        
        $context = ['timestamp' => time()];
        
        $this->assertFalse($this->evaluator->evaluate('off_hours', $config, $context));
        
        // Formato inválido - sem minutos
        $config = [
            'start' => '08',
            'end' => '18',
            'timezone' => 'America/Sao_Paulo'
        ];
        
        $this->assertFalse($this->evaluator->evaluate('off_hours', $config, $context));
        
        // Formato inválido - texto
        $config = [
            'start' => '8am',
            'end' => '6pm',
            'timezone' => 'America/Sao_Paulo'
        ];
        
        $this->assertFalse($this->evaluator->evaluate('off_hours', $config, $context));
    }
    
    /**
     * Testa off_hours trigger com timezone inválida
     * Validates: Requirements 2.4
     */
    public function testOffHoursInvalidTimezone(): void
    {
        $config = [
            'start' => '08:00',
            'end' => '18:00',
            'timezone' => 'Invalid/Timezone'
        ];
        
        $context = ['timestamp' => time()];
        
        $this->assertFalse($this->evaluator->evaluate('off_hours', $config, $context));
    }
    
    /**
     * Testa off_hours trigger - edge case: horário exatamente no limite
     * Validates: Requirements 2.4
     */
    public function testOffHoursExactBoundary(): void
    {
        $config = [
            'start' => '09:00',
            'end' => '17:00',
            'timezone' => 'UTC'
        ];
        
        $tz = new \DateTimeZone('UTC');
        
        // Exatamente 09:00 - Deve estar dentro do horário
        $dt = new \DateTime('2024-01-15 09:00:00', $tz);
        $context = ['timestamp' => $dt->getTimestamp()];
        $this->assertFalse($this->evaluator->evaluate('off_hours', $config, $context));
        
        // Exatamente 17:00 - Deve estar fora do horário (fim é exclusivo)
        $dt = new \DateTime('2024-01-15 17:00:00', $tz);
        $context = ['timestamp' => $dt->getTimestamp()];
        $this->assertTrue($this->evaluator->evaluate('off_hours', $config, $context));
    }
    
    /**
     * Testa off_hours trigger - edge case: horário com minutos
     * Validates: Requirements 2.4
     */
    public function testOffHoursWithMinutes(): void
    {
        $config = [
            'start' => '08:30',
            'end' => '17:45',
            'timezone' => 'UTC'
        ];
        
        $tz = new \DateTimeZone('UTC');
        
        // 08:29 - Fora do horário
        $dt = new \DateTime('2024-01-15 08:29:00', $tz);
        $context = ['timestamp' => $dt->getTimestamp()];
        $this->assertTrue($this->evaluator->evaluate('off_hours', $config, $context));
        
        // 08:30 - Dentro do horário
        $dt = new \DateTime('2024-01-15 08:30:00', $tz);
        $context = ['timestamp' => $dt->getTimestamp()];
        $this->assertFalse($this->evaluator->evaluate('off_hours', $config, $context));
        
        // 17:44 - Dentro do horário
        $dt = new \DateTime('2024-01-15 17:44:00', $tz);
        $context = ['timestamp' => $dt->getTimestamp()];
        $this->assertFalse($this->evaluator->evaluate('off_hours', $config, $context));
        
        // 17:45 - Fora do horário
        $dt = new \DateTime('2024-01-15 17:45:00', $tz);
        $context = ['timestamp' => $dt->getTimestamp()];
        $this->assertTrue($this->evaluator->evaluate('off_hours', $config, $context));
    }
