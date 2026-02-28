<?php

/**
 * Definição de Rotas
 * 
 * Mapeia URLs para Controllers
 */

function registerRoutes(Router $router) {
    // ============================================
    // ROTAS COMPATÍVEIS COM SISTEMA ATUAL
    // Formato: ?action=conversations
    // ============================================
    
    // Rota raiz - redireciona baseado em ?action
    $router->get('/', 'ApiController@dispatch');
    
    // ============================================
    // ROTAS FUTURAS (REST API)
    // Formato: /api/v2/conversations
    // ============================================
    
    // Conversas
    $router->get('/api/v2/conversations', 'ConversationController@index');
    $router->post('/api/v2/conversations', 'ConversationController@create');
    $router->put('/api/v2/conversations/:id', 'ConversationController@update');
    $router->delete('/api/v2/conversations/:id', 'ConversationController@delete');
    
    // Mensagens
    $router->get('/api/v2/messages', 'MessageController@index');
    $router->post('/api/v2/messages', 'MessageController@create');
    $router->delete('/api/v2/messages/:id', 'MessageController@delete');
}
