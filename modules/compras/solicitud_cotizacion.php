<?php
include '../../includes/auth.php';
require_once '../../includes/tracking.php';
require_once '../../config/db.php'; // Tu conexión a la BD
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../includes/mailer.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;



date_default_timezone_set('America/Bogota');
$nombre_usuario = $_SESSION['usuario_nombre'] ?? 'Usuario';
$id_usuario = $_SESSION['usuario_id'] ?? 1; // Ajusta esto a tu sesión real
$fecha_hoy = date('Y-m-d');
$fase = "cotizacion"; // Puedes cambiar esto dinámicamente luego

// --- BACKEND: Procesa el envío del formulario ---
$mensaje_envio = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validación mínima
        if (
            empty($_POST['proyecto']) ||
            empty($_POST['necesidad']) ||
            !isset($_POST['prod_nombre']) || count($_POST['prod_nombre']) < 1
        ) {
            throw new Exception("Por favor, completa todos los campos y agrega al menos un producto.");
        }
        // Insertar solicitud principal
        $stmt = $pdo->prepare("INSERT INTO solicitudes_cotizacion
            (solicitante_id, fecha, proyecto_oficina, necesidad)
            VALUES (?, ?, ?, ?)");
        $stmt->execute([
            $id_usuario,
            $fecha_hoy,
            $_POST['proyecto'],
            $_POST['necesidad']
        ]);
        $solicitud_id = $pdo->lastInsertId();

        // Insertar productos
        foreach ($_POST['prod_nombre'] as $i => $nombre) {
            $stmt = $pdo->prepare("INSERT INTO solicitudes_cotizacion_productos
                (solicitud_id, nombre, cantidad, descripcion)
                VALUES (?, ?, ?, ?)");
            $stmt->execute([
                $solicitud_id,
                $nombre,
                $_POST['prod_cantidad'][$i],
                $_POST['prod_descripcion'][$i]
            ]);
        }

                // Registrar evento en el tracking
$stmt = $pdo->prepare("INSERT INTO solicitudes_tracking
    (solicitud_cotizacion_id, estado, usuario_id, fecha_hora, comentario)
    VALUES (?, ?, ?, NOW(), ?)");
$stmt->execute([
    $solicitud_id,
    'solicitud_cotizacion_enviada',
    $id_usuario,
    "Solicitud de cotización registrada por el usuario."
]);

        // Subir cotizaciones y guardar en BD
        $cotizaciones = [];
        for ($i=1; $i<=3; $i++) {
            if (
                empty($_POST['proveedor'.$i]) ||
                empty($_POST['precio'.$i]) ||
                !isset($_FILES['archivo'.$i]) || $_FILES['archivo'.$i]['error'] !== 0
            ) {
                throw new Exception("Cotización $i incompleta.");
            }
            // Guardar archivo
            $dir = __DIR__ . '/uploads/cotizaciones/';
            if (!is_dir($dir)) mkdir($dir, 0777, true);
            $ext = strtolower(pathinfo($_FILES['archivo'.$i]['name'], PATHINFO_EXTENSION));
            $nombre_archivo = 'cot_'.$solicitud_id.'_'.$i.'_'.uniqid().'.'.$ext;
            $destino = $dir . $nombre_archivo;
            if (!move_uploaded_file($_FILES['archivo'.$i]['tmp_name'], $destino)) {
                throw new Exception("No se pudo subir el archivo de la cotización $i.");
            }
            // Guardar cotización en BD
            $stmt = $pdo->prepare("INSERT INTO solicitudes_cotizacion_cotizaciones
                (solicitud_id, proveedor, precio, archivo)
                VALUES (?, ?, ?, ?)");
            $stmt->execute([
                $solicitud_id,
                $_POST['proveedor'.$i],
                $_POST['precio'.$i],
                $nombre_archivo
            ]);
            $cotizaciones[] = [
                'proveedor' => $_POST['proveedor'.$i],
                'precio' => $_POST['precio'.$i],
                'archivo' => $nombre_archivo
            ];
        }




        // Enviar correo a gerencia
        $mail = new PHPMailer;
        $mail->isSMTP();
        $mail->Host = 'smtp.office365.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'notificaciones@fundocol.org';
        $mail->Password = 'Rsat8700';
        $mail->SMTPSecure = 'tls';
        $mail->Port = 587;

        $mail->setFrom('notificaciones@fundocol.org', 'Fundocol ERP');
        $mail->addAddress('direccion@fundocol.org', 'Gerencia');
        $mail->isHTML(true);
        $mail->Subject = "Nueva solicitud de cotizacion de $nombre_usuario";

        $tabla_prod = "<table border='1' cellpadding='5' style='border-collapse:collapse;'><thead><tr>
            <th>Nombre</th><th>Cantidad</th><th>Descripcion</th></tr></thead><tbody>";
        foreach ($_POST['prod_nombre'] as $k => $v) {
            $tabla_prod .= "<tr>
                <td>".htmlspecialchars($v)."</td>
                <td>".htmlspecialchars($_POST['prod_cantidad'][$k])."</td>
                <td>".htmlspecialchars($_POST['prod_descripcion'][$k])."</td>
            </tr>";
        }
        $tabla_prod .= "</tbody></table>";
        $link_erp = "https://tuerp.fundocol.org/modules/solicitudes/?id=$solicitud_id";

        $logo_url = "../assets/img/logo.png"; // Pon el URL directo a tu logo
        $mail->Body = '
<div style="max-width:600px;margin:0 auto;background:#fafdff;border-radius:12px;box-shadow:0 2px 16px rgba(36,42,60,.06);font-family:sans-serif;padding:0 0 32px 0;">
    <div style="border-radius:12px 12px 0 0;background:#225a89;padding:32px 0 22px 0;text-align:center;">
        <img src="'.$logo_url.'" alt="Logo Fundocol" style="height:56px;max-width:210px;margin-bottom:10px;">
        <h2 style="color:#fff;margin:0;font-weight:600;font-size:2em;letter-spacing:0.01em;">Nueva solicitud de cotizacion</h2>
    </div>
    <div style="padding:28px 38px 12px 38px;">
        <p style="color:#234269;font-size:1.1em;margin-bottom:6px;">Tienes una nueva solicitud de cotizacion registrada desde el ERP Fundocol.</p>
        <div style="background:#f1f7fe;border-radius:9px;padding:17px 20px 11px 20px;margin-bottom:18px;">
            <b>Solicitante:</b> '.htmlspecialchars($nombre_usuario).'<br>
            <b>Proyecto/Oficina:</b> '.htmlspecialchars($_POST['proyecto']).'<br>
            <b>Fecha:</b> '.$fecha_hoy.'<br>
            <b>Necesidad:</b> '.htmlspecialchars($_POST['necesidad']).'
        </div>
        <div style="margin-bottom:18px;">
            <b style="color:#215182;font-size:1.08em;">Productos solicitados:</b>
            '.$tabla_prod.'
        </div>
        <div style="text-align:center;margin:27px 0 14px 0;">
            <a href="'.$link_erp.'" style="display:inline-block;padding:13px 34px;font-size:1.13em;font-weight:700;background:#2577c5;color:#fff;text-decoration:none;border-radius:8px;box-shadow:0 1px 7px rgba(31,38,135,0.09);letter-spacing:0.01em;">Ver solicitud en el ERP</a>
        </div>
        <div style="color:#8ca0ba;font-size:0.99em;margin-top:25px;">Por favor, ingresa al sistema para revisar o aprobar esta solicitud.<br>
        <span style="color:#b7bfc7;">Este mensaje fue generado automaticamente. No respondas a este correo.</span></div>
    </div>
</div>
';
        $mail->send();

        $mensaje_envio = "<div class='alert-success'>Solicitud enviada correctamente.</div>";

    } catch(Exception $e) {
        $mensaje_envio = "<div class='alert-danger'>Ocurrió un error: ".$e->getMessage()."</div>";
    }
}

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Solicitud de Cotización</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="icon" type="image/x-icon" href="https://erp-fundocol.fundocol.org/assets/img/Fundocol_favicon.png">
</head>
<body class="compras-solicitud-cotizacion-page">
<?php include '../../includes/navbar.php'; ?>

<main>
    <div class="odoo-container">
        <!-- Barra de fases/proceso -->
        <div class="odoo-phase-bar">
            <div class="odoo-phase-step <?= ($fase == 'cotizacion') ? 'active' : 'done' ?>">Cotización</div>
            <div class="odoo-phase-step <?= ($fase == 'enviada') ? 'active' : (($fase != 'cotizacion') ? 'done' : '') ?>">Cotización Enviada</div>
            <div class="odoo-phase-step <?= ($fase == 'aprobada') ? 'active' : (($fase == 'aprobada' || $fase == 'rechazada') ? 'done' : '') ?>">Cotización Aprobada</div>
            <div class="odoo-phase-step <?= ($fase == 'rechazada') ? 'active rejected' : '' ?>">Cotización Rechazada</div>
        </div>

        <div class="odoo-header-form">
        <form id="form-cotizacion" method="post" enctype="multipart/form-data" autocomplete="off">
            <?php if(!empty($mensaje_envio)) echo $mensaje_envio; ?>
            <div class="odoo-form-row">
                <div class="odoo-form-label">Solicitante:</div>
                <div class="odoo-form-field">
                    <input type="text" name="solicitante" value="<?= htmlspecialchars($nombre_usuario) ?>" readonly>
                </div>
            </div>
            <div class="odoo-form-row">
                <div class="odoo-form-label">Fecha:</div>
                <div class="odoo-form-field">
                    <input type="date" name="fecha" value="<?= $fecha_hoy ?>" readonly>
                </div>
            </div>
            <div class="odoo-form-row">
                <div class="odoo-form-label">Proyecto/Oficina:</div>
                <div class="odoo-form-field">
                    <input type="text" name="proyecto" required placeholder="Ej: Proyecto ABC o Oficina Central">
                </div>
            </div>
            <div class="odoo-form-row">
                <div class="odoo-form-label">Necesidad:</div>
                <div class="odoo-form-field">
                    <textarea name="necesidad" required placeholder="¿Por qué se necesita esta compra?" rows="2"></textarea>
                </div>
            </div>

            <!-- Productos -->
            <div class="odoo-products-card">
                <div class="odoo-products-title">Productos a comprar:</div>
                <table class="odoo-products-table" id="productos-table">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Cantidad</th>
                            <th>Descripción</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="productos-tbody">
                        <!-- Productos se agregan aquí -->
                    </tbody>
                </table>
                <div class="odoo-products-add-row">
                    <input type="text" id="prod-nombre" placeholder="Nombre">
                    <input type="number" id="prod-cantidad" min="1" value="1" class="prod-cantidad-input">
                    <input type="text" id="prod-descripcion" placeholder="Descripción">
                    <button type="button" class="odoo-btn-min" onclick="addProducto()">Agregar</button>
                </div>
            </div>

            <!-- Cotizaciones -->
            <div class="odoo-cot-cards-title">Adjunta 3 cotizaciones</div>
            <div class="odoo-cot-cards-row" id="cotizaciones-cards">
                <?php for ($i=1; $i<=3; $i++): ?>
                    <div class="odoo-cot-card">
                        <div class="odoo-cot-card-header"><b>Cotización <?= $i ?></b></div>
                        <div class="odoo-cot-card-field">
                            <label>Proveedor:</label>
                            <input type="text" name="proveedor<?= $i ?>" required>
                        </div>
                        <div class="odoo-cot-card-field">
                            <label>Precio:</label>
                            <input type="number" min="0" name="precio<?= $i ?>" required>
                        </div>
                        <div class="odoo-cot-card-field">
                            <label>Archivo (PDF):</label>
                            <input type="file" name="archivo<?= $i ?>" accept="application/pdf" required>
                        </div>
                    </div>
                <?php endfor; ?>
            </div>
            <div class="odoo-form-actions">
                <button type="submit" class="odoo-btn odoo-btn-wide"><i class="bi bi-send"></i> Enviar solicitud</button>
            </div>
        </form>
        </div>
    </div>
</main>

<script>
// --- Productos dinámicos ---
let productos = [];
function addProducto() {
    const nombre = document.getElementById('prod-nombre').value.trim();
    const cantidad = document.getElementById('prod-cantidad').value;
    const descripcion = document.getElementById('prod-descripcion').value.trim();
    if (!nombre || cantidad < 1) return;
    productos.push({ nombre, cantidad, descripcion });
    renderProductos();
    document.getElementById('prod-nombre').value = '';
    document.getElementById('prod-cantidad').value = 1;
    document.getElementById('prod-descripcion').value = '';
}
function removeProducto(idx) {
    productos.splice(idx, 1);
    renderProductos();
}
function renderProductos() {
    const tbody = document.getElementById('productos-tbody');
    tbody.innerHTML = '';
    productos.forEach((p, idx) => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td><input type="hidden" name="prod_nombre[]" value="${p.nombre}">${p.nombre}</td>
            <td><input type="hidden" name="prod_cantidad[]" value="${p.cantidad}">${p.cantidad}</td>
            <td><input type="hidden" name="prod_descripcion[]" value="${p.descripcion}">${p.descripcion}</td>
            <td><button type="button" class="odoo-btn-min odoo-btn-danger" onclick="removeProducto(${idx})"><i class="bi bi-trash"></i></button></td>
        `;
        tbody.appendChild(row);
    });
}
</script>
</body>
</html>
