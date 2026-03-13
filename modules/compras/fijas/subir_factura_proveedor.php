<?php
include '../../../includes/navbar.php';
require_once '../../../config/db.php';
require_once '../../../includes/phpmailer.php';

$id = intval($_GET['id'] ?? 0);
if (!$id) { echo "Solicitud no especificada."; exit; }

// Buscar la compra fija
$stmt = $pdo->prepare("SELECT cf.*, u.nombre AS solicitante_nombre, u.email AS solicitante_email
    FROM compras_fijas cf
    INNER JOIN usuarios u ON cf.solicitante_id = u.id
    WHERE cf.id = ?");
$stmt->execute([$id]);
$compra = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$compra) { echo "Compra fija no encontrada."; exit; }

$mensaje = "";

// Procesar subida
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_FILES['factura_proveedor']) && $_FILES['factura_proveedor']['error'] == 0) {
        $ext = strtolower(pathinfo($_FILES['factura_proveedor']['name'], PATHINFO_EXTENSION));
        $permitidas = ['pdf', 'jpg', 'jpeg', 'png'];
        if (in_array($ext, $permitidas)) {
            $uploads_dir = '../../../uploads/compras_fijas/';
            $nombre_archivo = uniqid('factura_proveedor_') . '.' . $ext;
            move_uploaded_file($_FILES['factura_proveedor']['tmp_name'], $uploads_dir . $nombre_archivo);
            // Guardar en la BD
            $pdo->prepare("UPDATE compras_fijas SET archivo_factura_proveedor = ? WHERE id = ?")
                ->execute([$nombre_archivo, $id]);
            // Enviar correo al analista contable
            $correo_contable = "analista.contable@fundocol.org";
            $asunto = "Factura del proveedor subida para compra fija";
            $mensaje_html = "
                <h2>Factura del proveedor disponible</h2>
                <p>Se ha cargado la factura del proveedor para la compra fija:</p>
                <p><b>ID:</b> $id</p>
                <p><b>Proveedor:</b> ".htmlspecialchars($compra['proveedor'])."</p>
                <p><b>Monto:</b> $".number_format($compra['monto'], 0, ',', '.')."</p>
                <br>
                <small>ERP Fundocol</small>
            ";
            $adjuntos = [$uploads_dir . $nombre_archivo];
            enviarCorreoFundocol($correo_contable, "Analista Contable", $asunto, $mensaje_html, $adjuntos);

            header("Location: /erp-fundocol/modules/compras/fijas/compras_fijas.php");
            exit;
        } else {
            $mensaje = "Formato de archivo no permitido.";
        }
    } else {
        $mensaje = "Por favor adjunta la factura del proveedor.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Subir Factura del Proveedor</title>
    <link rel="stylesheet" href="../../../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../../assets/css/style.css">
    <link rel="icon" type="image/x-icon" href="https://erp-fundocol.fundocol.org/assets/img/Fundocol_favicon.png">
</head>
<body class="compras-fijas-subir-factura-page">
<div class="navbar-spacer"></div>
<div class="box-factura">
    <div class="titulo-factura">Subir Factura del Proveedor</div>
    <?php if($mensaje): ?><div class="msg-error"><?= $mensaje ?></div><?php endif; ?>
    <form method="post" enctype="multipart/form-data" autocomplete="off">
        <div class="form-group">
            <label for="factura_proveedor" class="label-factura">Factura (PDF/JPG/PNG):</label>
            <input type="file" name="factura_proveedor" id="factura_proveedor" class="form-control" required accept=".pdf,.jpg,.jpeg,.png">
        </div>
        <div class="acciones-factura">
            <button type="submit" class="btn-enviar"><i class="bi bi-upload"></i> Enviar factura</button>
            <a href="agregar.php" class="btn btn-secondary btn-cancelar">Cancelar</a>
        </div>
    </form>
</div>
</body>
</html>
