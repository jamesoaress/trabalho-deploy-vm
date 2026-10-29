<?php
declare(strict_types=1);

// Execute com a aplicação em outro terminal: php tests/smoke.php http://127.0.0.1:8080
// Cada execução usa sua própria sessão, sem modificar os dados do seu navegador.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
$base = rtrim($argv[1] ?? 'http://127.0.0.1:8080', '/');
$cookie = '';
$verificacoes = 0;

function verificar(bool $condicao, string $mensagem): void
{
    global $verificacoes;
    if (!$condicao) {
        throw new RuntimeException($mensagem);
    }
    $verificacoes++;
    echo "OK: $mensagem\n";
}

function requisitar(string $metodo, string $caminho = '/crud-filmes.php', array $dados = []): array
{
    global $base, $cookie;
    $cabecalhos = "Content-Type: application/x-www-form-urlencoded\r\n";
    if ($cookie !== '') {
        $cabecalhos .= "Cookie: $cookie\r\n";
    }
    $contexto = stream_context_create(['http' => [
        'method' => $metodo, 'header' => $cabecalhos,
        'content' => $metodo === 'POST' ? http_build_query($dados) : '',
        'ignore_errors' => true, 'follow_location' => 0, 'timeout' => 10,
    ]]);
    $corpo = @file_get_contents($base . $caminho, false, $contexto);
    if ($corpo === false) {
        throw new RuntimeException('Não foi possível acessar ' . $base . '. Inicie o servidor antes do teste.');
    }
    $cabecalhosRecebidos = $http_response_header ?? [];
    preg_match('/\s(\d{3})\s/', $cabecalhosRecebidos[0] ?? '', $status);
    foreach ($cabecalhosRecebidos as $cabecalho) {
        if (preg_match('/^Set-Cookie: (PHPSESSID=[^;]+)/i', $cabecalho, $sessao)) {
            $cookie = $sessao[1];
        }
    }
    if (preg_match('/(?:Warning|Fatal error|Notice|Deprecated):/', $corpo)) {
        throw new RuntimeException('O PHP exibiu um diagnóstico inesperado na resposta.');
    }
    return ['status' => (int) ($status[1] ?? 0), 'body' => $corpo, 'headers' => $cabecalhosRecebidos];
}

