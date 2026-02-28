<?php
/**
 * PDF Generator para Resumos de Conversas
 * WATS - Sistema de Automação WhatsApp
 * 
 * Gera PDFs simples usando HTML + CSS sem dependências externas
 */

/**
 * Gera PDF de um resumo de conversa
 * @param array $summary Dados do resumo
 * @return string|false Caminho do arquivo PDF ou false em caso de erro
 */
function generateSummaryPDF(array $summary): string|false {
    try {
        // Criar diretório temporário se não existir
        $tempDir = __DIR__ . '/../storage/temp';
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }
        
        // Nome do arquivo
        $filename = 'resumo_' . $summary['id'] . '_' . date('YmdHis') . '.html';
        $filepath = $tempDir . '/' . $filename;
        
        // Gerar HTML
        $html = generateSummaryHTML($summary);
        
        // Salvar HTML (pode ser convertido para PDF pelo navegador)
        file_put_contents($filepath, $html);
        
        return $filepath;
        
    } catch (Exception $e) {
        error_log("[PDF_GENERATOR] Erro: " . $e->getMessage());
        return false;
    }
}

/**
 * Gera HTML formatado do resumo
 */
function generateSummaryHTML(array $summary): string {
    $contactName = htmlspecialchars($summary['contact_name'] ?? 'Não identificado');
    $phone = htmlspecialchars($summary['phone'] ?? '');
    $attendantName = htmlspecialchars($summary['attendant_name'] ?? 'Não atribuído');
    $generatedBy = htmlspecialchars($summary['generated_by_name'] ?? '');
    $generatedAt = date('d/m/Y H:i', strtotime($summary['generated_at']));
    
    // Duração formatada
    $duration = gmdate('H:i:s', $summary['duration_seconds'] ?? 0);
    $messageCount = $summary['message_count'] ?? 0;
    
    // Sentimento
    $sentimentLabels = [
        'positive' => ['label' => 'Positivo', 'color' => '#16a34a', 'icon' => '😊'],
        'neutral' => ['label' => 'Neutro', 'color' => '#6b7280', 'icon' => '😐'],
        'negative' => ['label' => 'Negativo', 'color' => '#dc2626', 'icon' => '😞'],
        'mixed' => ['label' => 'Misto', 'color' => '#f59e0b', 'icon' => '🤔']
    ];
    $sentiment = $sentimentLabels[$summary['sentiment'] ?? 'neutral'];
    
    // Dados do resumo JSON
    $summaryData = $summary['summary_json'] ?? [];
    $motivo = htmlspecialchars($summaryData['motivo'] ?? 'Não disponível');
    $resultado = htmlspecialchars($summaryData['resultado'] ?? 'Não disponível');
    $justificativaSentimento = htmlspecialchars($summaryData['justificativa_sentimento'] ?? '');
    
    // Ações
    $acoes = $summaryData['acoes'] ?? [];
    $acoesHtml = '';
    if (!empty($acoes)) {
        foreach ($acoes as $acao) {
            $acoesHtml .= '<li>' . htmlspecialchars($acao) . '</li>';
        }
    } else {
        $acoesHtml = '<li>Nenhuma ação registrada</li>';
    }
    
    // Pontos de atenção
    $pontosAtencao = $summaryData['pontos_atencao'] ?? [];
    $pontosHtml = '';
    if (!empty($pontosAtencao)) {
        foreach ($pontosAtencao as $ponto) {
            $pontosHtml .= '<li>' . htmlspecialchars($ponto) . '</li>';
        }
    } else {
        $pontosHtml = '<li>Nenhum ponto de atenção identificado</li>';
    }
    
    // Keywords
    $keywords = $summary['keywords'] ?? [];
    $keywordsHtml = '';
    if (!empty($keywords)) {
        foreach ($keywords as $keyword) {
            $keywordsHtml .= '<span class="keyword">' . htmlspecialchars($keyword) . '</span>';
        }
    } else {
        $keywordsHtml = '<span class="keyword">Nenhuma</span>';
    }
    
    return <<<HTML
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resumo de Conversa #{$summary['id']}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            font-size: 14px;
            line-height: 1.6;
            color: #1f2937;
            background: #f9fafb;
            padding: 20px;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #16a34a 0%, #15803d 100%);
            color: white;
            padding: 24px;
        }
        
        .header h1 {
            font-size: 24px;
            margin-bottom: 8px;
        }
        
        .header .subtitle {
            opacity: 0.9;
            font-size: 14px;
        }
        
        .content {
            padding: 24px;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }
        
        .info-card {
            background: #f9fafb;
            border-radius: 8px;
            padding: 16px;
        }
        
        .info-card label {
            display: block;
            font-size: 12px;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }
        
        .info-card .value {
            font-size: 16px;
            font-weight: 600;
            color: #1f2937;
        }
        
        .section {
            margin-bottom: 24px;
        }
        
        .section-title {
            font-size: 16px;
            font-weight: 600;
            color: #16a34a;
            margin-bottom: 12px;
            padding-bottom: 8px;
            border-bottom: 2px solid #dcfce7;
        }
        
        .section-content {
            color: #374151;
        }
        
        .sentiment-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
            background: {$sentiment['color']}20;
            color: {$sentiment['color']};
        }
        
        .sentiment-icon {
            font-size: 20px;
        }
        
        ul {
            list-style: none;
            padding: 0;
        }
        
        ul li {
            padding: 8px 0 8px 24px;
            position: relative;
            border-bottom: 1px solid #f3f4f6;
        }
        
        ul li:last-child {
            border-bottom: none;
        }
        
        ul li::before {
            content: "•";
            color: #16a34a;
            font-weight: bold;
            position: absolute;
            left: 8px;
        }
        
        .keywords {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        
        .keyword {
            background: #dcfce7;
            color: #166534;
            padding: 4px 12px;
            border-radius: 16px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .footer {
            background: #f9fafb;
            padding: 16px 24px;
            text-align: center;
            font-size: 12px;
            color: #6b7280;
            border-top: 1px solid #e5e7eb;
        }
        
        @media print {
            body {
                background: white;
                padding: 0;
            }
            
            .container {
                box-shadow: none;
                border-radius: 0;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📋 Resumo de Conversa</h1>
            <div class="subtitle">Gerado em {$generatedAt} por {$generatedBy}</div>
        </div>
        
        <div class="content">
            <div class="info-grid">
                <div class="info-card">
                    <label>Contato</label>
                    <div class="value">{$contactName}</div>
                    <div style="font-size: 12px; color: #6b7280;">{$phone}</div>
                </div>
                <div class="info-card">
                    <label>Atendente</label>
                    <div class="value">{$attendantName}</div>
                </div>
                <div class="info-card">
                    <label>Duração</label>
                    <div class="value">{$duration}</div>
                </div>
                <div class="info-card">
                    <label>Mensagens</label>
                    <div class="value">{$messageCount}</div>
                </div>
            </div>
            
            <div class="section">
                <div class="section-title">🎯 Motivo do Contato</div>
                <div class="section-content">{$motivo}</div>
            </div>
            
            <div class="section">
                <div class="section-title">✅ Ações Realizadas</div>
                <ul>{$acoesHtml}</ul>
            </div>
            
            <div class="section">
                <div class="section-title">📊 Resultado</div>
                <div class="section-content">{$resultado}</div>
            </div>
            
            <div class="section">
                <div class="section-title">😊 Sentimento do Cliente</div>
                <div class="sentiment-badge">
                    <span class="sentiment-icon">{$sentiment['icon']}</span>
                    <span>{$sentiment['label']}</span>
                </div>
                <p style="margin-top: 8px; color: #6b7280; font-size: 13px;">{$justificativaSentimento}</p>
            </div>
            
            <div class="section">
                <div class="section-title">⚠️ Pontos de Atenção</div>
                <ul>{$pontosHtml}</ul>
            </div>
            
            <div class="section">
                <div class="section-title">🏷️ Palavras-chave</div>
                <div class="keywords">{$keywordsHtml}</div>
            </div>
        </div>
        
        <div class="footer">
            WATS - Sistema de Automação WhatsApp | MAC-IP Tecnologia LTDA<br>
            Resumo ID: #{$summary['id']} | Conversa ID: #{$summary['conversation_id']}
        </div>
    </div>
    
    <script>
        // Auto-print quando abrir
        // window.onload = function() { window.print(); }
    </script>
</body>
</html>
HTML;
}

/**
 * Gera PDF usando biblioteca TCPDF (se disponível)
 * Fallback para HTML se TCPDF não estiver instalado
 */
function generateSummaryPDFAdvanced(array $summary): string|false {
    // Verificar se TCPDF está disponível
    $tcpdfPath = __DIR__ . '/../vendor/tecnickcom/tcpdf/tcpdf.php';
    
    if (file_exists($tcpdfPath)) {
        require_once $tcpdfPath;
        return generateWithTCPDF($summary);
    }
    
    // Fallback para HTML
    return generateSummaryPDF($summary);
}

/**
 * Gera PDF usando TCPDF
 */
function generateWithTCPDF(array $summary): string|false {
    try {
        $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        
        // Configurações
        $pdf->SetCreator('WATS');
        $pdf->SetAuthor('MAC-IP Tecnologia');
        $pdf->SetTitle('Resumo de Conversa #' . $summary['id']);
        $pdf->SetMargins(15, 15, 15);
        $pdf->SetAutoPageBreak(true, 15);
        $pdf->AddPage();
        
        // Conteúdo HTML
        $html = generateSummaryHTML($summary);
        $pdf->writeHTML($html, true, false, true, false, '');
        
        // Salvar
        $tempDir = __DIR__ . '/../storage/temp';
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }
        
        $filename = 'resumo_' . $summary['id'] . '_' . date('YmdHis') . '.pdf';
        $filepath = $tempDir . '/' . $filename;
        
        $pdf->Output($filepath, 'F');
        
        return $filepath;
        
    } catch (Exception $e) {
        error_log("[PDF_GENERATOR] Erro TCPDF: " . $e->getMessage());
        return false;
    }
}

/**
 * Limpa arquivos temporários antigos (mais de 1 hora)
 */
function cleanupTempPDFs(): void {
    $tempDir = __DIR__ . '/../storage/temp';
    
    if (!is_dir($tempDir)) {
        return;
    }
    
    $files = glob($tempDir . '/resumo_*');
    $now = time();
    
    foreach ($files as $file) {
        if (is_file($file) && ($now - filemtime($file)) > 3600) {
            unlink($file);
        }
    }
}
