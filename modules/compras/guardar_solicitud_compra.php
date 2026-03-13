<?php
session_start();
require_once '../../config/db.php';

// ✅ NUEVO: Graph mailer
require_once '../../includes/mailer.php';

if (!isset($_SESSION['usuario_id'])) { header('Location: ../../login.php'); exit(); }

// --------------------
// 1. Validar datos iniciales
// --------------------
$usuario_id = $_SESSION['usuario_id'];
$usuario_nombre = $_SESSION['usuario_nombre'] ?? 'Usuario';
$fecha = date('Y-m-d');

$productos = $_POST['productos'] ?? [];
$id_cot = intval($_GET['id'] ?? $_POST['id_cot'] ?? 0);
$observaciones_compra = trim($_POST['observaciones_compra'] ?? '');

if (!$productos || !$id_cot) { echo "Faltan datos"; exit(); }

// --------------------
// 2. Traer solicitud de cotización aprobada
// --------------------
$sql = "SELECT sc.*, c.proveedor, c.precio AS cot_precio, c.archivo AS cot_archivo
        FROM solicitudes_cotizacion sc
        JOIN solicitudes_cotizacion_cotizaciones c ON sc.cotizacion_aprobada_id = c.id
        WHERE sc.id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$id_cot]);
$solicitud = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$solicitud) { echo "Cotizacion no encontrada"; exit(); }

// --------------------
// 3. Manejo de adjuntos (certificacion y RUT)
// --------------------
$proveedores_no_requieren = ['homecenter','exito','yumbo'];
$proveedor = strtolower($solicitud['proveedor']);
$requiere_docs = !in_array($proveedor, $proveedores_no_requieren);

$cert_bancaria_nombre = null;
$rut_nombre = null;

if ($requiere_docs) {
    if (!empty($_FILES['certificacion_bancaria']['name'])) {
        $ext = pathinfo($_FILES['certificacion_bancaria']['name'], PATHINFO_EXTENSION);
        $cert_bancaria_nombre = "cert_{$usuario_id}_{$id_cot}_" . time() . "." . $ext;
        $ruta = "../../uploads/certificaciones/" . $cert_bancaria_nombre;
        if (!is_dir(dirname($ruta))) mkdir(dirname($ruta), 0777, true);
        move_uploaded_file($_FILES['certificacion_bancaria']['tmp_name'], $ruta);
    }

    if (!empty($_FILES['rut_proveedor']['name'])) {
        $ext = pathinfo($_FILES['rut_proveedor']['name'], PATHINFO_EXTENSION);
        $rut_nombre = "rut_{$usuario_id}_{$id_cot}_" . time() . "." . $ext;
        $ruta = "../../uploads/rut/" . $rut_nombre;
        if (!is_dir(dirname($ruta))) mkdir(dirname($ruta), 0777, true);
        move_uploaded_file($_FILES['rut_proveedor']['tmp_name'], $ruta);
    }
}

// --------------------
// 4. Insertar solicitud de compra CON observacion_compra
// --------------------
$sql = "INSERT INTO solicitudes_compra
    (solicitante_id, fecha, proyecto_oficina, necesidad, cotizacion_aprobada_id, solicitud_cotizacion_id, proveedor, estado, certificacion_bancaria, rut_proveedor, observaciones_compra)
    VALUES (?, ?, ?, ?, ?, ?, ?, 'pendiente', ?, ?, ?)";

$stmt = $pdo->prepare($sql);
$stmt->execute([
    $usuario_id,
    $fecha,
    $solicitud['proyecto_oficina'],
    $solicitud['necesidad'],
    $solicitud['cotizacion_aprobada_id'],
    $solicitud['id'],
    $solicitud['proveedor'],
    $cert_bancaria_nombre,
    $rut_nombre,
    $observaciones_compra
]);

$id_compra = $pdo->lastInsertId();

// --------------------
// 5. Insertar productos
// --------------------
foreach ($productos as $prod) {
    $sql = "INSERT INTO solicitudes_compra_productos
            (solicitud_compra_id, nombre, cantidad, descripcion, precio_unitario, precio_total)
            VALUES (?, ?, ?, ?, ?, ?)";

    $pdo->prepare($sql)->execute([
        $id_compra,
        $prod['nombre'],
        $prod['cantidad'],
        $prod['descripcion'],
        $prod['precio_unitario'],
        $prod['precio_total']
    ]);
}

