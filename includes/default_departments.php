<?php
/**
 * Setores Padrão do Sistema
 * Criados automaticamente quando um usuário supervisor é ativado
 */

function getDefaultDepartments() {
    return [
        [
            'name' => 'Financeiro',
            'description' => 'Setor responsável por questões financeiras, cobranças e pagamentos',
            'color' => '#10B981', // Verde
            'icon' => 'fa-dollar-sign'
        ],
        [
            'name' => 'Suporte Técnico',
            'description' => 'Atendimento técnico e resolução de problemas',
            'color' => '#3B82F6', // Azul
            'icon' => 'fa-headset'
        ],
        [
            'name' => 'Vendas',
            'description' => 'Equipe comercial e vendas',
            'color' => '#8B5CF6', // Roxo
            'icon' => 'fa-shopping-cart'
        ],
        [
            'name' => 'Atendimento Geral',
            'description' => 'Atendimento ao cliente e informações gerais',
            'color' => '#F59E0B', // Laranja
            'icon' => 'fa-users'
        ],
        [
            'name' => 'Administrativo',
            'description' => 'Questões administrativas e documentação',
            'color' => '#EF4444', // Vermelho
            'icon' => 'fa-file-alt'
        ],
        [
            'name' => 'Recursos Humanos',
            'description' => 'RH, recrutamento e gestão de pessoas',
            'color' => '#EC4899', // Rosa
            'icon' => 'fa-user-tie'
        ],
        [
            'name' => 'Marketing',
            'description' => 'Marketing, comunicação e divulgação',
            'color' => '#06B6D4', // Ciano
            'icon' => 'fa-bullhorn'
        ],
        [
            'name' => 'Jurídico',
            'description' => 'Questões legais e jurídicas',
            'color' => '#6366F1', // Índigo
            'icon' => 'fa-gavel'
        ]
    ];
}

/**
 * Cria os setores padrão para um supervisor
 * @param PDO $pdo Conexão com banco de dados
 * @param int $supervisor_id ID do usuário supervisor
 * @return array Array com IDs dos setores criados
 */
function createDefaultDepartments($pdo, $supervisor_id) {
    $departments = getDefaultDepartments();
    $created_ids = [];
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO departments (supervisor_id, name, description, color, is_active)
            VALUES (:supervisor_id, :name, :description, :color, 1)
        ");
        
        foreach ($departments as $dept) {
            $stmt->execute([
                ':supervisor_id' => $supervisor_id,
                ':name' => $dept['name'],
                ':description' => $dept['description'],
                ':color' => $dept['color']
            ]);
            
            $created_ids[] = $pdo->lastInsertId();
        }
        
        return [
            'success' => true,
            'created_count' => count($created_ids),
            'department_ids' => $created_ids
        ];
        
    } catch (PDOException $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Verifica se o supervisor já tem setores criados
 * @param PDO $pdo Conexão com banco de dados
 * @param int $supervisor_id ID do usuário supervisor
 * @return bool
 */
function supervisorHasDepartments($pdo, $supervisor_id) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM departments 
        WHERE supervisor_id = :supervisor_id
    ");
    $stmt->execute([':supervisor_id' => $supervisor_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $result['count'] > 0;
}

/**
 * Obtém cores disponíveis para setores
 * @return array
 */
function getDepartmentColors() {
    return [
        ['name' => 'Verde', 'value' => '#10B981'],
        ['name' => 'Azul', 'value' => '#3B82F6'],
        ['name' => 'Roxo', 'value' => '#8B5CF6'],
        ['name' => 'Laranja', 'value' => '#F59E0B'],
        ['name' => 'Vermelho', 'value' => '#EF4444'],
        ['name' => 'Rosa', 'value' => '#EC4899'],
        ['name' => 'Ciano', 'value' => '#06B6D4'],
        ['name' => 'Índigo', 'value' => '#6366F1'],
        ['name' => 'Amarelo', 'value' => '#FBBF24'],
        ['name' => 'Verde Escuro', 'value' => '#059669'],
        ['name' => 'Azul Escuro', 'value' => '#2563EB'],
        ['name' => 'Cinza', 'value' => '#6B7280']
    ];
}

/**
 * Obtém ícones disponíveis para setores
 * @return array
 */
function getDepartmentIcons() {
    return [
        'fa-dollar-sign',
        'fa-headset',
        'fa-shopping-cart',
        'fa-users',
        'fa-file-alt',
        'fa-user-tie',
        'fa-bullhorn',
        'fa-gavel',
        'fa-cog',
        'fa-chart-line',
        'fa-phone',
        'fa-envelope',
        'fa-comments',
        'fa-handshake',
        'fa-briefcase',
        'fa-building',
        'fa-clipboard-list',
        'fa-tasks'
    ];
}

/**
 * Obtém status disponíveis para conversas
 * @return array
 */
function getConversationStatuses() {
    return [
        [
            'value' => 'open',
            'label' => 'Aberto',
            'color' => '#3B82F6', // Azul
            'icon' => 'fa-inbox'
        ],
        [
            'value' => 'in_progress',
            'label' => 'Em Atendimento',
            'color' => '#F59E0B', // Laranja
            'icon' => 'fa-clock'
        ],
        [
            'value' => 'resolved',
            'label' => 'Resolvido',
            'color' => '#10B981', // Verde
            'icon' => 'fa-check-circle'
        ],
        [
            'value' => 'closed',
            'label' => 'Encerrado',
            'color' => '#6B7280', // Cinza
            'icon' => 'fa-times-circle'
        ],
        [
            'value' => 'transferred',
            'label' => 'Transferido',
            'color' => '#8B5CF6', // Roxo
            'icon' => 'fa-exchange-alt'
        ]
    ];
}

/**
 * Obtém prioridades disponíveis para conversas
 * @return array
 */
function getConversationPriorities() {
    return [
        [
            'value' => 'low',
            'label' => 'Baixa',
            'color' => '#6B7280', // Cinza
            'icon' => 'fa-arrow-down'
        ],
        [
            'value' => 'normal',
            'label' => 'Normal',
            'color' => '#3B82F6', // Azul
            'icon' => 'fa-minus'
        ],
        [
            'value' => 'high',
            'label' => 'Alta',
            'color' => '#F59E0B', // Laranja
            'icon' => 'fa-arrow-up'
        ],
        [
            'value' => 'urgent',
            'label' => 'Urgente',
            'color' => '#EF4444', // Vermelho
            'icon' => 'fa-exclamation-triangle'
        ]
    ];
}
