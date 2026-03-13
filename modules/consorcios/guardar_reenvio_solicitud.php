<?php
// Mostrar errores para depuración
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once '../../config/db.php';
require_once '../../includes/mailer.php';

if (!isset($_SESSION['usuario_id'])) { 
    echo "⚠️ Sesion no valida"; 
    exit();
}

$usuario_id = $_SESSION['usuario_id'];
$usuario_nombre = $_SESSION['usuario_nombre'] ?? 'Usuario';
$fecha = date('Y-m-d');

$id_cot = intval($_POST['id_cot'] ?? 0);
$id_cotizacion = intval($_POST['id_cotizacion'] ?? 0);
$productos = $_POST['productos'] ?? [];

if (!$id_cot || empty($productos)) {
    echo "⚠️ Datos incompletos<br>";
    echo "ID Cotizacion: $id_cot<br>";
    echo "Productos: "; print_r($productos);
    exit();
}

// 🔹 Traer información base de la solicitud original
$sql = "SELECT sc.*, c.proveedor
        FROM solicitudes_cotizacion_consorcios sc
        JOIN solicitudes_cotizacion_consorcios_cotizaciones c ON sc.cotizacion_aprobada_id = c.id
        WHERE sc.id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$id_cot]);
$solicitud = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$solicitud) {
    echo "❌ Solicitud original no encontrada (ID: $id_cot).";
    exit();
}

// =============================
// 1️⃣ Subir nueva cotización (ruta del módulo consorcios)
// =============================
$nueva_cotizacion = null;
if (!empty($_FILES['nueva_cotizacion']['name'])) {
    if ($_FILES['nueva_cotizacion']['error'] !== UPLOAD_ERR_OK) {
        echo "❌ Error al subir la nueva cotizacion: " . $_FILES['nueva_cotizacion']['error'];
        exit();
    }

    $ext = pathinfo($_FILES['nueva_cotizacion']['name'], PATHINFO_EXTENSION);
    $nombreArchivo = "cot_{$usuario_id}_{$id_cot}_" . time() . "." . $ext;
    $ruta = "uploads/cotizaciones/" . $nombreArchivo; // dentro del módulo
    $rutaServidor = __DIR__ . "/uploads/cotizaciones/";
    if (!is_dir($rutaServidor)) mkdir($rutaServidor, 0777, true);

    move_uploaded_file($_FILES['nueva_cotizacion']['tmp_name'], $rutaServidor . $nombreArchivo);
    $nueva_cotizacion = $nombreArchivo;
} else {
    echo "❌ Debe adjuntar la nueva cotizacion.";
    exit();
}

// =============================
// 2️⃣ Subir Certificación Bancaria y RUT (rutas globales en /uploads/)
// =============================
$proveedores_no_requieren = ['homecenter', 'exito', 'yumbo'];
$proveedor = strtolower($solicitud['proveedor']);
$requiere_docs = !in_array($proveedor, $proveedores_no_requieren);

$cert_bancaria_nombre = null;
$rut_nombre = null;

if ($requiere_docs) {
    // 🏦 Certificación bancaria
    if (!empty($_FILES['certificacion_bancaria']['name'])) {
        if ($_FILES['certificacion_bancaria']['error'] !== UPLOAD_ERR_OK) {
            echo "❌ Error al subir certificación bancaria: " . $_FILES['certificacion_bancaria']['error'];
            exit();
        }
        $ext = pathinfo($_FILES['certificacion_bancaria']['name'], PATHINFO_EXTENSION);
        $cert_bancaria_nombre = "cert_{$usuario_id}_{$id_cot}_" . time() . "." . $ext;
        $rutaCert = "../../uploads/certificaciones/";
        if (!is_dir($rutaCert)) mkdir($rutaCert, 0777, true);
        move_uploaded_file($_FILES['certificacion_bancaria']['tmp_name'], $rutaCert . $cert_bancaria_nombre);
    }

    // 🧾 RUT proveedor
    if (!empty($_FILES['rut_proveedor']['name'])) {
        if ($_FILES['rut_proveedor']['error'] !== UPLOAD_ERR_OK) {
            echo "❌ Error al subir RUT: " . $_FILES['rut_proveedor']['error'];
            exit();
        }
        $ext = pathinfo($_FILES['rut_proveedor']['name'], PATHINFO_EXTENSION);
        $rut_nombre = "rut_{$usuario_id}_{$id_cot}_" . time() . "." . $ext;
        $rutaRut = "../../uploads/rut/";
        if (!is_dir($rutaRut)) mkdir($rutaRut, 0777, true);
        move_uploaded_file($_FILES['rut_proveedor']['tmp_name'], $rutaRut . $rut_nombre);
    }
}