// --------------------
// 6. Registrar tracking
// --------------------
$stmt = $pdo->prepare("INSERT INTO solicitudes_tracking
    (solicitud_compra_id, estado, usuario_id, fecha_hora, comentario)
    VALUES (?, 'solicitud_compra_enviada', ?, NOW(), ?)");
$stmt->execute([$id_compra, $usuario_id, "Solicitud enviada por el usuario."]);

// --------------------
// 7. Enviar correo a presupuesto (Graph)
// --------------------

// Construccion de tabla
$tabla = '<table border="1" cellpadding="6" cellspacing="0" style="border-collapse:collapse;font-size:1rem;width:99%;">';
$tabla .= '<tr>
            <th style="background:#eaf4ff">Producto</th>
            <th style="background:#eaf4ff">Cantidad</th>
            <th style="background:#eaf4ff">Descripción</th>
            <th style="background:#eaf4ff">Precio Unitario</th>
            <th style="background:#eaf4ff">Total</th>
          </tr>';

foreach ($productos as $p) {
    $tabla .= "<tr>
                <td>".htmlspecialchars($p['nombre'])."</td>
                <td align='center'>".intval($p['cantidad'])."</td>
                <td>".htmlspecialchars($p['descripcion'])."</td>
                <td align='right'>$".number_format($p['precio_unitario'],0,',','.')."</td>
                <td align='right'>$".number_format($p['precio_total'],0,',','.')."</td>
               </tr>";
}
$tabla .= "</table>";

$obsTexto = $observaciones_compra
    ? "<p><b>Observaciones del usuario:</b> ".nl2br(htmlspecialchars($observaciones_compra))."</p>"
    : "";

$linkAprob = "https://erp.fundocol.org/modules/pendientes/index.php";

$body = "
<div style='font-family:Arial,sans-serif;background:#f5f7fb;padding:30px 0;'>
<div style='max-width:540px;margin:0 auto;background:#fff;border-radius:18px;
     box-shadow:0 8px 32px #2176ff33;padding:38px;'>

<h2 style='color:#2176ff;margin-top:0;'>Nueva solicitud de compra</h2>

<p>Usuario: <b>".htmlspecialchars($usuario_nombre)."</b></p>
<p>Proyecto/Oficina: <b>".htmlspecialchars($solicitud['proyecto_oficina'])."</b></p>
<p>Necesidad: <b>".htmlspecialchars($solicitud['necesidad'])."</b></p>
<p>Proveedor: <b>".htmlspecialchars($solicitud['proveedor'])."</b></p>

{$obsTexto}

<h3 style='margin-top:24px;color:#194574;'>Productos solicitados:</h3>
{$tabla}

<br>
<a href='{$linkAprob}' 
   style='display:inline-block;background:#2176ff;color:#fff;
   padding:12px 30px;border-radius:10px;text-decoration:none;font-weight:bold;'>
   Abrir en ERP Fundocol
</a>

<p style='margin-top:22px;color:#667;'>No responder este mensaje.</p>
</div>
</div>
";

// ✅ Adjuntos: cotización + docs proveedor si existen
$adjuntos = [];

// Cotización aprobada (archivo guardado en /modules/.../uploads/cotizaciones/)
if (!empty($solicitud['cot_archivo'])) {
    $ruta_cot = __DIR__ . "/uploads/cotizaciones/" . $solicitud['cot_archivo'];
    if (file_exists($ruta_cot)) $adjuntos[] = $ruta_cot;
}

// Certificación bancaria
if (!empty($cert_bancaria_nombre)) {
    $ruta_cert = __DIR__ . "/../../uploads/certificaciones/" . $cert_bancaria_nombre;
    if (file_exists($ruta_cert)) $adjuntos[] = $ruta_cert;
}

// RUT
if (!empty($rut_nombre)) {
    $ruta_rut = __DIR__ . "/../../uploads/rut/" . $rut_nombre;
    if (file_exists($ruta_rut)) $adjuntos[] = $ruta_rut;
}

enviarCorreoFundocol(
    'presupuesto@fundocol.org',
    'Presupuesto Fundocol',
    'Nueva solicitud de compra pendiente',
    $body,
    $adjuntos
);

// --------------------
// 8. Redirigir
// --------------------
header("Location: index.php?msg=solicitud_enviada");
exit();
?>





