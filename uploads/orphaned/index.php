<?php
// Impedir listagem de diretório
http_response_code(403);
echo 'Acesso negado';
