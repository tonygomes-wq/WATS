<?php
/**
 * API: Listar Ramais VoIP Disponíveis
 * Retorna lista de ramais que o usuário pode ver e transferir chamadas
 * 
 * Regras:
 * - Supervisor vê todos os ramais do seu grupo
 * - Atendente vê seu ramal + ramais compartilhados do supervisor
 * - Atendente com instância própria vê ambos (próprio + compartilhados)
 */

session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

requireLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método não permitido']);
    exit;
}

$userId = $_SESSION['user_id'];
$userType = $_SESSION['user_type'] ?? 'supervisor'; // 'supervisor' ou 'attendant'

try {
    $extensions = [];
    $supervisorId = null;
    $useOwnInstance = 0;
    
    // ============================================
    // 1. IDENTIFICAR TIPO DE USUÁRIO
    // ============================================
    
    if ($userType === 'attendant') {
        // Buscar informações do atendente
        $stmt = $pdo->prepare("
            SELECT supervisor_id, use_own_instance, name, email
            FROM supervisor_users 
            WHERE id = ?
        ");
        $stmt->execute([$userId]);
        $attendant = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$attendant) {
            throw new Exception('Atendente não encontrado');
        }
        
        $supervisorId = $attendant['supervisor_id'];
        $useOwnInstance = $attendant['use_own_instance'];
        
    } else {
        // É supervisor
        $supervisorId = $userId;
        $useOwnInstance = 0;
    }
    
    // ============================================
    // 2. BUSCAR RAMAL PRÓPRIO DO USUÁRIO
    // ============================================
    
    $stmt = $pdo->prepare("
        SELECT 
            v.*,
            COALESCE(u.name, su.name) as user_name,
            COALESCE(u.email, su.email) as user_email,
            CASE 
                WHEN u.id IS NOT NULL THEN 'supervisor'
                ELSE 'attendant'
            END as owner_type
        FROM voip_users v
        LEFT JOIN users u ON v.user_id = u.id AND v.supervisor_id IS NULL
        LEFT JOIN supervisor_users su ON v.user_id = su.id AND v.supervisor_id IS NOT NULL
        WHERE v.user_id = ?
        ORDER BY v.is_shared ASC, v.extension ASC
    ");
    $stmt->execute([$userId]);
    $ownExtensions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($ownExtensions as $ext) {
        $extensions[] = [
            'id' => $ext['id'],
            'extension' => $ext['extension'],
            'display_name' => $ext['display_name'] ?: $ext['user_name'],
            'account_name' => $ext['account_name'],
            'email' => $ext['user_email'],
            'group_name' => $ext['group_name'],
            'owner_type' => $ext['owner_type'],
            'is_own' => true,
            'is_shared' => (bool)$ext['is_shared'],
            'status' => $ext['status'],
            'sip_server' => $ext['sip_server'],
            'sip_domain' => $ext['sip_domain']
        ];
    }
    
    // ============================================
    // 3. BUSCAR RAMAIS COMPARTILHADOS DO GRUPO
    // ============================================
    
    // Se não usa instância própria OU é supervisor, busca ramais compartilhados
    if ($useOwnInstance == 0 || $userType === 'supervisor') {
        
        // Buscar ramal do supervisor (se usuário é atendente)
        if ($userType === 'attendant' && $supervisorId) {
            $stmt = $pdo->prepare("
                SELECT 
                    v.*,
                    u.name as user_name,
                    u.email as user_email,
                    'supervisor' as owner_type
                FROM voip_users v
                INNER JOIN users u ON v.user_id = u.id
                WHERE v.user_id = ?
                  AND v.supervisor_id IS NULL
                ORDER BY v.extension ASC
            ");
            $stmt->execute([$supervisorId]);
            $supervisorExtensions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($supervisorExtensions as $ext) {
                $extensions[] = [
                    'id' => $ext['id'],
                    'extension' => $ext['extension'],
                    'display_name' => $ext['display_name'] ?: $ext['user_name'],
                    'account_name' => $ext['account_name'],
                    'email' => $ext['user_email'],
                    'group_name' => $ext['group_name'],
                    'owner_type' => 'supervisor',
                    'is_own' => false,
                    'is_shared' => false,
                    'is_supervisor' => true,
                    'status' => $ext['status'],
                    'sip_server' => $ext['sip_server'],
                    'sip_domain' => $ext['sip_domain']
                ];
            }
        }
        
        // Buscar ramais compartilhados de outros atendentes
        if ($supervisorId) {
            $stmt = $pdo->prepare("
                SELECT 
                    v.*,
                    su.name as user_name,
                    su.email as user_email,
                    'attendant' as owner_type
                FROM voip_users v
                INNER JOIN supervisor_users su ON v.user_id = su.id
                WHERE v.supervisor_id = ? 
                  AND v.is_shared = 1
                  AND v.user_id != ?
                ORDER BY v.group_name ASC, v.extension ASC
            ");
            $stmt->execute([$supervisorId, $userId]);
            $sharedExtensions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($sharedExtensions as $ext) {
                $extensions[] = [
                    'id' => $ext['id'],
                    'extension' => $ext['extension'],
                    'display_name' => $ext['display_name'] ?: $ext['user_name'],
                    'account_name' => $ext['account_name'],
                    'email' => $ext['user_email'],
                    'group_name' => $ext['group_name'],
                    'owner_type' => 'attendant',
                    'is_own' => false,
                    'is_shared' => true,
                    'is_supervisor' => false,
                    'status' => $ext['status'],
                    'sip_server' => $ext['sip_server'],
                    'sip_domain' => $ext['sip_domain']
                ];
            }
        }
    }
    
    // ============================================
    // 4. AGRUPAR POR CATEGORIA
    // ============================================
    
    $grouped = [
        'own' => [],
        'supervisor' => [],
        'team' => []
    ];
    
    foreach ($extensions as $ext) {
        if ($ext['is_own']) {
            $grouped['own'][] = $ext;
        } elseif (isset($ext['is_supervisor']) && $ext['is_supervisor']) {
            $grouped['supervisor'][] = $ext;
        } else {
            $grouped['team'][] = $ext;
        }
    }
    
    // ============================================
    // 5. RETORNAR RESPOSTA
    // ============================================
    
    echo json_encode([
        'success' => true,
        'extensions' => $extensions,
        'grouped' => $grouped,
        'total' => count($extensions),
        'user_type' => $userType,
        'use_own_instance' => (bool)$useOwnInstance,
        'supervisor_id' => $supervisorId
    ]);
    
} catch (Exception $e) {
    error_log("VoIP List Extensions Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
