<?php
function checkPlanLimit($userId) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT plan, plan_limit, messages_sent FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    // Verificar se atingiu o limite
    if ($user['messages_sent'] >= $user['plan_limit']) {
        return [
            'allowed' => false,
            'message' => 'Você atingiu o limite do seu plano. Faça upgrade!'
        ];
    }
    
    return ['allowed' => true];
}

function incrementMessageCount($userId) {
    global $pdo;
    $stmt = $pdo->prepare("UPDATE users SET messages_sent = messages_sent + 1 WHERE id = ?");
    $stmt->execute([$userId]);
}
?>