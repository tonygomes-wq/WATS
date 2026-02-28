<?php
/**
 * EMAIL CHAT V2 - Interface Estilo Gmail
 * Layout moderno com 3 colunas
 */

session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$userId = $_SESSION['user_id'];
$pageTitle = 'Email - WATS';

require_once 'includes/header_spa.php';
?>

<link rel="stylesheet" href="assets/css/email-modern.css?v=<?php echo time(); ?>">

<div class="email-layout">
    <!-- Coluna 1: Sidebar (Pastas) -->
    <div class="email-sidebar">
        <button class="compose-btn" onclick="event.preventDefault(); composeEmail();">
            <i class="fas fa-plus"></i> Escrever
        </button>
        
        <nav class="email-folders">
            <a href="#" class="folder active" data-folder="inbox" onclick="event.preventDefault(); filterByFolder('inbox');">
                <i class="fas fa-inbox"></i>
                <span>Caixa de entrada</span>
                <span class="count" id="inbox-count">0</span>
            </a>
            <a href="#" class="folder" data-folder="starred" onclick="event.preventDefault(); filterByFolder('starred');">
                <i class="fas fa-star"></i>
                <span>Com estrela</span>
            </a>
            <a href="#" class="folder" data-folder="sent" onclick="event.preventDefault(); filterByFolder('sent');">
                <i class="fas fa-paper-plane"></i>
                <span>Enviados</span>
            </a>
            <a href="#" class="folder" data-folder="drafts" onclick="event.preventDefault(); filterByFolder('drafts');">
                <i class="fas fa-file"></i>
                <span>Rascunhos</span>
            </a>
            <a href="#" class="folder" data-folder="trash" onclick="event.preventDefault(); filterByFolder('trash');">
                <i class="fas fa-trash"></i>
                <span>Lixeira</span>
            </a>
        </nav>
    </div>
    
    <!-- Coluna 2: Lista de Emails -->
    <div class="email-list">
        <div class="email-list-header">
            <div class="email-actions">
                <input type="checkbox" id="select-all" onchange="toggleSelectAll();" title="Selecionar todos">
                <button onclick="refreshEmails();" title="Atualizar">
                    <i class="fas fa-sync"></i>
                </button>
                <button onclick="showBulkActions();" title="Mais ações">
                    <i class="fas fa-ellipsis-v"></i>
                </button>
            </div>
            <div class="email-search">
                <i class="fas fa-search"></i>
                <input type="text" placeholder="Pesquisar emails..." id="email-search-input" onkeyup="searchEmails()">
            </div>
            <div class="email-pagination">
                <span id="pagination-info">0-0 de 0</span>
                <button onclick="previousPage();" id="prev-btn" disabled title="Página anterior">
                    <i class="fas fa-chevron-left"></i>
                </button>
                <button onclick="nextPage();" id="next-btn" disabled title="Próxima página">
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>
        </div>
        
        <div class="email-items" id="email-items">
            <div class="email-loading">
                <i class="fas fa-spinner fa-spin"></i><br><br>
                Carregando emails...
            </div>
        </div>
    </div>
    
    <!-- Coluna 3: Visualização do Email -->
    <div class="email-viewer" id="email-viewer">
        <div class="email-empty-state">
            <i class="fas fa-envelope-open"></i>
            <p>Selecione um email para visualizar</p>
        </div>
    </div>
</div>

<!-- Modal Compose -->
<div id="compose-modal" class="compose-modal hidden">
    <div class="compose-container">
        <div class="compose-header">
            <h3>Nova mensagem</h3>
            <div class="compose-header-actions">
                <button onclick="minimizeCompose();" title="Minimizar"><i class="fas fa-minus"></i></button>
                <button onclick="maximizeCompose();" title="Tela cheia"><i class="fas fa-expand-alt"></i></button>
                <button onclick="closeCompose();" title="Fechar"><i class="fas fa-times"></i></button>
            </div>
        </div>
        <div class="compose-body">
            <div class="compose-field">
                <label>Para</label>
                <div class="compose-field-content">
                    <input type="email" id="compose-to" placeholder="">
                    <a href="#" class="compose-cc-link" onclick="event.preventDefault(); toggleCcBcc();">Cc Cco</a>
                </div>
            </div>
            <div class="compose-field compose-cc hidden" id="compose-cc-field">
                <label>Cc</label>
                <div class="compose-field-content">
                    <input type="email" id="compose-cc" placeholder="">
                </div>
            </div>
            <div class="compose-field compose-bcc hidden" id="compose-bcc-field">
                <label>Cco</label>
                <div class="compose-field-content">
                    <input type="email" id="compose-bcc" placeholder="">
                </div>
            </div>
            <div class="compose-field">
                <label>Assunto</label>
                <div class="compose-field-content">
                    <input type="text" id="compose-subject" placeholder="">
                </div>
            </div>
            <div class="compose-field">
                <textarea id="compose-message" placeholder=""></textarea>
            </div>
        </div>
        <div class="compose-footer">
            <div class="btn-send-group">
                <button class="btn-send" onclick="sendEmail();" title="Enviar email">
                    Enviar
                </button>
                <button class="btn-send-dropdown" onclick="showSendOptions();" title="Opções de envio">
                    <i class="fas fa-caret-down"></i>
                </button>
            </div>
            <div class="compose-toolbar">
                <button onclick="formatText();" title="Formatação de texto"><i class="fas fa-font"></i></button>
                <button onclick="attachFile();" title="Anexar arquivo"><i class="fas fa-paperclip"></i></button>
                <button onclick="insertLink();" title="Inserir link"><i class="fas fa-link"></i></button>
                <button onclick="insertEmoji();" title="Inserir emoji"><i class="far fa-smile"></i></button>
                <button onclick="insertImage();" title="Inserir imagem"><i class="far fa-image"></i></button>
                <button onclick="showMoreOptions();" title="Mais opções"><i class="fas fa-ellipsis-v"></i></button>
            </div>
            <button class="btn-delete" onclick="deleteCompose();" title="Descartar rascunho">
                <i class="far fa-trash-alt"></i>
            </button>
        </div>
    </div>
</div>

<script src="assets/js/email-modern.js?v=<?php echo time(); ?>"></script>

<?php require_once 'includes/footer_spa.php'; ?>
