<?php
require_once __DIR__ . '/includes/bootstrap.php';

session_start();
erp_send_private_page_headers();

if (isset($_SESSION['usuario_id'])) {
    erp_redirect('index.php');
}

$_SESSION['LAST_ACTIVITY'] = time();

require_once 'config/db.php';

$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = mb_strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = :email AND activo = 1");
    $stmt->execute(['email' => $email]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($usuario && password_verify($password, $usuario['password'])) {
        session_regenerate_id(true);
        $_SESSION['usuario_id'] = $usuario['id'];
        $_SESSION['usuario_nombre'] = $usuario['nombre'];
        $_SESSION['usuario_rol'] = $usuario['rol_id'];
        unset($_SESSION['notif_count'], $_SESSION['notif_count_ts']);
        erp_redirect('index.php');
    } else {
        $error = "Correo o contraseña incorrectos";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Acceso privado al ERP de Fundocol para la gestión interna de operaciones, compras, inventarios y solicitudes.">
    <meta name="robots" content="noindex, nofollow, noarchive">
    <title>Iniciar sesión | ERP Fundocol</title>
    <link rel="stylesheet" href="<?= htmlspecialchars(erp_asset_url('assets/css/style.css')) ?>">
    <link rel="icon" type="image/x-icon" href="<?= htmlspecialchars(erp_asset_url('assets/img/Fundocol_favicon.png')) ?>">
</head>
<body class="login-bg">
    <main class="login-container">
        <section class="login-card" aria-labelledby="login-title">
            <img src="<?= htmlspecialchars(erp_asset_url('assets/img/logo.png')) ?>" alt="Logo de Fundocol" class="logo-login" width="115" height="115" fetchpriority="high">
            <?php if (isset($_GET['timeout'])): ?>
                <div class="login-message" role="status">La sesión expiró por inactividad. Ingresa de nuevo para continuar.</div>
            <?php endif; ?>
            <h2 id="login-title">Iniciar sesión</h2>
            <?php if ($error): ?>
                <div class="login-error" role="alert"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <form method="POST">
                <input type="email" name="email" placeholder="Correo electrónico" autocomplete="username" inputmode="email" required>
                <input type="password" name="password" placeholder="Contraseña" autocomplete="current-password" required>
                <button type="submit">Ingresar</button>
            </form>
        </section>
    </main>
</body>
</html>
