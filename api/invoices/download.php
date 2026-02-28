<?php
session_start();

require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/InvoiceService.php';

if (!isLoggedIn()) {
    http_response_code(401);
    exit('Não autorizado');
}

$invoiceId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($invoiceId <= 0) {
    http_response_code(400);
    exit('ID inválido');
}

$service = new InvoiceService($pdo);
$invoice = $service->getInvoice($invoiceId);

if (!$invoice) {
    http_response_code(404);
    exit('Fatura não encontrada');
}

if (!isAdmin() && $invoice['user_id'] != $_SESSION['user_id']) {
    http_response_code(403);
    exit('Acesso negado');
}

$pdfPath = $service->ensurePdfExists($invoice);
if (!file_exists($pdfPath)) {
    http_response_code(500);
    exit('PDF não encontrado');
}

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . basename($pdfPath) . '"');
header('Content-Length: ' . filesize($pdfPath));
readfile($pdfPath);
