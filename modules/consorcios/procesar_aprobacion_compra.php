<?php
session_start();
require_once '../../config/db.php';

// ✅ Graph mailer
require_once __DIR__ . '/../../includes/mailer.php';

if (!isset($_SESSION['usuario_id'])) { 
    header('Location: ../../login.php'); 
    exit(); 
}

$rol = $_SESSION['usuario_rol'];
$id_usuario = $_SESSION['usuario_id'];

$id_solicitud = intval($_POST['id_solicitud'] ?? 0);
if ($id_solicitud <= 0) { 
    echo "ID invalido"; 
    exit(); 
}

// ===============================
// TRAER DATOS DE LA SOLICITUD
// ===============================
$sql = "SELECT sc.*, u.email AS correo_solicitante, u.nombre AS nombre_solicitante
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

// ✅ Función para correo destino de presupuesto según consorcio
function obtenerCorreoPresupuestoConsorcio($consorcio) {
    $consorcio_normalizado = strtoupper(trim($consorcio ?? ''));

    if ($consorcio_normalizado === 'CONSORCIO ABURRA 2025') {
        return [
            'correo' => 'ingeniero.civil@fundocol.org',
            'nombre' => 'Ingeniero Civil Fundocol'
        ];
    }

    return [
        'correo' => 'presupuesto@fundocol.org',
        'nombre' => 'Presupuesto Fundocol'
    ];
}

$destinoPresupuesto = obtenerCorreoPresupuestoConsorcio($solicitud['consorcio'] ?? '');

// ===============================
// TRAER PRODUCTOS
// ===============================
$sql = "SELECT nombre, cantidad, descripcion, precio_unitario, precio_total
        FROM solicitudes_compra_consorcios_productos 
        WHERE solicitud_compra_id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$id_solicitud]);
$productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ===============================
// TABLA HTML
// ===============================
$tabla = '<table style="width:100%;border-collapse:collapse;font-size:1rem;margin-top:8px;">
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


