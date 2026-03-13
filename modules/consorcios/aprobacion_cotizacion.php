<?php
session_start();
require_once '../../config/db.php';
require_once '../../includes/mailer.php';

if(!isset($_SESSION['usuario_id'])){
    header('Location: ../../login.php');
    exit();
}

// =====================================================
// 1. Obtener solicitud
// =====================================================
$id = intval($_GET['id'] ?? 0);

$sql = "SELECT sc.*, u.nombre AS solicitante, u.email as solicitante_email
        FROM solicitudes_cotizacion_consorcios sc
        JOIN usuarios u ON sc.solicitante_id = u.id
        WHERE sc.id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$id]);
$solicitud = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$solicitud){
    echo "<div class='alert alert-danger'>Solicitud no encontrada.</div>";
    exit();
}

// =====================================================
// 2. Productos
// =====================================================
$stmt = $pdo->prepare("SELECT * FROM solicitudes_cotizacion_consorcios_productos WHERE solicitud_id = ?");
$stmt->execute([$id]);
$productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// =====================================================
// 3. Cotizaciones
// =====================================================
$stmt = $pdo->prepare("SELECT * FROM solicitudes_cotizacion_consorcios_cotizaciones
                       WHERE solicitud_id = ? ORDER BY id");
$stmt->execute([$id]);
$cotizaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Base URL para cotizaciones (consistencia en módulos de aprobación)
$cotizacion_base = '../../modules/consorcios/uploads/cotizaciones/';

// =====================================================
// 4. Procesar acciones
// =====================================================
$msg                 = '';
$correo_destino      = $solicitud['solicitante_email'];
$nombre_solicitante  = $solicitud['solicitante'];

if($_SERVER['REQUEST_METHOD'] === 'POST'){

    // =====================================================
    //  🔥 APROBAR COTIZACION POR DIRECCION
    // =====================================================
    if(isset($_POST['aprobar'])){
        
        $cotizacion_aprobada = intval($_POST['cotizacion_aprobada'] ?? 0);

        if($cotizacion_aprobada <= 0){
            $msg = "<div class='alert alert-danger'>Selecciona una cotizacion para aprobar.</div>";

        } else {

            // Guardar estado, cotizacion seleccionada y fecha
            $pdo->prepare("UPDATE solicitudes_cotizacion_consorcios 
                           SET estado='aprobada', 
                               cotizacion_aprobada_id=?, 
                               fecha_aprobacion_direccion = NOW()
                           WHERE id=?")
            ->execute([$cotizacion_aprobada, $id]);

            // Registrar en tracking
            $pdo->prepare("INSERT INTO solicitudes_tracking
                (solicitud_cotizacion_id, estado, usuario_id, fecha_hora, comentario)
                VALUES (?, 'cotizacion_aprobada', ?, NOW(), ?)")
            ->execute([
                $id,
                $_SESSION['usuario_id'],
                "Cotizacion aprobada por Direccion. Cotizacion ID: $cotizacion_aprobada"
            ]);

            // Info cotizacion aprobada
            $stmt = $pdo->prepare("SELECT * FROM solicitudes_cotizacion_consorcios_cotizaciones WHERE id=?");
            $stmt->execute([$cotizacion_aprobada]);
            $cot_selected = $stmt->fetch(PDO::FETCH_ASSOC);

            // ✉ Correo Profesional al usuario
            $asunto = "Cotizacion aprobada | Solicitud #$id";
            $mensaje = '
            <div style="font-family:Arial,Helvetica,sans-serif;max-width:620px;margin:auto;padding:20px;
            background:#ffffff;border-radius:10px;border:1px solid #e5e7eb">
                
                <div style="background:#0B3F8F;padding:25px;border-radius:10px 10px 0 0;text-align:center;color:white">
                    <h2 style="margin:0;font-size:22px;font-weight:bold">Cotizacion aprobada</h2>
                    <p style="margin:0;font-size:14px;opacity:.9">ERP Fundocol</p>
                </div>
                
                <p style="font-size:15px;color:#1f2937">
                    Hola <b>'.$nombre_solicitante.'</b>, tu cotizacion ha sido aprobada por Direccion.
                </p>

                <div style="background:#f7f9fc;padding:14px;border-radius:8px;font-size:14px;margin-bottom:15px">
                    <b>Solicitud:</b> #'.$id.'<br>
                    <b>Fecha aprobacion:</b> '.date("Y-m-d H:i").'<br>
                    <b>Proveedor seleccionado:</b> '.$cot_selected['proveedor'].'<br>
                    <b>Valor aprobado:</b> $'.number_format($cot_selected['precio'],0,',','.').'
                </div>

                <p style="font-size:15px;color:#374151">
                    Ahora puedes continuar con la fase de <b>Solicitud de Compra</b> en el ERP.
                </p>

                <div style="text-align:center;margin:22px 0">
                    <a href="https://erp.fundocol.org/modulos/consorcios/aprobacion_cotizacion.php?id='.$id.'" 
                    style="background:#0059d4;color:white;text-decoration:none;padding:12px 28px;font-size:15px;
                    font-weight:bold;border-radius:8px;display:inline-block">Ver solicitud</a>
                </div>

                <p style="font-size:12px;color:#6b7280;text-align:center;padding-top:15px;border-top:1px solid #e5e7eb">
                    Mensaje automatico del ERP Fundocol. No responder.
                </p>
            </div>';

            enviarCorreoFundocol($correo_destino, $nombre_solicitante, $asunto, $mensaje);

            header("Location: ../../modules/pendientes/index.php");
            exit();
        }
    }

    // =====================================================
    // ❌ RECHAZAR
    // =====================================================
    if(isset($_POST['rechazar'])){

        $comentario = trim($_POST['comentario']);

        $pdo->prepare("UPDATE solicitudes_cotizacion_consorcios 
                       SET estado='rechazada', comentario_rechazo=? 
                       WHERE id=?")->execute([$comentario, $id]);

        // Tracking
        $pdo->prepare("INSERT INTO solicitudes_tracking
            (solicitud_cotizacion_id, estado, usuario_id, fecha_hora, comentario)
            VALUES (?, 'cotizacion_rechazada', ?, NOW(), ?)")
        ->execute([
            $id,
            $_SESSION['usuario_id'],
            "Cotizacion rechazada por Direccion. Motivo: $comentario"
        ]);

        $asunto  = "Cotizacion rechazada | Solicitud #$id";
        $mensaje = "
        <div style='font-family:Arial;background:#fff2f1;padding:22px;border-radius:8px;border:1px solid #fca5a5'>
            <h3 style='color:#c53030;margin-bottom:8px'>Cotizacion rechazada</h3>
            <p>Hola <b>$nombre_solicitante</b>, tu cotizacion ha sido rechazada.</p>
            <p><b>Motivo:</b> $comentario</p>
        </div>";

        enviarCorreoFundocol($correo_destino, $nombre_solicitante, $asunto, $mensaje);

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
    <!-- estilos movidos a assets/css/style.css -->
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
<body class="consorcios-aprobacion-cotizacion-page">
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
                        <span class="meta-label">Consorcio</span>
                        <span class="meta-value"><?= htmlspecialchars($solicitud['consorcio']) ?></span>
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
                                    <a href="<?= $cotizacion_base . urlencode($cot['archivo']) ?>"
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

