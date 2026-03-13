<?php
session_start();
require_once '../../config/db.php';

// ✅ CAMBIO: usar Graph mailer (como las otras)
require_once __DIR__ . '/../../includes/mailer.php';

if (!isset($_SESSION['usuario_id'])) { 
    header('Location: ../../login.php'); 
    exit(); 
}

if ($_SESSION['usuario_rol'] != 6) { 
    echo "Sin permisos."; 
    exit(); 
}

$id_solicitud = intval($_POST['id_solicitud'] ?? 0);
if ($id_solicitud <= 0) { 
    echo "ID invalido"; 
    exit(); 
}

$comentario_pago = trim($_POST['comentario_pagos'] ?? '');
$rechazar = isset($_POST['rechazar_pago']); // viene del boton rojo

// ==========================================
// TRAER DATOS DE CONSORCIOS
// ==========================================
$sql = "SELECT sc.*, u.email AS email_solicitante, u.nombre AS nombre_solicitante
        FROM solicitudes_compra_consorcios sc
        JOIN usuarios u ON sc.solicitante_id = u.id
        WHERE sc.id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$id_solicitud]);
$solicitud = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$solicitud) { 
    echo "Solicitud no encontrada"; 
    exit(); 
}

// ==========================================
// TRAER PRODUCTOS (para tabla en correos)
// ==========================================
$sql = "SELECT nombre, cantidad, descripcion, precio_unitario, precio_total
        FROM solicitudes_compra_consorcios_productos
        WHERE solicitud_compra_id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$id_solicitud]);
$productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ==========================================
// TABLA HTML COMUN PARA LOS CORREOS
// ==========================================
$tabla = '<table style="width:100%;border-collapse:collapse;font-size:1rem;margin-top:12px;">
<thead>
<tr style="background:#eaf3ff;color:#183357;">
<th style="padding:8px;border:1px solid #dde7f6;">Nombre</th>
<th style="padding:8px;border:1px solid #dde7f6;">Cantidad</th>
<th style="padding:8px;border:1px solid #dde7f6;">Descripcion</th>
<th style="padding:8px;border:1px solid #dde7f6;">Precio unitario</th>
<th style="padding:8px;border:1px solid #dde7f6;">Precio total</th>
</tr>
</thead><tbody>';

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


