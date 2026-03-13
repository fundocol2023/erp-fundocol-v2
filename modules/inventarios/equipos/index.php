<?php
include '../../../includes/navbar.php';
include_once '../../../config/db.php';

$stats = $pdo->query("SELECT
    (SELECT COUNT(*) FROM inventario_equipos) AS total,
    (SELECT COUNT(*) FROM inventario_equipos WHERE estado='disponible') AS disponibles,
    (SELECT COUNT(*) FROM inventario_equipos WHERE estado='asignado') AS asignados,
    (SELECT COUNT(*) FROM inventario_equipos WHERE estado='mantenimiento') AS mantenimiento
")->fetch(PDO::FETCH_ASSOC);

$sql = "SELECT * FROM inventario_equipos ORDER BY tipo ASC, marca ASC, modelo ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$equipos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$msg = $_GET['msg'] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Inventario de Equipo de Computo</title>
    <link rel="stylesheet" href="../../../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@500;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="https://erp-fundocol.fundocol.org/assets/img/Fundocol_favicon.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="inventarios-equipos-page">
    <div class="navbar-spacer"></div>

    <header class="equipos-header">
        <div class="equipos-title">Inventario de Equipo de Computo</div>
        <div class="equipos-subtitle">Panel tecnico para hardware, estado y asignaciones</div>
    </header>

    <div class="equipos-actions">
        <button class="btn-equipo primary" onclick="window.location.href='agregar.php'">
            <i class="bi bi-plus-circle"></i> Registrar equipo
        </button>
        <button class="btn-equipo" type="button" onclick="window.location.href='exportar_excel.php'">
            <i class="bi bi-file-earmark-excel"></i> Exportar Excel
        </button>
    </div>

    <?php if ($msg === 'ok'): ?>
        <div class="alert alert-success" style="margin: 0 7%;">Registro guardado correctamente.</div>
    <?php elseif ($msg === 'eliminado'): ?>
        <div class="alert alert-success" style="margin: 0 7%;">Equipo eliminado.</div>
    <?php endif; ?>

    <section class="equipos-stats">
        <div class="stat-card">
            <span class="stat-label">Total</span>
            <span class="stat-value"><?= (int)($stats['total'] ?? 0) ?></span>
        </div>
        <div class="stat-card">
            <span class="stat-label">Disponibles</span>
            <span class="stat-value"><?= (int)($stats['disponibles'] ?? 0) ?></span>
        </div>
        <div class="stat-card">
            <span class="stat-label">Asignados</span>
            <span class="stat-value"><?= (int)($stats['asignados'] ?? 0) ?></span>
        </div>
        <div class="stat-card">
            <span class="stat-label">Mantenimiento</span>
            <span class="stat-value"><?= (int)($stats['mantenimiento'] ?? 0) ?></span>
        </div>
    </section>

    <section class="equipos-board">
        <div class="equipos-board-head">
            <div>Equipo</div>
            <div>Identidad</div>
            <div>Especificaciones</div>
            <div>Responsable</div>
            <div>Estado</div>
            <div>Acciones</div>
        </div>

        <?php foreach ($equipos as $e): ?>
            <?php
                $estado = $e['estado'] ?? 'disponible';
                $estadoClass = $estado === 'asignado' ? 'asignado' : ($estado === 'mantenimiento' ? 'mantenimiento' : ($estado === 'baja' ? 'baja' : 'disponible'));
                $nombre = trim(($e['tipo'] ?? '').' '.($e['marca'] ?? '').' '.($e['modelo'] ?? ''));
                $serial = $e['serial'] ? 'Serial: '.$e['serial'] : 'Serial N/D';
                $hostname = $e['hostname'] ? 'Host: '.$e['hostname'] : 'Host N/D';
            ?>
            <article class="equipo-row">
                <div class="equipo-main">
                    <div class="equipo-name"><?= htmlspecialchars($nombre !== '' ? $nombre : 'Equipo sin nombre') ?></div>
                    <div class="equipo-meta">
                        <span class="chip"><i class="bi bi-cpu"></i> <?= htmlspecialchars($e['so'] ?? 'SO N/D') ?></span>
                        <span class="chip"><i class="bi bi-geo-alt"></i> <?= htmlspecialchars($e['ubicacion'] ?? 'Ubicacion N/D') ?></span>
                    </div>
                </div>
                <div class="equipo-identidad">
                    <div class="chip dark"><?= htmlspecialchars($serial) ?></div>
                    <div class="chip dark"><?= htmlspecialchars($hostname) ?></div>
                </div>
                <div class="equipo-specs">
                    <span class="spec-chip">CPU: <?= htmlspecialchars($e['procesador'] ?? 'N/D') ?></span>
                    <span class="spec-chip">RAM: <?= htmlspecialchars($e['ram'] ?? 'N/D') ?></span>
                    <span class="spec-chip">SSD/HDD: <?= htmlspecialchars($e['almacenamiento'] ?? 'N/D') ?></span>
                </div>
                <div class="equipo-user">
                    <div class="equipo-user-name"><i class="bi bi-person-badge"></i> <?= htmlspecialchars($e['responsable'] ?? 'Sin responsable') ?></div>
                    <div class="equipo-meta">Area: <?= htmlspecialchars($e['area'] ?? 'Sin area') ?></div>
                </div>
                <div>
                    <span class="estado-pill <?= $estadoClass ?>">
                        <?= ucfirst($estadoClass) ?>
                    </span>
                </div>
                <div class="equipo-actions">
                    <button class="btn btn-sm btn-outline-secondary" onclick="window.location.href='editar.php?id=<?= (int)$e['id'] ?>'" title="Editar">
                        <i class="bi bi-pencil"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-danger" onclick="if(confirm('¿Eliminar equipo?'))window.location.href='eliminar.php?id=<?= (int)$e['id'] ?>'" title="Eliminar">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            </article>
        <?php endforeach; ?>
    </section>
</body>
</html>
