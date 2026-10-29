<?php
session_start();

// --- ARRAY BASE (edite aqui quando quiser adicionar novos padrões) ---
$filmesBase = [
    [
        'titulo' => 'James',
        'diretor' => 'Soares Silva',
        'ano' => 2007
    ],
    [
        'titulo' => 'Interestelar',
        'diretor' => 'Christopher Nolan',
        'ano' => 2014
    ],
    [
        'titulo' => 'Batman Begins',
        'diretor' => 'Christopher Nolan',
        'ano' => 2005
    ]
];

// --- GARANTE SESSÃO ---
if (!isset($_SESSION['filmes'])) {
    $_SESSION['filmes'] = [];
}

// --- SINCRONIZAÇÃO (array base -> sessão, sem duplicar) ---
foreach ($filmesBase as $base) {

    $existe = false;

    foreach ($_SESSION['filmes'] as $f) {
        if ($f['titulo'] === $base['titulo'] && $f['ano'] == $base['ano']) {
            $existe = true;
            break;
        }
    }

    if (!$existe) {
        $base['id'] = uniqid();
        $_SESSION['filmes'][] = $base;
    }
}

$filmes = &$_SESSION['filmes'];

// --- ADICIONAR ---
if (isset($_POST['adicionar'])) {

    $novoFilme = [
        'id' => uniqid(),
        'titulo' => $_POST['titulo'],
        'diretor' => $_POST['diretor'],
        'ano' => $_POST['ano']
    ];

    $filmes[] = $novoFilme;

    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// --- REMOVER ---
if (isset($_GET['remover'])) {
    foreach ($filmes as $index => $f) {
        if ($f['id'] === $_GET['remover']) {
            unset($filmes[$index]);
            break;
        }
    }

    $filmes = array_values($filmes);

    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// --- EDITAR ---
$filmeParaEditar = null;

if (isset($_GET['editar'])) {
    foreach ($filmes as $f) {
        if ($f['id'] === $_GET['editar']) {
            $filmeParaEditar = $f;
            break;
        }
    }
}

// --- ATUALIZAR ---
if (isset($_POST['atualizar'])) {
    foreach ($filmes as &$f) {
        if ($f['id'] === $_POST['id']) {
            $f['titulo'] = $_POST['titulo'];
            $f['diretor'] = $_POST['diretor'];
            $f['ano'] = $_POST['ano'];
            break;
        }
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>CRUD de Filmes (Array + Sessão)</title>
    <style>
        body {
            font-family: Arial;
            background: #f0f2f5;
            padding: 40px;
        }
        .container {
            max-width: 800px;
            margin: auto;
            background: white;
            padding: 20px;
            border-radius: 10px;
        }
        form {
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        input {
            padding: 8px;
            flex: 1;
        }
        button {
            padding: 8px;
            cursor: pointer;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 10px;
            border-bottom: 1px solid #ccc;
        }
        th {
            background: #007bff;
            color: white;
        }
        a {
            text-decoration: none;
            margin-right: 5px;
        }
    </style>
</head>
<body>

<div class="container">

    <h2><?= $filmeParaEditar ? "Editar Filme" : "Adicionar Filme" ?></h2>

    <form method="POST">
        <?php if ($filmeParaEditar): ?>
            <input type="hidden" name="id" value="<?= $filmeParaEditar['id'] ?>">
        <?php endif; ?>

        <input type="text" name="titulo" placeholder="Título" required
            value="<?= $filmeParaEditar['titulo'] ?? '' ?>">

        <input type="text" name="diretor" placeholder="Diretor"
            value="<?= $filmeParaEditar['diretor'] ?? '' ?>">

        <input type="number" name="ano" placeholder="Ano"
            value="<?= $filmeParaEditar['ano'] ?? '' ?>">

        <?php if ($filmeParaEditar): ?>
            <button type="submit" name="atualizar">Atualizar</button>
            <a href="<?= $_SERVER['PHP_SELF'] ?>">Cancelar</a>
        <?php else: ?>
            <button type="submit" name="adicionar">Adicionar</button>
        <?php endif; ?>
    </form>

    <h2>Lista de Filmes do James</h2>
    <p> teste de deploy </p>



    <table>
        <thead>
            <tr>
                <th>Título</th>
                <th>Diretor</th>
                <th>Ano</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($filmes as $f): ?>
                <tr>
                    <td><?= htmlspecialchars($f['titulo']) ?></td>
                    <td><?= htmlspecialchars($f['diretor']) ?></td>
                    <td><?= htmlspecialchars($f['ano']) ?></td>
                    <td>
                        <a href="?editar=<?= $f['id'] ?>">Editar</a>
                        <a href="?remover=<?= $f['id'] ?>"
                           onclick="return confirm('Excluir este filme?')">Remover</a>
                    </td>
                </tr>
            <?php endforeach; ?>

            <?php if (empty($filmes)): ?>
                <tr>
                    <td colspan="4">Nenhum filme cadastrado</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <br>
    <small>* Dados ficam na sessão (não duplicam com F5)</small>

</div>

</body>
</html>
