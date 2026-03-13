<?php
session_start();
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol'] != 1) {
    header("Location: ../../index.php");
    exit();
}

require_once "../../config/db.php";
require_once "../../includes/phpmailer.php";

$cantidad = intval($_POST['cantidad'] ?? 0);

// Correo destinatario
$correo_destino = "analista.contable@fundocol.org";
$nombre_destino = "Analista Contable Fundocol";

// Armado del mensaje
$asunto = "Recordatorio: Tienes $cantidad solicitudes de compras fijas por aprobar";

$mensaje_html = "
    <h2>Recordatorio de solicitudes pendientes</h2>
    <p>Tienes <b>$cantidad compras fijas</b> pendientes por aprobar en el modulo de <b>Contabilidad</b>.</p>
    <br>
    <a href='https://erp.fundocol.org/modules/direccion/index.php'
       style='display:inline-block;background:#2176ff;color:#fff;
              padding:12px 24px;border-radius:10px;font-weight:700;text-decoration:none;'>
        Ingresar al ERP
    </a>
    <br><br>
    <small>Mensaje automatico del sistema ERP Fundocol.</small>
";

// Enviar
$enviado = enviarCorreoFundocol($correo_destino, $nombre_destino, $asunto, $mensaje_html);

if ($enviado) {
    echo "<script>alert('Correo enviado correctamente.'); window.history.back();</script>";
} else {
    echo "<script>alert('Error al enviar el correo.'); window.history.back();</script>";
}
