<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_login();

$query = trim($_GET['q'] ?? '');
$resultados = [];

if ($query !== '') {
    $sql = 'SELECT p.nome_completo, p.idade, p.documento, e.data_escala, e.turno, e.local_consultorio, m.nome AS medico_nome, a.observacoes
            FROM pacientes p
            INNER JOIN agendamentos a ON a.paciente_id = p.id
            INNER JOIN escalas e ON e.id = a.escala_id
            INNER JOIN medicos m ON m.id = e.medico_id
            WHERE p.nome_completo LIKE :q OR p.documento LIKE :q
            ORDER BY p.nome_completo, e.data_escala DESC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['q' => '%' . $query . '%']);
    $resultados = $stmt->fetchAll();
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Busca de Pacientes - Agenda Médica</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">Busca Rápida de Pacientes</h1>
        <a class="btn btn-outline-secondary" href="index.php">Voltar</a>
    </div>

    <form class="card card-body mb-3" method="get">
        <div class="row g-2">
            <div class="col-md-10"><input class="form-control" name="q" placeholder="Nome, CPF ou Cartão SUS" value="<?= htmlspecialchars($query) ?>"></div>
            <div class="col-md-2"><button class="btn btn-primary w-100" type="submit">Buscar</button></div>
        </div>
    </form>

    <?php if ($query !== ''): ?>
    <div class="table-responsive">
        <table class="table table-striped table-bordered bg-white">
            <thead><tr><th>Paciente</th><th>Documento</th><th>Idade</th><th>Médico</th><th>Data/Turno</th><th>Local</th><th>Observações</th></tr></thead>
            <tbody>
            <?php foreach ($resultados as $item): ?>
                <tr>
                    <td><?= htmlspecialchars($item['nome_completo']) ?></td>
                    <td><?= htmlspecialchars($item['documento']) ?></td>
                    <td><?= (int) $item['idade'] ?></td>
                    <td><?= htmlspecialchars($item['medico_nome']) ?></td>
                    <td><?= htmlspecialchars($item['data_escala']) ?> - <?= htmlspecialchars($item['turno']) ?></td>
                    <td><?= htmlspecialchars($item['local_consultorio']) ?></td>
                    <td><?= htmlspecialchars($item['observacoes'] ?? '') ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$resultados): ?>
                <tr><td colspan="7" class="text-center">Nenhum resultado encontrado.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
</body>
</html>
