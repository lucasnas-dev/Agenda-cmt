<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_login();

$tipo = $_GET['tipo'] ?? 'diaria';
$dataRef = $_GET['data'] ?? date('Y-m-d');

if ($tipo === 'semanal') {
    $inicio = date('Y-m-d', strtotime('monday this week', strtotime($dataRef)));
    $fim = date('Y-m-d', strtotime($inicio . ' +6 days'));
    $stmt = $pdo->prepare('SELECT e.data_escala, e.turno, e.local_consultorio, m.nome AS medico_nome, p.nome_completo, p.documento, a.observacoes
                           FROM escalas e
                           INNER JOIN medicos m ON m.id = e.medico_id
                           LEFT JOIN agendamentos a ON a.escala_id = e.id
                           LEFT JOIN pacientes p ON p.id = a.paciente_id
                           WHERE e.data_escala BETWEEN ? AND ?
                           ORDER BY e.data_escala, m.nome, p.nome_completo');
    $stmt->execute([$inicio, $fim]);
    $titulo = "Escala semanal ({$inicio} a {$fim})";
} else {
    $stmt = $pdo->prepare('SELECT e.data_escala, e.turno, e.local_consultorio, m.nome AS medico_nome, p.nome_completo, p.documento, a.observacoes
                           FROM escalas e
                           INNER JOIN medicos m ON m.id = e.medico_id
                           LEFT JOIN agendamentos a ON a.escala_id = e.id
                           LEFT JOIN pacientes p ON p.id = a.paciente_id
                           WHERE e.data_escala = ?
                           ORDER BY m.nome, p.nome_completo');
    $stmt->execute([$dataRef]);
    $titulo = "Escala diária ({$dataRef})";
}

$linhas = $stmt->fetchAll();
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Impressão de Escala</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: #fff !important; color: #000; font-size: 12px; }
            table { border: 1px solid #000; }
            th, td { border: 1px solid #000 !important; padding: 6px !important; }
            .obs-linha { height: 30px; border-bottom: 1px dashed #000; }
        }
    </style>
</head>
<body class="bg-light">
<div class="container py-4">
    <div class="no-print d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Impressão de Escala</h1>
        <a class="btn btn-outline-secondary" href="index.php">Voltar</a>
    </div>

    <form method="get" class="row g-2 mb-3 no-print">
        <div class="col-md-3">
            <select class="form-select" name="tipo">
                <option value="diaria" <?= $tipo === 'diaria' ? 'selected' : '' ?>>Diária</option>
                <option value="semanal" <?= $tipo === 'semanal' ? 'selected' : '' ?>>Semanal</option>
            </select>
        </div>
        <div class="col-md-3"><input class="form-control" type="date" name="data" value="<?= htmlspecialchars($dataRef) ?>"></div>
        <div class="col-md-3"><button class="btn btn-primary" type="submit">Filtrar</button></div>
        <div class="col-md-3 text-md-end"><button class="btn btn-success" type="button" onclick="window.print()">Imprimir</button></div>
    </form>

    <h2 class="h5 mb-3"><?= htmlspecialchars($titulo) ?></h2>
    <div class="table-responsive">
        <table class="table table-bordered bg-white">
            <thead><tr><th>Data</th><th>Médico</th><th>Turno</th><th>Local</th><th>Paciente</th><th>Documento</th><th>Observações</th></tr></thead>
            <tbody>
            <?php foreach ($linhas as $linha): ?>
                <tr>
                    <td><?= htmlspecialchars($linha['data_escala']) ?></td>
                    <td><?= htmlspecialchars($linha['medico_nome']) ?></td>
                    <td><?= htmlspecialchars($linha['turno']) ?></td>
                    <td><?= htmlspecialchars($linha['local_consultorio']) ?></td>
                    <td><?= htmlspecialchars($linha['nome_completo'] ?? '') ?></td>
                    <td><?= htmlspecialchars($linha['documento'] ?? '') ?></td>
                    <td><?= htmlspecialchars($linha['observacoes'] ?? '') ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$linhas): ?>
                <tr><td colspan="7" class="text-center">Sem registros para o período.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        <h3 class="h6">Anotações manuais</h3>
        <div class="obs-linha"></div>
        <div class="obs-linha"></div>
        <div class="obs-linha"></div>
    </div>
</div>
</body>
</html>
