<?php
session_start();
require_once '../../config/db.php';
require_once '../../includes/mailer.php'; // Aquí cargas tu función de correo personalizada

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../../login.php');
    exit();
}

// 1. Obtener ID por GET
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// 2. Consultar solicitud
$sql = "SELECT sc.*, u.nombre AS solicitante, u.email as solicitante_email
        FROM solicitudes_cotizacion sc
        JOIN usuarios u ON sc.solicitante_id = u.id
        WHERE sc.id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$id]);
$solicitud = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$solicitud) {
    echo "<div class='alert alert-danger'>Solicitud no encontrada.</div>";
    exit();
}

// 3. Consultar productos
$sql = "SELECT * FROM solicitudes_cotizacion_productos WHERE solicitud_id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$id]);
$productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 4. Consultar cotizaciones
$sql = "SELECT * FROM solicitudes_cotizacion_cotizaciones WHERE solicitud_id = ? ORDER BY id";
$stmt = $pdo->prepare($sql);
$stmt->execute([$id]);
$cotizaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 5. Procesar formulario de aprobación/rechazo
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $correo_destino = $solicitud['solicitante_email'];
    $nombre_solicitante = $solicitud['solicitante'];

    // Aprobar
    if (isset($_POST['aprobar'])) {
        $cotizacion_aprobada_id = intval($_POST['cotizacion_aprobada'] ?? 0);
        if ($cotizacion_aprobada_id > 0) {
            // Actualiza estado y la cotización aprobada
            $sql = "UPDATE solicitudes_cotizacion SET estado = 'aprobada', cotizacion_aprobada_id = ? WHERE id = ?";
            $pdo->prepare($sql)->execute([$cotizacion_aprobada_id, $id]);

            // TRACKING: Dirección aprobó la cotización
$stmt = $pdo->prepare("INSERT INTO solicitudes_tracking
    (solicitud_cotizacion_id, estado, usuario_id, fecha_hora, comentario)
    VALUES (?, ?, ?, NOW(), ?)");
$stmt->execute([
    $id,
    'cotizacion_aprobada',
    $_SESSION['usuario_id'],
    "Cotización aprobada por Dirección. Cotización ID: $cotizacion_aprobada_id"
]);


            // Buscar datos de la cotización aprobada
            $sql = "SELECT * FROM solicitudes_cotizacion_cotizaciones WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$cotizacion_aprobada_id]);
            $cot_aprobada = $stmt->fetch(PDO::FETCH_ASSOC);

            // Preparar correo
            $asunto = 'Tu cotizacion ha sido aprobada - ERP Fundocol';
            $mensaje = "
                <p>Hola <b>$nombre_solicitante</b>,</p>
                <p>Tu solicitud de cotizacion <b>#{$solicitud['id']}</b> ha sido <span style='color:green'><b>aprobada</b></span>.</p>
                <p><b>Cotizacion seleccionada:</b></p>
                <ul>
                    <li><b>Proveedor:</b> {$cot_aprobada['proveedor']}</li>
                    <li><b>Precio:</b> $" . number_format($cot_aprobada['precio'],0,',','.') . "</li>
                </ul>
                <p>Puedes ingresar a la aplicacion y realizar la <b>solicitud de compra</b> usando esta cotizacion aprobada.</p>
                <p>Saludos,<br>ERP Fundocol</p>
            ";
            // ENVÍO DE CORREO
            enviarCorreoFundocol(
    $correo_destino,
    $nombre_solicitante,
    $asunto,
    $mensaje
);


            $msg = "<div class='alert alert-success'>¡Solicitud y cotización aprobadas! Correo enviado al usuario.</div>";
            header("Location: ../../modules/pendientes/index.php");
            exit();
        } else {
            $msg = "<div class='alert alert-danger'>Debes seleccionar una cotización para aprobar.</div>";
        }
    }
    // Rechazar
    if (isset($_POST['rechazar'])) {
        $comentario = trim($_POST['comentario']);
        // Actualiza estado a rechazado y guarda comentario
        $sql = "UPDATE solicitudes_cotizacion SET estado = 'rechazada', comentario_rechazo = ? WHERE id = ?";
        $pdo->prepare($sql)->execute([$comentario, $id]);

        // TRACKING: Dirección rechazó la cotización
$stmt = $pdo->prepare("INSERT INTO solicitudes_tracking
    (solicitud_cotizacion_id, estado, usuario_id, fecha_hora, comentario)
    VALUES (?, ?, ?, NOW(), ?)");
$stmt->execute([
    $id,
    'cotizacion_rechazada',
    $_SESSION['usuario_id'],
    "Cotización rechazada por Dirección. Motivo: $comentario"
]);


        // Preparar correo
        $asunto = 'Tu cotización ha sido rechazada - ERP Fundocol';
        $mensaje = "
            <p>Hola <b>$nombre_solicitante</b>,</p>
            <p>Lamentamos informarte que tu solicitud de cotizacion <b>#{$solicitud['id']}</b> ha sido <span style='color:#e53a3a'><b>rechazada</b></span>.</p>
            <p><b>Motivo del rechazo:</b></p>
            <blockquote style='color:#e53a3a'>{$comentario}</blockquote>
            <p>Si tienes dudas, por favor comunícate con el área correspondiente.</p>
            <p>Saludos,<br>ERP Fundocol</p>
        ";
        // ENVÍO DE CORREO
        enviarCorreoFundocol(
    $correo_destino,
    $nombre_solicitante,
    $asunto,
    $mensaje
);


        $msg = "<div class='alert alert-danger'>Solicitud rechazada. Correo enviado al usuario.</div>";
        header("Location: ../../modules/pendientes/index.php");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Aprobar Cotización | ERP Fundocol</title>
    <link rel="stylesheet" href="../../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="icon" type="image/x-icon" href="https://erp-fundocol.fundocol.org/assets/img/Fundocol_favicon.png">
    <script>
        function showRechazo() {
            document.getElementById('comentario_rechazo').classList.add('show-rechazo');
            document.getElementById('btn_aprobar').disabled = true;
        }
        function hideRechazo() {
            document.getElementById('comentario_rechazo').classList.remove('show-rechazo');
            document.getElementById('btn_aprobar').disabled = false;
        }
    </script>
</head>
<body class="compras-aprobacion-cotizacion-page">
    <?php include '../../includes/navbar.php'; ?>
    <div class="container-fluid">
        <div class="erp-card aprobacion-cotizacion-card">
            <div class="aprobacion-header">
                <div class="erp-title mb-1">
                <i class="bi bi-file-earmark-text"></i>
                Aprobación de Cotización #<?= $solicitud['id'] ?>
            </div>
                <div class="aprobacion-subtitle">Revisión y selección de la mejor oferta</div>
            </div>
            <?= $msg ?>
            <div class="aprobacion-section">
                <div class="section-title"><i class="bi bi-info-circle"></i> Resumen</div>
                <div class="solicitud-meta-grid">
                    <div class="meta-item">
                        <span class="meta-label">Solicitante</span>
                        <span class="meta-value"><?= htmlspecialchars($solicitud['solicitante']) ?></span>
                    </div>
                    <div class="meta-item">
                        <span class="meta-label">Fecha</span>
                        <span class="meta-value"><?= htmlspecialchars($solicitud['fecha']) ?></span>
                    </div>
                    <div class="meta-item">
                        <span class="meta-label">Proyecto/Oficina</span>
                        <span class="meta-value"><?= htmlspecialchars($solicitud['proyecto_oficina']) ?></span>
                    </div>
                    <div class="meta-item meta-item-wide">
                        <span class="meta-label">Necesidad</span>
                        <span class="meta-value"><?= htmlspecialchars($solicitud['necesidad']) ?></span>
                    </div>
                </div>
            </div>

            <div class="aprobacion-section">
                <div class="section-title"><i class="bi bi-box-seam"></i> Productos solicitados</div>
                <div class="table-responsive">
                    <table class="erp-prod-table mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Nombre</th>
                                <th>Cantidad</th>
                                <th>Descripción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($productos as $i => $prod): ?>
                            <tr>
                                <td><?= $i+1 ?></td>
                                <td><?= htmlspecialchars($prod['nombre']) ?></td>
                                <td><?= htmlspecialchars($prod['cantidad']) ?></td>
                                <td><?= htmlspecialchars($prod['descripcion']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <form method="post">
                <div class="aprobacion-section">
                    <div class="section-title"><i class="bi bi-clipboard-check"></i> Cotizaciones recibidas</div>
                    <div class="cotizaciones-grid">
                        <?php foreach ($cotizaciones as $i => $cot): ?>
                        <label class="cotiz-option">
                            <input class="form-check-input" type="radio" name="cotizacion_aprobada"
                                   value="<?= $cot['id'] ?>" id="cotiz<?= $cot['id'] ?>" required>
                            <span class="cotiz-card">
                                <span class="cotiz-header">
                                    <span class="cotiz-title">Cotización <?= $i+1 ?></span>
                                    <span class="cotiz-chip">Seleccionar</span>
                                </span>
                                <span class="cotiz-body">
                                    <span class="cotiz-row">
                                        <span class="cotiz-label">Proveedor</span>
                                        <span class="cotiz-value"><?= htmlspecialchars($cot['proveedor']) ?></span>
                                    </span>
                                    <span class="cotiz-row">
                                        <span class="cotiz-label">Precio</span>
                                        <span class="cotiz-precio"><?= '$' . number_format($cot['precio'],0,',','.') ?></span>
                                    </span>
                                </span>
                                <span class="cotiz-actions">
                                    <a href="uploads/cotizaciones/<?= urlencode($cot['archivo']) ?>"
                                       target="_blank" class="btn btn-pdf-mini">
                                        <i class="bi bi-file-earmark-pdf"></i> PDF
                                    </a>
                                </span>
                            </span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="aprob-rechazo-btns">
                    <button type="submit" name="aprobar" class="btn-erp" id="btn_aprobar" onclick="hideRechazo()">
                        <i class="bi bi-check-circle"></i> Aprobar
                    </button>
                    <button type="button" class="btn-erp btn-rechazar" onclick="showRechazo()">
                        <i class="bi bi-x-circle"></i> Rechazar
                    </button>
                </div>
                <div id="comentario_rechazo" class="comentario-rechazo mt-4">
                    <label for="comentario" class="label-rechazo">Motivo de rechazo:</label>
                    <textarea name="comentario" id="comentario" class="textarea-rechazo" rows="3" placeholder="Explica el motivo del rechazo..."></textarea>
                    <div class="rechazo-btns mt-2">
                        <button type="submit" name="rechazar" class="btn-rechazar">
                            <i class="bi bi-x-circle"></i> Confirmar Rechazo
                        </button>
                        <button type="button" class="btn-cancelar" onclick="hideRechazo()">Cancelar</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
