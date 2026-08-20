<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_login();

function parse_intervalo_para_segundos(string $valor): int
{
    $valor = strtolower(trim($valor));
    $valor = str_replace(',', '.', $valor);
    $valor = preg_replace('/\s+/', ' ', $valor);

    if ($valor === '') {
        return 0;
    }

    if (preg_match('/^(\d+)\s*(?:min|mins|minutos|m)$/', $valor, $m)) {
        return (int) $m[1] * 60;
    }

    if (preg_match('/^(\d+)\s*(?:seg|secs|segundos|s)$/', $valor, $m)) {
        return (int) $m[1];
    }

    if (preg_match('/^(\d+)\s*(?:min|mins|minutos|m)\s*(?:e|and|\+)?\s*(\d+)\s*(?:seg|secs|segundos|s)$/', $valor, $m)) {
        return ((int) $m[1] * 60) + (int) $m[2];
    }

    if (preg_match('/^(\d+)\:(\d{1,2})$/', $valor, $m)) {
        return ((int) $m[1] * 60) + (int) $m[2];
    }

    if (preg_match('/^(\d+(?:\.\d+)?)$/', $valor, $m)) {
        return (int) round((float) $m[1] * 60);
    }

    if (preg_match('/^(\d+(?:\.\d+)?)\s*(?:min|mins|minutos|m)$/', $valor, $m)) {
        return (int) round((float) $m[1] * 60);
    }

    return 0;
}

function gerar_turno_por_intervalo(string $horaInicio, string $intervalo, int $vagas): string
{
    $inicio = DateTimeImmutable::createFromFormat('H:i', $horaInicio);
    $intervaloSegundos = parse_intervalo_para_segundos($intervalo);

    if (!$inicio || $intervaloSegundos <= 0 || $vagas <= 0) {
        return '';
    }

    $horarios = [];
    for ($i = 0; $i < $vagas; $i++) {
        $horarios[] = $inicio->modify('+' . ($i * $intervaloSegundos) . ' seconds')->format('H:i:s');
    }

    return implode(', ', $horarios);
}

function formatar_data_brasil(string $data): string
{
    $dt = DateTimeImmutable::createFromFormat('Y-m-d', $data);
    if (!$dt) {
        return $data;
    }

    return $dt->format('d/m/Y');
}

function extrair_primeiro_ultimo_turno(string $turno): string
{
    $horarios = array_filter(array_map('trim', explode(',', $turno)), static fn ($valor) => $valor !== '');
    if (!$horarios) {
        return $turno;
    }

    $primeiro = trim($horarios[0]);
    $ultimo = trim($horarios[count($horarios) - 1]);

    return $primeiro . ' - ' . $ultimo;
}

