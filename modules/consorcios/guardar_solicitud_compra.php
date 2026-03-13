<?php
// Activar modo debug temporal
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once '../../config/db.php';

// ✅ Graph mailer
require_once __DIR__ . '/../../includes/mailer.php';

date_default_timezone_set('America/Bogota');

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../../login.php');
    exit();
}

// --------------------
// 1. Validar datos iniciales
// --------------------
$usuario_id = $_SESSION['usuario_id'];
$usuario_nombre = $_SESSION['usuario_nombre'] ?? 'Usuario';
$fecha = date('Y-m-d');

$productos = $_POST['productos'] ?? [];
$id_cot = intval($_GET['id'] ?? $_POST['id_cot'] ?? 0);
$observaciones_compra = trim($_POST['observaciones_compra'] ?? '');

if (!is_array($productos) || empty($productos) || !$id_cot) {
    echo "Faltan datos o productos inválidos";
    exit();
}

// --------------------
// 2. Traer solicitud de cotización aprobada
// --------------------
$sql = "SELECT sc.*, c.proveedor, c.precio AS cot_precio, c.archivo AS cot_archivo
        FROM solicitudes_cotizacion_consorcios sc
        JOIN solicitudes_cotizacion_consorcios_cotizaciones c 
          ON sc.cotizacion_aprobada_id = c.id
        WHERE sc.id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$id_cot]);
$solicitud = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$solicitud) {
    echo "❌ Error: Cotización no encontrada para ID $id_cot";
    exit();
}

// --------------------
// 3. Manejo de adjuntos (certificación y RUT)
// --------------------
$proveedores_no_requieren = ['homecenter', 'exito', 'yumbo'];
$proveedor = strtolower($solicitud['proveedor'] ?? '');
$requiere_docs = !in_array($proveedor, $proveedores_no_requieren, true);

$cert_bancaria_nombre = null;
$rut_nombre = null;

$path_cert = null;
$path_rut  = null;

if ($requiere_docs) {

    if (!empty($_FILES['certificacion_bancaria']['name'])) {
        if ($_FILES['certificacion_bancaria']['error'] !== UPLOAD_ERR_OK) {
            echo "❌ Error al subir certificación bancaria: " . $_FILES['certificacion_bancaria']['error'];
            exit();
        }

        $ext = pathinfo($_FILES['certificacion_bancaria']['name'], PATHINFO_EXTENSION);
        $cert_bancaria_nombre = "cert_{$usuario_id}_{$id_cot}_" . time() . "." . $ext;

        $dir_cert = "../../uploads/certificaciones/";
        if (!is_dir($dir_cert)) mkdir($dir_cert, 0777, true);

        $path_cert = $dir_cert . $cert_bancaria_nombre;
        if (!move_uploaded_file($_FILES['certificacion_bancaria']['tmp_name'], $path_cert)) {
            echo "❌ No se pudo guardar la certificación bancaria.";
            exit();
        }
    }

    if (!empty($_FILES['rut_proveedor']['name'])) {
        if ($_FILES['rut_proveedor']['error'] !== UPLOAD_ERR_OK) {
            echo "❌ Error al subir RUT: " . $_FILES['rut_proveedor']['error'];
            exit();
        }

        $ext = pathinfo($_FILES['rut_proveedor']['name'], PATHINFO_EXTENSION);
        $rut_nombre = "rut_{$usuario_id}_{$id_cot}_" . time() . "." . $ext;

        $dir_rut = "../../uploads/rut/";
        if (!is_dir($dir_rut)) mkdir($dir_rut, 0777, true);

        $path_rut = $dir_rut . $rut_nombre;
        if (!move_uploaded_file($_FILES['rut_proveedor']['tmp_name'], $path_rut)) {
            echo "❌ No se pudo guardar el RUT.";
            exit();
        }
    }
}

// --------------------
// 4. Insertar solicitud de compra con observaciones
// --------------------
try {
    $sql = "INSERT INTO solicitudes_compra_consorcios
        (solicitante_id, fecha, consorcio, necesidad, cotizacion_aprobada_id, 
         solicitud_cotizacion_id, proveedor, estado, certificacion_bancaria, 
         rut_proveedor, observaciones_compra)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'pendiente', ?, ?, ?)";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $usuario_id,
        $fecha,
        $solicitud['consorcio'],
        $solicitud['necesidad'],
        $solicitud['cotizacion_aprobada_id'],
        $solicitud['id'],
        $solicitud['proveedor'],
        $cert_bancaria_nombre,
        $rut_nombre,
        $observaciones_compra
    ]);

    $id_compra = $pdo->lastInsertId();

} catch (Exception $e) {
    echo "❌ Error al insertar solicitud: " . $e->getMessage();
    exit();
}