// ==================================================================
// 1) SI VIENE DESDE EL BOTON ROJO: RECHAZAR SOLICITUD
// ==================================================================
if ($rechazar) {

    // ------------------------------------------
    // Actualizar estado a RECHAZADO (pagos)
    // ------------------------------------------
    $sql = "UPDATE solicitudes_compra_consorcios
            SET estado = 'rechazado',
                comentario_pagos = ?
            WHERE id = ?";
    $pdo->prepare($sql)->execute([$comentario_pago, $id_solicitud]);

    // Tracking
    $comentario_tracking = $comentario_pago !== '' 
        ? 'Solicitud rechazada por Pagos: ' . $comentario_pago
        : 'Solicitud rechazada por Pagos.';

    $pdo->prepare("INSERT INTO solicitudes_tracking 
                   (solicitud_compra_id, estado, usuario_id, fecha_hora, comentario)
                   VALUES (?, 'rechazado', ?, NOW(), ?)")
        ->execute([$id_solicitud, $_SESSION['usuario_id'], $comentario_tracking]);

    // ------------------------------------------
    // Correo al solicitante
    // ------------------------------------------
    $asunto = "Solicitud de compra CONSORCIOS rechazada";

    $body = '
    <div style="font-family:Segoe UI,Arial,sans-serif;background:#f6fafd;padding:26px;">
      <div style="max-width:650px;margin:0 auto;background:#ffffff;
                  border-radius:18px;box-shadow:0 8px 32px #ff000022;
                  padding:33px 32px 28px 32px;">

        <div style="font-size:1.35rem;font-weight:700;color:#d62828;margin-bottom:18px;">
          Solicitud de compra rechazada - CONSORCIOS
        </div>

        <div style="font-size:1.05rem;color:#24374e;margin-bottom:18px;line-height:1.55;">
          Hola <b>'.htmlspecialchars($solicitud['nombre_solicitante']).'</b>,<br>
          Te informamos que el area de Pagos ha <b>rechazado</b> tu solicitud de compra.
        </div>

        <div style="background:#f6fafd;border-radius:12px;padding:15px 18px;
                    margin-bottom:20px;border:1px solid #dde7f6;">
          <div style="font-size:0.96rem;font-weight:600;color:#506484;margin-bottom:8px;">
            Informacion de la solicitud
          </div>

          <div style="font-size:0.98rem;color:#24374e;line-height:1.45;">
            <div><b>ID de solicitud:</b> '.$id_solicitud.'</div>
            <div><b>Consorcio:</b> '.htmlspecialchars($solicitud['consorcio']).'</div>
            <div><b>Necesidad:</b> '.htmlspecialchars($solicitud['necesidad']).'</div>
            <div><b>Estado:</b> Rechazado por Pagos</div>
          </div>
        </div>';

    if ($comentario_pago !== '') {
        $body .= '
        <div style="background:#fff7f7;border-radius:12px;padding:15px 18px;
                    margin-bottom:20px;border:1px solid #f5b5b5;">
          <div style="font-size:0.96rem;font-weight:600;color:#b91c1c;margin-bottom:8px;">
            Motivo o comentario del rechazo
          </div>
          <div style="font-size:0.98rem;color:#7f1d1d;line-height:1.45;">
            '.nl2br(htmlspecialchars($comentario_pago)).'
          </div>
        </div>';
    }

    $body .= '
        <div style="font-size:1.05rem;color:#22314b;font-weight:600;margin-bottom:8px;">
          Productos de la solicitud
        </div>

        '.$tabla.'

        <p style="margin-top:25px;font-size:0.92rem;color:#93a3ba;">
          Este mensaje es automatico, no respondas este correo.
        </p>

      </div>
    </div>
    ';

    enviarCorreoFundocol(
        $solicitud['email_solicitante'],
        $solicitud['nombre_solicitante'],
        $asunto,
        $body
    );

    header("Location: ../pendientes/index.php?msg=rechazado_pagos");
    exit();
}


// ==================================================================
// 2) SI NO ES RECHAZAR → CONFIRMAR PAGO (FLUJO ORIGINAL)
// ==================================================================

// ==========================================
// VALIDAR ARCHIVO
// ==========================================
if (!isset($_FILES['soporte_pago']) || $_FILES['soporte_pago']['error'] !== UPLOAD_ERR_OK) {
    echo "Debe adjuntar soporte de pago.";
    exit();
}

// ==========================================
// GUARDAR ARCHIVO
// ==========================================
$dir = "../../uploads/soportes_pago/";
if (!is_dir($dir)) mkdir($dir, 0777, true);

$ext = pathinfo($_FILES['soporte_pago']['name'], PATHINFO_EXTENSION);
$filename = "soporte_" . $id_solicitud . "_" . time() . "." . $ext;
$path = $dir . $filename;

move_uploaded_file($_FILES['soporte_pago']['tmp_name'], $path);

// ==========================================
// GUARDAR EN BD
// ==========================================
$sql = "UPDATE solicitudes_compra_consorcios
        SET estado='pago_confirmado',
            soporte_pago=?,
            comentario_pagos=?,
            fecha_aprob_pagos = NOW()
        WHERE id=?";
$pdo->prepare($sql)->execute([$filename, $comentario_pago, $id_solicitud]);

// TRACKING
$pdo->prepare("INSERT INTO solicitudes_tracking 
               (solicitud_compra_id, estado, usuario_id, fecha_hora, comentario)
               VALUES (?, 'pago_confirmado', ?, NOW(), 'Pago confirmado por Pagos')")
    ->execute([$id_solicitud, $_SESSION['usuario_id']]);

// ==========================================
// CORREO FINAL AL SOLICITANTE (PAGO CONFIRMADO)
// ==========================================
$asunto = "Pago Confirmado - Solicitud Compra CONSORCIOS";

$body = '
<div style="font-family:Segoe UI,Arial,sans-serif;background:#f6fafd;padding:26px;">
  <div style="max-width:650px;margin:0 auto;background:#ffffff;
              border-radius:18px;box-shadow:0 8px 32px #2176ff22;
              padding:33px 32px 28px 32px;">

    <div style="font-size:1.35rem;font-weight:700;color:#15a156;margin-bottom:18px;">
      Pago confirmado - Solicitud de compra CONSORCIOS
    </div>

    <div style="font-size:1.05rem;color:#24374e;margin-bottom:18px;line-height:1.55;">
      Hola <b>'.htmlspecialchars($solicitud['nombre_solicitante']).'</b>,<br>
      Te informamos que el area de Pagos ha confirmado el pago de tu solicitud de compra.
      A continuacion veras el resumen completo:
    </div>

    <div style="background:#f6fafd;border-radius:12px;padding:15px 18px;
                margin-bottom:20px;border:1px solid #dde7f6;">
      <div style="font-size:0.96rem;font-weight:600;color:#506484;margin-bottom:8px;">
        Informacion de la solicitud
      </div>

      <div style="font-size:0.98rem;color:#24374e;line-height:1.45;">
        <div><b>ID de solicitud:</b> '.$id_solicitud.'</div>
        <div><b>Consorcio:</b> '.htmlspecialchars($solicitud['consorcio']).'</div>
        <div><b>Necesidad:</b> '.htmlspecialchars($solicitud['necesidad']).'</div>
        <div><b>Estado:</b> Pago confirmado</div>
      </div>
    </div>';

if ($comentario_pago != "") {
    $body .= '
    <div style="background:#fff7e6;border-radius:12px;padding:15px 18px;
                margin-bottom:20px;border:1px solid #ffe1a7;">
      <div style="font-size:0.96rem;font-weight:600;color:#996300;margin-bottom:8px;">
        Comentario del area de Pagos
      </div>
      <div style="font-size:0.98rem;color:#7a5700;line-height:1.45;">
        '.nl2br(htmlspecialchars($comentario_pago)).'
      </div>
    </div>';
}

$body .= '
    <div style="font-size:1.05rem;color:#22314b;font-weight:600;margin-bottom:8px;">
      Productos de la solicitud
    </div>

    '.$tabla.'

    <p style="margin-top:25px;font-size:0.92rem;color:#93a3ba;">
      Se adjunta el soporte de pago para tu consulta.<br>
      Este mensaje es automatico, no respondas este correo.
    </p>

  </div>
</div>
';

// ✅ Adjuntar soporte como array
enviarCorreoFundocol(
    $solicitud['email_solicitante'],
    $solicitud['nombre_solicitante'],
    $asunto,
    $body,
    [$path]
);

// ==========================================
// ✅ NUEVO: CORREO A PRESUPUESTO (con adjunto)
// ==========================================
$asunto_pres = "Pago confirmado CONSORCIOS - Solicitud #".$id_solicitud;

$body_pres = '
<div style="font-family:Segoe UI,Arial,sans-serif;background:#f5f7fb;padding:26px;">
  <div style="max-width:650px;margin:0 auto;background:#ffffff;
              border-radius:18px;box-shadow:0 8px 32px #2176ff18;
              padding:33px 32px 28px 32px;">

    <div style="font-size:1.25rem;font-weight:700;color:#2176ff;margin-bottom:14px;">
      Pago confirmado - CONSORCIOS
    </div>

    <div style="font-size:1.03rem;color:#24374e;margin-bottom:16px;line-height:1.55;">
      Hola <b>Presupuesto</b>,<br>
      El area de Pagos confirmo el pago de la siguiente solicitud de compra (Consorcios).
    </div>

    <div style="background:#f6fafd;border-radius:12px;padding:15px 18px;
                margin-bottom:18px;border:1px solid #dde7f6;">
      <div style="font-size:0.98rem;color:#24374e;line-height:1.5;">
        <div><b>ID de solicitud:</b> '.$id_solicitud.'</div>
        <div><b>Solicitante:</b> '.htmlspecialchars($solicitud['nombre_solicitante']).'</div>
        <div><b>Consorcio:</b> '.htmlspecialchars($solicitud['consorcio']).'</div>
        <div><b>Necesidad:</b> '.htmlspecialchars($solicitud['necesidad']).'</div>
        <div><b>Estado:</b> Pago confirmado</div>
      </div>
    </div>

    '.($comentario_pago !== '' ? '
    <div style="background:#fff7e6;border-radius:12px;padding:15px 18px;margin-bottom:18px;border:1px solid #ffe1a7;">
      <div style="font-size:0.96rem;font-weight:600;color:#996300;margin-bottom:8px;">
        Comentario del area de Pagos
      </div>
      <div style="font-size:0.98rem;color:#7a5700;line-height:1.45;">
        '.nl2br(htmlspecialchars($comentario_pago)).'
      </div>
    </div>' : '').'

    <div style="font-size:1.05rem;color:#22314b;font-weight:600;margin-bottom:8px;">
      Productos
    </div>

    '.$tabla.'

    <div style="text-align:center;margin:22px 0 5px 0;">
      <a href="https://erp.fundocol.org/modules/pendientes/index.php"
         style="display:inline-block;background:#2176ff;color:#fff;
         padding:12px 30px;border-radius:10px;text-decoration:none;font-weight:bold;">
         Ver en ERP
      </a>
    </div>

    <p style="margin-top:18px;font-size:0.92rem;color:#93a3ba;">
      Se adjunta soporte de pago. Mensaje automatico del ERP Fundocol.
    </p>

  </div>
</div>
';

enviarCorreoFundocol(
    'presupuesto@fundocol.org',
    'Presupuesto Fundocol',
    $asunto_pres,
    $body_pres,
    [$path]
);

header("Location: ../pendientes/index.php?msg=pago_confirmado");
exit();
?>

