<?php
/**
 * CRON: Processar disparos agendados
 * Executar via CLI ou HTTP com token secreto
 * Ex.: */5 * * * * php /caminho/wats/cron/process_scheduled_dispatches.php
 */

define('CRON_SECRET_TOKEN', 'troque_este_token_agora_123');
define('LOG_FILE', __DIR__ . '/logs/scheduled_dispatches.log');
define('CRON_ALERT_EMAIL', 'suporte@macip.com.br');
define('CRON_ALERT_THRESHOLD', 3); // número mínimo de falhas para notificar

$isCLI = (php_sapi_name() === 'cli');
$hasValidToken = isset($_GET['token']) && hash_equals(CRON_SECRET_TOKEN, $_GET['token']);

if (!$isCLI && !$hasValidToken) {
    http_response_code(403);
    exit('Acesso negado.');
}

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/ScheduledDispatchService.php';
require_once dirname(__DIR__) . '/includes/DispatchSender.php';

function logMessage($message)
{
    $timestamp = date('Y-m-d H:i:s');
    $entry = "[$timestamp] $message" . PHP_EOL;
    $logDir = dirname(LOG_FILE);
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    file_put_contents(LOG_FILE, $entry, FILE_APPEND);
    if (php_sapi_name() === 'cli') {
        echo $entry;
    }
}

$service = new ScheduledDispatchService($pdo);
$sender = new DispatchSender($pdo);

try {
    logMessage('=== Iniciando processamento de agendamentos ===');
    $dueDispatches = $service->fetchDueDispatches(10);

    if (empty($dueDispatches)) {
        logMessage('Nenhum agendamento pendente.');
        if (!$isCLI) {
            echo json_encode(['success' => true, 'processed' => 0]);
        }
        exit;
    }

    $totalFailedContacts = 0;
    $failedDispatches = [];

    foreach ($dueDispatches as $dispatch) {
        $dispatchId = (int) $dispatch['id'];
        $contacts = json_decode($dispatch['contacts_json'] ?? '[]', true) ?: [];

        if (!$service->markProcessing($dispatchId)) {
            logMessage("Agendamento #$dispatchId já foi processado por outro worker.");
            continue;
        }

        logMessage("Processando agendamento #$dispatchId para usuário {$dispatch['user_id']} ({count($contacts)} contatos)");
        $sent = 0;
        $failed = 0;
        $lastError = null;

        foreach ($contacts as $contact) {
            $contactData = [
                'user_id' => (int) $dispatch['user_id'],
                'contact_id' => $contact['contact_id'] ?? null,
                'phone' => $contact['phone'] ?? '',
                'message' => processMacros($dispatch['message'], [
                    'name' => $contact['name'] ?? 'Cliente',
                    'phone' => $contact['phone'] ?? ''
                ])
            ];

            $result = $sender->send($contactData);
            if ($result['success']) {
                $sent++;
            } else {
                $failed++;
                $lastError = $result['error'] ?? 'Falha desconhecida';
                logMessage(" - Contato {$contact['phone']} falhou: {$lastError}");
            }
            usleep(250000); // 0.25s para evitar rajadas
        }

        if ($failed === 0) {
            $service->markCompleted($dispatchId, $sent, $failed, null);
            logMessage("Agendamento #$dispatchId concluído com sucesso.");
        } elseif ($sent === 0) {
            $service->markFailed($dispatchId, $lastError ?? 'Falha geral', $sent, $failed);
            logMessage("Agendamento #$dispatchId falhou completamente.");
            $failedDispatches[] = $dispatch;
            $totalFailedContacts += $failed;
        } else {
            $service->markCompleted($dispatchId, $sent, $failed, $lastError);
            logMessage("Agendamento #$dispatchId concluído com erros (sent: $sent, failed: $failed).");
            if ($failed > 0) {
                $failedDispatches[] = $dispatch;
                $totalFailedContacts += $failed;
            }
        }
    }

    if ($totalFailedContacts >= CRON_ALERT_THRESHOLD && !empty(CRON_ALERT_EMAIL)) {
        $subject = SITE_NAME . ' - Falhas em disparos agendados';
        $bodyLines = [
            'Foram detectadas falhas em agendamentos automáticos:',
            'Total de contatos com falha: ' . $totalFailedContacts,
            ''
        ];
        foreach ($failedDispatches as $failedDispatch) {
            $bodyLines[] = sprintf('- ID #%d | Usuário #%d | Horário: %s | Status atual: %s',
                $failedDispatch['id'],
                $failedDispatch['user_id'],
                $failedDispatch['scheduled_for'],
                $failedDispatch['status']
            );
        }
        $body = implode(PHP_EOL, $bodyLines);
        if (!notifyUser(CRON_ALERT_EMAIL, $subject, $body)) {
            logMessage('Falha ao enviar alerta por e-mail.');
        } else {
            logMessage('Alerta de falhas enviado para ' . CRON_ALERT_EMAIL);
        }
    }

    logMessage('=== Processamento finalizado ===');

    if (!$isCLI) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'processed' => count($dueDispatches)]);
    }
} catch (Throwable $e) {
    logMessage('ERRO: ' . $e->getMessage());
    if (!$isCLI) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit(1);
}
