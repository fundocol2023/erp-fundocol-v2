<?php
session_start();
require_once '../../config/db.php';
require_once '../../includes/mailer.php'; // o mailer.php si ya migraste a Graph

if (!isset($_SESSION['usuario_id'])) { 
    header('Location: ../../login.php'); 
    exit(); 
}

if ($_SESSION['usuario_rol'] != 6) { 
    echo "Sin permisos."; 
    exit(); 
}

$id_solicitud = intval($_POST['id_solicitud'] ?? 0);
if (!$id_solicitud) { 
    echo "ID invalido"; 
    exit(); 
}

// Comentario opcional de Pagos
$comentario_pago = trim($_POST['comentario_pagos'] ?? '');

// Traer informacion de la solicitud + usuario solicitante
$sql = "SELECT sc.*, u.email AS email_solicitante, u.nombre AS nombre_solicitante
        FROM solicitudes_compra sc
        JOIN usuarios u ON sc.solicitante_id = u.id
        WHERE sc.id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$id_solicitud]);
$solicitud = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$solicitud) { 
    echo "Solicitud no encontrada"; 
    exit(); 
}

// Traer productos de la solicitud
$sql = "SELECT nombre, cantidad, descripcion, precio_unitario, precio_total 
        FROM solicitudes_compra_productos 
        WHERE solicitud_compra_id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$id_solicitud]);
$productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Construir tabla HTML para el correo
$tabla = '<table style="width:100%;border-collapse:collapse;font-size:1rem;margin-top:8px;">
    <thead>
        <tr style="background:#eaf3ff;color:#183357;">
            <th style="padding:8px 5px;border:1px solid #dde7f6;">Nombre</th>
            <th style="padding:8px 5px;border:1px solid #dde7f6;">Cantidad</th>
            <th style="padding:8px 5px;border:1px solid #dde7f6;">Descripcion</th>
            <th style="padding:8px 5px;border:1px solid #dde7f6;">Precio unitario</th>
            <th style="padding:8px 5px;border:1px solid #dde7f6;">Precio total</th>
        </tr>
    </thead>
    <tbody>';

foreach ($productos as $p) {
    $tabla .= '
        <tr>
            <td style="padding:8px;border:1px solid #dde7f6;">'.htmlspecialchars($p['nombre']).'</td>
            <td style="padding:8px;text-align:center;border:1px solid #dde7f6;">'.intval($p['cantidad']).'</td>
            <td style="padding:8px;border:1px solid #dde7f6;">'.htmlspecialchars($p['descripcion']).'</td>
            <td style="padding:8px;text-align:right;border:1px solid #dde7f6;">$'.number_format($p['precio_unitario'],0,',','.').'</td>
            <td style="padding:8px;text-align:right;border:1px solid #dde7f6;">$'.number_format($p['precio_total'],0,',','.').'</td>
        </tr>';
}
$tabla .= '</tbody></table>';

// Validar adjunto
if (!isset($_FILES['soporte_pago']) || $_FILES['soporte_pago']['error'] !== UPLOAD_ERR_OK) {
    echo "Debe adjuntar soporte de pago.";
    exit();
}

// Guardar soporte de pago
$dir = "../../uploads/soportes_pago/";
if (!is_dir($dir)) { mkdir($dir, 0777, true); }

$ext = strtolower(pathinfo($_FILES['soporte_pago']['name'], PATHINFO_EXTENSION));
$permitidas = ['pdf', 'jpg', 'jpeg', 'png'];

if (!in_array($ext, $permitidas)) {
    echo "Formato de soporte no permitido. Solo PDF/JPG/PNG.";
    exit();
}

$filename = "soporte_" . $id_solicitud . "_" . time() . "." . $ext;
$path = $dir . $filename;

if (!move_uploaded_file($_FILES['soporte_pago']['tmp_name'], $path)) {
    echo "No se pudo guardar el soporte de pago.";
    exit();
}

// Actualizar DB
$sql = "UPDATE solicitudes_compra 
        SET estado='pago_confirmado', 
            soporte_pago=?, 
            comentario_pagos=?
        WHERE id=?";
$pdo->prepare($sql)->execute([$filename, $comentario_pago, $id_solicitud]);

// ====================================
// CORREO AL SOLICITANTE
// ====================================
$asunto = "Pago confirmado - ERP Fundocol";

