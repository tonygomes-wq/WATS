<?php

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}

require_once BASE_PATH . '/config/database.php';
require_once BASE_PATH . '/config/env.php';
require_once BASE_PATH . '/libs/fpdf/fpdf.php';
require_once BASE_PATH . '/includes/functions.php';

class InvoiceService
{
    private $pdo;
    private string $storagePath;

    public function __construct($pdo = null)
    {
        $this->pdo = $pdo ?? $GLOBALS['pdo'];
        $this->storagePath = BASE_PATH . '/storage/invoices';
        $this->ensureStoragePath();
    }

    private function ensureStoragePath(): void
    {
        if (!is_dir($this->storagePath)) {
            mkdir($this->storagePath, 0775, true);
        }
    }

    private function generateInvoiceNumber(): string
    {
        $prefix = 'INV-' . date('Ym') . '-';
        $stmt = $this->pdo->prepare("SELECT invoice_number FROM invoices WHERE invoice_number LIKE ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$prefix . '%']);
        $last = $stmt->fetchColumn();
        $sequence = 1;
        if ($last) {
            $parts = explode('-', $last);
            $sequence = intval(end($parts)) + 1;
        }
        return $prefix . str_pad((string)$sequence, 5, '0', STR_PAD_LEFT);
    }

    public function createInvoice(array $data): array
    {
        $items = $data['items'] ?? [];
        if (empty($items)) {
            throw new InvalidArgumentException('Informe ao menos um item para gerar a fatura.');
        }

        $amount = $data['amount'] ?? 0;
        if ($amount <= 0) {
            $amount = $this->calculateItemsAmount($items);
        }

        $tax = $data['tax_amount'] ?? 0;
        $discount = $data['discount_amount'] ?? 0;
        $total = max($amount + $tax - $discount, 0);

        $invoiceNumber = $this->generateInvoiceNumber();

        $stmt = $this->pdo->prepare("INSERT INTO invoices (
            invoice_number, user_id, subscription_id, payment_id, amount, tax_amount, discount_amount, total_amount,
            currency, status, due_date, items, notes, created_at
        ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())");

        $stmt->execute([
            $invoiceNumber,
            $data['user_id'],
            $data['subscription_id'] ?? null,
            $data['payment_id'] ?? null,
            $amount,
            $tax,
            $discount,
            $total,
            $data['currency'] ?? 'BRL',
            $data['status'] ?? 'sent',
            $data['due_date'] ?? date('Y-m-d'),
            json_encode($items, JSON_UNESCAPED_UNICODE),
            $data['notes'] ?? null
        ]);

        $invoiceId = (int)$this->pdo->lastInsertId();
        $invoice = $this->getInvoice($invoiceId);
        $pdfPath = $this->generatePdf($invoice);

        $this->pdo->prepare("UPDATE invoices SET pdf_path = ? WHERE id = ?")
            ->execute([$pdfPath, $invoiceId]);

        $invoice['pdf_path'] = $pdfPath;
        return $invoice;
    }

    public function getInvoice(int $invoiceId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT i.*, u.name AS user_name, u.email AS user_email
            FROM invoices i
            JOIN users u ON u.id = i.user_id
            WHERE i.id = ?");
        $stmt->execute([$invoiceId]);
        $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
        return $invoice ?: null;
    }

    public function ensurePdfExists(array $invoice): string
    {
        if (!empty($invoice['pdf_path']) && file_exists($invoice['pdf_path'])) {
            return $invoice['pdf_path'];
        }
        return $this->generatePdf($invoice);
    }

    private function calculateItemsAmount(array $items): float
    {
        $total = 0;
        foreach ($items as $item) {
            $qty = isset($item['quantity']) ? (float)$item['quantity'] : 1;
            $price = isset($item['unit_price']) ? (float)$item['unit_price'] : 0;
            $total += $qty * $price;
        }
        return round($total, 2);
    }

    private function generatePdf(array $invoice): string
    {
        $items = json_decode($invoice['items'] ?? '[]', true) ?: [];
        $company = $this->getCompanyInfo();

        $pdf = new FPDF();
        $pdf->AddPage();

        $pdf->SetFont('Arial', 'B', 16);
        $pdf->Cell(0, 10, $company['name'], 0, 1);
        $pdf->SetFont('Arial', '', 12);
        $pdf->Cell(0, 6, $company['document'], 0, 1);
        $pdf->Cell(0, 6, $company['address'], 0, 1);
        $pdf->Cell(0, 6, $company['contact'], 0, 1);
        $pdf->Ln(8);

        $pdf->SetFont('Arial', 'B', 14);
        $pdf->Cell(0, 10, 'FATURA #' . $invoice['invoice_number'], 0, 1);
        $pdf->SetFont('Arial', '', 12);
        $pdf->Cell(0, 6, 'Data de Emissão: ' . date('d/m/Y', strtotime($invoice['created_at'])), 0, 1);
        $pdf->Cell(0, 6, 'Vencimento: ' . date('d/m/Y', strtotime($invoice['due_date'])), 0, 1);
        $pdf->Ln(4);

        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(0, 8, 'Cliente', 0, 1);
        $pdf->SetFont('Arial', '', 12);
        $pdf->Cell(0, 6, $invoice['user_name'], 0, 1);
        $pdf->Cell(0, 6, $invoice['user_email'], 0, 1);
        $pdf->Ln(8);

        $pdf->SetFont('Arial', 'B', 11);
        $pdf->Cell(100, 8, 'Descrição', 1);
        $pdf->Cell(30, 8, 'Qtd', 1, 0, 'C');
        $pdf->Cell(30, 8, 'Valor', 1, 0, 'R');
        $pdf->Cell(30, 8, 'Total', 1, 1, 'R');

        $pdf->SetFont('Arial', '', 11);
        foreach ($items as $item) {
            $description = substr($item['description'] ?? 'Item', 0, 100);
            $qty = number_format($item['quantity'] ?? 1, 2, ',', '.');
            $unit = number_format($item['unit_price'] ?? 0, 2, ',', '.');
            $lineTotal = number_format(($item['quantity'] ?? 1) * ($item['unit_price'] ?? 0), 2, ',', '.');
            $pdf->Cell(100, 8, $description, 1);
            $pdf->Cell(30, 8, $qty, 1, 0, 'C');
            $pdf->Cell(30, 8, 'R$ ' . $unit, 1, 0, 'R');
            $pdf->Cell(30, 8, 'R$ ' . $lineTotal, 1, 1, 'R');
        }

        $pdf->Ln(4);
        $pdf->Cell(160, 8, 'Subtotal:', 0, 0, 'R');
        $pdf->Cell(30, 8, 'R$ ' . number_format($invoice['amount'], 2, ',', '.'), 0, 1, 'R');
        if ($invoice['tax_amount'] > 0) {
            $pdf->Cell(160, 8, 'Impostos:', 0, 0, 'R');
            $pdf->Cell(30, 8, 'R$ ' . number_format($invoice['tax_amount'], 2, ',', '.'), 0, 1, 'R');
        }
        if ($invoice['discount_amount'] > 0) {
            $pdf->Cell(160, 8, 'Descontos:', 0, 0, 'R');
            $pdf->Cell(30, 8, '- R$ ' . number_format($invoice['discount_amount'], 2, ',', '.'), 0, 1, 'R');
        }

        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(160, 10, 'Total:', 0, 0, 'R');
        $pdf->Cell(30, 10, 'R$ ' . number_format($invoice['total_amount'], 2, ',', '.'), 0, 1, 'R');

        if (!empty($invoice['notes'])) {
            $pdf->Ln(6);
            $pdf->SetFont('Arial', 'B', 11);
            $pdf->Cell(0, 8, 'Observações', 0, 1);
            $pdf->SetFont('Arial', '', 11);
            $pdf->MultiCell(0, 6, $invoice['notes']);
        }

        $filePath = sprintf('%s/%s.pdf', $this->storagePath, $invoice['invoice_number']);
        $pdf->Output('F', $filePath);

        return $filePath;
    }

    private function getCompanyInfo(): array
    {
        if (function_exists('env')) {
            return [
                'name' => env('COMPANY_NAME', 'MACIP Tecnologia LTDA'),
                'document' => env('COMPANY_DOCUMENT', 'CNPJ: 00.000.000/0000-00'),
                'address' => env('COMPANY_ADDRESS', 'Rua Exemplo, 123 - São Paulo/SP'),
                'contact' => env('COMPANY_CONTACT', 'suporte@macip.com.br | (11) 0000-0000'),
            ];
        }

        return [
            'name' => 'MACIP Tecnologia LTDA',
            'document' => 'CNPJ: 00.000.000/0000-00',
            'address' => 'Rua Exemplo, 123 - São Paulo/SP',
            'contact' => 'suporte@macip.com.br',
        ];
    }
}
