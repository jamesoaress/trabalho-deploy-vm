<?php
declare(strict_types=1);

session_start(['cookie_httponly' => true, 'cookie_samesite' => 'Lax', 'use_strict_mode' => true]);

// A base é carregada só na primeira visita desta sessão. Edições e exclusões são preservadas.
$filmesBase = [
    ['titulo' => 'James', 'diretor' => 'Soares Silva', 'ano' => 2007],
    ['titulo' => 'Interestelar', 'diretor' => 'Christopher Nolan', 'ano' => 2014],
    ['titulo' => 'Batman Begins', 'diretor' => 'Christopher Nolan', 'ano' => 2005],
];
if (!isset($_SESSION['filmes'])) {
    $_SESSION['filmes'] = array_map(static function (array $filme): array {
        $filme['id'] = bin2hex(random_bytes(16));
        return $filme;
    }, $filmesBase);
}
if (!isset($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

function escapar(string|int $valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function texto(array $dados, string $campo): string
{
    return isset($dados[$campo]) && is_string($dados[$campo]) ? trim($dados[$campo]) : '';
}

function redirecionar(): never
{
    header('Location: crud-filmes.php', true, 303);
    exit;
}

$filmes = &$_SESSION['filmes'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = texto($_POST, 'csrf');
    if (!hash_equals($_SESSION['csrf'], $token)) {
        http_response_code(403);
        exit('Pedido recusado: token de segurança inválido. Recarregue a página.');
    }

    $acao = texto($_POST, 'acao');
    if (!in_array($acao, ['adicionar', 'atualizar', 'remover'], true)) {
        http_response_code(400);
        exit('Ação inválida.');
    }

    $id = texto($_POST, 'id');
    $indice = null;
    if ($acao !== 'adicionar') {
        foreach ($filmes as $chave => $filme) {
            if ($filme['id'] === $id) {
                $indice = $chave;
                break;
            }
        }
        if ($indice === null) {
            $_SESSION['aviso'] = 'Filme não encontrado. A lista pode ter sido alterada em outra aba.';
            redirecionar();
        }
    }

    if ($acao === 'remover') {
        unset($filmes[$indice]);
        $filmes = array_values($filmes);
        $_SESSION['aviso'] = 'Filme removido.';
        redirecionar();
    }

    $titulo = texto($_POST, 'titulo');
    $diretor = texto($_POST, 'diretor');
    $anoTexto = texto($_POST, 'ano');
    $ano = filter_var($anoTexto, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1888, 'max_range' => 2100]]);
    $erros = [];
    if (!preg_match('/^.{1,150}$/us', $titulo)) {
        $erros[] = 'Informe um título com até 150 caracteres.';
    }
    if (!preg_match('/^.{1,150}$/us', $diretor)) {
        $erros[] = 'Informe um diretor com até 150 caracteres.';
    }
    if ($ano === false) {
        $erros[] = 'Informe um ano inteiro entre 1888 e 2100.';
    }
    if ($erros) {
        $_SESSION['erros'] = $erros;
        $_SESSION['formulario'] = ['id' => $id, 'titulo' => $titulo, 'diretor' => $diretor, 'ano' => $anoTexto, 'acao' => $acao];
        redirecionar();
    }

    $filme = ['id' => $acao === 'adicionar' ? bin2hex(random_bytes(16)) : $id, 'titulo' => $titulo, 'diretor' => $diretor, 'ano' => $ano];
    if ($acao === 'adicionar') {
        $filmes[] = $filme;
        $_SESSION['aviso'] = 'Filme adicionado.';
    } else {
        $filmes[$indice] = $filme;
        $_SESSION['aviso'] = 'Filme atualizado.';
    }
    redirecionar();
}

$filmeParaEditar = null;
if (isset($_GET['editar'])) {
    $idEditar = texto($_GET, 'editar');
    foreach ($filmes as $filme) {
        if ($filme['id'] === $idEditar) {
            $filmeParaEditar = $filme;
            break;
        }
    }
}
$aviso = $_SESSION['aviso'] ?? '';
$erros = $_SESSION['erros'] ?? [];
$formulario = $_SESSION['formulario'] ?? $filmeParaEditar ?? ['titulo' => '', 'diretor' => '', 'ano' => ''];
$editando = isset($_SESSION['formulario']) ? $_SESSION['formulario']['acao'] === 'atualizar' : $filmeParaEditar !== null;
unset($_SESSION['aviso'], $_SESSION['erros'], $_SESSION['formulario']);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>CRUD de Filmes</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f0f2f5; padding: 24px; color: #17212c; }
        .container { max-width: 900px; margin: auto; background: white; padding: 24px; border-radius: 10px; }
        form.cadastro { display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 24px; align-items: end; }
        label { flex: 1; min-width: 140px; }
        input { display: block; box-sizing: border-box; width: 100%; padding: 9px; margin-top: 6px; }
        button { padding: 9px; cursor: pointer; }
        .tabela { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; border-bottom: 1px solid #ccc; text-align: left; overflow-wrap: anywhere; }
        th { background: #0865c8; color: white; }
        a { color: #064fab; margin-right: 10px; }
        .remover { display: inline; }
        .aviso { background: #edf6ff; padding: 12px; }
        .erros { background: #fff0ef; padding: 12px 12px 12px 30px; }
        small { display: block; margin-top: 24px; line-height: 1.5; }
    </style>
</head>
<body>
<main class="container">
    <h1>Lista de Filmes do James</h1>
    <!-- Altere esta frase e faça push para demonstrar a atualização automática. -->
    <p>Versão para demonstração: 1</p>
    <?php if ($aviso !== ''): ?><p class="aviso" role="status"><?= escapar($aviso) ?></p><?php endif; ?>
    <?php if ($erros): ?>
        <ul class="erros" role="alert"><?php foreach ($erros as $erro): ?><li><?= escapar($erro) ?></li><?php endforeach; ?></ul>
    <?php endif; ?>
    <h2><?= $editando ? 'Editar filme' : 'Adicionar filme' ?></h2>
    <form class="cadastro" method="post" action="crud-filmes.php">
        <input type="hidden" name="csrf" value="<?= escapar($_SESSION['csrf']) ?>">
        <input type="hidden" name="acao" value="<?= $editando ? 'atualizar' : 'adicionar' ?>">
        <?php if ($editando): ?><input type="hidden" name="id" value="<?= escapar($formulario['id']) ?>"><?php endif; ?>
        <label>Título<input type="text" name="titulo" maxlength="150" required value="<?= escapar($formulario['titulo']) ?>"></label>
        <label>Diretor<input type="text" name="diretor" maxlength="150" required value="<?= escapar($formulario['diretor']) ?>"></label>
        <label>Ano<input type="number" name="ano" min="1888" max="2100" required value="<?= escapar($formulario['ano']) ?>"></label>
        <button type="submit"><?= $editando ? 'Atualizar' : 'Adicionar' ?></button>
        <?php if ($editando): ?><a href="crud-filmes.php">Cancelar</a><?php endif; ?>
    </form>
    <div class="tabela">
        <table>
            <thead><tr><th>Título</th><th>Diretor</th><th>Ano</th><th>Ações</th></tr></thead>
            <tbody>
            <?php foreach ($filmes as $filme): ?>
                <tr>
                    <td><?= escapar($filme['titulo']) ?></td>
                    <td><?= escapar($filme['diretor']) ?></td>
                    <td><?= escapar($filme['ano']) ?></td>
                    <td>
                        <a href="?editar=<?= escapar($filme['id']) ?>">Editar</a>
                        <form class="remover" method="post" action="crud-filmes.php" onsubmit="return confirm('Excluir este filme?')">
                            <input type="hidden" name="csrf" value="<?= escapar($_SESSION['csrf']) ?>">
                            <input type="hidden" name="acao" value="remover">
                            <input type="hidden" name="id" value="<?= escapar($filme['id']) ?>">
                            <button type="submit">Remover</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$filmes): ?><tr><td colspan="4">Nenhum filme cadastrado.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <small>Os dados ficam na sessão PHP deste navegador. Outro navegador ou uma nova sessão tem sua própria lista.
        Os dados podem desaparecer quando a sessão expira. O projeto não utiliza banco de dados.</small>
</main>
</body>
</html>