$message = $_SESSION['flash_message'] ?? '';
$error = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_message'], $_SESSION['flash_error']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_medico') {
        $nome = trim($_POST['nome'] ?? '');
        $contrato = $_POST['contrato'] ?? 'SES';
        $contratoValido = in_array($contrato, ['SES', 'COOAP', 'COPED'], true);

        if ($nome && $contratoValido) {
            try {
                $stmt = $pdo->prepare('INSERT INTO medicos (nome, contrato) VALUES (?, ?)');
                $stmt->execute([$nome, $contrato]);
                $_SESSION['flash_message'] = 'Médico cadastrado com sucesso.';
            } catch (PDOException $e) {
                $_SESSION['flash_error'] = 'Não foi possível cadastrar o médico.';
            }
        } else {
            $_SESSION['flash_error'] = 'Preencha o nome e selecione o contrato.';
        }
    }

    if ($action === 'add_escala') {
        $medicoId = (int) ($_POST['medico_id'] ?? 0);
        $dataEscala = $_POST['data_escala'] ?? '';
        $horaInicio = trim($_POST['hora_inicio'] ?? '');
        $intervalo = trim((string) ($_POST['intervalo_minutos'] ?? ''));
        $local = trim($_POST['local_consultorio'] ?? '');
        $vagas = (int) ($_POST['vagas_disponiveis'] ?? 0);

        $consultorioNumero = (int) filter_var($local, FILTER_SANITIZE_NUMBER_INT);
        $consultorioValido = $consultorioNumero >= 1 && $consultorioNumero <= 6 && $local === (string) $consultorioNumero;

        if ($medicoId > 0 && $dataEscala && $horaInicio && $intervalo !== '' && $consultorioValido && $vagas > 0) {
            $intervaloSegundos = parse_intervalo_para_segundos($intervalo);
            $turno = gerar_turno_por_intervalo($horaInicio, $intervalo, $vagas);

            if ($intervaloSegundos > 0 && $turno !== '') {
                $stmt = $pdo->prepare('INSERT INTO escalas (medico_id, data_escala, turno, local_consultorio, vagas_disponiveis) VALUES (?, ?, ?, ?, ?)');
                $stmt->execute([$medicoId, $dataEscala, $turno, $local, $vagas]);
                log_action($pdo, (int) $_SESSION['user_id'], 'create', 'escala', (int) $pdo->lastInsertId());
                $_SESSION['flash_message'] = 'Escala cadastrada com sucesso.';
            } else {
                $_SESSION['flash_error'] = 'Informe um horário inicial válido e um intervalo maior que zero.';
            }
        } else {
            $_SESSION['flash_error'] = 'Preencha todos os dados da escala corretamente. O consultório deve ser um número de 1 a 6.';
        }
    }

    if ($action === 'delete_escala') {
        $escalaId = (int) ($_POST['escala_id'] ?? 0);
        if ($escalaId > 0) {
            $stmt = $pdo->prepare('DELETE FROM escalas WHERE id = ?');
            $stmt->execute([$escalaId]);
            log_action($pdo, (int) $_SESSION['user_id'], 'delete', 'escala', $escalaId);
            $_SESSION['flash_message'] = 'Escala excluída.';
        }
    }

    if ($action === 'delete_medico') {
        $medicoId = (int) ($_POST['medico_id'] ?? 0);
        if ($medicoId > 0) {
            try {
                $pdo->beginTransaction();

                $pdo->prepare('DELETE FROM agendamentos WHERE escala_id IN (SELECT id FROM escalas WHERE medico_id = ?)')->execute([$medicoId]);
                $pdo->prepare('DELETE FROM escalas WHERE medico_id = ?')->execute([$medicoId]);
                $pdo->prepare('DELETE FROM medicos WHERE id = ?')->execute([$medicoId]);

                $pdo->commit();
                log_action($pdo, (int) $_SESSION['user_id'], 'delete', 'escala', $medicoId);
                $_SESSION['flash_message'] = 'Médico e registros relacionados excluídos.';
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $_SESSION['flash_error'] = 'Não foi possível excluir o médico.';
            }
        }
    }

    if ($action === 'edit_medico') {
        $medicoId = (int) ($_POST['medico_id'] ?? 0);
        $nome = trim($_POST['nome'] ?? '');
        $contrato = $_POST['contrato'] ?? 'SES';
        if ($medicoId > 0 && $nome && in_array($contrato, ['SES', 'COOAP', 'COPED'], true)) {
            $stmt = $pdo->prepare('UPDATE medicos SET nome = ?, contrato = ? WHERE id = ?');
            $stmt->execute([$nome, $contrato, $medicoId]);
            $_SESSION['flash_message'] = 'Médico atualizado com sucesso.';
        } else {
            $_SESSION['flash_error'] = 'Dados do médico inválidos.';
        }
    }

    if ($action === 'edit_escala') {
        $escalaId = (int) ($_POST['escala_id'] ?? 0);
        $dataEscala = $_POST['data_escala'] ?? '';
        $horaInicio = trim($_POST['hora_inicio'] ?? '');
        $intervalo = trim((string) ($_POST['intervalo_minutos'] ?? ''));
        $local = trim($_POST['local_consultorio'] ?? '');
        $vagas = (int) ($_POST['vagas_disponiveis'] ?? 0);
        $consultorioNumero = (int) filter_var($local, FILTER_SANITIZE_NUMBER_INT);
        $consultorioValido = $consultorioNumero >= 1 && $consultorioNumero <= 6 && $local === (string) $consultorioNumero;

        if ($escalaId > 0 && $dataEscala && $horaInicio && $intervalo !== '' && $consultorioValido && $vagas > 0) {
            $intervaloSegundos = parse_intervalo_para_segundos($intervalo);
            $turno = gerar_turno_por_intervalo($horaInicio, $intervalo, $vagas);
            if ($intervaloSegundos > 0 && $turno !== '') {
                $stmt = $pdo->prepare('UPDATE escalas SET data_escala = ?, turno = ?, local_consultorio = ?, vagas_disponiveis = ? WHERE id = ?');
                $stmt->execute([$dataEscala, $turno, $local, $vagas, $escalaId]);
                $_SESSION['flash_message'] = 'Escala atualizada com sucesso.';
            } else {
                $_SESSION['flash_error'] = 'Hora e intervalo inválidos para essa escala.';
            }
        } else {
            $_SESSION['flash_error'] = 'Preencha os dados da escala para edição.';
        }
    }

    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

