<?php
/**
 * AuthService centraliza regras de autenticação (login, 2FA, sessões)
 */

class AuthService
{
    private $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Autentica email/senha para usuários e atendentes
     */
    public function authenticate(string $email, string $password): array
    {
        if (empty($email) || empty($password)) {
            return [
                'success' => false,
                'message' => 'Por favor, preencha todos os campos.'
            ];
        }

        $isAttendant = false;
        $supervisorId = null;
        $user = null;

        // 1) Procurar atendente primeiro
        $stmt = $this->pdo->prepare("SELECT * FROM supervisor_users WHERE email = ? AND status = 'active'");
        $stmt->execute([$email]);
        $attendantUser = $stmt->fetch();

        if ($attendantUser) {
            $isAttendant = true;
            $user = $attendantUser;
            $supervisorId = $attendantUser['supervisor_id'] ?? null;
        } else {
            // 2) Buscar usuário comum
            $stmt = $this->pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
        }

        if (!$user || !verifyPassword($password, $user['password'])) {
            return [
                'success' => false,
                'message' => 'Email ou senha incorretos.'
            ];
        }

        if ($isAttendant) {
            return $this->handleAttendantLogin($user, $supervisorId);
        }

        return $this->handleUserLogin($user);
    }

    /**
     * Verifica 2FA com TOTP ou código backup
     */
    public function verifyTwoFactor(?string $code, ?string $backupCode): array
    {
        if (empty($_SESSION['temp_user_data'])) {
            return [
                'success' => false,
                'message' => 'Sessão expirada. Faça login novamente.'
            ];
        }

        $tempUser = $_SESSION['temp_user_data'];
        $userType = $tempUser['user_type'] ?? 'user';

        // Buscar secrets
        if ($userType === 'attendant') {
            $stmt = $this->pdo->prepare("SELECT two_factor_secret, COALESCE(backup_codes, '[]') AS backup_codes FROM supervisor_users WHERE id = ?");
        } else {
            $stmt = $this->pdo->prepare("SELECT two_factor_secret, COALESCE(backup_codes, '[]') AS backup_codes FROM users WHERE id = ?");
        }

        $stmt->execute([$tempUser['id']]);
        $userData = $stmt->fetch();

        if (!$userData) {
            return [
                'success' => false,
                'message' => 'Usuário não encontrado para 2FA.'
            ];
        }

        $valid = false;

        if (!empty($code)) {
            $valid = TOTP::verifyCode($userData['two_factor_secret'], $code);
        } elseif (!empty($backupCode)) {
            $backupResult = TOTP::verifyBackupCode($userData['backup_codes'], $backupCode);
            if ($backupResult['valid']) {
                $valid = true;

                if ($userType === 'attendant') {
                    $stmt = $this->pdo->prepare("UPDATE supervisor_users SET backup_codes = ? WHERE id = ?");
                } else {
                    $stmt = $this->pdo->prepare("UPDATE users SET backup_codes = ? WHERE id = ?");
                }
                $stmt->execute([json_encode($backupResult['remaining_codes']), $tempUser['id']]);
            }
        }

        if (!$valid) {
            return [
                'success' => false,
                'message' => 'Código inválido. Verifique se o horário do seu dispositivo está correto.'
            ];
        }

        // Converter temp em sessão definitiva
        $_SESSION['user_id'] = $tempUser['id'];
        $_SESSION['user_name'] = $tempUser['name'];
        $_SESSION['user_email'] = $tempUser['email'];
        $_SESSION['is_admin'] = $tempUser['is_admin'];
        $_SESSION['is_supervisor'] = $tempUser['is_supervisor'] ?? 0;
        $_SESSION['user_type'] = $tempUser['user_type'] ?? 'user';
        $_SESSION['supervisor_id'] = $tempUser['supervisor_id'] ?? null;
        $_SESSION['attendant_id'] = $tempUser['attendant_id'] ?? null;

        unset($_SESSION['temp_user_data']);

        return $this->finalizeLogin($_SESSION['user_type']);
    }

