<?php
if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}

require_once BASE_PATH . '/config/database.php';

class ScheduledDispatchService
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? $GLOBALS['pdo'];
    }

    public function schedule(array $data): array
    {
        $contacts = $data['contacts'] ?? [];
        if (empty($contacts)) {
            throw new InvalidArgumentException('Selecione ao menos um contato para o agendamento.');
        }

        $scheduledAt = $data['scheduled_for'] ?? null;
        if (!$scheduledAt) {
            throw new InvalidArgumentException('Informe a data/hora do agendamento.');
        }

        $total = count($contacts);
        $stmt = $this->pdo->prepare("INSERT INTO scheduled_dispatches (
            user_id, message, scheduled_for, status, contacts_json, total_contacts, created_at
        ) VALUES (?,?,?, 'pending', ?, ?, NOW())");

        $stmt->execute([
            $data['user_id'],
            $data['message'],
            $scheduledAt,
            json_encode($contacts, JSON_UNESCAPED_UNICODE),
            $total,
        ]);

        return $this->getDispatch((int)$this->pdo->lastInsertId());
    }

    public function getDispatch(int $dispatchId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM scheduled_dispatches WHERE id = ?");
        $stmt->execute([$dispatchId]);
        $dispatch = $stmt->fetch(PDO::FETCH_ASSOC);
        return $dispatch ?: null;
    }

    public function getDispatches(int $userId, array $filters = [], bool $isAdmin = false, int $page = 1, int $limit = 10): array
    {
        $where = ['1=1'];
        $params = [];

        if (!$isAdmin) {
            $where[] = 'user_id = ?';
            $params[] = $userId;
        } elseif (!empty($filters['user_id'])) {
            $where[] = 'user_id = ?';
            $params[] = (int)$filters['user_id'];
        }

        if (!empty($filters['status'])) {
            $where[] = 'status = ?';
            $params[] = $filters['status'];
        }

        if (!empty($filters['from'])) {
            $where[] = 'scheduled_for >= ?';
            $params[] = $filters['from'];
        }

        if (!empty($filters['to'])) {
            $where[] = 'scheduled_for <= ?';
            $params[] = $filters['to'];
        }

        $offset = max(0, ($page - 1) * $limit);

        $sql = sprintf(
            'SELECT * FROM scheduled_dispatches WHERE %s ORDER BY scheduled_for DESC LIMIT %d OFFSET %d',
            implode(' AND ', $where),
            $limit,
            $offset
        );

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countDispatches(int $userId, array $filters = [], bool $isAdmin = false): int
    {
        $where = ['1=1'];
        $params = [];

        if (!$isAdmin) {
            $where[] = 'user_id = ?';
            $params[] = $userId;
        } elseif (!empty($filters['user_id'])) {
            $where[] = 'user_id = ?';
            $params[] = (int)$filters['user_id'];
        }

        if (!empty($filters['status'])) {
            $where[] = 'status = ?';
            $params[] = $filters['status'];
        }

        if (!empty($filters['from'])) {
            $where[] = 'scheduled_for >= ?';
            $params[] = $filters['from'];
        }

        if (!empty($filters['to'])) {
            $where[] = 'scheduled_for <= ?';
            $params[] = $filters['to'];
        }

        $sql = sprintf('SELECT COUNT(*) FROM scheduled_dispatches WHERE %s', implode(' AND ', $where));
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    public function cancelDispatch(int $dispatchId, int $userId, bool $isAdmin = false): bool
    {
        $conditions = 'id = ? AND status IN (\'pending\', \'processing\')';
        $params = [$dispatchId];

        if (!$isAdmin) {
            $conditions .= ' AND user_id = ?';
            $params[] = $userId;
        }

        $stmt = $this->pdo->prepare("UPDATE scheduled_dispatches SET status = 'cancelled', completed_at = NOW() WHERE {$conditions}");
        $stmt->execute($params);
        return $stmt->rowCount() > 0;
    }

    public function fetchDueDispatches(int $limit = 10): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM scheduled_dispatches WHERE status = 'pending' AND scheduled_for <= NOW() ORDER BY scheduled_for ASC LIMIT ?");
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function markProcessing(int $dispatchId): bool
    {
        $stmt = $this->pdo->prepare("UPDATE scheduled_dispatches SET status = 'processing', started_at = NOW() WHERE id = ? AND status = 'pending'");
        $stmt->execute([$dispatchId]);
        return $stmt->rowCount() > 0;
    }

    public function markCompleted(int $dispatchId, int $sent, int $failed, ?string $lastError = null): void
    {
        $stmt = $this->pdo->prepare("UPDATE scheduled_dispatches SET status = 'completed', sent_count = ?, failed_count = ?, completed_at = NOW(), last_error = ? WHERE id = ?");
        $stmt->execute([$sent, $failed, $lastError, $dispatchId]);
    }

    public function markFailed(int $dispatchId, string $error, int $sent = 0, int $failed = 0): void
    {
        $stmt = $this->pdo->prepare("UPDATE scheduled_dispatches SET status = 'failed', sent_count = ?, failed_count = ?, completed_at = NOW(), last_error = ? WHERE id = ?");
        $stmt->execute([$sent, $failed, $error, $dispatchId]);
    }
}
