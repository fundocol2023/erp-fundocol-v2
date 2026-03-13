<?php
include '../../../includes/navbar.php';
include_once '../../../config/db.php';

$mensaje = "";

// Obtener productos actuales
$sql = "SELECT id, nombre FROM inventario_papeleria ORDER BY nombre ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $cantidades = $_POST['cantidad'] ?? [];
    $nuevo_nombre = $_POST['nuevo_nombre'] ?? [];
    $nuevo_cantidad = $_POST['nuevo_cantidad'] ?? [];

    $productos_solicitados = [];

    // Productos existentes
    foreach($productos as $prod) {
        $cantidad = intval($cantidades[$prod['id']] ?? 0);
        if ($cantidad > 0) {
            $productos_solicitados[] = [
                "id" => $prod['id'],
                "nombre" => $prod['nombre'],
                "cantidad" => $cantidad,
                "nuevo" => false
            ];
        }
    }
    // Nuevos productos
    if (is_array($nuevo_nombre) && is_array($nuevo_cantidad)) {
        for ($i=0; $i<count($nuevo_nombre); $i++) {
            $nombre_nuevo = trim($nuevo_nombre[$i]);
            $cant_nuevo = intval($nuevo_cantidad[$i] ?? 0);
            if ($nombre_nuevo && $cant_nuevo > 0) {
                $productos_solicitados[] = [
                    "id" => null,
                    "nombre" => $nombre_nuevo,
                    "cantidad" => $cant_nuevo,
                    "nuevo" => true
                ];
            }
        }
    }

    if (count($productos_solicitados) > 0) {
        // Guardar en tabla solicitudes_papeleria
        $sql_insert = "INSERT INTO solicitudes_papeleria (productos, estado) VALUES (?, 'pendiente')";
        $stmt_insert = $pdo->prepare($sql_insert);
        $stmt_insert->execute([json_encode($productos_solicitados)]);

        // (Opcional: también envía el correo, como ya tienes)
        $tabla = "<table border='1' cellpadding='6' style='border-collapse:collapse; font-family:Arial;font-size:15px'>";
        $tabla .= "<tr style='background:#e0f2fe; color:#0369a1;'><th>Producto</th><th>Cantidad Solicitada</th></tr>";
        foreach ($productos_solicitados as $p) {
            $tabla .= "<tr><td>".htmlspecialchars($p['nombre']).($p['nuevo'] ? " (Nuevo)" : "")."</td><td>{$p['cantidad']}</td></tr>";
        }
        $tabla .= "</table>";

        // Cambia por el correo real del encargado
        $destinatario = "inventario@tucorreo.com";
        $asunto = "Nueva solicitud de stock de papelería";
        $mensaje_html = "
            <b>Productos solicitados:</b><br>$tabla
            <br><br><small>Este es un mensaje automático del sistema ERP Fundocol.</small>
        ";
        $cabeceras = "MIME-Version: 1.0\r\n";
        $cabeceras .= "Content-type: text/html; charset=utf-8\r\n";
        $cabeceras .= "From: ERP Fundocol <no-reply@tudominio.com>\r\n";
        mail($destinatario, $asunto, $mensaje_html, $cabeceras);

        $mensaje = "<span style='color:#22c55e;font-weight:600'>Solicitud enviada y registrada correctamente.</span>";
    } else {
        $mensaje = "<span style='color:#ef4444;font-weight:600'>Debes solicitar al menos un producto.</span>";
    }
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Solicitar Stock de Papelería</title>
    <link rel="stylesheet" href="../../../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@500;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="https://erp-fundocol.fundocol.org/assets/img/Fundocol_favicon.png">
    <!-- estilos movidos a assets/css/style.css -->
    <script>
        // JS para añadir más líneas de productos nuevos
        function addNuevoProducto() {
            const container = document.getElementById('nuevos-productos');
            const grupo = document.createElement('div');
            grupo.className = 'nuevo-grupo';
            grupo.innerHTML = `
                <input type="text" name="nuevo_nombre[]" class="form-control" maxlength="70" placeholder="Producto nuevo">
                <input type="number" name="nuevo_cantidad[]" class="form-control" min="0" max="9999" placeholder="Cantidad">
            `;
            container.appendChild(grupo);
        }
    </script>
</head>
<body class="inventarios-papeleria-solicitar-page">
<div class="navbar-spacer"></div>
<div class="solicitar-contenedor">
    <div class="form-titulo">Solicitar Stock de Papelería</div>
    <?php if($mensaje): ?><div class="msg-nota"><?= $mensaje ?></div><?php endif; ?>
    <form method="post" autocomplete="off">
        <label class="form-label">Marca las cantidades que deseas solicitar de productos existentes:</label>
        <table class="solicitud-productos">
            <tr>
                <th>Producto</th>
                <th>Cantidad a solicitar</th>
            </tr>
            <?php foreach($productos as $prod): ?>
            <tr>
                <td><?= htmlspecialchars($prod['nombre']) ?></td>
                <td>
                    <input type="number" name="cantidad[<?= $prod['id'] ?>]" min="0" max="9999" value="0">
                </td>
            </tr>
            <?php endforeach; ?>
        </table>

        <div class="nuevos-label">¿Deseas solicitar algún producto NUEVO que no está en la lista?</div>
        <div id="nuevos-productos">
            <div class="nuevo-grupo">
                <input type="text" name="nuevo_nombre[]" class="form-control" maxlength="70" placeholder="Producto nuevo">
                <input type="number" name="nuevo_cantidad[]" class="form-control" min="0" max="9999" placeholder="Cantidad">
            </div>
        </div>
        <button type="button" class="btn-add-nuevo" onclick="addNuevoProducto()">+ Añadir otro nuevo</button>

        <div class="form-btns">
            <button type="submit" class="btn-principal"><i class="bi bi-send"></i> Enviar solicitud</button>
            <a href="index.php" class="btn-sec">Cancelar</a>
        </div>
    </form>
</div>
</body>
</html>

