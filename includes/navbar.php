<?php
require_once __DIR__ . '/bootstrap.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

erp_send_private_page_headers();
require_once __DIR__ . '/../config/db.php';

/* -------------------------------------------
   CONTROL DE INACTIVIDAD (expira a los 3600s)
--------------------------------------------*/
$tiempo_maximo_inactividad = 7200; // 2 horas

if (isset($_SESSION['LAST_ACTIVITY'])) {
    if (time() - $_SESSION['LAST_ACTIVITY'] > $tiempo_maximo_inactividad) {
        session_unset();
        session_destroy();
        erp_redirect('login.php?timeout=1');
    }
}
$_SESSION['LAST_ACTIVITY'] = time();

/* -------------------------------------------
   VALIDAR SESIÓN
--------------------------------------------*/
if (!isset($_SESSION['usuario_id'])) {
    erp_redirect('login.php');
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

ob_start();
?>
<header class="navbar-top">
    <div class="navbar-left">
        <img src="<?= htmlspecialchars(erp_asset_url('assets/img/logo.svg')) ?>" alt="Logo de Fundocol" class="navbar-logo" width="44" height="44">
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
            <button type="button" class="navbar-avatar" id="avatar-btn" aria-expanded="false" aria-controls="avatar-dropdown"><?= htmlspecialchars($iniciales) ?></button>
            <div class="navbar-dropdown" id="avatar-dropdown">
                <span class="navbar-user-name"><?= htmlspecialchars($nombre) ?></span>
                <a href="<?= htmlspecialchars(erp_app_url('index.php')) ?>" class="navbar-dropdown-link"><i class="bi bi-grid"></i> Volver al menú</a>
                <a href="<?= htmlspecialchars(erp_app_url('modules/usuarios/perfil.php')) ?>" class="navbar-dropdown-link"><i class="bi bi-person-circle"></i> Perfil</a>
                <a href="<?= htmlspecialchars(erp_app_url('logout.php')) ?>" class="navbar-dropdown-link"><i class="bi bi-box-arrow-right"></i> Cerrar sesión</a>
            </div>
        </div>
    </div>
</header>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const avatarBtn = document.getElementById('avatar-btn');
    const dropdown = document.getElementById('avatar-dropdown');
    if (!avatarBtn || !dropdown) {
        return;
    }

    avatarBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        dropdown.classList.toggle('show');
        avatarBtn.setAttribute('aria-expanded', dropdown.classList.contains('show') ? 'true' : 'false');
    });
    document.addEventListener('click', function() {
        dropdown.classList.remove('show');
        avatarBtn.setAttribute('aria-expanded', 'false');
    });
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            dropdown.classList.remove('show');
            avatarBtn.setAttribute('aria-expanded', 'false');
        }
    });
});
</script>
<?php
erp_register_body_fragment('navbar', trim((string) ob_get_clean()));
