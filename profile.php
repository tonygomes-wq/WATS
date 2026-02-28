<?php
$page_title = 'Meu Perfil';
require_once 'includes/header_spa.php';

$user_id = $_SESSION['user_id'];

// Buscar dados do usuário
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if (!$user) {
        die('Usuário não encontrado');
    }
} catch (Exception $e) {
    die('Erro ao buscar usuário: ' . $e->getMessage());
}

// Processar upload de foto de perfil
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'upload_photo') {
        if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === 0) {
            $allowed = ['jpg', 'jpeg', 'png', 'gif'];
            $filename = $_FILES['profile_photo']['name'];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            
            if (!in_array($ext, $allowed)) {
                $_SESSION['error'] = 'Formato de imagem não permitido. Use JPG, PNG ou GIF.';
            } elseif ($_FILES['profile_photo']['size'] > 5 * 1024 * 1024) {
                $_SESSION['error'] = 'Imagem muito grande. Máximo 5MB.';
            } else {
                $upload_dir = 'uploads/profile_pictures/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                $new_filename = 'user_' . $user_id . '_' . time() . '.' . $ext;
                $upload_path = $upload_dir . $new_filename;
                
                if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $upload_path)) {
                    if (!empty($user['profile_photo']) && file_exists($user['profile_photo'])) {
                        @unlink($user['profile_photo']);
                    }
                    
                    $stmt = $pdo->prepare("UPDATE users SET profile_photo = ? WHERE id = ?");
                    if ($stmt->execute([$upload_path, $user_id])) {
                        $_SESSION['success'] = 'Foto de perfil atualizada com sucesso!';
                        // Atualizar sessão
                        $_SESSION['profile_photo'] = $upload_path;
                    } else {
                        $_SESSION['error'] = 'Erro ao salvar no banco de dados.';
                    }
                } else {
                    $_SESSION['error'] = 'Erro ao fazer upload da imagem.';
                }
            }
        } else {
            $_SESSION['error'] = 'Nenhum arquivo foi enviado ou houve erro no upload.';
        }
        header('Location: /profile.php');
        exit;
    }
    
    if ($action === 'update_profile') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $position = trim($_POST['position'] ?? '');
        
        if (empty($name) || empty($email)) {
            setError('Nome e email são obrigatórios.');
        } else {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->execute([$email, $user_id]);
            if ($stmt->fetch()) {
                setError('Este email já está em uso por outro usuário.');
            } else {
                try {
                    $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, phone = ?, position = ? WHERE id = ?");
                    $success = $stmt->execute([$name, $email, $phone, $position, $user_id]);
                } catch (Exception $e) {
                    $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ? WHERE id = ?");
                    $success = $stmt->execute([$name, $email, $user_id]);
                }
                
                if ($success) {
                    $_SESSION['user_name'] = $name;
                    $_SESSION['user_email'] = $email;
                    setSuccess('Perfil atualizado com sucesso!');
                    header('Location: /profile.php');
                    exit;
                } else {
                    setError('Erro ao atualizar perfil.');
                }
            }
        }
    }
    
    if ($action === 'change_password') {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            setError('Todos os campos de senha são obrigatórios.');
        } elseif ($new_password !== $confirm_password) {
            setError('As senhas não coincidem.');
        } elseif (strlen($new_password) < 6) {
            setError('A nova senha deve ter pelo menos 6 caracteres.');
        } else {
            if (!password_verify($current_password, $user['password'])) {
                setError('Senha atual incorreta.');
            } else {
                $hashed = password_hash($new_password, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                if ($stmt->execute([$hashed, $user_id])) {
                    setSuccess('Senha alterada com sucesso!');
                    header('Location: /profile.php');
                    exit;
                } else {
                    setError('Erro ao alterar senha.');
                }
            }
        }
    }
}

// Calcular estatísticas do usuário
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM contacts WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $total_contacts = $stmt->fetchColumn() ?? 0;
} catch (Exception $e) {
    $total_contacts = 0;
}

try {
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM categories WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $total_categories = $stmt->fetchColumn() ?? 0;
} catch (Exception $e) {
    $total_categories = 0;
}

try {
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM dispatch_history WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $total_dispatches = $stmt->fetchColumn() ?? 0;
} catch (Exception $e) {
    $total_dispatches = 0;
}