// =============================================================
// ====================     RECHAZAR      =======================
// =============================================================
if (isset($_POST['rechazar'])) {

    $comentario = trim($_POST['comentario'] ?? '');
    if ($comentario == "") { 
        echo "Debe escribir comentario"; 
        exit(); 
    }

    $pdo->prepare("UPDATE solicitudes_compra_consorcios 
                   SET estado='rechazado', comentario_rechazo=? 
                   WHERE id=?")
        ->execute([$comentario, $id_solicitud]);

    // Tracking
    $pdo->prepare("INSERT INTO solicitudes_tracking 
        (solicitud_compra_id, estado, usuario_id, fecha_hora, comentario)
        VALUES (?, 'rechazado', ?, NOW(), ?)")
        ->execute([$id_solicitud, $id_usuario, $comentario]);

    // Correo al solicitante
    $body = '
    <div style="font-family:Segoe UI,Arial,sans-serif;background:#f8fafd;padding:26px;">
    <div style="max-width:560px;margin:0 auto;background:#fff;border-radius:18px;
    box-shadow:0 8px 32px #e3262616;padding:33px;">
    
    <h2 style="color:#d82626;margin-bottom:10px;">Solicitud de compra CONSORCIOS rechazada</h2>

    <p style="color:#24374e;font-size:1.05rem;">Hola <b>'.htmlspecialchars($solicitud['nombre_solicitante']).'</b>,<br>
    Tu solicitud fue rechazada por el siguiente motivo:</p>

    <div style="background:#ffeeee;padding:14px;border-left:4px solid #d82626;">
        '.htmlspecialchars($comentario).'
    </div>

    <h3 style="color:#22314b;margin-top:25px;font-size:1.1rem;">Resumen de la solicitud</h3>

    <p><b>ID:</b> '.$id_solicitud.'<br>
       <b>Consorcio:</b> '.htmlspecialchars($solicitud['consorcio']).'<br>
       <b>Necesidad:</b> '.htmlspecialchars($solicitud['necesidad']).'</p>

    <h3 style="color:#22314b;margin-top:25px;font-size:1.1rem;">Productos</h3>
    '.$tabla.'

    <p style="margin-top:25px;font-size:0.9rem;color:#98a2b3;">
        No responda este correo. Notificacion automatica del ERP Fundocol.
    </p>

    </div></div>';

    enviarCorreoFundocol(
        $solicitud['correo_solicitante'],
        $solicitud['nombre_solicitante'],
        'Solicitud de compra CONSORCIOS rechazada',
        $body
    );

    header("Location: ../pendientes/index.php?msg=rechazada");
    exit();
}



// =============================================================
// ========== APROBAR PRESUPUESTO (ROL 4 Y ROL 13) =============
// =============================================================
if (($rol == 4 || $rol == 13) && isset($_POST['aprobar'])) {

    // ✅ Rol 13 solo puede aprobar Consorcio Aburra 2025
    if ($rol == 13 && trim($solicitud['consorcio']) !== 'Consorcio Aburra 2025') {
        echo "Sin permisos para aprobar esta solicitud.";
        exit();
    }

    $pdo->prepare("UPDATE solicitudes_compra_consorcios 
                   SET estado='aprobado_presupuesto', comentario_rechazo=NULL, fecha_aprob_presupuesto = NOW()
                   WHERE id=?")
        ->execute([$id_solicitud]);

    // TRACKING
    $pdo->prepare("INSERT INTO solicitudes_tracking 
    (solicitud_compra_id, estado, usuario_id, fecha_hora, comentario)
    VALUES (?, 'aprobado_presupuesto', ?, NOW(), 'Aprobado por Presupuesto')")
    ->execute([$id_solicitud, $id_usuario]);

    // CORREO AL SOLICITANTE
    $body_solic = '
    <div style="font-family:Segoe UI,Arial,sans-serif;background:#f6fafd;padding:26px;">
    <div style="max-width:560px;margin:0 auto;background:#fff;border-radius:18px;
    box-shadow:0 8px 32px #2176ff16;padding:33px;">

    <h2 style="color:#2176ff;">Solicitud de compra CONSORCIOS aprobada por Presupuesto</h2>

    <p style="font-size:1.05rem;">Hola <b>'.htmlspecialchars($solicitud['nombre_solicitante']).'</b>,<br>
    Tu solicitud fue aprobada por Presupuesto. Ahora esta pendiente de aprobacion por Contabilidad.</p>

    <h3 style="color:#22314b;margin-top:25px;">Resumen</h3>
    <p><b>ID:</b> '.$id_solicitud.'<br>
       <b>Consorcio:</b> '.htmlspecialchars($solicitud['consorcio']).'<br>
       <b>Necesidad:</b> '.htmlspecialchars($solicitud['necesidad']).'</p>

    '.$tabla.'

    </div></div>';

    enviarCorreoFundocol(
        $solicitud['correo_solicitante'],
        $solicitud['nombre_solicitante'],
        'Solicitud de compra CONSORCIOS aprobada',
        $body_solic
    );

    // CORREO A CONTABILIDAD
    $body_contab = '
    <div style="font-family:Segoe UI,Arial,sans-serif;padding:26px;background:#f5f7fb;">
    <div style="max-width:600px;margin:auto;background:#fff;border-radius:18px;padding:33px;
    box-shadow:0 8px 32px #2176ff26;">

    <h2 style="color:#2176ff;">Nueva solicitud CONSORCIOS pendiente de aprobacion</h2>

    <p>Solicitante: <b>'.htmlspecialchars($solicitud['nombre_solicitante']).'</b><br>
       Consorcio: <b>'.htmlspecialchars($solicitud['consorcio']).'</b><br>
       Necesidad: <b>'.htmlspecialchars($solicitud['necesidad']).'</b></p>

    '.$tabla.'

    <a href="https://erp.fundocol.org/modules/pendientes/index.php"
       style="display:inline-block;margin-top:20px;padding:12px 30px;
       background:#2176ff;color:#fff;border-radius:10px;text-decoration:none;font-weight:bold;">
       Abrir ERP
    </a>

    </div></div>';

    enviarCorreoFundocol(
        'analista.contable@fundocol.org',
        'Contabilidad Fundocol',
        'Solicitud CONSORCIOS pendiente de aprobacion',
        $body_contab
    );

    header("Location: ../pendientes/index.php?msg=presupuesto_ok");
    exit();
}



// =============================================================
// ================= APROBAR CONTABILIDAD ======================
// =============================================================
if ($rol == 8 && isset($_POST['aprobar'])) {

    // Guardar factura SIGGO si existe
    if (isset($_FILES['factura_siggo']) && $_FILES['factura_siggo']['error'] == UPLOAD_ERR_OK) {

        $dir = "../../uploads/facturas_siggo/";
        if (!is_dir($dir)) mkdir($dir, 0777, true);

        $ext = pathinfo($_FILES['factura_siggo']['name'], PATHINFO_EXTENSION);
        $file = "siggo_".$id_solicitud."_".time().".".$ext;

        move_uploaded_file($_FILES['factura_siggo']['tmp_name'], $dir.$file);

        $pdo->prepare("UPDATE solicitudes_compra_consorcios SET factura_siggo=? WHERE id=?")
            ->execute([$file, $id_solicitud]);
    }

    $pdo->prepare("UPDATE solicitudes_compra_consorcios 
                   SET estado='aprobado_contabilidad', comentario_rechazo=NULL, fecha_aprob_contabilidad = NOW()
                   WHERE id=?")
        ->execute([$id_solicitud]);

    // TRACKING
    $pdo->prepare("INSERT INTO solicitudes_tracking 
    (solicitud_compra_id, estado, usuario_id, fecha_hora, comentario)
    VALUES (?, 'aprobado_contabilidad', ?, NOW(), 'Aprobado por Contabilidad')")
    ->execute([$id_solicitud, $id_usuario]);

    // CORREO AL SOLICITANTE
    $body_solic = '
    <div style="font-family:Segoe UI,Arial,sans-serif;background:#eef6f1;padding:26px;">
    <div style="max-width:560px;margin:auto;background:#fff;border-radius:18px;padding:33px;
    box-shadow:0 8px 32px #15a15622;">

    <h2 style="color:#15a156;">Solicitud CONSORCIOS aprobada por Contabilidad</h2>

    <p>Hola <b>'.htmlspecialchars($solicitud['nombre_solicitante']).'</b>,<br>
    Tu solicitud ya esta aprobada y lista para pago. <br>
    Consorcio: <b>'.htmlspecialchars($solicitud['consorcio']).'</b><br>
    Necesidad: <b>'.htmlspecialchars($solicitud['necesidad']).'</b></p>

    '.$tabla.'

    </div></div>';

    enviarCorreoFundocol(
        $solicitud['correo_solicitante'],
        $solicitud['nombre_solicitante'],
        'Solicitud CONSORCIOS aprobada por contabilidad',
        $body_solic
    );

    // CORREO A PAGOS
    $body_pagos = '
    <div style="font-family:Segoe UI,Arial,sans-serif;padding:26px;background:#f5faf7;">
    <div style="max-width:600px;margin:auto;background:#fff;border-radius:18px;padding:33px;
    box-shadow:0 8px 32px #15a15622;">

    <h2 style="color:#15a156;">Solicitud CONSORCIOS lista para pago</h2>

    <p>Solicitante: <b>'.htmlspecialchars($solicitud['nombre_solicitante']).'</b><br>
       Consorcio: <b>'.htmlspecialchars($solicitud['consorcio']).'</b><br>
       Necesidad: <b>'.htmlspecialchars($solicitud['necesidad']).'</b></p>

    '.$tabla.'

    <a href="https://erp.fundocol.org/modules/pendientes/index.php"
       style="display:inline-block;margin-top:20px;padding:12px 30px;
       background:#15a156;color:#fff;border-radius:10px;text-decoration:none;font-weight:bold;">
       Procesar pago
    </a>

    </div></div>';

    enviarCorreoFundocol(
        'pagos@fundocol.org',
        'Pagos Fundocol',
        'Solicitud CONSORCIOS aprobada y lista para pago',
        $body_pagos
    );

    header("Location: ../pendientes/index.php?msg=conta_ok");
    exit();
}



// =============================================================
// ===================== CONFIRMAR PAGO ========================
// =============================================================
if ($rol == 6 && isset($_POST['aprobar'])) {

    // VALIDAR SOPORTE
    if (!isset($_FILES['soporte_pago']) || $_FILES['soporte_pago']['error'] !== UPLOAD_ERR_OK) {
        echo "Debe adjuntar soporte de pago.";
        exit();
    }

    $dir = "../../uploads/soportes_pago/";
    if (!is_dir($dir)) mkdir($dir, 0777, true);

    $ext = pathinfo($_FILES['soporte_pago']['name'], PATHINFO_EXTENSION);
    $file = "soporte_" . $id_solicitud . "_" . time() . "." . $ext;

    $ruta_soporte = $dir . $file;
    move_uploaded_file($_FILES['soporte_pago']['tmp_name'], $ruta_soporte);

    $comentario_pagos = trim($_POST['comentario_pagos'] ?? '');

    // Actualizar BD
    $pdo->prepare("UPDATE solicitudes_compra_consorcios 
                   SET estado='pago_confirmado', soporte_pago=?, comentario_pagos=? 
                   WHERE id=?")
        ->execute([$file, $comentario_pagos, $id_solicitud]);

    // TRACKING
    $pdo->prepare("INSERT INTO solicitudes_tracking 
    (solicitud_compra_id, estado, usuario_id, fecha_hora, comentario)
    VALUES (?, 'pago_confirmado', ?, NOW(), 'Pago confirmado por Pagos')")
    ->execute([$id_solicitud, $id_usuario]);

    // ================================
    // 1) CORREO AL SOLICITANTE (✅ adjunto como array)
    // ================================
    $body = '
    <div style="font-family:Segoe UI,Arial,sans-serif;padding:26px;background:#f6fafd;">
      <div style="max-width:600px;margin:auto;background:#fff;border-radius:18px;padding:33px;
      box-shadow:0 8px 32px #2176ff22;">

        <h2 style="color:#15a156;">Pago confirmado - CONSORCIOS</h2>

        <p>Hola <b>'.htmlspecialchars($solicitud['nombre_solicitante']).'</b>,<br>
        El area de Pagos confirmo el pago de tu solicitud.</p>

        <p><b>ID:</b> '.$id_solicitud.'<br>
           <b>Consorcio:</b> '.htmlspecialchars($solicitud['consorcio']).'<br>
           <b>Necesidad:</b> '.htmlspecialchars($solicitud['necesidad']).'</p>

        '.($comentario_pagos !== '' ? '
        <div style="background:#fff7e6;border-radius:12px;padding:14px 16px;margin:18px 0;border:1px solid #ffe1a7;">
          <div style="font-size:0.96rem;color:#996300;font-weight:600;margin-bottom:8px;">Comentario de Pagos</div>
          <div style="font-size:0.98rem;color:#7a5700;line-height:1.45;">'.nl2br(htmlspecialchars($comentario_pagos)).'</div>
        </div>' : '').'

        '.$tabla.'

        <p style="margin-top:10px;">Adjunto soporte de pago.</p>

      </div>
    </div>';

    enviarCorreoFundocol(
        $solicitud['correo_solicitante'],
        $solicitud['nombre_solicitante'],
        'Pago confirmado - Consorcios',
        $body,
        [$ruta_soporte]
    );

    // ================================
    // 2) ✅ CORREO A PRESUPUESTO (con soporte adjunto)
    // ================================
    $body_presupuesto = '
    <div style="font-family:Segoe UI,Arial,sans-serif;padding:26px;background:#f5f7fb;">
      <div style="max-width:620px;margin:auto;background:#fff;border-radius:18px;padding:33px;
      box-shadow:0 8px 32px #2176ff18;">

        <h2 style="color:#2176ff;margin-top:0;">Pago confirmado - CONSORCIOS</h2>

        <p style="color:#24374e;font-size:1.05rem;line-height:1.55;">
          Hola <b>Presupuesto</b>,<br>
          El area de Pagos confirmo el pago de una solicitud de compra de consorcios.
        </p>

        <div style="background:#f6fafd;border-radius:12px;padding:14px 16px;margin-bottom:18px;border:1px solid #dde7f6;">
          <div><b>ID solicitud:</b> '.$id_solicitud.'</div>
          <div><b>Solicitante:</b> '.htmlspecialchars($solicitud['nombre_solicitante']).'</div>
          <div><b>Consorcio:</b> '.htmlspecialchars($solicitud['consorcio']).'</div>
          <div><b>Necesidad:</b> '.htmlspecialchars($solicitud['necesidad']).'</div>
          <div><b>Estado:</b> Pago confirmado</div>
        </div>

        '.($comentario_pagos !== '' ? '
        <div style="background:#fff7e6;border-radius:12px;padding:14px 16px;margin-bottom:18px;border:1px solid #ffe1a7;">
          <div style="font-size:0.96rem;color:#996300;font-weight:600;margin-bottom:8px;">Comentario de Pagos</div>
          <div style="font-size:0.98rem;color:#7a5700;line-height:1.45;">'.nl2br(htmlspecialchars($comentario_pagos)).'</div>
        </div>' : '').'

        <div style="font-size:1.02rem;color:#24374e;font-weight:600;margin-bottom:8px;">
          Productos incluidos
        </div>

        '.$tabla.'

        <div style="text-align:center;margin:22px 0 5px 0;">
          <a href="https://erp.fundocol.org/modules/pendientes/index.php"
             style="display:inline-block;background:#2176ff;color:#fff;
             padding:12px 30px;border-radius:10px;text-decoration:none;font-weight:bold;">
             Ver en ERP
          </a>
        </div>

        <p style="margin-top:18px;color:#667;font-size:0.92rem;">
          Se adjunta soporte de pago. Mensaje automatico del ERP Fundocol.
        </p>

      </div>
    </div>';

    enviarCorreoFundocol(
        'presupuesto@fundocol.org',
        'Presupuesto Fundocol',
        "Pago confirmado CONSORCIOS - Solicitud #{$id_solicitud}",
        $body_presupuesto,
        [$ruta_soporte]
    );

    header("Location: ../pendientes/index.php?msg=pago_ok");
    exit();
}


// SI LLEGO AQUI
echo "Accion no reconocida.";
exit();
?>