    /**
     * Login de atendente (sem 2FA ou após verificação)
     */
    private function handleAttendantLogin(array $user, ?int $supervisorId): array
    {
        if ($user['status'] !== 'active') {
            return [
                'success' => false,
                'message' => 'Sua conta está bloqueada ou inativa. Entre em contato com seu supervisor.'
            ];
        }

        if (!empty($user['two_factor_enabled']) && !empty($user['two_factor_secret'])) {
            $_SESSION['temp_user_data'] = [
                'id' => $user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'is_admin' => 0,
                'is_supervisor' => 0,
                'user_type' => 'attendant',
                'supervisor_id' => $supervisorId,
                'attendant_id' => $user['id']
            ];

            return [
                'success' => true,
                'require_2fa' => true,
                'message' => 'Digite o código de verificação',
                'user_type' => 'attendant'
            ];
        }

        // Regenerar session ID (prevenir session fixation)
        session_regenerate_id(true);

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['is_admin'] = 0;
        $_SESSION['is_supervisor'] = 0;
        $_SESSION['user_type'] = 'attendant';
        $_SESSION['supervisor_id'] = $supervisorId;
        $_SESSION['attendant_id'] = $user['id'];

        $stmt = $this->pdo->prepare("UPDATE supervisor_users SET last_login = NOW() WHERE id = ?");
        $stmt->execute([$user['id']]);

        $redirect = '/chat.php';
        if (!empty($user['must_change_password'])) {
            $redirect = '/change_password_required.php';
        }

        return [
            'success' => true,
            'require_2fa' => false,
            'redirect' => $redirect,
            'message' => 'Login realizado com sucesso!'
        ];
    }

    /**
     * Login de usuário comum (sem 2FA ou após verificação)
     */
    private function handleUserLogin(array $user): array
    {
        if (isset($user['is_active']) && $user['is_active'] == 0) {
            return [
                'success' => false,
                'message' => 'Sua conta está desativada. Entre em contato com o suporte.'
            ];
        }

        if (!empty($user['two_factor_enabled']) && !empty($user['two_factor_secret'])) {
            $_SESSION['temp_user_data'] = [
                'id' => $user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'is_admin' => $user['is_admin'],
                'is_supervisor' => $user['is_supervisor'] ?? 0,
                'user_type' => 'user'
            ];

            return [
                'success' => true,
                'require_2fa' => true,
                'message' => 'Digite o código de verificação'
            ];
        }

        // Regenerar session ID (prevenir session fixation)
        session_regenerate_id(true);

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['is_admin'] = $user['is_admin'];
        $_SESSION['is_supervisor'] = $user['is_supervisor'] ?? 0;
        $_SESSION['user_type'] = 'user';

        $stmt = $this->pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
        $stmt->execute([$user['id']]);

        $redirect = '/dashboard.php';
        if (!empty($user['must_change_password']) || !empty($user['first_login'])) {
            $redirect = '/change_password_required.php';
        }

        return [
            'success' => true,
            'require_2fa' => false,
            'redirect' => $redirect,
            'message' => 'Login realizado com sucesso!'
        ];
    }

    /**
     * Pós 2FA: atualiza dados e decide redirect
     */
    private function finalizeLogin(string $userType): array
    {
        // Regenerar session ID (prevenir session fixation)
        session_regenerate_id(true);
        
        $userId = $_SESSION['user_id'];

        if ($userType === 'attendant') {
            $stmt = $this->pdo->prepare("UPDATE supervisor_users SET last_login = NOW() WHERE id = ?");
            $stmt->execute([$userId]);

            $stmt = $this->pdo->prepare("SELECT must_change_password FROM supervisor_users WHERE id = ?");
            $stmt->execute([$userId]);
            $userCheck = $stmt->fetch();

            $redirect = '/chat.php';
            if ($userCheck && !empty($userCheck['must_change_password'])) {
                $redirect = '/change_password_required.php';
            }
        } else {
            $stmt = $this->pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            $stmt->execute([$userId]);

            $stmt = $this->pdo->prepare("SELECT must_change_password, first_login FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $userCheck = $stmt->fetch();

            $redirect = '/dashboard.php';
            if ($userCheck && (!empty($userCheck['must_change_password']) || !empty($userCheck['first_login']))) {
                $redirect = '/change_password_required.php';
            }
        }

        return [
            'success' => true,
            'redirect' => $redirect,
            'message' => 'Autenticação concluída com sucesso!'
        ];
    }
}
