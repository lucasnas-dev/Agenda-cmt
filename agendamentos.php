<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_login();

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_agendamento') {
        $escalaId = (int) ($_POST['escala_id'] ?? 0);
        $nomeCompleto = trim($_POST['nome_completo'] ?? '');
        $idade = (int) ($_POST['idade'] ?? 0);
        $documento = trim($_POST['documento'] ?? '');
        $observacoes = trim($_POST['observacoes'] ?? '');

        if ($escalaId > 0 && $nomeCompleto && $idade > 0 && $documento) {
            $stmt = $pdo->prepare('SELECT id FROM pacientes WHERE documento = ? LIMIT 1');
            $stmt->execute([$documento]);
            $paciente = $stmt->fetch();

            if ($paciente) {
                $pacienteId = (int) $paciente['id'];
                $update = $pdo->prepare('UPDATE pacientes SET nome_completo = ?, idade = ? WHERE id = ?');
                $update->execute([$nomeCompleto, $idade, $pacienteId]);
            } else {
                $insertPaciente = $pdo->prepare('INSERT INTO pacientes (nome_completo, idade, documento) VALUES (?, ?, ?)');
                $insertPaciente->execute([$nomeCompleto, $idade, $documento]);
                $pacienteId = (int) $pdo->lastInsertId();
            }

            $vagaSql = 'SELECT e.vagas_disponiveis, COUNT(a.id) AS agendados FROM escalas e LEFT JOIN agendamentos a ON a.escala_id = e.id WHERE e.id = ? GROUP BY e.id';
            $vagaStmt = $pdo->prepare($vagaSql);
            $vagaStmt->execute([$escalaId]);
            $vagaInfo = $vagaStmt->fetch();
            if (!$vagaInfo || (int) $vagaInfo['agendados'] >= (int) $vagaInfo['vagas_disponiveis']) {
                $error = 'Sem vagas disponíveis para esta escala.';
            } else {
                $insertAgendamento = $pdo->prepare('INSERT INTO agendamentos (escala_id, paciente_id, observacoes) VALUES (?, ?, ?)');
                $insertAgendamento->execute([$escalaId, $pacienteId, $observacoes ?: null]);
                log_action($pdo, (int) $_SESSION['user_id'], 'create', 'agendamento', (int) $pdo->lastInsertId());
                $message = 'Agendamento realizado com sucesso.';
            }
        } else {
            $error = 'Preencha todos os campos obrigatórios.';
        }
    }

    if ($action === 'delete_agendamento') {
        $agendamentoId = (int) ($_POST['agendamento_id'] ?? 0);
        if ($agendamentoId > 0) {
            $stmt = $pdo->prepare('DELETE FROM agendamentos WHERE id = ?');
            $stmt->execute([$agendamentoId]);
            log_action($pdo, (int) $_SESSION['user_id'], 'delete', 'agendamento', $agendamentoId);
            $message = 'Agendamento excluído.';
        }
    }
}

$escalas = $pdo->query('SELECT e.id, e.data_escala, e.turno, m.nome AS medico_nome, m.especialidade, e.local_consultorio FROM escalas e INNER JOIN medicos m ON m.id = e.medico_id ORDER BY e.data_escala DESC')->fetchAll();
$agendamentos = $pdo->query('SELECT a.id, p.nome_completo, p.idade, p.documento, a.observacoes, e.data_escala, e.turno, m.nome AS medico_nome FROM agendamentos a INNER JOIN pacientes p ON a.paciente_id = p.id INNER JOIN escalas e ON a.escala_id = e.id INNER JOIN medicos m ON e.medico_id = m.id ORDER BY e.data_escala DESC, p.nome_completo')->fetchAll();
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Agendamentos - Agenda Médica</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">Agendamentos de Pacientes</h1>
        <a class="btn btn-outline-secondary" href="index.php">Voltar</a>
    </div>

    <?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <div class="card mb-4"><div class="card-body">
        <h2 class="h5">Novo Agendamento</h2>
        <form method="post" class="row g-2">
            <input type="hidden" name="action" value="add_agendamento">
            <div class="col-md-6">
                <select class="form-select" name="escala_id" required>
                    <option value="">Selecione a escala</option>
                    <?php foreach ($escalas as $escala): ?>
                        <option value="<?= (int) $escala['id'] ?>"><?= htmlspecialchars($escala['data_escala']) ?> | <?= htmlspecialchars($escala['turno']) ?> | Dr(a). <?= htmlspecialchars($escala['medico_nome']) ?> - <?= htmlspecialchars($escala['local_consultorio']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6"><input class="form-control" name="nome_completo" placeholder="Nome Completo" required></div>
            <div class="col-md-2"><input class="form-control" type="number" min="1" name="idade" placeholder="Idade" required></div>
            <div class="col-md-4"><input class="form-control" name="documento" placeholder="CPF ou Cartão SUS" required></div>
            <div class="col-md-6"><input class="form-control" name="observacoes" placeholder="Observações"></div>
            <div class="col-12"><button class="btn btn-primary" type="submit">Agendar Paciente</button></div>
        </form>
    </div></div>

    <div class="table-responsive">
        <table class="table table-striped table-bordered bg-white">
            <thead><tr><th>Paciente</th><th>Idade</th><th>Documento</th><th>Médico</th><th>Data/Turno</th><th>Observações</th><th>Ações</th></tr></thead>
            <tbody>
            <?php foreach ($agendamentos as $item): ?>
                <tr>
                    <td><?= htmlspecialchars($item['nome_completo']) ?></td>
                    <td><?= (int) $item['idade'] ?></td>
                    <td><?= htmlspecialchars($item['documento']) ?></td>
                    <td><?= htmlspecialchars($item['medico_nome']) ?></td>
                    <td><?= htmlspecialchars($item['data_escala']) ?> - <?= htmlspecialchars($item['turno']) ?></td>
                    <td><?= htmlspecialchars($item['observacoes'] ?? '') ?></td>
                    <td>
                        <form method="post" onsubmit="return confirm('Excluir agendamento?');">
                            <input type="hidden" name="action" value="delete_agendamento">
                            <input type="hidden" name="agendamento_id" value="<?= (int) $item['id'] ?>">
                            <button class="btn btn-sm btn-outline-danger" type="submit">Excluir</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
