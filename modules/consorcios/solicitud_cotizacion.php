<?php
include '../../includes/auth.php';
require_once '../../includes/tracking.php';
require_once '../../config/db.php';
require_once __DIR__ . '/../../includes/mailer.php';

date_default_timezone_set('America/Bogota');

$nombre_usuario = $_SESSION['usuario_nombre'] ?? 'Usuario';
$id_usuario = $_SESSION['usuario_id'] ?? 1;
$fecha_hoy = date('Y-m-d');
$fase = "cotizacion";
$mensaje_envio = '';

// ===============================
// LISTA FIJA DE CONSORCIOS
// (igual estilo compras fijas)
// ===============================
$consorcios = [
    "Negocios Verdes",
    "Consorcio Aburra 2025",
    "Bellos Amaneceres",
    "Plaza Caicedo",
    "Arbol de vida"
];

// Para conservar valores si falla el envío
$proyecto_val = $_POST['proyecto'] ?? '';
$necesidad_val = $_POST['necesidad'] ?? '';

// --- BACKEND: Procesa el envío del formulario ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $proyecto = trim($_POST['proyecto'] ?? '');
        $necesidad = trim($_POST['necesidad'] ?? '');

        // Validación mínima
        if (
            $proyecto === '' ||
            $necesidad === '' ||
            !isset($_POST['prod_nombre']) ||
            count($_POST['prod_nombre']) < 1
        ) {
            throw new Exception("Por favor, completa todos los campos y agrega al menos un producto.");
        }

        // Validar que el consorcio exista en la lista fija
        if (!in_array($proyecto, $consorcios, true)) {
            throw new Exception("Debes seleccionar un consorcio válido de la lista.");
        }

        // Insertar solicitud principal
        $stmt = $pdo->prepare("INSERT INTO solicitudes_cotizacion_consorcios
            (solicitante_id, fecha, consorcio, necesidad)
            VALUES (?, ?, ?, ?)");
        $stmt->execute([
            $id_usuario,
            $fecha_hoy,
            $proyecto,
            $necesidad
        ]);

        $solicitud_id = $pdo->lastInsertId();

        // Insertar productos
        foreach ($_POST['prod_nombre'] as $i => $nombre) {
            $nombre = trim($nombre ?? '');
            $cantidad = intval($_POST['prod_cantidad'][$i] ?? 0);
            $descripcion = trim($_POST['prod_descripcion'][$i] ?? '');

            if ($nombre === '' || $cantidad < 1) {
                throw new Exception("Hay productos incompletos o con cantidad inválida.");
            }

            $stmt = $pdo->prepare("INSERT INTO solicitudes_cotizacion_consorcios_productos
                (solicitud_id, nombre, cantidad, descripcion)
                VALUES (?, ?, ?, ?)");
            $stmt->execute([
                $solicitud_id,
                $nombre,
                $cantidad,
                $descripcion
            ]);
        }

        // Tracking
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
        for ($i = 1; $i <= 3; $i++) {
            $proveedor = trim($_POST['proveedor' . $i] ?? '');
            $precio = trim($_POST['precio' . $i] ?? '');

            if (
                $proveedor === '' ||
                $precio === '' ||
                !isset($_FILES['archivo' . $i]) ||
                $_FILES['archivo' . $i]['error'] !== 0
            ) {
                throw new Exception("Cotización $i incompleta.");
            }

            $dir = __DIR__ . '/uploads/cotizaciones/';
            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
            }

            $ext = strtolower(pathinfo($_FILES['archivo' . $i]['name'], PATHINFO_EXTENSION));
            if ($ext !== 'pdf') {
                throw new Exception("La cotización $i debe estar en PDF.");
            }

            $nombre_archivo = 'cot_' . $solicitud_id . '_' . $i . '_' . uniqid() . '.' . $ext;
            $destino = $dir . $nombre_archivo;

            if (!move_uploaded_file($_FILES['archivo' . $i]['tmp_name'], $destino)) {
                throw new Exception("No se pudo subir el archivo de la cotización $i.");
            }

            $stmt = $pdo->prepare("INSERT INTO solicitudes_cotizacion_consorcios_cotizaciones
                (solicitud_id, proveedor, precio, archivo)
                VALUES (?, ?, ?, ?)");
            $stmt->execute([
                $solicitud_id,
                $proveedor,
                $precio,
                $nombre_archivo
            ]);

            $cotizaciones[] = [
                'proveedor' => $proveedor,
                'precio'    => $precio,
                'archivo'   => $nombre_archivo
            ];
        }

        // Construir tabla de productos para el correo
        $tabla_prod = "
        <table style='width:100%;border-collapse:collapse;font-family:Segoe UI,Arial,sans-serif;font-size:14px;margin-top:10px;'>
            <thead>
                <tr style='background:#eaf3ff;color:#183357;'>
                    <th style='padding:10px;border:1px solid #d9e6f5;'>Nombre</th>
                    <th style='padding:10px;border:1px solid #d9e6f5;'>Cantidad</th>
                    <th style='padding:10px;border:1px solid #d9e6f5;'>Descripción</th>
                </tr>
            </thead>
            <tbody>";

        foreach ($_POST['prod_nombre'] as $k => $v) {
            $tabla_prod .= "
                <tr>
                    <td style='padding:10px;border:1px solid #d9e6f5;'>" . htmlspecialchars($v) . "</td>
                    <td style='padding:10px;border:1px solid #d9e6f5;text-align:center;'>" . htmlspecialchars($_POST['prod_cantidad'][$k]) . "</td>
                    <td style='padding:10px;border:1px solid #d9e6f5;'>" . htmlspecialchars($_POST['prod_descripcion'][$k]) . "</td>
                </tr>";
        }

        $tabla_prod .= "
            </tbody>
        </table>";

        $link_erp = "https://erp.fundocol.org/modules/pendientes/index.php";

        $html = '
        <div style="font-family:Segoe UI,Arial,sans-serif;background:#f5f8fc;padding:28px;">
            <div style="max-width:700px;margin:0 auto;background:#ffffff;border-radius:18px;box-shadow:0 8px 28px rgba(36,42,60,.10);overflow:hidden;border:1px solid #e1ebf5;">

                <div style="background:linear-gradient(120deg,#114b85 0%,#0ea5e9 100%);padding:26px 30px;color:#fff;">
                    <div style="font-size:22px;font-weight:700;">Nueva solicitud de cotización</div>
                    <div style="font-size:14px;opacity:.95;margin-top:6px;">ERP Fundocol • Consorcios</div>
                </div>

                <div style="padding:28px 30px;">
                    <p style="margin:0 0 16px 0;color:#2b3c4d;font-size:15px;line-height:1.6;">
                        Se ha registrado una nueva solicitud de cotización en el módulo de <b>Consorcios</b>.
                    </p>

                    <div style="background:#f7fbff;border:1px solid #d8e8f8;border-radius:12px;padding:16px 18px;margin-bottom:20px;">
                        <div style="margin-bottom:8px;"><b>Solicitante:</b> ' . htmlspecialchars($nombre_usuario) . '</div>
                        <div style="margin-bottom:8px;"><b>Consorcio:</b> ' . htmlspecialchars($proyecto) . '</div>
                        <div style="margin-bottom:8px;"><b>Fecha:</b> ' . htmlspecialchars($fecha_hoy) . '</div>
                        <div><b>Necesidad:</b> ' . nl2br(htmlspecialchars($necesidad)) . '</div>
                    </div>

                    <div style="margin-bottom:18px;">
                        <div style="font-size:16px;font-weight:700;color:#1f4f82;margin-bottom:10px;">Productos solicitados</div>
                        ' . $tabla_prod . '
                    </div>

                    <div style="text-align:center;margin:26px 0 8px 0;">
                        <a href="' . $link_erp . '" style="display:inline-block;background:#1f78d1;color:#fff;text-decoration:none;padding:12px 26px;border-radius:10px;font-weight:700;">
                            Ver solicitud en el ERP
                        </a>
                    </div>

                    <div style="margin-top:22px;font-size:12px;color:#8ca0ba;line-height:1.5;">
                        Este mensaje fue generado automáticamente por el ERP Fundocol. No respondas este correo.
                    </div>
                </div>
            </div>
        </div>';

        // Enviar correo usando el mailer central
        $correo_ok = enviarCorreoFundocol(
            'direccion@fundocol.org',
            'Dirección Fundocol',
            "Nueva solicitud de cotización de $nombre_usuario",
            $html
        );

        if ($correo_ok) {
            $mensaje_envio = "<div class='alert-success'>Solicitud enviada correctamente.</div>";
            $proyecto_val = '';
            $necesidad_val = '';
        } else {
            $mensaje_envio = "<div class='alert-danger'>La solicitud se registró, pero falló el envío del correo.</div>";
        }

    } catch (Exception $e) {
        $mensaje_envio = "<div class='alert-danger'>Ocurrió un error: " . htmlspecialchars($e->getMessage()) . "</div>";
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
</head>
<body class="consorcios-solicitud-cotizacion-page">
<?php include '../../includes/navbar.php'; ?>

<main>
    <div class="odoo-container">
        <div class="odoo-phase-bar">
            <div class="odoo-phase-step <?= ($fase == 'cotizacion') ? 'active' : 'done' ?>">Cotización</div>
            <div class="odoo-phase-step <?= ($fase == 'enviada') ? 'active' : (($fase != 'cotizacion') ? 'done' : '') ?>">Cotización Enviada</div>
            <div class="odoo-phase-step <?= ($fase == 'aprobada') ? 'active' : (($fase == 'aprobada' || $fase == 'rechazada') ? 'done' : '') ?>">Cotización Aprobada</div>
            <div class="odoo-phase-step <?= ($fase == 'rechazada') ? 'active rejected' : '' ?>">Cotización Rechazada</div>
        </div>

        <div class="odoo-header-form">
            <form id="form-cotizacion" method="post" enctype="multipart/form-data" autocomplete="off">
                <?php if (!empty($mensaje_envio)) echo $mensaje_envio; ?>

                <div class="odoo-form-row">
                    <div class="odoo-form-label">Solicitante:</div>
                    <div class="odoo-form-field">
                        <input type="text" name="solicitante" value="<?= htmlspecialchars($nombre_usuario) ?>" readonly>
                    </div>
                </div>

                <div class="odoo-form-row">
                    <div class="odoo-form-label">Fecha:</div>
                    <div class="odoo-form-field">
                        <input type="date" name="fecha" value="<?= htmlspecialchars($fecha_hoy) ?>" readonly>
                    </div>
                </div>

                <div class="odoo-form-row">
                    <div class="odoo-form-label">Consorcio:</div>
                    <div class="odoo-form-field">
                        <select name="proyecto" required>
                            <option value="">Seleccione un consorcio</option>
                            <?php foreach ($consorcios as $consorcio): ?>
                                <option value="<?= htmlspecialchars($consorcio) ?>" <?= ($proyecto_val === $consorcio) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($consorcio) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="odoo-form-row">
                    <div class="odoo-form-label">Necesidad:</div>
                    <div class="odoo-form-field">
                        <textarea name="necesidad" required placeholder="¿Por qué se necesita esta compra?" rows="2" maxlength="500"><?= htmlspecialchars($necesidad_val) ?></textarea>
                    </div>
                </div>

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
                        <tbody id="productos-tbody"></tbody>
                    </table>

                    <div class="odoo-products-add-row">
                        <input type="text" id="prod-nombre" placeholder="Nombre">
                        <input type="number" id="prod-cantidad" min="1" value="1" style="width:54px;">
                        <input type="text" id="prod-descripcion" placeholder="Descripción" maxlength="500">
                        <button type="button" class="odoo-btn-min" onclick="addProducto()">Agregar</button>
                    </div>
                </div>

                <div class="odoo-cot-cards-title">Adjunta 3 cotizaciones</div>
                <div class="odoo-cot-cards-row" id="cotizaciones-cards">
                    <?php for ($i = 1; $i <= 3; $i++): ?>
                        <div class="odoo-cot-card">
                            <div class="odoo-cot-card-header"><b>Cotización <?= $i ?></b></div>

                            <div class="odoo-cot-card-field">
                                <label>Proveedor:</label>
                                <input type="text" name="proveedor<?= $i ?>" required value="<?= htmlspecialchars($_POST['proveedor' . $i] ?? '') ?>">
                            </div>

                            <div class="odoo-cot-card-field">
                                <label>Precio:</label>
                                <input type="number" min="0" name="precio<?= $i ?>" required value="<?= htmlspecialchars($_POST['precio' . $i] ?? '') ?>">
                            </div>

                            <div class="odoo-cot-card-field">
                                <label>Archivo (PDF):</label>
                                <input type="file" name="archivo<?= $i ?>" accept="application/pdf" required>
                            </div>
                        </div>
                    <?php endfor; ?>
                </div>

                <div class="odoo-form-actions">
                    <button type="submit" class="odoo-btn btn-enviar-solicitud">
                        <i class="bi bi-send"></i> Enviar solicitud
                    </button>
                </div>
            </form>
        </div>
    </div>
</main>

<script>
let productos = [];

function escapeHtml(text) {
    const div = document.createElement('div');
    div.innerText = text;
    return div.innerHTML;
}

function addProducto() {
    const nombre = document.getElementById('prod-nombre').value.trim();
    const cantidad = parseInt(document.getElementById('prod-cantidad').value, 10);
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
            <td><input type="hidden" name="prod_nombre[]" value="${escapeHtml(p.nombre)}">${escapeHtml(p.nombre)}</td>
            <td><input type="hidden" name="prod_cantidad[]" value="${p.cantidad}">${p.cantidad}</td>
            <td><input type="hidden" name="prod_descripcion[]" value="${escapeHtml(p.descripcion)}">${escapeHtml(p.descripcion)}</td>
            <td><button type="button" class="odoo-btn-min btn-remove-producto" onclick="removeProducto(${idx})"><i class="bi bi-trash"></i></button></td>
        `;
        tbody.appendChild(row);
    });
}
</script>
</body>
</html>