<?php
include '../../../includes/navbar.php';
require_once '../../../config/db.php';
require_once '../../../includes/phpmailer.php';

$id = intval($_GET['id'] ?? 0);
$mensaje = "";
$accion_realizada = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    $comentario = trim($_POST['comentario'] ?? '');
    $solicitud_id = intval($_POST['solicitud_id'] ?? 0);

    $sql = "SELECT s.*, u.nombre AS usuario, u.email, v.nombre AS vehiculo, v.placa
            FROM vehiculos_solicitudes s
            JOIN usuarios u ON s.solicitante_id = u.id
            JOIN vehiculos v ON s.vehiculo_id = v.id
            WHERE s.id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$solicitud_id]);
    $solicitud = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$solicitud) {
        $mensaje = "Solicitud no encontrada.";
    } else {
        $solicitante_email = $solicitud['email'] ?? '';
        $asunto = "Estado de su Solicitud de Vehiculo";
        $sistema_url = "http://localhost/erp-fundocol/";

if ($accion == 'aprobar') {
    $pdo->prepare("UPDATE vehiculos_solicitudes SET estado = 'aprobado' WHERE id = ?")->execute([$solicitud_id]);
    $accion_realizada = 'aprobada';
    // Correo para el solicitante
    $html = "<p>Hola <b>".htmlspecialchars($solicitud['usuario'])."</b>,</p>
        <p>Tu solicitud de vehiculo ha sido <b>APROBADA</b>.</p>
        <p>Vehiculo: <b>".htmlspecialchars($solicitud['vehiculo'])." (".htmlspecialchars($solicitud['placa']).")</b></p>
        <p>Desde: <b>".htmlspecialchars($solicitud['fecha_inicio'])."</b> hasta <b>".htmlspecialchars($solicitud['fecha_fin'])."</b></p>
        <a href='$sistema_url' style='display:inline-block;margin:12px 0 0 0;padding:10px 28px;background:#0ea5e9;color:#fff;border-radius:9px;font-weight:700;text-decoration:none;'>Ir al sistema Fundocol</a>
        <p style='margin-top:18px;'><small>Este es un mensaje automatico. No responda este correo.</small></p>";
    enviarCorreoFundocol($solicitante_email, $solicitud['usuario'], $asunto, $html);

    // Correo para dirección
    $asunto_dir = "Vehículo asignado a " . htmlspecialchars($solicitud['usuario']);
    $html_dir = "<p>Se ha <b>aprobado</b> el prestamo del vehiculo:</p>
        <ul>
            <li><b>Vehiculo:</b> ".htmlspecialchars($solicitud['vehiculo'])." (".htmlspecialchars($solicitud['placa']).")</li>
            <li><b>Solicitante:</b> ".htmlspecialchars($solicitud['usuario'])."</li>
            <li><b>Desde:</b> ".htmlspecialchars($solicitud['fecha_inicio'])."</li>
            <li><b>Hasta:</b> ".htmlspecialchars($solicitud['fecha_fin'])."</li>
        </ul>
        <a href='$sistema_url' style='display:inline-block;margin:12px 0 0 0;padding:10px 28px;background:#0ea5e9;color:#fff;border-radius:9px;font-weight:700;text-decoration:none;'>Ver en el sistema Fundocol</a>
        <p style='margin-top:18px;'><small>Este es un mensaje automatico. No responda este correo.</small></p>";
    enviarCorreoFundocol("direccion@fundocol.org", "Dirección Fundocol", $asunto_dir, $html_dir);

    $mensaje = "✅ Solicitud aprobada. Se notificó al solicitante y a Dirección.";
    
} elseif ($accion == 'rechazar') {
            $pdo->prepare("UPDATE vehiculos_solicitudes SET estado = 'rechazado', comentario = ? WHERE id = ?")->execute([$comentario, $solicitud_id]);
            $accion_realizada = 'rechazada';
            $html = "<p>Hola <b>".htmlspecialchars($solicitud['usuario'])."</b>,</p>
                <p>Tu solicitud de vehiculo fue <b>RECHAZADA</b>.</p>
                <p>Motivo de rechazo: <b>".htmlspecialchars($comentario)."</b></p>
                <p>Vehiculo: <b>".htmlspecialchars($solicitud['vehiculo'])." (".htmlspecialchars($solicitud['placa']).")</b></p>
                <a href='$sistema_url' style='display:inline-block;margin:12px 0 0 0;padding:10px 28px;background:#ef4444;color:#fff;border-radius:9px;font-weight:700;text-decoration:none;'>Ir al sistema Fundocol</a>
                <p style='margin-top:18px;'><small>Este es un mensaje automatico. No responda este correo.</small></p>";
            enviarCorreoFundocol($solicitante_email, $solicitud['usuario'], $asunto, $html);
            $mensaje = "❌ Solicitud rechazada y notificada al solicitante.";
        }
    }
}