try {
    $stmt = $pdo->prepare("SELECT SUM(messages_sent) as total FROM dispatch_history WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $total_messages = $stmt->fetchColumn() ?? 0;
} catch (Exception $e) {
    $total_messages = 0;
}

// Buscar últimas atividades
$recent_activities = [];
try {
    $stmt = $pdo->prepare("
        SELECT action, entity_type, entity_id, details, created_at 
        FROM audit_logs 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $stmt->execute([$user_id]);
    $recent_activities = $stmt->fetchAll();
} catch (Exception $e) {
    // Tabela não existe
}

// Foto de perfil
$profile_photo = $user['profile_photo'] ?? '';
if (empty($profile_photo) || !file_exists($profile_photo)) {
    $profile_photo = 'assets/images/default-avatar.png';
}

// Nome do plano
try {
    $currentPlanSlug = $user['plan'] ?? 'free';
    $planStmt = $pdo->prepare("SELECT name FROM pricing_plans WHERE slug = ? LIMIT 1");
    $planStmt->execute([$currentPlanSlug]);
    $planData = $planStmt->fetch();
    $planName = $planData ? $planData['name'] : ucfirst($currentPlanSlug);
} catch (Exception $e) {
    $planNames = ['free' => 'Gratuito', 'basic' => 'Básico', 'pro' => 'Pro', 'enterprise' => 'Enterprise'];
    $planName = $planNames[$currentPlanSlug] ?? ucfirst($currentPlanSlug);
}
?>

<style>
/* Alert Messages */
.alert {
    padding: var(--space-4);
    border-radius: var(--radius-md);
    margin-bottom: var(--space-6);
    display: flex;
    align-items: center;
    gap: var(--space-3);
    animation: slideDown 0.3s ease-out;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.alert-success {
    background: var(--color-success-bg);
    border: 1px solid var(--color-success-border);
    color: var(--color-success);
}

.alert-error {
    background: var(--color-error-bg);
    border: 1px solid var(--color-error-border);
    color: var(--color-error);
}

.alert i {
    font-size: var(--text-xl);
}

/* Profile Page - Design System Compliant */
.profile-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: var(--space-6);
}

.profile-header {
    background: linear-gradient(135deg, var(--color-primary) 0%, var(--color-primary-active) 100%);
    border-radius: var(--radius-lg);
    padding: var(--space-8);
    color: white;
    margin-bottom: var(--space-6);
    border: 1px solid var(--border-subtle);
}

.profile-avatar {
    width: 96px;
    height: 96px;
    border-radius: var(--radius-full);
    border: 3px solid white;
    object-fit: cover;
    box-shadow: var(--shadow-md);
}

.profile-upload-btn {
    position: relative;
    overflow: hidden;
    display: inline-block;
}

.profile-upload-btn:hover {
    background: #f3f4f6 !important;
    border-color: #d1d5db !important;
}

.profile-upload-btn:hover i {
    color: #111827 !important;
}

.profile-upload-btn input[type=file] {
    position: absolute;
    left: 0;
    top: 0;
    opacity: 0;
    cursor: pointer;
    width: 100%;
    height: 100%;
}

.stat-card {
    background: var(--bg-base);
    border: 1px solid var(--border);
    border-radius: var(--radius-md);
    padding: var(--space-4);
    transition: all var(--transition-fast);
}

.stat-card:hover {
    border-color: var(--border-strong);
    box-shadow: var(--shadow-sm);
    transform: translateY(-1px);
}

.form-card {
    background: var(--bg-base);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    padding: var(--space-6);
}

.form-label {
    display: block;
    font-size: var(--text-sm);
    font-weight: var(--font-medium);
    color: var(--fg-secondary);
    margin-bottom: var(--space-2);
}

.form-input {
    width: 100%;
    padding: var(--space-3);
    border: 1px solid var(--border);
    border-radius: var(--radius-md);
    font-size: var(--text-base);
    color: var(--fg-primary);
    background: var(--bg-base);
    transition: all var(--transition-fast);
}

.form-input:focus {
    outline: none;
    border-color: var(--color-primary);
    box-shadow: 0 0 0 3px var(--color-primary-subtle);
}

.btn-primary {
    background: var(--color-primary);
    color: white;
    padding: var(--space-3) var(--space-6);
    border-radius: var(--radius-md);
    font-size: var(--text-base);
    font-weight: var(--font-medium);
    border: none;
    cursor: pointer;
    transition: all var(--transition-fast);
}

.btn-primary:hover {
    background: var(--color-primary-hover);
    transform: translateY(-1px);
    box-shadow: var(--shadow-sm);
}

.badge {
    display: inline-flex;
    align-items: center;
    gap: var(--space-2);
    padding: var(--space-2) var(--space-4);
    border-radius: var(--radius-full);
    font-size: var(--text-sm);
    font-weight: var(--font-medium);
}

.badge-admin {
    background: #f3e8ff;
    color: #7c3aed !important;
}

.badge-supervisor {
    background: #dbeafe;
    color: #2563eb !important;
}

.badge-user {
    background: var(--bg-muted);
    color: var(--fg-secondary);
}
</style>

<div class="profile-container">
    
    <!-- Mensagens de Sucesso/Erro -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <span><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></span>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <span><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></span>
        </div>
    <?php endif; ?>
    
    <!-- Header do Perfil -->
    <div class="profile-header">
        <div style="display: flex; align-items: center; gap: var(--space-6);">
            <div style="position: relative;">
                <img src="/<?php echo htmlspecialchars($profile_photo); ?>?v=<?php echo time(); ?>" 
                     alt="Foto de Perfil" 
                     class="profile-avatar"
                     onerror="this.src='/assets/images/default-avatar.png'">
                <form method="POST" enctype="multipart/form-data" id="photoForm">
                    <input type="hidden" name="action" value="upload_photo">
                    <label class="profile-upload-btn" style="position: absolute; bottom: 0; right: 0; background: white; color: #1f2937; border-radius: var(--radius-full); padding: var(--space-2); cursor: pointer; box-shadow: var(--shadow-md); border: 2px solid #e5e7eb; display: flex; align-items: center; justify-content: center; width: 32px; height: 32px; transition: all 0.2s ease;">
                        <i class="fas fa-camera" style="font-size: 14px; color: #1f2937;"></i>
                        <input type="file" name="profile_photo" accept="image/*" onchange="document.getElementById('photoForm').submit()">
                    </label>
                </form>
            </div>
            <div style="flex: 1;">
                <h1 style="font-size: var(--text-3xl); font-weight: var(--font-bold); margin-bottom: var(--space-2);">
                    <?php echo htmlspecialchars($user['name']); ?>
                </h1>
                <p style="opacity: 0.9; margin-bottom: var(--space-2);">
                    <i class="fas fa-envelope" style="margin-right: var(--space-2);"></i>
                    <?php echo htmlspecialchars($user['email']); ?>
                </p>
                <?php if (!empty($user['phone'])): ?>
                <p style="opacity: 0.9; margin-bottom: var(--space-2);">
                    <i class="fas fa-phone" style="margin-right: var(--space-2);"></i>
                    <?php echo htmlspecialchars($user['phone']); ?>
                </p>
                <?php endif; ?>
                <?php if (!empty($user['position'])): ?>
                <p style="opacity: 0.9;">
                    <i class="fas fa-briefcase" style="margin-right: var(--space-2);"></i>
                    <?php echo htmlspecialchars($user['position']); ?>
                </p>
                <?php endif; ?>
            </div>
            <div style="text-align: right;">
                <?php if ($user['is_admin']): ?>
                    <span class="badge badge-admin">
                        <i class="fas fa-crown"></i>Administrador
                    </span>
                <?php elseif ($user['is_supervisor']): ?>
                    <span class="badge badge-supervisor">
                        <i class="fas fa-user-tie"></i>Supervisor
                    </span>
                <?php else: ?>
                    <span class="badge badge-user">
                        <i class="fas fa-user"></i>Usuário
                    </span>
                <?php endif; ?>
                <p style="opacity: 0.9; font-size: var(--text-sm); margin-top: var(--space-2); color: white !important;">
                    Membro desde <?php echo date('d/m/Y', strtotime($user['created_at'])); ?>
                </p>
            </div>
        </div>
    </div>

    <!-- Informações da Conta -->
    <div class="form-card" style="margin-bottom: var(--space-6);">
        <h2 style="font-size: var(--text-xl); font-weight: var(--font-semibold); color: var(--fg-primary); margin-bottom: var(--space-4); display: flex; align-items: center; gap: var(--space-2);">
            <i class="fas fa-info-circle" style="color: var(--color-info);"></i>
            Informações da Conta
        </h2>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: var(--space-4);">
            <div class="stat-card" style="border-left: 3px solid #f59e0b;">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: var(--space-2);">
                    <i class="fas fa-crown" style="font-size: var(--text-2xl); color: #f59e0b;"></i>
                    <span style="padding: var(--space-1) var(--space-3); background: #fef3c7; color: #92400e !important; border-radius: var(--radius-full); font-size: var(--text-xs); font-weight: var(--font-semibold); text-transform: uppercase;">
                        <?php echo htmlspecialchars($user['plan'] ?? 'free'); ?>
                    </span>
                </div>
                <p style="font-size: var(--text-sm); color: var(--fg-muted); margin-bottom: var(--space-1);">Plano Atual</p>
                <p style="font-size: var(--text-2xl); font-weight: var(--font-bold); color: var(--fg-primary);">
                    <?php echo htmlspecialchars($planName); ?>
                </p>
            </div>
            
            <div class="stat-card" style="border-left: 3px solid var(--color-primary);">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: var(--space-2);">
                    <i class="fas fa-envelope" style="font-size: var(--text-2xl); color: var(--color-primary);"></i>
                </div>
                <p style="font-size: var(--text-sm); color: var(--fg-muted); margin-bottom: var(--space-1);">Mensagens Disponíveis</p>
                <p style="font-size: var(--text-2xl); font-weight: var(--font-bold); color: var(--fg-primary);">
                    <?php echo number_format($user['plan_limit']); ?>
                </p>
                <div style="margin-top: var(--space-2); background: var(--bg-muted); border-radius: var(--radius-full); height: 6px; overflow: hidden;">
                    <?php 
                    $percentage = ($user['messages_sent'] / max($user['plan_limit'], 1)) * 100;
                    $percentage = min($percentage, 100);
                    ?>
                    <div style="background: var(--color-primary); height: 100%; width: <?php echo $percentage; ?>%; transition: width var(--transition-base);"></div>
                </div>
                <p style="font-size: var(--text-xs); color: var(--fg-muted); margin-top: var(--space-1);">
                    <?php echo number_format($user['messages_sent']); ?> enviadas (<?php echo round($percentage); ?>%)
                </p>
            </div>
            
            <div class="stat-card" style="border-left: 3px solid #f97316;">
                <div style="display: flex; align-items: center; justify-between; margin-bottom: var(--space-2);">
                    <i class="fas fa-calendar-alt" style="font-size: var(--text-2xl); color: #f97316;"></i>
                </div>
                <p style="font-size: var(--text-sm); color: var(--fg-muted); margin-bottom: var(--space-1);">Vencimento</p>
                <p style="font-size: var(--text-lg); font-weight: var(--font-bold); color: var(--fg-primary);">
                    <?php 
                    if ($user['plan_expires_at']) {
                        $end_date = new DateTime($user['plan_expires_at']);
                        echo $end_date->format('d/m/Y');
                        
                        $now = new DateTime();
                        $diff = $now->diff($end_date);
                        if ($end_date > $now) {
                            echo '<p style="font-size: var(--text-xs); color: var(--color-success); margin-top: var(--space-1);"><i class="fas fa-check-circle"></i> ' . $diff->days . ' dias restantes</p>';
                        } else {
                            echo '<p style="font-size: var(--text-xs); color: var(--color-error); margin-top: var(--space-1);"><i class="fas fa-exclamation-circle"></i> Vencido há ' . $diff->days . ' dias</p>';
                        }
                    } else {
                        echo 'Sem vencimento';
                        echo '<p style="font-size: var(--text-xs); color: var(--fg-muted); margin-top: var(--space-1);">Plano vitalício</p>';
                    }
                    ?>
                </p>
            </div>
            
            <div class="stat-card" style="border-left: 3px solid var(--color-info);">
                <div style="display: flex; align-items: center; justify-between; margin-bottom: var(--space-2);">
                    <i class="fas fa-shield-alt" style="font-size: var(--text-2xl); color: var(--color-info);"></i>
                </div>
                <p style="font-size: var(--text-sm); color: var(--fg-muted); margin-bottom: var(--space-1);">Segurança</p>
                <p style="font-size: var(--text-lg); font-weight: var(--font-bold);">
                    <?php if ($user['two_factor_enabled']): ?>
                        <span style="color: var(--color-success);"><i class="fas fa-check-circle"></i> 2FA Ativo</span>
                    <?php else: ?>
                        <span style="color: var(--color-warning);"><i class="fas fa-exclamation-triangle"></i> 2FA Inativo</span>
                    <?php endif; ?>
                </p>
                <p style="font-size: var(--text-xs); color: var(--fg-muted); margin-top: var(--space-1);">
                    <?php if ($user['is_active']): ?>
                        <span style="color: var(--color-success);">Conta Ativa</span>
                    <?php else: ?>
                        <span style="color: var(--color-error);">Conta Inativa</span>
                    <?php endif; ?>
                </p>
            </div>
        </div>
    </div>

    <!-- Estatísticas de Uso -->
    <div class="form-card" style="margin-bottom: var(--space-6);">
        <h2 style="font-size: var(--text-xl); font-weight: var(--font-semibold); color: var(--fg-primary); margin-bottom: var(--space-4); display: flex; align-items: center; gap: var(--space-2);">
            <i class="fas fa-chart-bar" style="color: var(--color-primary);"></i>
            Estatísticas de Uso
        </h2>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: var(--space-4);">
            <div style="text-align: center; padding: var(--space-6); background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%); border-radius: var(--radius-md); border: 1px solid var(--border);">
                <i class="fas fa-users" style="font-size: 40px; color: #1e40af !important; margin-bottom: var(--space-3);"></i>
                <p style="font-size: 36px; font-weight: var(--font-bold); color: #1e3a8a !important; margin-bottom: var(--space-1);">
                    <?php echo number_format($total_contacts); ?>
                </p>
                <p style="font-size: var(--text-sm); color: #1e40af !important; font-weight: var(--font-semibold);">Contatos</p>
            </div>
            
            <div style="text-align: center; padding: var(--space-6); background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%); border-radius: var(--radius-md); border: 1px solid var(--border);">
                <i class="fas fa-folder" style="font-size: 40px; color: #047857 !important; margin-bottom: var(--space-3);"></i>
                <p style="font-size: 36px; font-weight: var(--font-bold); color: #065f46 !important; margin-bottom: var(--space-1);">
                    <?php echo number_format($total_categories); ?>
                </p>
                <p style="font-size: var(--text-sm); color: #047857 !important; font-weight: var(--font-semibold);">Categorias</p>
            </div>
            
            <div style="text-align: center; padding: var(--space-6); background: linear-gradient(135deg, #f3e8ff 0%, #e9d5ff 100%); border-radius: var(--radius-md); border: 1px solid var(--border);">
                <i class="fas fa-paper-plane" style="font-size: 40px; color: #7c3aed !important; margin-bottom: var(--space-3);"></i>
                <p style="font-size: 36px; font-weight: var(--font-bold); color: #6b21a8 !important; margin-bottom: var(--space-1);">
                    <?php echo number_format($total_dispatches); ?>
                </p>
                <p style="font-size: var(--text-sm); color: #7c3aed !important; font-weight: var(--font-semibold);">Disparos</p>
            </div>
            
            <div style="text-align: center; padding: var(--space-6); background: linear-gradient(135deg, #fed7aa 0%, #fdba74 100%); border-radius: var(--radius-md); border: 1px solid var(--border);">
                <i class="fas fa-envelope" style="font-size: 40px; color: #c2410c !important; margin-bottom: var(--space-3);"></i>
                <p style="font-size: 36px; font-weight: var(--font-bold); color: #9a3412 !important; margin-bottom: var(--space-1);">
                    <?php echo number_format($total_messages); ?>
                </p>
                <p style="font-size: var(--text-sm); color: #c2410c !important; font-weight: var(--font-semibold);">Mensagens Enviadas</p>
            </div>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: var(--space-6);">
        <!-- Editar Perfil -->
        <div>
            <div class="form-card" style="margin-bottom: var(--space-6);">
                <h2 style="font-size: var(--text-xl); font-weight: var(--font-semibold); color: var(--fg-primary); margin-bottom: var(--space-4); display: flex; align-items: center; gap: var(--space-2);">
                    <i class="fas fa-user-edit" style="color: var(--color-primary);"></i>
                    Editar Perfil
                </h2>
                
                <form method="POST" action="">
                    <input type="hidden" name="action" value="update_profile">
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-4); margin-bottom: var(--space-4);">
                        <div>
                            <label class="form-label">
                                <i class="fas fa-user" style="margin-right: var(--space-1);"></i>Nome Completo *
                            </label>
                            <input type="text" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" required class="form-input">
                        </div>
                        
                        <div>
                            <label class="form-label">
                                <i class="fas fa-envelope" style="margin-right: var(--space-1);"></i>Email *
                            </label>
                            <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required class="form-input">
                        </div>
                        
                        <div>
                            <label class="form-label">
                                <i class="fas fa-phone" style="margin-right: var(--space-1);"></i>Telefone
                            </label>
                            <input type="tel" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" placeholder="(00) 00000-0000" class="form-input">
                        </div>
                        
                        <div>
                            <label class="form-label">
                                <i class="fas fa-briefcase" style="margin-right: var(--space-1);"></i>Cargo/Função
                            </label>
                            <input type="text" name="position" value="<?php echo htmlspecialchars($user['position'] ?? ''); ?>" placeholder="Ex: Gerente de Marketing" class="form-input">
                        </div>
                    </div>
                    
                    <button type="submit" class="btn-primary" style="width: 100%;">
                        <i class="fas fa-save" style="margin-right: var(--space-2);"></i>Salvar Alterações
                    </button>
                </form>
            </div>
            
            <!-- Alterar Senha -->
            <div class="form-card">
                <h2 style="font-size: var(--text-xl); font-weight: var(--font-semibold); color: var(--fg-primary); margin-bottom: var(--space-4); display: flex; align-items: center; gap: var(--space-2);">
                    <i class="fas fa-key" style="color: #f97316;"></i>
                    Alterar Senha
                </h2>
                
                <form method="POST" action="">
                    <input type="hidden" name="action" value="change_password">
                    
                    <div style="margin-bottom: var(--space-4);">
                        <label class="form-label">
                            <i class="fas fa-lock" style="margin-right: var(--space-1);"></i>Senha Atual *
                        </label>
                        <input type="password" name="current_password" required class="form-input">
                    </div>
                    
                    <div style="margin-bottom: var(--space-4);">
                        <label class="form-label">
                            <i class="fas fa-key" style="margin-right: var(--space-1);"></i>Nova Senha *
                        </label>
                        <input type="password" name="new_password" required minlength="6" class="form-input">
                        <p style="font-size: var(--text-xs); color: var(--fg-muted); margin-top: var(--space-1);">Mínimo de 6 caracteres</p>
                    </div>
                    
                    <div style="margin-bottom: var(--space-4);">
                        <label class="form-label">
                            <i class="fas fa-check-circle" style="margin-right: var(--space-1);"></i>Confirmar Nova Senha *
                        </label>
                        <input type="password" name="confirm_password" required minlength="6" class="form-input">
                    </div>
                    
                    <button type="submit" class="btn-primary" style="width: 100%; background: #f97316;">
                        <i class="fas fa-lock" style="margin-right: var(--space-2);"></i>Alterar Senha
                    </button>
                </form>
            </div>
        </div>

        <!-- Sidebar Direita -->
        <div>
            <!-- Atividades Recentes -->
            <div class="form-card" style="margin-bottom: var(--space-6);">
                <h2 style="font-size: var(--text-xl); font-weight: var(--font-semibold); color: var(--fg-primary); margin-bottom: var(--space-4); display: flex; align-items: center; gap: var(--space-2);">
                    <i class="fas fa-history" style="color: var(--color-info);"></i>
                    Atividades Recentes
                </h2>
                
                <?php if (!empty($recent_activities)): ?>
                    <div style="max-height: 400px; overflow-y: auto;">
                        <?php foreach ($recent_activities as $activity): ?>
                            <div style="padding: var(--space-3); border-left: 2px solid var(--color-primary); background: var(--bg-subtle); border-radius: 0 var(--radius-md) var(--radius-md) 0; margin-bottom: var(--space-3); transition: all var(--transition-fast);" onmouseover="this.style.background='var(--bg-muted)'; this.style.transform='translateX(4px)';" onmouseout="this.style.background='var(--bg-subtle)'; this.style.transform='translateX(0)';">
                                <div style="display: flex; align-items: start; gap: var(--space-3);">
                                    <i class="fas fa-circle" style="font-size: 8px; color: var(--color-primary); margin-top: 6px;"></i>
                                    <div style="flex: 1;">
                                        <p style="font-size: var(--text-sm); font-weight: var(--font-medium); color: var(--fg-primary);">
                                            <?php echo htmlspecialchars($activity['action']); ?>
                                        </p>
                                        <p style="font-size: var(--text-xs); color: var(--fg-muted);">
                                            <?php echo htmlspecialchars($activity['entity_type']); ?>
                                            <?php if ($activity['entity_id']): ?>
                                                #<?php echo $activity['entity_id']; ?>
                                            <?php endif; ?>
                                        </p>
                                        <p style="font-size: var(--text-xs); color: var(--fg-faint); margin-top: var(--space-1);">
                                            <i class="fas fa-clock" style="margin-right: var(--space-1);"></i>
                                            <?php echo date('d/m/Y H:i', strtotime($activity['created_at'])); ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div style="text-align: center; padding: var(--space-8);">
                        <i class="fas fa-inbox" style="font-size: 48px; color: var(--fg-faint); margin-bottom: var(--space-3);"></i>
                        <p style="color: var(--fg-muted);">Nenhuma atividade recente</p>
                        <p style="font-size: var(--text-xs); color: var(--fg-faint); margin-top: var(--space-1);">Suas ações aparecerão aqui</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Informações Adicionais -->
            <div class="form-card">
                <h2 style="font-size: var(--text-xl); font-weight: var(--font-semibold); color: var(--fg-primary); margin-bottom: var(--space-4); display: flex; align-items: center; gap: var(--space-2);">
                    <i class="fas fa-info-circle" style="color: #9333ea;"></i>
                    Informações
                </h2>
                
                <div style="display: flex; flex-direction: column; gap: var(--space-3);">
                    <div style="display: flex; align-items: center; justify-between; padding: var(--space-3); background: var(--bg-subtle); border-radius: var(--radius-md);">
                        <span style="font-size: var(--text-sm); color: var(--fg-secondary);">
                            <i class="fas fa-calendar-plus" style="margin-right: var(--space-2); color: var(--color-success);"></i>Conta criada
                        </span>
                        <span style="font-size: var(--text-sm); font-weight: var(--font-semibold); color: var(--fg-primary);">
                            <?php echo date('d/m/Y', strtotime($user['created_at'])); ?>
                        </span>
                    </div>
                    
                    <?php if ($user['last_login']): ?>
                    <div style="display: flex; align-items: center; justify-between; padding: var(--space-3); background: var(--bg-subtle); border-radius: var(--radius-md);">
                        <span style="font-size: var(--text-sm); color: var(--fg-secondary);">
                            <i class="fas fa-sign-in-alt" style="margin-right: var(--space-2); color: var(--color-info);"></i>Último acesso
                        </span>
                        <span style="font-size: var(--text-sm); font-weight: var(--font-semibold); color: var(--fg-primary);">
                            <?php echo date('d/m/Y H:i', strtotime($user['last_login'])); ?>
                        </span>
                    </div>
                    <?php endif; ?>
                    
                    <div style="display: flex; align-items: center; justify-between; padding: var(--space-3); background: var(--bg-subtle); border-radius: var(--radius-md);">
                        <span style="font-size: var(--text-sm); color: var(--fg-secondary);">
                            <i class="fas fa-id-badge" style="margin-right: var(--space-2); color: #9333ea;"></i>ID do Usuário
                        </span>
                        <span style="font-size: var(--text-sm); font-weight: var(--font-semibold); color: var(--fg-primary);">
                            #<?php echo $user['id']; ?>
                        </span>
                    </div>
                    
                    <?php if ($user['two_factor_enabled']): ?>
                    <div style="padding: var(--space-3); background: var(--color-success-bg); border: 1px solid var(--color-success-border); border-radius: var(--radius-md);">
                        <p style="font-size: var(--text-sm); color: var(--color-success) !important; font-weight: var(--font-medium);">
                            <i class="fas fa-shield-alt" style="margin-right: var(--space-2); color: var(--color-success) !important;"></i>2FA Ativado
                        </p>
                        <p style="font-size: var(--text-xs); color: var(--color-success) !important; margin-top: var(--space-1);">Sua conta está protegida</p>
                    </div>
                    <?php else: ?>
                    <div style="padding: var(--space-3); background: var(--color-warning-bg); border: 1px solid var(--color-warning-border); border-radius: var(--radius-md);">
                        <p style="font-size: var(--text-sm); color: var(--color-warning) !important; font-weight: var(--font-medium);">
                            <i class="fas fa-exclamation-triangle" style="margin-right: var(--space-2); color: var(--color-warning) !important;"></i>2FA Desativado
                        </p>
                        <p style="font-size: var(--text-xs); color: var(--color-warning) !important; margin-top: var(--space-1);">Recomendamos ativar para maior segurança</p>
                        <a href="/setup_2fa.php" style="font-size: var(--text-xs); color: var(--color-warning) !important; text-decoration: underline; margin-top: var(--space-2); display: inline-block;">
                            Ativar agora
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Correção de visibilidade para textos e números em cards */
/* IMPORTANTE: Usar !important para sobrescrever CSS do modo escuro */

/* PRIORIDADE MÁXIMA: Forçar cores inline nos cards de estatísticas */
.profile-container [style*="color: #1e3a8a"] {
    color: #1e3a8a !important;
}

.profile-container [style*="color: #1e40af"] {
    color: #1e40af !important;
}

.profile-container [style*="color: #065f46"] {
    color: #065f46 !important;
}

.profile-container [style*="color: #047857"] {
    color: #047857 !important;
}

.profile-container [style*="color: #6b21a8"] {
    color: #6b21a8 !important;
}

.profile-container [style*="color: #7c3aed"] {
    color: #7c3aed !important;
}

.profile-container [style*="color: #9a3412"] {
    color: #9a3412 !important;
}

.profile-container [style*="color: #c2410c"] {
    color: #c2410c !important;
}

/* Números grandes nos cards de estatísticas - FORÇAR VISIBILIDADE */
.profile-container [style*="font-size: 36px"] {
    text-shadow: 0 1px 2px rgba(0, 0, 0, 0.1) !important;
}

/* MODO ESCURO - Manter as cores inline mesmo no dark mode */
:root[data-theme="dark"] .profile-container [style*="color: #1e3a8a"],
:root[data-theme="dark"] .profile-container [style*="color: #1e40af"],
:root[data-theme="dark"] .profile-container [style*="color: #065f46"],
:root[data-theme="dark"] .profile-container [style*="color: #047857"],
:root[data-theme="dark"] .profile-container [style*="color: #6b21a8"],
:root[data-theme="dark"] .profile-container [style*="color: #7c3aed"],
:root[data-theme="dark"] .profile-container [style*="color: #9a3412"],
:root[data-theme="dark"] .profile-container [style*="color: #c2410c"] {
    color: inherit !important;
    filter: brightness(1.2) !important;
}

/* CORREÇÃO: Cards de status 2FA - Forçar cores de sucesso e aviso */
.profile-container [style*="background: var(--color-success-bg)"] p,
.profile-container [style*="background: var(--color-success-bg)"] i {
    color: #047857 !important;
}

.profile-container [style*="background: var(--color-warning-bg)"] p,
.profile-container [style*="background: var(--color-warning-bg)"] i,
.profile-container [style*="background: var(--color-warning-bg)"] a {
    color: #d97706 !important;
}

/* Modo escuro - ajustar cores dos cards de status */
:root[data-theme="dark"] .profile-container [style*="background: var(--color-success-bg)"] {
    background: rgba(16, 185, 129, 0.15) !important;
}

:root[data-theme="dark"] .profile-container [style*="background: var(--color-success-bg)"] p,
:root[data-theme="dark"] .profile-container [style*="background: var(--color-success-bg)"] i {
    color: #34d399 !important;
}

:root[data-theme="dark"] .profile-container [style*="background: var(--color-warning-bg)"] {
    background: rgba(245, 158, 11, 0.15) !important;
}

:root[data-theme="dark"] .profile-container [style*="background: var(--color-warning-bg)"] p,
:root[data-theme="dark"] .profile-container [style*="background: var(--color-warning-bg)"] i,
:root[data-theme="dark"] .profile-container [style*="background: var(--color-warning-bg)"] a {
    color: #fbbf24 !important;
}

/* CORREÇÃO: Badge do plano (amarelo) - Forçar cor escura */
.profile-container [style*="background: #fef3c7"] {
    color: #92400e !important;
}

.profile-container span[style*="background: #fef3c7"] {
    color: #92400e !important;
}

.profile-container .stat-card span[style*="background: #fef3c7"] {
    background: #fef3c7 !important;
    color: #92400e !important;
}

:root[data-theme="dark"] .profile-container [style*="background: #fef3c7"] {
    background: #fef3c7 !important;
    color: #92400e !important;
}

:root[data-theme="dark"] .profile-container span[style*="background: #fef3c7"] {
    background: #fef3c7 !important;
    color: #92400e !important;
}

:root[data-theme="dark"] .profile-container .stat-card span[style*="background: #fef3c7"] {
    background: #fef3c7 !important;
    color: #92400e !important;
}

/* CORREÇÃO: Texto "Membro desde" no header verde */
.profile-header p {
    color: white !important;
}

:root[data-theme="dark"] .profile-header p {
    color: white !important;
}

/* CORREÇÃO: Badges de Administrador/Supervisor no header */
.badge-admin,
.badge-supervisor,
.badge-user {
    color: inherit !important;
}

.badge-admin i,
.badge-supervisor i,
.badge-user i {
    color: inherit !important;
}

:root[data-theme="dark"] .badge-admin {
    background: #f3e8ff !important;
    color: #7c3aed !important;
}

:root[data-theme="dark"] .badge-admin i {
    color: #7c3aed !important;
}

:root[data-theme="dark"] .badge-supervisor {
    background: #dbeafe !important;
    color: #2563eb !important;
}

:root[data-theme="dark"] .badge-supervisor i {
    color: #2563eb !important;
}

:root[data-theme="dark"] .badge-user {
    background: var(--bg-muted) !important;
    color: var(--fg-secondary) !important;
}

:root[data-theme="dark"] .badge-user i {
    color: var(--fg-secondary) !important;
}

/* Garantir visibilidade dos botões do header */
.theme-toggle span,
.theme-toggle i {
    color: white !important;
}

a[href="/support.php"] span,
a[href="/support.php"] i {
    color: white !important;
}

/* Garantir que textos em botões azuis sejam sempre brancos */
.bg-blue-600 span,
.bg-blue-600 i,
.bg-blue-700 span,
.bg-blue-700 i {
    color: white !important;
}
</style>

<?php require_once 'includes/footer_spa.php'; ?>