// =============================
// 3️⃣ Crear la nueva solicitud de compra
// =============================
try {
    $pdo->beginTransaction();

    $sqlInsert = "INSERT INTO solicitudes_compra_consorcios 
        (solicitud_cotizacion_id, solicitante_id, cotizacion_aprobada_id, consorcio, necesidad, proveedor, fecha, 
         certificacion_bancaria, rut_proveedor, estado, observaciones_compra) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pendiente', ?)";
    $stmt = $pdo->prepare($sqlInsert);
    $stmt->execute([
        $id_cot,
        $usuario_id,
        $id_cotizacion,
        $solicitud['consorcio'],
        $solicitud['necesidad'],
        $solicitud['proveedor'],
        $fecha,
        $cert_bancaria_nombre,
        $rut_nombre,
        $solicitud['observaciones_compra'] ?? ''
    ]);

    $idNuevaSolicitud = $pdo->lastInsertId();

    // 🔹 Guardar productos
    $sqlProd = "INSERT INTO solicitudes_compra_consorcios_productos 
                (solicitud_compra_id, nombre, descripcion, cantidad, precio_unitario, precio_total)
                VALUES (?, ?, ?, ?, ?, ?)";
    $stmtProd = $pdo->prepare($sqlProd);

    $precioTotalGeneral = 0;
    foreach ($productos as $p) {
        $nombre = $p['nombre'] ?? '';
        $descripcion = $p['descripcion'] ?? '';
        $cantidad = floatval($p['cantidad'] ?? 0);
        $precio_unitario = floatval($p['precio_unitario'] ?? 0);
        $precio_total = floatval($p['precio_total'] ?? ($cantidad * $precio_unitario));
        $precioTotalGeneral += $precio_total;

        $stmtProd->execute([$idNuevaSolicitud, $nombre, $descripcion, $cantidad, $precio_unitario, $precio_total]);
    }

    // 🔹 Actualizar cotización aprobada con nuevo PDF
    $sqlUpdateCot = "UPDATE solicitudes_cotizacion_consorcios_cotizaciones 
                     SET archivo = ?, precio = ? WHERE id = ?";
    $stmt = $pdo->prepare($sqlUpdateCot);
    $stmt->execute([$nueva_cotizacion, $precioTotalGeneral, $id_cotizacion]);

    $pdo->commit();

    // =============================
    // 7. Enviar correo a Presupuesto (sin tildes)
// --------------------
function quitarTildes($cadena) {
    $no = ['á','é','í','ó','ú','ñ','Á','É','Í','Ó','Ú','Ñ'];
    $si = ['a','e','i','o','u','n','A','E','I','O','U','N'];
    return str_replace($no, $si, $cadena);
}

// Construcción de tabla
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
                <td>".htmlspecialchars($p['nombre'])."</td>
                <td align='center'>".intval($p['cantidad'])."</td>
                <td>".htmlspecialchars($p['descripcion'])."</td>
                <td align='right'>$".number_format($p['precio_unitario'],0,',','.')."</td>
                <td align='right'>$".number_format($p['precio_total'],0,',','.')."</td>
               </tr>";
}
$tabla .= "</table>";

$obsTexto = $observaciones_compra ? "<p><b>Observaciones del usuario:</b> ".htmlspecialchars($observaciones_compra)."</p>" : "";
$linkAprob = "https://erp.fundocol.org/modules/pendientes/index.php";

$body = quitarTildes("
<div style='font-family:Arial,sans-serif;background:#f5f7fb;padding:30px 0;'>
<div style='max-width:540px;margin:0 auto;background:#fff;border-radius:18px;
     box-shadow:0 8px 32px #2176ff33;padding:38px;'>

<h2 style='color:#2176ff;margin-top:0;'>Nueva solicitud de compra</h2>

<p>Usuario: <b>{$usuario_nombre}</b></p>
<p>Proyecto/Oficina: <b>{$solicitud['consorcio']}</b></p>
<p>Necesidad: <b>{$solicitud['necesidad']}</b></p>
<p>Proveedor: <b>{$solicitud['proveedor']}</b></p>

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

try {
    enviarCorreoFundocol(
        'presupuesto@fundocol.org',
        'Presupuesto Fundocol',
        'Nueva solicitud de compra pendiente',
        $body
    );
} catch (Exception $e) {
    echo "❌ Error al enviar correo: " . $e->getMessage();
    exit();
}

    echo "<script>
        alert('✅ Solicitud reenviada correctamente y enviada a Presupuesto.');
        window.location.href='https://erp.fundocol.org/index.php';
    </script>";

} catch (Exception $e) {
    $pdo->rollBack();
    echo "<pre>❌ Error al reenviar solicitud:\n" . $e->getMessage() . "\n\nTrace:\n" . $e->getTraceAsString() . "</pre>";
}
?>






