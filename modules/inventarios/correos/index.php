<?php
include '../../../includes/navbar.php';
include_once '../../../config/db.php';

$stats = $pdo->query("SELECT
    (SELECT COUNT(*) FROM inventario_correos) AS total,
    (SELECT COUNT(*) FROM inventario_correos WHERE estado='activo') AS activos,
    (SELECT COUNT(*) FROM inventario_correos WHERE estado='inactivo') AS inactivos
")->fetch(PDO::FETCH_ASSOC);

$sql = "SELECT * FROM inventario_correos ORDER BY correo ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$correos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$msg = $_GET['msg'] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Inventario de Correos</title>
    <link rel="stylesheet" href="../../../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@500;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="https://erp-fundocol.fundocol.org/assets/img/Fundocol_favicon.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="inventarios-correos-page">
    <div class="navbar-spacer"></div>

    <header class="correos-header">
        <div class="correos-title">Inventario de Correos</div>
        <div class="correos-subtitle">Control de cuentas, responsables y estado de acceso</div>
    </header>

    <div class="correos-actions">
        <button class="btn-correo primary" onclick="window.location.href='agregar.php'">
            <i class="bi bi-plus-circle"></i> Agregar correo
        </button>
        <button class="btn-correo" type="button" onclick="window.location.href='exportar_excel.php'">
            <i class="bi bi-file-earmark-excel"></i> Exportar Excel
        </button>
    </div>

    <?php if ($msg === 'ok'): ?>
        <div class="alert alert-success" style="margin: 0 7%;">Registro guardado correctamente.</div>
    <?php elseif ($msg === 'eliminado'): ?>
        <div class="alert alert-success" style="margin: 0 7%;">Cuenta eliminada.</div>
    <?php endif; ?>

    <section class="correos-stats">
        <div class="stat-card">
            <span class="stat-label">Total cuentas</span>
            <span class="stat-value"><?= (int)($stats['total'] ?? 0) ?></span>
        </div>
        <div class="stat-card">
            <span class="stat-label">Activas</span>
            <span class="stat-value"><?= (int)($stats['activos'] ?? 0) ?></span>
        </div>
        <div class="stat-card">
            <span class="stat-label">Inactivas</span>
            <span class="stat-value"><?= (int)($stats['inactivos'] ?? 0) ?></span>
        </div>
    </section>

    <section class="correos-board">
        <div class="correos-board-head">
            <div>Cuenta</div>
            <div>Plataforma</div>
            <div>Responsable</div>
            <div>Area</div>
            <div>Estado</div>
            <div>Acciones</div>
        </div>

        <?php foreach ($correos as $c): ?>
            <article class="correo-row">
                <div class="correo-main">
                    <div class="correo-email"><?= htmlspecialchars($c['correo']) ?></div>
                    <div class="correo-meta"><?= htmlspecialchars($c['usuario'] ?? 'Usuario no definido') ?></div>
                </div>
                <div class="correo-platform">
                    <div class="correo-platform-name"><?= htmlspecialchars($c['plataforma'] ?? 'Sin plataforma') ?></div>
                    <div class="correo-meta"><?= htmlspecialchars($c['proveedor'] ?? 'Proveedor no definido') ?></div>
                </div>
                <div class="correo-pill">
                    <i class="bi bi-person-badge"></i>
                    <span><?= htmlspecialchars($c['responsable'] ?? 'Sin responsable') ?></span>
                </div>
                <div class="correo-area">
                    <i class="bi bi-building"></i>
                    <span><?= htmlspecialchars($c['area'] ?? 'Sin area') ?></span>
                </div>
                <div>
                    <span class="estado-pill <?= $c['estado'] === 'activo' ? 'activo' : 'inactivo' ?>">
                        <?= $c['estado'] === 'activo' ? 'Activo' : 'Inactivo' ?>
                    </span>
                </div>
                <div class="correo-actions">
                    <button class="btn btn-sm btn-outline-secondary" onclick="window.location.href='editar.php?id=<?= (int)$c['id'] ?>'" title="Editar">
                        <i class="bi bi-pencil"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-danger" onclick="if(confirm('¿Eliminar cuenta?'))window.location.href='eliminar.php?id=<?= (int)$c['id'] ?>'" title="Eliminar">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            </article>
        <?php endforeach; ?>
    </section>
</body>
</html>
