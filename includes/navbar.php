<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/db.php';

/* -------------------------------------------
   CONTROL DE INACTIVIDAD (expira a los 3600s)
--------------------------------------------*/
$tiempo_maximo_inactividad = 7200; // 2 horas

if (isset($_SESSION['LAST_ACTIVITY'])) {
    if (time() - $_SESSION['LAST_ACTIVITY'] > $tiempo_maximo_inactividad) {
        session_unset();
        session_destroy();
        echo "<script>window.location.href='https://erp.fundocol.org/login.php?timeout=1';</script>";
        return;
    }
}
$_SESSION['LAST_ACTIVITY'] = time();

/* -------------------------------------------
   VALIDAR SESIÓN
--------------------------------------------*/
if (!isset($_SESSION['usuario_id'])) {
    echo "<script>window.location.href='https://erp.fundocol.org/login.php';</script>";
    return;
}

// Contar notificaciones no leídas (cache breve para reducir consultas)
$n_notificaciones = 0;
$notif_cache_ttl = 60; // segundos
if (!isset($_SESSION['notif_count_ts'], $_SESSION['notif_count']) || (time() - $_SESSION['notif_count_ts'] > $notif_cache_ttl)) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notificaciones WHERE usuario_id = ? AND leido = 0");
    $stmt->execute([$_SESSION['usuario_id']]);
    $n_notificaciones = (int)$stmt->fetchColumn();
    $_SESSION['notif_count'] = $n_notificaciones;
    $_SESSION['notif_count_ts'] = time();
} else {
    $n_notificaciones = (int)$_SESSION['notif_count'];
}

// Iniciales para el avatar
$nombre = $_SESSION['usuario_nombre'] ?? '';
$iniciales = '';
foreach (explode(' ', $nombre) as $parte) {
    if ($parte !== '') $iniciales .= mb_substr($parte, 0, 1, 'UTF-8');
}
$iniciales = strtoupper(mb_substr($iniciales, 0, 2, 'UTF-8'));
?>
<header class="navbar-top">
    <div class="navbar-left">
        <img src="https://erp.fundocol.org/assets/img/logo.svg" alt="Logo empresa" class="navbar-logo">
    </div>
    <div class="navbar-center"></div>
    <div class="navbar-right">
        <div class="navbar-item">
            <a href="#" class="notif-bell">
                <i class="bi bi-bell"></i>
                <?php if ($n_notificaciones > 0): ?>
                    <span class="notif-count"><?= $n_notificaciones ?></span>
                <?php endif; ?>
            </a>
        </div>
        <div class="navbar-item navbar-avatar-container">
            <div class="navbar-avatar" id="avatar-btn"><?= htmlspecialchars($iniciales) ?></div>
            <div class="navbar-dropdown" id="avatar-dropdown">
                <span class="navbar-user-name"><?= htmlspecialchars($nombre) ?></span>
                <a href="https://erp.fundocol.org/index.php" class="navbar-dropdown-link"><i class="bi bi-grid"></i> Volver al menú</a>
                <a href="https://erp.fundocol.org/modules/usuarios/perfil.php" class="navbar-dropdown-link"><i class="bi bi-person-circle"></i> Perfil</a>
                <a href="https://erp.fundocol.org/logout.php" class="navbar-dropdown-link"><i class="bi bi-box-arrow-right"></i> Cerrar sesión</a>
            </div>
        </div>
    </div>
</header>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const avatarBtn = document.getElementById('avatar-btn');
    const dropdown = document.getElementById('avatar-dropdown');
    avatarBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        dropdown.classList.toggle('show');
    });
    document.addEventListener('click', function() {
        dropdown.classList.remove('show');
    });
});
</script>



