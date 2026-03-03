<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Teste de Exemplo
 * 
 * Verifica se o ambiente de testes está configurado corretamente
 */
class ExampleTest extends TestCase
{
    public function testBasicAssertion(): void
    {
        $this->assertTrue(true);
    }
    
    public function testAutoloaderWorks(): void
    {
        $this->assertTrue(class_exists('App\Controllers\BaseController'));
        $this->assertTrue(class_exists('App\Services\BaseService'));
        $this->assertTrue(class_exists('App\Repositories\BaseRepository'));
    }
    
    public function testConstantsAreDefined(): void
    {
        $this->assertTrue(defined('TESTING'));
        $this->assertEquals(true, TESTING);
    }
}