// Cargar solicitud actualizada
$sql = "SELECT s.*, u.nombre AS usuario, u.email, v.nombre AS vehiculo, v.placa
        FROM vehiculos_solicitudes s
        JOIN usuarios u ON s.solicitante_id = u.id
        JOIN vehiculos v ON s.vehiculo_id = v.id
        WHERE s.id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$id]);
$solicitud = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$solicitud) {
    echo "<div class='solicitud-no-encontrada'>Solicitud no encontrada.</div>";
    exit;
}
$estado_actual = strtolower($solicitud['estado']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Revisar Solicitud de Vehiculo</title>
    <link rel="stylesheet" href="../../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../../assets/css/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="icon" type="image/x-icon" href="https://erp-fundocol.fundocol.org/assets/img/Fundocol_favicon.png">
</head>
<body class="vehiculo-aprobar-solicitud-page">
<div class="navbar-spacer"></div>
<div class="solicitud-card">
    <div class="center">
        <div class="badge-estado estado-<?= $estado_actual ?>">
            <?php
            if ($estado_actual == 'pendiente')    echo '<i class="bi bi-hourglass-split"></i> PENDIENTE';
            elseif ($estado_actual == 'aprobado') echo '<i class="bi bi-patch-check-fill"></i> APROBADO';
            elseif ($estado_actual == 'rechazado') echo '<i class="bi bi-x-octagon-fill"></i> RECHAZADO';
            ?>
        </div>
    </div>
    <div class="solicitud-title">
        <i class="bi bi-clipboard-check"></i> Revisar Solicitud de Vehículo
    </div>
    <?php if($mensaje): ?>
        <div class="<?= strpos($mensaje,'aprobada')!==false ? 'msg':'msg-err' ?>">
            <?= $mensaje ?>
        </div>
    <?php endif; ?>
    <div class="solicitud-info">
        <div><span class="solicitud-label">Solicitante:</span> <?= htmlspecialchars($solicitud['usuario']) ?></div>
        <div><span class="solicitud-label">Correo:</span> <?= htmlspecialchars($solicitud['email']) ?></div>
        <div><span class="solicitud-label">Vehículo:</span> <?= htmlspecialchars($solicitud['vehiculo']) ?> (<?= htmlspecialchars($solicitud['placa']) ?>)</div>
        <div><span class="solicitud-label">Desde:</span> <?= htmlspecialchars($solicitud['fecha_inicio']) ?></div>
        <div><span class="solicitud-label">Hasta:</span> <?= htmlspecialchars($solicitud['fecha_fin']) ?></div>
        <div><span class="solicitud-label">Motivo:</span> <?= nl2br(htmlspecialchars($solicitud['motivo'])) ?></div>
        <?php if($solicitud['estado']=='rechazado' && $solicitud['comentario']): ?>
            <div class="comentario-rechazo-wrap">
                <span class="solicitud-label">Comentario de rechazo:</span>
                <span class="comentario-rechazo-text"><?= nl2br(htmlspecialchars($solicitud['comentario'])) ?></span>
            </div>
        <?php endif; ?>
    </div>
    <?php if($solicitud['estado']=='pendiente'): ?>
        <form method="post" class="form-rechazo">
            <input type="hidden" name="solicitud_id" value="<?= $solicitud['id'] ?>">
            <div class="btns-aprobar">
                <button type="submit" name="accion" value="aprobar" class="btn-aprobar"><i class="bi bi-check-circle"></i> Aprobar</button>
                <button type="button" class="btn-rechazar" id="btnShowRechazar"><i class="bi bi-x-circle"></i> Rechazar</button>
            </div>
            <div id="rechazar-campo" class="rechazar-campo">
                <label for="comentario" class="rechazo-label">Motivo de rechazo (requerido):</label>
                <textarea name="comentario" id="comentario" rows="2"></textarea>
                <div class="rechazo-actions">
                    <button type="submit" name="accion" value="rechazar" class="btn-rechazar"><i class="bi bi-x-circle"></i> Confirmar rechazo</button>
                </div>
            </div>
        </form>
        <script>
            // Mostrar textarea de rechazo y enfocar
            document.getElementById('btnShowRechazar').onclick = function() {
                document.getElementById('rechazar-campo').style.display = 'block';
                setTimeout(function(){
                    document.getElementById('comentario').focus();
                }, 100);
            };
        </script>
    <?php elseif($solicitud['estado']=='aprobado'): ?>
        <div class="center estado-final">
            <span class="estado-icon aprobado"><i class="bi bi-patch-check-fill"></i></span><br>
            <span class="estado-text aprobado">¡Solicitud aprobada!</span>
        </div>
    <?php elseif($solicitud['estado']=='rechazado'): ?>
        <div class="center estado-final">
            <span class="estado-icon rechazado"><i class="bi bi-x-octagon-fill"></i></span><br>
            <span class="estado-text rechazado">Solicitud rechazada</span>
        </div>
    <?php endif; ?>
</div>
</body>
</html>
