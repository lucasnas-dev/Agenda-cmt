<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_login();

$totalMedicos = (int) $pdo->query('SELECT COUNT(*) FROM medicos')->fetchColumn();
$totalEscalas = (int) $pdo->query('SELECT COUNT(*) FROM escalas WHERE data_escala >= CURDATE()')->fetchColumn();
$totalAgendamentosHoje = (int) $pdo->query('SELECT COUNT(*) FROM agendamentos a INNER JOIN escalas e ON a.escala_id = e.id WHERE e.data_escala = CURDATE()')->fetchColumn();
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard - Agenda Médica</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container">
        <a class="navbar-brand" href="index.php">Agenda Médica</a>
        <div class="ms-auto d-flex align-items-center gap-3 text-white">
            <span><?= htmlspecialchars(current_user_name()) ?></span>
            <a class="btn btn-sm btn-outline-light" href="logout.php">Sair</a>
        </div>
    </div>
</nav>
<main class="container py-4">
    <div class="row g-3 mb-4">
        <div class="col-md-4"><div class="card"><div class="card-body"><h2 class="h6">Médicos cadastrados</h2><p class="display-6 mb-0"><?= $totalMedicos ?></p></div></div></div>
        <div class="col-md-4"><div class="card"><div class="card-body"><h2 class="h6">Escalas futuras</h2><p class="display-6 mb-0"><?= $totalEscalas ?></p></div></div></div>
        <div class="col-md-4"><div class="card"><div class="card-body"><h2 class="h6">Agendamentos hoje</h2><p class="display-6 mb-0"><?= $totalAgendamentosHoje ?></p></div></div></div>
    </div>

    <div class="row g-3">
        <div class="col-md-3"><a class="btn btn-outline-primary w-100" href="escalas.php">Gestão de Escalas</a></div>
        <div class="col-md-3"><a class="btn btn-outline-primary w-100" href="agendamentos.php">Agendamentos</a></div>
        <div class="col-md-3"><a class="btn btn-outline-primary w-100" href="busca.php">Busca de Pacientes</a></div>
        <div class="col-md-3"><a class="btn btn-outline-primary w-100" href="imprimir_escala.php">Imprimir Escala</a></div>
    </div>
</main>
</body>
</html>
