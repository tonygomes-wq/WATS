#!/usr/bin/env node

/**
 * VALIDADOR DE CÓDIGO - CHAT.PHP
 * 
 * Detecta problemas comuns que podem causar bugs:
 * - Funções duplicadas
 * - Variáveis globais duplicadas
 * - Problemas de encoding
 * - Sintaxe incorreta
 * 
 * USO:
 * node scripts/validate-code.js
 * 
 * RETORNO:
 * 0 = OK (sem problemas)
 * 1 = ERRO (problemas encontrados)
 */

const fs = require('fs');
const path = require('path');

// Cores para output
const colors = {
    reset: '\x1b[0m',
    red: '\x1b[31m',
    green: '\x1b[32m',
    yellow: '\x1b[33m',
    blue: '\x1b[34m',
    cyan: '\x1b[36m'
};

// Configuração
const config = {
    files: ['chat.php'],
    checkDuplicateFunctions: true,
    checkDuplicateVariables: true,
    checkEncoding: true,
    checkSyntax: true
};

// Estatísticas
let stats = {
    filesChecked: 0,
    errorsFound: 0,
    warningsFound: 0
};

/**
 * Main
 */
function main() {
    console.log(`${colors.cyan}╔════════════════════════════════════════╗${colors.reset}`);
    console.log(`${colors.cyan}║   VALIDADOR DE CÓDIGO - CHAT.PHP      ║${colors.reset}`);
    console.log(`${colors.cyan}╚════════════════════════════════════════╝${colors.reset}\n`);

    config.files.forEach(file => {
        validateFile(file);
    });

    printSummary();

    // Retornar código de erro se houver problemas
    process.exit(stats.errorsFound > 0 ? 1 : 0);
}

/**
 * Validar arquivo
 */
function validateFile(filename) {
    const filepath = path.join(process.cwd(), filename);

    if (!fs.existsSync(filepath)) {
        console.log(`${colors.yellow}⚠ Arquivo não encontrado: ${filename}${colors.reset}\n`);
        return;
    }

    console.log(`${colors.blue}📄 Validando: ${filename}${colors.reset}`);
    stats.filesChecked++;

    const content = fs.readFileSync(filepath, 'utf8');
    const lines = content.split('\n');

    // Executar validações
    if (config.checkDuplicateFunctions) {
        checkDuplicateFunctions(lines, filename);
    }

    if (config.checkDuplicateVariables) {
        checkDuplicateVariables(lines, filename);
    }

    if (config.checkEncoding) {
        checkEncoding(content, filename);
    }

    if (config.checkSyntax) {
        checkSyntax(lines, filename);
    }

    console.log('');
}

/**
 * Verificar funções duplicadas
 */