$dataFiltroInicio = isset($_GET['data_inicio']) ? trim((string) $_GET['data_inicio']) : '';
$dataFiltroFim = isset($_GET['data_fim']) ? trim((string) $_GET['data_fim']) : '';

$medicos = $pdo->query('SELECT id, nome, contrato FROM medicos ORDER BY nome')->fetchAll();

$baseSql = 'SELECT e.id, e.data_escala, e.turno, e.local_consultorio, e.vagas_disponiveis, m.nome AS medico_nome, m.contrato AS medico_contrato, (SELECT COUNT(*) FROM agendamentos a WHERE a.escala_id = e.id) AS vagas_preenchidas, (e.vagas_disponiveis - (SELECT COUNT(*) FROM agendamentos a WHERE a.escala_id = e.id)) AS vagas_restantes FROM escalas e INNER JOIN medicos m ON e.medico_id = m.id';
$params = [];
$where = [];

if ($dataFiltroInicio !== '') {
    $where[] = 'e.data_escala >= :data_inicio';
    $params[':data_inicio'] = $dataFiltroInicio;
}

if ($dataFiltroFim !== '') {
    $where[] = 'e.data_escala <= :data_fim';
    $params[':data_fim'] = $dataFiltroFim;
}

if ($where) {
    $baseSql .= ' WHERE ' . implode(' AND ', $where);
}

$baseSql .= ' ORDER BY e.data_escala DESC, e.turno';

$stmtEscalas = $pdo->prepare($baseSql);
$stmtEscalas->execute($params);
$escalas = $stmtEscalas->fetchAll();

