<?php
session_start();
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol'] != 1) { 
    header('Location: ../../index.php');
    exit();
}

require_once "../../config/db.php"; // Necesario para contar solicitudes

$seccion = $_GET['seccion'] ?? 'usuarios';
$secciones = [
    'usuarios' => 'Usuarios',
    'roles' => 'Roles',
    'permisos' => 'Permisos',
    'modulos' => 'Módulos'
];

/* =====================================================
   CONTAR COMPRAS FIJAS PENDIENTES (aprobado_direccion)
   ===================================================== */
$sqlCount = "SELECT COUNT(*) AS total FROM compras_fijas WHERE estado = 'aprobado_direccion'";
$stmtCount = $pdo->query($sqlCount);
$resultCount = $stmtCount->fetch(PDO::FETCH_ASSOC);
$pendientes_conta = $resultCount['total']; // este numero se enviara por correo
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Módulo de Usuarios | ERP</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="icon" type="image/x-icon" href="https://erp-fundocol.fundocol.org/assets/img/Fundocol_favicon.png">
</head>
<body class="usuarios-admin-page">

<?php include '../../includes/navbar.php'; ?>

<main class="main-dashboard">
    <div class="odoo-menu usuarios-admin-menu">

        <div class="odoo-menu-title usuarios-admin-title">
            <h1>Gestión de Usuarios, Roles y Permisos</h1>

            <!-- BOTÓN DE NOTIFICAR CONTABILIDAD -->
            <div class="usuarios-notify-wrap">
                <form action="enviar_recordatorio_conta.php" method="POST" class="usuarios-notify-form">
                    <input type="hidden" name="cantidad" value="<?= $pendientes_conta ?>">
                    <button type="submit" class="usuarios-notify-btn">
                        📬 Notificar Contabilidad (<?= $pendientes_conta ?> pendientes a aprobar)
                    </button>
                </form>
            </div>
        </div>

        <!-- SUBMENÚ -->
        <div class="odoo-submenu">
            <?php foreach ($secciones as $key => $label): ?>
                <a href="?seccion=<?= $key ?>" class="odoo-submenu-link<?= $seccion === $key ? ' active' : '' ?>">
                    <?= $label ?>
                </a>
            <?php endforeach; ?>
        </div>

        <!-- CONTENIDO DEL MÓDULO -->
        <div class="odoo-module-content">
            <?php
                if ($seccion === 'usuarios')       include 'usuarios.php';
                elseif ($seccion === 'roles')      include 'roles.php';
                elseif ($seccion === 'permisos')   include 'permisos.php';
                elseif ($seccion === 'modulos')    include 'modulos.php';
            ?>
        </div>

    </div>
</main>

</body>
</html>