function idDoFilme(string $html, string $titulo): string
{
    $tituloHtml = htmlspecialchars($titulo, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    if (!preg_match('/<td>' . preg_quote($tituloHtml, '/') . '<\/td>.*?href="\?editar=([^"]+)"/s', $html, $id)) {
        throw new RuntimeException('Filme não encontrado na tabela: ' . $titulo);
    }
    return $id[1];
}

function quantidade(string $html): int
{
    return preg_match_all('/href="\?editar=/', $html);
}

try {
    $resposta = requisitar('GET', '/');
    verificar($resposta['status'] === 302, 'A raiz encaminha para o CRUD.');
    $resposta = requisitar('GET');
    verificar($resposta['status'] === 200 && quantidade($resposta['body']) === 3, 'A primeira visita mostra os três filmes padrão.');
    preg_match('/name="csrf" value="([^"]+)"/', $resposta['body'], $token);
    $csrf = $token[1] ?? '';
    verificar(strlen($csrf) === 64, 'Os formulários incluem token CSRF.');
    $idJames = idDoFilme($resposta['body'], 'James');
    $idInterestelar = idDoFilme($resposta['body'], 'Interestelar');

    verificar(requisitar('POST', '/crud-filmes.php', ['acao' => 'remover', 'id' => $idJames])['status'] === 403, 'A remoção sem CSRF é recusada.');
    verificar(requisitar('POST', '/crud-filmes.php', ['acao' => 'remover', 'id' => $idJames, 'csrf' => ['invalido']])['status'] === 403, 'Um CSRF do tipo array é recusado sem warnings.');
    $resposta = requisitar('GET', '/crud-filmes.php?remover=' . $idJames);
    verificar(quantidade($resposta['body']) === 3, 'Um GET não remove filmes.');

    $tituloTeste = 'Teste "<script>alert(1)</script>';
    $dados = ['acao' => 'adicionar', 'titulo' => $tituloTeste, 'diretor' => 'Diretor "teste"', 'ano' => '2026', 'csrf' => $csrf];
    verificar(requisitar('POST', '/crud-filmes.php', $dados)['status'] === 303, 'Adicionar usa Post/Redirect/Get.');
    $resposta = requisitar('GET');
    verificar(quantidade($resposta['body']) === 4 && !str_contains($resposta['body'], '<script>alert(1)</script>'), 'A criação aparece na tabela e escapa HTML.');
    $idTeste = idDoFilme($resposta['body'], $tituloTeste);
    $resposta = requisitar('GET', '/crud-filmes.php?editar=' . $idTeste);
    verificar(str_contains($resposta['body'], 'value="Teste &quot;&lt;script&gt;alert(1)&lt;/script&gt;"'), 'O formulário de edição escapa aspas e HTML em atributos.');

    $dados = ['acao' => 'atualizar', 'id' => $idInterestelar, 'titulo' => 'Interestelar atualizado', 'diretor' => 'Christopher Nolan', 'ano' => '2015', 'csrf' => $csrf];
    verificar(requisitar('POST', '/crud-filmes.php', $dados)['status'] === 303, 'Um filme padrão pode ser atualizado.');
    $resposta = requisitar('GET');
    verificar(quantidade($resposta['body']) === 4 && !str_contains($resposta['body'], '<td>Interestelar</td>'), 'Editar um filme padrão não recria sua versão original.');
    verificar(quantidade(requisitar('GET')['body']) === 4, 'Recarregar não duplica a base.');

    $invalidos = ['acao' => 'adicionar', 'titulo' => ['array'], 'diretor' => 'Diretor', 'ano' => ['array'], 'csrf' => $csrf];
    verificar(requisitar('POST', '/crud-filmes.php', $invalidos)['status'] === 303, 'Campos do tipo array são rejeitados com redirecionamento.');
    $resposta = requisitar('GET');
    verificar(quantidade($resposta['body']) === 4 && str_contains($resposta['body'], 'Informe um ano inteiro'), 'Dados inválidos mostram orientação e não criam registro.');
    $invalidos = ['acao' => 'adicionar', 'titulo' => 'Ano inválido', 'diretor' => 'Diretor', 'ano' => '1700', 'csrf' => $csrf];
    requisitar('POST', '/crud-filmes.php', $invalidos);
    verificar(quantidade(requisitar('GET')['body']) === 4, 'Anos fora do intervalo são rejeitados.');
    verificar(requisitar('POST', '/crud-filmes.php', ['acao' => ['array'], 'csrf' => $csrf])['status'] === 400, 'Uma ação inválida é recusada sem warnings.');
    verificar(requisitar('GET', '/crud-filmes.php?editar%5B%5D=x')['status'] === 200, 'Uma consulta de edição malformada não gera erro PHP.');
    verificar(requisitar('POST', '/crud-filmes.php', ['acao' => 'remover', 'id' => ['array'], 'csrf' => $csrf])['status'] === 303, 'Um identificador malformado é tratado sem erro PHP.');

    verificar(requisitar('POST', '/crud-filmes.php', ['acao' => 'remover', 'id' => $idJames, 'csrf' => $csrf])['status'] === 303, 'Um filme padrão pode ser removido por POST.');
    $resposta = requisitar('GET');
    verificar(quantidade($resposta['body']) === 3 && !str_contains($resposta['body'], '<td>James</td>'), 'O filme removido não reaparece na próxima visita.');
    verificar(quantidade(requisitar('GET')['body']) === 3, 'A remoção continua válida após recarregar novamente.');
    preg_match_all('/href="\?editar=([^"]+)"/', $resposta['body'], $ids);
    foreach ($ids[1] as $id) {
        requisitar('POST', '/crud-filmes.php', ['acao' => 'remover', 'id' => $id, 'csrf' => $csrf]);
    }
    verificar(quantidade(requisitar('GET')['body']) === 0 && quantidade(requisitar('GET')['body']) === 0, 'É possível esvaziar a lista sem recriar os filmes padrão.');
    $cookie = '';
    verificar(quantidade(requisitar('GET')['body']) === 3, 'Uma nova sessão tem sua própria lista inicial.');
    echo "\nSucesso: $verificacoes verificações HTTP passaram.\n";
} catch (Throwable $erro) {
    fwrite(STDERR, 'FALHA: ' . $erro->getMessage() . "\n");
    exit(1);
}