$editMedicoId = isset($_GET['edit_medico']) ? (int) $_GET['edit_medico'] : 0;
$editEscalaId = isset($_GET['edit_escala']) ? (int) $_GET['edit_escala'] : 0;
$medicoEditando = $editMedicoId > 0 ? $pdo->prepare('SELECT id, nome, contrato FROM medicos WHERE id = ? LIMIT 1') : null;
if ($medicoEditando) {
    $medicoEditando->execute([$editMedicoId]);
    $medicoEditando = $medicoEditando->fetch();
}
$escalaEditando = $editEscalaId > 0 ? $pdo->prepare('SELECT id, medico_id, data_escala, turno, local_consultorio, vagas_disponiveis FROM escalas WHERE id = ? LIMIT 1') : null;
if ($escalaEditando) {
    $escalaEditando->execute([$editEscalaId]);
    $escalaEditando = $escalaEditando->fetch();
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Escalas - Agenda Médica</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { font-family: Arial, sans-serif; }
        .filtro-bar {
            display: flex;
            gap: 12px;
            margin-bottom: 1.25rem;
            padding: 0.25rem 0;
            border-bottom: 1px solid #e5e7eb;
        }
        .filtro-btn {
            min-width: 130px;
            font-weight: 600;
            border-radius: 10px;
            padding: 0.6rem 1rem;
        }
        .filtro-btn.active {
            box-shadow: 0 0 0 0.15rem rgba(13, 110, 253, 0.15);
        }
        .card {
            border-radius: 14px;
            border: 1px solid #e5e7eb;
        }
        .form-control, .form-select {
            border-radius: 10px;
            min-height: 46px;
        }
        .lista-secao .card-body {
            padding: 1.1rem 1.1rem 1rem;
        }
    </style>
</head>
<body class="bg-light">
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">Gestão de Médicos e Escalas</h1>
        <a class="btn btn-outline-secondary" href="index.php">Voltar</a>
    </div>

    <?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <?php if ($medicoEditando): ?>
        <div class="card mb-4">
            <div class="card-body">
                <h2 class="h5">Editar médico</h2>
                <form method="post" class="row g-2 align-items-end">
                    <input type="hidden" name="action" value="edit_medico">
                    <input type="hidden" name="medico_id" value="<?= (int) $medicoEditando['id'] ?>">
                    <div class="col-md-6"><input class="form-control" name="nome" value="<?= htmlspecialchars($medicoEditando['nome']) ?>" required></div>
                    <div class="col-md-4">
                        <select class="form-select" name="contrato" required>
                            <?php foreach (['SES', 'COOAP', 'COPED'] as $valor): ?>
                                <option value="<?= $valor ?>" <?= $medicoEditando['contrato'] === $valor ? 'selected' : '' ?>><?= $valor ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2"><button class="btn btn-primary w-100" type="submit">Salvar</button></div>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($escalaEditando): ?>
        <div class="card mb-4">
            <div class="card-body">
                <h2 class="h5">Editar escala</h2>
                <form method="post" class="row g-2 align-items-end">
                    <input type="hidden" name="action" value="edit_escala">
                    <input type="hidden" name="escala_id" value="<?= (int) $escalaEditando['id'] ?>">
                    <div class="col-md-3"><input class="form-control" type="date" name="data_escala" value="<?= htmlspecialchars($escalaEditando['data_escala']) ?>" required></div>
                    <div class="col-md-2"><input class="form-control" type="time" name="hora_inicio" value="<?= htmlspecialchars(substr($escalaEditando['turno'], 0, 5)) ?>" required></div>
                    <div class="col-md-2"><input class="form-control" type="text" name="intervalo_minutos" value="10" placeholder="Ex.: 10 ou 3:30" required></div>
                    <div class="col-md-2">
                        <select class="form-select" name="local_consultorio" required>
                            <?php for ($i = 1; $i <= 6; $i++): ?>
                                <option value="<?= $i ?>" <?= (string) $escalaEditando['local_consultorio'] === (string) $i ? 'selected' : '' ?>><?= $i ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-md-2"><input class="form-control" type="number" min="1" name="vagas_disponiveis" value="<?= (int) $escalaEditando['vagas_disponiveis'] ?>" required></div>
                    <div class="col-md-1"><button class="btn btn-primary w-100" type="submit">Salvar</button></div>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <div class="row g-3 mb-4">
        <div class="col-lg-7">
            <div class="card h-100"><div class="card-body">
                <h2 class="h5">Cadastrar Escala</h2>
                <form method="post" class="row g-2">
                    <input type="hidden" name="action" value="add_escala">
                    <div class="col-md-6">
                        <select class="form-select" name="medico_id" required>
                            <option value="">Selecione o médico</option>
                            <?php foreach ($medicos as $medico): ?>
                                <option value="<?= (int) $medico['id'] ?>"><?= htmlspecialchars($medico['nome']) ?> - <?= htmlspecialchars($medico['contrato']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3"><input class="form-control" type="date" name="data_escala" required></div>
                    <div class="col-md-3"><input class="form-control" type="time" name="hora_inicio" value="14:00" required></div>
                    <div class="col-md-4">
                        <label class="form-label mb-1">Intervalo</label>
                        <input class="form-control" type="text" name="intervalo_minutos" value="10" placeholder="Ex.: 10 ou 3:30" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label mb-1">Consultório</label>
                        <select class="form-select" name="local_consultorio" required>
                            <option value="">Selecione</option>
                            <?php for ($i = 1; $i <= 6; $i++): ?>
                                <option value="<?= $i ?>" <?= $i === 1 ? 'selected' : '' ?>><?= $i ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label mb-1">Vagas</label>
                        <input class="form-control" type="number" min="1" name="vagas_disponiveis" placeholder="Vagas" required>
                    </div>
                    <div class="col-12"><button class="btn btn-primary" type="submit">Salvar Escala</button></div>
                </form>
            </div></div>
        </div>
        <div class="col-lg-5">
            <div class="card h-100"><div class="card-body">
                <h2 class="h5">Cadastrar Médico</h2>
                <form method="post" class="row g-2">
                    <input type="hidden" name="action" value="add_medico">
                    <div class="col-12"><input class="form-control" name="nome" placeholder="Nome" required></div>

                    <div class="col-12">
                        <label class="form-label mb-2">Contrato</label>
                        <div class="d-flex flex-wrap gap-3">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="contrato" id="contrato_ses" value="SES" checked>
                                <label class="form-check-label" for="contrato_ses">SES</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="contrato" id="contrato_cooap" value="COOAP">
                                <label class="form-check-label" for="contrato_cooap">COOAP</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="contrato" id="contrato_coped" value="COPED">
                                <label class="form-check-label" for="contrato_coped">COPED</label>
                            </div>
                        </div>
                    </div>

                    <div class="col-12"><button class="btn btn-primary" type="submit">Salvar Médico</button></div>
                </form>
            </div></div>
        </div>
    </div>

    <div class="filtro-bar mt-2">
        <button type="button" class="btn btn-outline-primary filtro-btn" data-target="medicos-section" style="order: 2;">Médicos</button>
        <button type="button" class="btn btn-primary filtro-btn active" data-target="escalas-section" style="order: 1;">Escalas</button>
    </div>

    <div id="medicos-section" class="lista-secao" style="display: none;">
        <div class="card mb-4">
            <div class="card-body">
                <h2 class="h5 mb-3">Médicos cadastrados</h2>
                <div class="row g-3">
                    <?php foreach ($medicos as $medico): ?>
                        <div class="col-md-6 col-xl-4">
                            <div class="card h-100 border-0 bg-light">
                                <div class="card-body">
                                    <h3 class="h6"><?= htmlspecialchars($medico['nome']) ?></h3>
                                    <p class="mb-3"><strong>Contrato:</strong> <?= htmlspecialchars($medico['contrato']) ?></p>
                                    <div class="d-flex gap-2">
                                        <form method="post" onsubmit="return confirm('Excluir este médico e seus registros relacionados?');">
                                            <input type="hidden" name="action" value="delete_medico">
                                            <input type="hidden" name="medico_id" value="<?= (int) $medico['id'] ?>">
                                            <button class="btn btn-sm btn-outline-danger" type="submit">Excluir</button>
                                        </form>
                                        <a class="btn btn-sm btn-outline-primary" href="?edit_medico=<?= (int) $medico['id'] ?>">Editar</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <div id="escalas-section" class="lista-secao">
        <div class="card mb-3">
            <div class="card-body">
                <h2 class="h5 mb-3">Filtrar escalas por data</h2>
                <form method="get" class="row g-2 align-items-end">
                    <input type="hidden" name="section" value="escalas-section">
                    <div class="col-md-4">
                        <label class="form-label mb-1">Data inicial</label>
                        <input class="form-control" type="date" name="data_inicio" value="<?= htmlspecialchars($dataFiltroInicio) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label mb-1">Data final</label>
                        <input class="form-control" type="date" name="data_fim" value="<?= htmlspecialchars($dataFiltroFim) ?>">
                    </div>
                    <div class="col-md-4 d-flex gap-2">
                        <button class="btn btn-primary" type="submit">Filtrar</button>
                        <a class="btn btn-outline-secondary" href="escalas.php?section=escalas-section">Limpar</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <h2 class="h5 mb-3">Escalas cadastradas</h2>
                <?php if (!$escalas): ?>
                    <div class="alert alert-light border mb-0">Nenhuma escala encontrada para o período informado.</div>
                <?php else: ?>
                    <div class="row g-3">
                        <?php foreach ($escalas as $escala): ?>
                            <div class="col-md-6 col-xl-4">
                                <div class="card h-100 border-0 bg-light">
                                    <div class="card-body">
                                        <h3 class="h6"><?= htmlspecialchars($escala['medico_nome']) ?></h3>
                                        <p class="mb-1"><strong>Contrato:</strong> <?= htmlspecialchars($escala['medico_contrato']) ?></p>
                                        <p class="mb-1"><strong>Data:</strong> <?= htmlspecialchars(formatar_data_brasil($escala['data_escala'])) ?></p>
                                        <p class="mb-1"><strong>Turno:</strong> <?= htmlspecialchars(extrair_primeiro_ultimo_turno($escala['turno'])) ?></p>
                                        <p class="mb-1"><strong>Consultório:</strong> <?= htmlspecialchars($escala['local_consultorio']) ?></p>
                                        <p class="mb-1"><strong>Total de vagas:</strong> <?= (int) $escala['vagas_disponiveis'] ?></p>
                                        <p class="mb-1"><strong>Preenchidas:</strong> <?= max(0, (int) $escala['vagas_preenchidas']) ?></p>
                                        <p class="mb-3"><strong>Restantes:</strong> <?= max(0, (int) $escala['vagas_restantes']) ?></p>
                                        <div class="d-flex gap-2">
                                            <form method="post" onsubmit="return confirm('Excluir escala?');">
                                                <input type="hidden" name="action" value="delete_escala">
                                                <input type="hidden" name="escala_id" value="<?= (int) $escala['id'] ?>">
                                                <button class="btn btn-sm btn-outline-danger" type="submit">Excluir</button>
                                            </form>
                                            <a class="btn btn-sm btn-outline-primary" href="?edit_escala=<?= (int) $escala['id'] ?>">Editar</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const buttons = document.querySelectorAll('.filtro-btn');
        const sections = {
            'medicos-section': document.getElementById('medicos-section'),
            'escalas-section': document.getElementById('escalas-section')
        };

        const params = new URLSearchParams(window.location.search);
        const activeTarget = params.get('section') || (params.has('data_inicio') || params.has('data_fim') ? 'escalas-section' : 'escalas-section');

        function applySection(target) {
            buttons.forEach((btn) => {
                const isActive = btn.dataset.target === target;
                btn.classList.toggle('active', isActive);
                btn.classList.toggle('btn-primary', isActive);
                btn.classList.toggle('btn-outline-primary', !isActive);
            });

            Object.entries(sections).forEach(([key, section]) => {
                if (section) {
                    section.style.display = key === target ? 'block' : 'none';
                }
            });

            const sectionInput = document.querySelector('input[name="section"]');
            if (sectionInput) {
                sectionInput.value = target;
            }
        }

        buttons.forEach((button) => {
            button.addEventListener('click', function () {
                applySection(this.dataset.target);
            });
        });

        applySection(activeTarget);
    });
</script>
</body>
</html>