$body = '
<div style="font-family:Segoe UI,Arial,sans-serif;background:#f5f7fb;padding:26px;">
  <div style="max-width:620px;margin:0 auto;background:#ffffff;border-radius:18px;
              box-shadow:0 6px 26px #15a15622;padding:33px 32px 28px 32px;">

    <div style="font-size:1.32rem;font-weight:700;color:#15a156;margin-bottom:15px;">
      Pago de solicitud de compra confirmado
    </div>

    <div style="font-size:1.06rem;color:#24374e;margin-bottom:16px;line-height:1.55;">
      Hola <b>'.htmlspecialchars($solicitud['nombre_solicitante']).'</b>,<br><br>
      El area de pagos confirma que tu solicitud ha sido pagada exitosamente. 
      Este es el resumen general de tu solicitud.
    </div>

    <div style="background:#f6fafd;border-radius:12px;padding:14px 16px;margin-bottom:18px;
                border:1px solid #dde7f6;">
      <div style="font-size:0.96rem;color:#506484;font-weight:600;margin-bottom:8px;">
        Informacion general
      </div>

      <div style="font-size:0.98rem;color:#24374e;line-height:1.45;">
        <div><b>ID solicitud:</b> '.$id_solicitud.'</div>
        <div><b>Proyecto u oficina:</b> '.htmlspecialchars($solicitud['proyecto_oficina']).'</div>
        <div><b>Necesidad:</b> '.htmlspecialchars($solicitud['necesidad']).'</div>
        <div><b>Estado:</b> Pago confirmado</div>
      </div>
    </div>
';

if ($comentario_pago !== "") {
    $body .= '
    <div style="background:#fff7e6;border-radius:12px;padding:14px 16px;margin-bottom:18px;
                border:1px solid #ffe1a7;">
      <div style="font-size:0.96rem;color:#996300;font-weight:600;margin-bottom:8px;">
        Comentario del area de pagos
      </div>
      <div style="font-size:0.98rem;color:#7a5700;line-height:1.45;">
        '.nl2br(htmlspecialchars($comentario_pago)).'
      </div>
    </div>';
}

$body .= '
    <div style="font-size:1.02rem;color:#24374e;font-weight:600;margin-bottom:8px;margin-top:18px;">
      Productos incluidos
    </div>

    '.$tabla.'

    <div style="margin-top:24px;font-size:0.93rem;color:#93a3ba;">
      Se adjunta el soporte de pago correspondiente.<br>
      Este mensaje es automatico. No responder.
    </div>

  </div>
</div>
';

enviarCorreoFundocol(
    $solicitud['email_solicitante'],
    $solicitud['nombre_solicitante'],
    $asunto,
    $body,
    [$path]
);

// ====================================
// ✅ CORREO A PRESUPUESTO (NUEVO)
// ====================================
$correo_presupuesto = "presupuesto@fundocol.org";
$nombre_presupuesto = "Presupuesto Fundocol";
$asunto_presupuesto = "Pago confirmado - Solicitud de compra #{$id_solicitud}";

$body_presupuesto = '
<div style="font-family:Segoe UI,Arial,sans-serif;background:#f5f7fb;padding:26px;">
  <div style="max-width:620px;margin:0 auto;background:#ffffff;border-radius:18px;
              box-shadow:0 6px 26px #2176ff22;padding:33px 32px 28px 32px;">

    <div style="font-size:1.28rem;font-weight:700;color:#2176ff;margin-bottom:15px;">
      Notificacion a Presupuesto: Pago confirmado
    </div>

    <div style="font-size:1.06rem;color:#24374e;margin-bottom:16px;line-height:1.55;">
      Se informa que el area de Pagos confirmo el pago de la siguiente solicitud.
      Se adjunta el soporte de pago.
    </div>

    <div style="background:#f6fafd;border-radius:12px;padding:14px 16px;margin-bottom:18px;
                border:1px solid #dde7f6;">
      <div style="font-size:0.96rem;color:#506484;font-weight:600;margin-bottom:8px;">
        Informacion general
      </div>

      <div style="font-size:0.98rem;color:#24374e;line-height:1.45;">
        <div><b>ID solicitud:</b> '.$id_solicitud.'</div>
        <div><b>Solicitante:</b> '.htmlspecialchars($solicitud['nombre_solicitante']).'</div>
        <div><b>Proyecto u oficina:</b> '.htmlspecialchars($solicitud['proyecto_oficina']).'</div>
        <div><b>Necesidad:</b> '.htmlspecialchars($solicitud['necesidad']).'</div>
        <div><b>Estado:</b> Pago confirmado</div>
      </div>
    </div>
';

if ($comentario_pago !== "") {
    $body_presupuesto .= '
    <div style="background:#fff7e6;border-radius:12px;padding:14px 16px;margin-bottom:18px;
                border:1px solid #ffe1a7;">
      <div style="font-size:0.96rem;color:#996300;font-weight:600;margin-bottom:8px;">
        Comentario del area de pagos
      </div>
      <div style="font-size:0.98rem;color:#7a5700;line-height:1.45;">
        '.nl2br(htmlspecialchars($comentario_pago)).'
      </div>
    </div>';
}

$body_presupuesto .= '
    <div style="font-size:1.02rem;color:#24374e;font-weight:600;margin-bottom:8px;margin-top:18px;">
      Productos incluidos
    </div>

    '.$tabla.'

    <div style="margin-top:24px;font-size:0.93rem;color:#93a3ba;">
      Se adjunta el soporte de pago.<br>
      Mensaje automatico. No responder.
    </div>

  </div>
</div>
';

enviarCorreoFundocol(
    $correo_presupuesto,
    $nombre_presupuesto,
    $asunto_presupuesto,
    $body_presupuesto,
    [$path]
);

// Redireccion
header("Location: ../pendientes/index.php?msg=pago_confirmado");
exit();
?>

