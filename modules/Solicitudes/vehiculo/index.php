<?php
include '../../../includes/navbar.php';
require_once '../../../config/db.php';
require_once '../../../includes/phpmailer.php'; // <--- IMPORTANTE: Incluye PHPMailer

$mensaje = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $vehiculo_id = intval($_POST['vehiculo_id'] ?? 0);
    $solicitante_id = $_SESSION['usuario_id'] ?? 0;
    $fecha_inicio = $_POST['fecha_inicio'] ?? '';
    $fecha_fin = $_POST['fecha_fin'] ?? '';
    $motivo = trim($_POST['motivo'] ?? '');

    if ($vehiculo_id && $fecha_inicio && $fecha_fin && $motivo && $solicitante_id) {
        $sql = "INSERT INTO vehiculos_solicitudes (vehiculo_id, solicitante_id, fecha_inicio, fecha_fin, motivo, estado) 
                VALUES (?, ?, ?, ?, ?, 'pendiente')";
        $stmt = $pdo->prepare($sql);
        $ok = $stmt->execute([$vehiculo_id, $solicitante_id, $fecha_inicio, $fecha_fin, $motivo]);

        if ($ok) {
            $usuario = $pdo->query("SELECT nombre FROM usuarios WHERE id = $solicitante_id")->fetchColumn();
            $vehiculo = $pdo->query("SELECT nombre FROM vehiculos WHERE id = $vehiculo_id")->fetchColumn();

            $to = "presupuesto@fundocol.org";
            $asunto = "Nueva Solicitud de Vehículo";
            $htmlCuerpo = "
                <h2>Solicitud de Vehículo</h2>
                <p><b>Solicitante:</b> ".htmlspecialchars($usuario)."</p>
                <p><b>Vehículo:</b> ".htmlspecialchars($vehiculo)."</p>
                <p><b>Desde:</b> ".htmlspecialchars($fecha_inicio)." <b>Hasta:</b> ".htmlspecialchars($fecha_fin)."</p>
                <p><b>Motivo:</b> ".nl2br(htmlspecialchars($motivo))."</p>
                <br><small>Revisar y aprobar/rechazar en el sistema ERP Fundocol.</small>
            ";
            // Usar PHPMailer
            $correo_enviado = enviarCorreoFundocol($to, "Presupuesto Fundocol", $asunto, $htmlCuerpo);

            if ($correo_enviado) {
                $mensaje = "¡Solicitud enviada con éxito! Se notificó a Presupuesto.";
            } else {
                $mensaje = "La solicitud fue registrada, pero hubo un problema al enviar el correo.";
            }
        } else {
            $mensaje = "Error al registrar la solicitud.";
        }
    } else {
        $mensaje = "Debes completar todos los campos.";
    }
}

$vehiculos = $pdo->query("SELECT id, nombre, placa FROM vehiculos ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Solicitar Vehículo</title>
    <link rel="stylesheet" href="../../../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../../assets/css/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="icon" type="image/x-icon" href="https://erp-fundocol.fundocol.org/assets/img/Fundocol_favicon.png">
</head>
<body class="vehiculo-solicitar-page">
<div class="navbar-spacer"></div>
<div class="form-vehiculo-box">
    <div class="form-vehiculo-title">
        <i class="bi bi-truck-front-fill"></i>
        Solicitar Vehículo
    </div>
    <div class="form-subt">Diligencie el formulario para solicitar el préstamo de un vehículo institucional.</div>
    <?php if($mensaje): ?>
        <div class="<?= strpos($mensaje,'éxito')!==false ? 'msg':'msg-err' ?>"><?= $mensaje ?></div>
    <?php endif; ?>
    <form method="post" autocomplete="off" id="form-vehiculo">
        <div class="vehiculo-group">
            <label class="vehiculo-label" for="vehiculo_id">Vehículo *</label>
            <span class="vehiculo-icon"><i class="bi bi-truck"></i></span>
            <select name="vehiculo_id" id="vehiculo_id" class="vehiculo-select" required>
                <option value="">Seleccione...</option>
                <?php foreach($vehiculos as $v): ?>
                    <option value="<?= $v['id'] ?>">
                        <?= htmlspecialchars($v['nombre']) ?> (<?= htmlspecialchars($v['placa']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="vehiculo-group">
            <label class="vehiculo-label" for="fecha_inicio">Desde *</label>
            <span class="vehiculo-icon"><i class="bi bi-calendar-week"></i></span>
            <input type="date" name="fecha_inicio" id="fecha_inicio" class="vehiculo-input" required disabled>
        </div>
        <div class="vehiculo-group">
            <label class="vehiculo-label" for="fecha_fin">Hasta *</label>
            <span class="vehiculo-icon"><i class="bi bi-calendar-check"></i></span>
            <input type="date" name="fecha_fin" id="fecha_fin" class="vehiculo-input" required disabled>
        </div>
        <div class="vehiculo-group">
            <label class="vehiculo-label" for="motivo">Motivo *</label>
            <span class="vehiculo-icon"><i class="bi bi-chat-dots"></i></span>
            <textarea name="motivo" id="motivo" class="vehiculo-textarea" rows="2" required maxlength="300" placeholder="Describa el motivo del préstamo"></textarea>
        </div>
        <button type="submit" class="btn-vehiculo">
            <i class="bi bi-send"></i> Enviar solicitud
        </button>
    </form>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const vehiculoSel = document.getElementById('vehiculo_id');
    const inicioInput = document.getElementById('fecha_inicio');
    const finInput = document.getElementById('fecha_fin');
    let fechasOcupadas = [];

    vehiculoSel.addEventListener('change', function() {
        inicioInput.value = "";
        finInput.value = "";
        if (this.value) {
            inicioInput.disabled = false;
            finInput.disabled = false;
            fetch('fechas_ocupadas.php?vehiculo_id=' + this.value)
                .then(response => response.json())
                .then(fechas => {
                    fechasOcupadas = fechas;
                    bloquearFechas(inicioInput);
                    bloquearFechas(finInput);
                });
        } else {
            fechasOcupadas = [];
            inicioInput.disabled = true;
            finInput.disabled = true;
        }
    });

    document.getElementById('form-vehiculo').addEventListener('submit', function(e) {
        if (fechasOcupadas.includes(inicioInput.value) || fechasOcupadas.includes(finInput.value)) {
            alert("Hay fechas seleccionadas que ya están ocupadas. Corrige antes de enviar.");
            e.preventDefault();
        }
    });
});
</script>
</body>
</html>