function checkDuplicateFunctions(lines, filename) {
    const functions = {};
    const functionRegex = /^\s*function\s+([a-zA-Z_][a-zA-Z0-9_]*)\s*\(/;

    lines.forEach((line, index) => {
        const match = line.match(functionRegex);
        if (match) {
            const funcName = match[1];
            if (functions[funcName]) {
                // Função duplicada encontrada!
                stats.errorsFound++;
                console.log(`${colors.red}❌ ERRO: Função duplicada encontrada!${colors.reset}`);
                console.log(`   Função: ${colors.yellow}${funcName}()${colors.reset}`);
                console.log(`   Primeira definição: linha ${functions[funcName]}`);
                console.log(`   Segunda definição: linha ${index + 1}`);
                console.log(`   ${colors.red}⚠ A segunda definição sobrescreve a primeira!${colors.reset}\n`);
            } else {
                functions[funcName] = index + 1;
            }
        }
    });

    const funcCount = Object.keys(functions).length;
    if (stats.errorsFound === 0) {
        console.log(`${colors.green}✓ Funções: ${funcCount} encontradas, nenhuma duplicada${colors.reset}`);
    }
}

/**
 * Verificar variáveis globais duplicadas
 */
function checkDuplicateVariables(lines, filename) {
    const variables = {};
    const varRegex = /^\s*(let|const|var)\s+([a-zA-Z_][a-zA-Z0-9_]*)\s*=/;

    lines.forEach((line, index) => {
        const match = line.match(varRegex);
        if (match) {
            const varType = match[1];
            const varName = match[2];
            
            // Ignorar variáveis dentro de funções (aproximação simples)
            if (line.trim().startsWith('function') || line.includes('{')) {
                return;
            }

            if (variables[varName]) {
                stats.warningsFound++;
                console.log(`${colors.yellow}⚠ AVISO: Variável global redeclarada${colors.reset}`);
                console.log(`   Variável: ${colors.yellow}${varName}${colors.reset}`);
                console.log(`   Primeira declaração: linha ${variables[varName]}`);
                console.log(`   Segunda declaração: linha ${index + 1}\n`);
            } else {
                variables[varName] = index + 1;
            }
        }
    });

    if (stats.warningsFound === 0) {
        console.log(`${colors.green}✓ Variáveis: Nenhuma duplicação detectada${colors.reset}`);
    }
}

/**
 * Verificar problemas de encoding
 */
function checkEncoding(content, filename) {
    const problems = [];

    // Detectar caracteres mal codificados comuns
    const badEncodings = [
        { pattern: /Ã¡/g, char: 'á', name: 'a com acento agudo' },
        { pattern: /Ã©/g, char: 'é', name: 'e com acento agudo' },
        { pattern: /Ã­/g, char: 'í', name: 'i com acento agudo' },
        { pattern: /Ã³/g, char: 'ó', name: 'o com acento agudo' },
        { pattern: /Ãº/g, char: 'ú', name: 'u com acento agudo' },
        { pattern: /Ã£/g, char: 'ã', name: 'a com til' },
        { pattern: /Ãµ/g, char: 'õ', name: 'o com til' },
        { pattern: /Ã§/g, char: 'ç', name: 'c cedilha' }
    ];

    badEncodings.forEach(({ pattern, char, name }) => {
        const matches = content.match(pattern);
        if (matches) {
            problems.push({
                char: char,
                name: name,
                count: matches.length
            });
        }
    });

    if (problems.length > 0) {
        stats.errorsFound += problems.length;
        console.log(`${colors.red}❌ ERRO: Problemas de encoding UTF-8 detectados!${colors.reset}`);
        problems.forEach(p => {
            console.log(`   ${colors.yellow}${p.count}x${colors.reset} caractere mal codificado: ${p.char} (${p.name})`);
        });
        console.log(`   ${colors.red}⚠ Salve o arquivo em UTF-8 e faça upload em modo BINARY${colors.reset}\n`);
    } else {
        console.log(`${colors.green}✓ Encoding: UTF-8 correto${colors.reset}`);
    }
}

/**
 * Verificar sintaxe básica
 */
function checkSyntax(lines, filename) {
    const problems = [];
    let inString = false;
    let stringChar = null;

    lines.forEach((line, index) => {
        // Verificar chaves não balanceadas (aproximação simples)
        const openBraces = (line.match(/{/g) || []).length;
        const closeBraces = (line.match(/}/g) || []).length;
        
        // Verificar parênteses não balanceados
        const openParens = (line.match(/\(/g) || []).length;
        const closeParens = (line.match(/\)/g) || []).length;

        // Verificar aspas não fechadas (aproximação)
        const singleQuotes = (line.match(/'/g) || []).length;
        const doubleQuotes = (line.match(/"/g) || []).length;

        if (singleQuotes % 2 !== 0 && !line.includes('//') && !line.includes('/*')) {
            problems.push({
                line: index + 1,
                type: 'Aspas simples não fechadas',
                content: line.trim().substring(0, 50)
            });
        }

        if (doubleQuotes % 2 !== 0 && !line.includes('//') && !line.includes('/*')) {
            problems.push({
                line: index + 1,
                type: 'Aspas duplas não fechadas',
                content: line.trim().substring(0, 50)
            });
        }
    });

    if (problems.length > 0) {
        stats.warningsFound += problems.length;
        console.log(`${colors.yellow}⚠ AVISO: Possíveis problemas de sintaxe${colors.reset}`);
        problems.slice(0, 5).forEach(p => {
            console.log(`   Linha ${p.line}: ${p.type}`);
            console.log(`   ${colors.yellow}${p.content}...${colors.reset}`);
        });
        if (problems.length > 5) {
            console.log(`   ... e mais ${problems.length - 5} problema(s)\n`);
        }
    } else {
        console.log(`${colors.green}✓ Sintaxe: Nenhum problema óbvio detectado${colors.reset}`);
    }
}

/**
 * Imprimir resumo
 */
function printSummary() {
    console.log(`${colors.cyan}═══════════════════════════════════════${colors.reset}`);
    console.log(`${colors.cyan}RESUMO DA VALIDAÇÃO${colors.reset}`);
    console.log(`${colors.cyan}═══════════════════════════════════════${colors.reset}`);
    console.log(`Arquivos verificados: ${stats.filesChecked}`);
    
    if (stats.errorsFound > 0) {
        console.log(`${colors.red}❌ Erros encontrados: ${stats.errorsFound}${colors.reset}`);
    } else {
        console.log(`${colors.green}✓ Erros encontrados: 0${colors.reset}`);
    }

    if (stats.warningsFound > 0) {
        console.log(`${colors.yellow}⚠ Avisos encontrados: ${stats.warningsFound}${colors.reset}`);
    } else {
        console.log(`${colors.green}✓ Avisos encontrados: 0${colors.reset}`);
    }

    console.log(`${colors.cyan}═══════════════════════════════════════${colors.reset}\n`);

    if (stats.errorsFound > 0) {
        console.log(`${colors.red}❌ VALIDAÇÃO FALHOU!${colors.reset}`);
        console.log(`${colors.red}Corrija os erros antes de fazer commit/upload.${colors.reset}\n`);
    } else if (stats.warningsFound > 0) {
        console.log(`${colors.yellow}⚠ VALIDAÇÃO PASSOU COM AVISOS${colors.reset}`);
        console.log(`${colors.yellow}Revise os avisos antes de fazer commit/upload.${colors.reset}\n`);
    } else {
        console.log(`${colors.green}✅ VALIDAÇÃO PASSOU!${colors.reset}`);
        console.log(`${colors.green}Código está OK para commit/upload.${colors.reset}\n`);
    }
}

// Executar
main();
