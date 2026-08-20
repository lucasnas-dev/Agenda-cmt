<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_login();

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_medico') {
        $nome = trim($_POST['nome'] ?? '');
        $crm = trim($_POST['crm'] ?? '');
        $especialidade = trim($_POST['especialidade'] ?? '');
        if ($nome && $crm && $especialidade) {
            try {
                $stmt = $pdo->prepare('INSERT INTO medicos (nome, crm, especialidade) VALUES (?, ?, ?)');
                $stmt->execute([$nome, $crm, $especialidade]);
                $message = 'Médico cadastrado com sucesso.';
            } catch (PDOException $e) {
                $error = 'Não foi possível cadastrar o médico (CRM já pode existir).';
            }
        } else {
            $error = 'Preencha todos os dados do médico.';
        }
    }

    if ($action === 'add_escala') {
        $medicoId = (int) ($_POST['medico_id'] ?? 0);
        $dataEscala = $_POST['data_escala'] ?? '';
        $turno = trim($_POST['turno'] ?? '');
        $local = trim($_POST['local_consultorio'] ?? '');
        $vagas = (int) ($_POST['vagas_disponiveis'] ?? 0);
        if ($medicoId > 0 && $dataEscala && $turno && $local && $vagas > 0) {
            $stmt = $pdo->prepare('INSERT INTO escalas (medico_id, data_escala, turno, local_consultorio, vagas_disponiveis) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$medicoId, $dataEscala, $turno, $local, $vagas]);
            log_action($pdo, (int) $_SESSION['user_id'], 'create', 'escala', (int) $pdo->lastInsertId());
            $message = 'Escala cadastrada com sucesso.';
        } else {
            $error = 'Preencha todos os dados da escala corretamente.';
        }
    }

    if ($action === 'delete_escala') {
        $escalaId = (int) ($_POST['escala_id'] ?? 0);
        if ($escalaId > 0) {
            $stmt = $pdo->prepare('DELETE FROM escalas WHERE id = ?');
            $stmt->execute([$escalaId]);
            log_action($pdo, (int) $_SESSION['user_id'], 'delete', 'escala', $escalaId);
            $message = 'Escala excluída.';
        }
    }
}

$medicos = $pdo->query('SELECT id, nome, crm, especialidade FROM medicos ORDER BY nome')->fetchAll();
$escalas = $pdo->query('SELECT e.id, e.data_escala, e.turno, e.local_consultorio, e.vagas_disponiveis, m.nome AS medico_nome, m.especialidade FROM escalas e INNER JOIN medicos m ON e.medico_id = m.id ORDER BY e.data_escala DESC, e.turno')->fetchAll();
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Escalas - Agenda Médica</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">Gestão de Médicos e Escalas</h1>
        <a class="btn btn-outline-secondary" href="index.php">Voltar</a>
    </div>

    <?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <div class="row g-3 mb-4">
        <div class="col-lg-5">
            <div class="card h-100"><div class="card-body">
                <h2 class="h5">Cadastrar Médico</h2>
                <form method="post" class="row g-2">
                    <input type="hidden" name="action" value="add_medico">
                    <div class="col-12"><input class="form-control" name="nome" placeholder="Nome" required></div>
                    <div class="col-6"><input class="form-control" name="crm" placeholder="CRM" required></div>
                    <div class="col-6"><input class="form-control" name="especialidade" placeholder="Especialidade" required></div>
                    <div class="col-12"><button class="btn btn-primary" type="submit">Salvar Médico</button></div>
                </form>
            </div></div>
        </div>
        <div class="col-lg-7">
            <div class="card h-100"><div class="card-body">
                <h2 class="h5">Cadastrar Escala</h2>
                <form method="post" class="row g-2">
                    <input type="hidden" name="action" value="add_escala">
                    <div class="col-md-6">
                        <select class="form-select" name="medico_id" required>
                            <option value="">Selecione o médico</option>
                            <?php foreach ($medicos as $medico): ?>
                                <option value="<?= (int) $medico['id'] ?>"><?= htmlspecialchars($medico['nome']) ?> - <?= htmlspecialchars($medico['especialidade']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3"><input class="form-control" type="date" name="data_escala" required></div>
                    <div class="col-md-3"><input class="form-control" name="turno" placeholder="Turno/Horário" required></div>
                    <div class="col-md-8"><input class="form-control" name="local_consultorio" placeholder="Local/Consultório" required></div>
                    <div class="col-md-4"><input class="form-control" type="number" min="1" name="vagas_disponiveis" placeholder="Vagas" required></div>
                    <div class="col-12"><button class="btn btn-primary" type="submit">Salvar Escala</button></div>
                </form>
            </div></div>
        </div>
    </div>

    <div class="row g-3">
        <?php foreach ($escalas as $escala): ?>
            <div class="col-md-6 col-xl-4">
                <div class="card h-100">
                    <div class="card-body">
                        <h3 class="h6"><?= htmlspecialchars($escala['medico_nome']) ?> (<?= htmlspecialchars($escala['especialidade']) ?>)</h3>
                        <p class="mb-1"><strong>Data:</strong> <?= htmlspecialchars($escala['data_escala']) ?></p>
                        <p class="mb-1"><strong>Turno:</strong> <?= htmlspecialchars($escala['turno']) ?></p>
                        <p class="mb-1"><strong>Local:</strong> <?= htmlspecialchars($escala['local_consultorio']) ?></p>
                        <p class="mb-3"><strong>Vagas:</strong> <?= (int) $escala['vagas_disponiveis'] ?></p>
                        <form method="post" onsubmit="return confirm('Excluir escala?');">
                            <input type="hidden" name="action" value="delete_escala">
                            <input type="hidden" name="escala_id" value="<?= (int) $escala['id'] ?>">
                            <button class="btn btn-sm btn-outline-danger" type="submit">Excluir</button>
                        </form>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
</body>
</html>