// --------------------
// 5. Insertar productos
// --------------------
foreach ($productos as $prod) {
    if (!isset($prod['nombre'], $prod['cantidad'], $prod['precio_unitario'])) continue;

    try {
        $sql = "INSERT INTO solicitudes_compra_consorcios_productos
                (solicitud_compra_id, nombre, cantidad, descripcion, precio_unitario, precio_total)
                VALUES (?, ?, ?, ?, ?, ?)";
        $pdo->prepare($sql)->execute([
            $id_compra,
            $prod['nombre'],
            $prod['cantidad'],
            $prod['descripcion'] ?? '',
            $prod['precio_unitario'],
            $prod['precio_total'] ?? (intval($prod['cantidad']) * floatval($prod['precio_unitario']))
        ]);
    } catch (Exception $e) {
        echo "❌ Error al insertar producto: " . $e->getMessage();
        exit();
    }
}

// --------------------
// 6. Registrar tracking
// --------------------
try {
    $stmt = $pdo->prepare("INSERT INTO solicitudes_tracking
        (solicitud_compra_id, estado, usuario_id, fecha_hora, comentario)
        VALUES (?, 'solicitud_compra_enviada', ?, NOW(), ?)");
    $stmt->execute([$id_compra, $usuario_id, "Solicitud enviada por el usuario."]);
} catch (Exception $e) {
    echo "❌ Error al registrar tracking: " . $e->getMessage();
    exit();
}

// --------------------
// 7. Preparar correo
// --------------------
function quitarTildes($cadena) {
    $no = ['á','é','í','ó','ú','ñ','Á','É','Í','Ó','Ú','Ñ'];
    $si = ['a','e','i','o','u','n','A','E','I','O','U','N'];
    return str_replace($no, $si, $cadena);
}

// Tabla de productos
$tabla = '<table border="1" cellpadding="6" cellspacing="0" style="border-collapse:collapse;font-size:1rem;width:99%;">';
$tabla .= '<tr>
            <th style="background:#eaf4ff">Producto</th>
            <th style="background:#eaf4ff">Cantidad</th>
            <th style="background:#eaf4ff">Descripcion</th>
            <th style="background:#eaf4ff">Precio Unitario</th>
            <th style="background:#eaf4ff">Total</th>
          </tr>';

foreach ($productos as $p) {
    $tabla .= "<tr>
                <td>" . htmlspecialchars($p['nombre']) . "</td>
                <td align='center'>" . intval($p['cantidad']) . "</td>
                <td>" . htmlspecialchars($p['descripcion'] ?? '') . "</td>
                <td align='right'>$" . number_format($p['precio_unitario'], 0, ',', '.') . "</td>
                <td align='right'>$" . number_format($p['precio_total'] ?? (intval($p['cantidad']) * floatval($p['precio_unitario'])), 0, ',', '.') . "</td>
               </tr>";
}
$tabla .= "</table>";

$obsTexto = $observaciones_compra
    ? "<p><b>Observaciones del usuario:</b> " . htmlspecialchars($observaciones_compra) . "</p>"
    : "";

$linkAprob = "https://erp.fundocol.org/modules/pendientes/index.php";

$body = quitarTildes("
<div style='font-family:Arial,sans-serif;background:#f5f7fb;padding:30px 0;'>
<div style='max-width:540px;margin:0 auto;background:#fff;border-radius:18px;
     box-shadow:0 8px 32px #2176ff33;padding:38px;'>

<h2 style='color:#2176ff;margin-top:0;'>Nueva solicitud de compra (Consorcios)</h2>

<p>Usuario: <b>{$usuario_nombre}</b></p>
<p>Consorcio: <b>" . htmlspecialchars($solicitud['consorcio']) . "</b></p>
<p>Necesidad: <b>" . htmlspecialchars($solicitud['necesidad']) . "</b></p>
<p>Proveedor: <b>" . htmlspecialchars($solicitud['proveedor']) . "</b></p>

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
");

// --------------------
// 8. Adjuntos
// --------------------
$adjuntos = [];

// Cotización aprobada (si existe)
$cot_path = __DIR__ . '/uploads/cotizaciones/' . ($solicitud['cot_archivo'] ?? '');
if (!empty($solicitud['cot_archivo']) && file_exists($cot_path)) {
    $adjuntos[] = $cot_path;
}

// Certificación y RUT
if ($path_cert && file_exists($path_cert)) $adjuntos[] = $path_cert;
if ($path_rut && file_exists($path_rut)) $adjuntos[] = $path_rut;

// --------------------
// 9. Definir correo destino según consorcio
// --------------------
$consorcio_actual = strtoupper(trim($solicitud['consorcio'] ?? ''));

if ($consorcio_actual === 'CONSORCIO ABURRA 2025') {
    $correo_presupuesto = 'ingeniero.civil@fundocol.org';
    $nombre_presupuesto = 'Ingeniero Civil Fundocol';
} else {
    $correo_presupuesto = 'presupuesto@fundocol.org';
    $nombre_presupuesto = 'Presupuesto Fundocol';
}

// --------------------
// 10. Enviar correo
// --------------------
try {
    enviarCorreoFundocol(
        $correo_presupuesto,
        $nombre_presupuesto,
        'Nueva solicitud de compra consorcios pendiente',
        $body,
        $adjuntos
    );
} catch (Exception $e) {
    echo "❌ Error al enviar correo: " . $e->getMessage();
    exit();
}

// --------------------
// 11. Redirigir
// --------------------
header("Location: index.php?msg=solicitud_enviada");
exit();
?>
