<?php
require_once __DIR__ . '/bootstrap.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

erp_send_private_page_headers();

if (!isset($_SESSION['usuario_id'])) {
    erp_redirect('login.php');
}
?>
