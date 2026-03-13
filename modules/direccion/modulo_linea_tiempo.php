<?php
include '../../includes/navbar.php';
require_once '../../config/db.php';

$id = intval($_GET['id'] ?? 0);
$tipo = $_GET['tipo'] ?? 'normal'; // normal | fija
$origen = $_GET['origen'] ?? 'fundocol'; // fundocol | consorcio

if ($id <= 0) {
    die("<div class='alert alert-danger'>Solicitud no encontrada.</div>");
}

/* =====================================================
   1) CARGAR DATOS
   ===================================================== */
if ($tipo === 'normal') {

    if ($origen === 'fundocol') {
        $sql = "SELECT sc.*, u.nombre AS solicitante
                FROM solicitudes_compra sc
                INNER JOIN usuarios u ON sc.solicitante_id = u.id
                WHERE sc.id = ?";
        $campo_fecha = "fecha_creacion";

    } else {
        $sql = "SELECT sc.*, u.nombre AS solicitante
                FROM solicitudes_compra_consorcios sc
                INNER JOIN usuarios u ON sc.solicitante_id = u.id
                WHERE sc.id = ?";
        $campo_fecha = "fecha_creacion";
    }

} else { // FIJA

    if ($origen === 'fundocol') {
        $sql = "SELECT cf.*, u.nombre AS solicitante
                FROM compras_fijas cf
                INNER JOIN usuarios u ON cf.solicitante_id = u.id
                WHERE cf.id = ?";
        $campo_fecha = "fecha_solicitud";

    } else {
        $sql = "SELECT cf.*, u.nombre AS solicitante
                FROM compras_fijas_consorcios cf
                INNER JOIN usuarios u ON cf.solicitante_id = u.id
                WHERE cf.id = ?";
        $campo_fecha = "fecha";
    }
}

$stmt = $pdo->prepare($sql);
$stmt->execute([$id]);
$data = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$data) {
    die("<div class='alert alert-danger'>No existe la solicitud.</div>");
}


/* =====================================================
   2) UNIFICAR FECHAS
   ===================================================== */

$fecha_presupuesto   = null;
$fecha_direccion     = null;
$fecha_contabilidad  = null;
$fecha_pagos         = null;

if ($tipo === 'normal') {

    $fecha_presupuesto   = $data['fecha_aprob_presupuesto'] ?? null;
    $fecha_direccion     = $data['fecha_aprobacion_direccion'] ?? null;
    $fecha_contabilidad  = $data['fecha_aprob_contabilidad'] ?? null;
    $fecha_pagos         = $data['fecha_aprob_pagos'] ?? null;

} elseif ($tipo === 'fija' && $origen === 'fundocol') {

    $fecha_presupuesto   = $data['fecha_aprobacion_presupuesto'] ?? null;
    $fecha_direccion     = $data['fecha_aprobacion_direccion'] ?? null;
    $fecha_contabilidad  = $data['fecha_aprobacion_contabilidad'] ?? null;
    $fecha_pagos         = $data['fechas_aprobacion_pagos'] ?? null;

} elseif ($tipo === 'fija' && $origen === 'consorcio') {

    $fecha_presupuesto   = $data['fecha_aprob_presupuesto'] ?? null;
    $fecha_direccion     = $data['fecha_aprob_direccion'] ?? null;
    $fecha_contabilidad  = $data['fecha_aprob_contabilidad'] ?? null;
    $fecha_pagos         = $data['fecha_aprob_pagos'] ?? null;
}


/* =====================================================
   3) ARMADO TIMELINE
   ===================================================== */

$timeline = [];
$demoras = [];

$timeline[] = [
    'titulo' => 'Solicitud Creada',
    'fecha'  => $data[$campo_fecha],
    'color'  => '#0ea5e9',
    'alerta' => false
];

function agregarEtapa(&$timeline, &$demoras, $titulo, $fecha) {
    if (!$fecha) return;

    $prev = $timeline[count($timeline)-1]['fecha'];
    $dias = (strtotime($fecha) - strtotime($prev)) / 86400;

    if ($dias <= 2) {
        $color = '#16a34a'; // VERDE
        $alerta = false;
        $titulo_f = "$titulo (2 días o menos)";
    }
    elseif ($dias <= 4) {
        $color = '#f59e0b'; // AMARILLO
        $alerta = true;
        $titulo_f = "$titulo (3-4 días)";
    }
    else {
        $color = '#dc2626'; // ROJO
        $alerta = true;
        $titulo_f = "$titulo (5+ días)";
    }

    $demoras[] = $dias;

    $timeline[] = [
        'titulo' => $titulo_f,
        'fecha'  => $fecha,
        'color'  => $color,
        'alerta' => $alerta
    ];
}

agregarEtapa($timeline, $demoras, "Aprobado por Presupuesto",   $fecha_presupuesto);
agregarEtapa($timeline, $demoras, "Aprobado por Dirección",     $fecha_direccion);
agregarEtapa($timeline, $demoras, "Aprobado por Contabilidad",  $fecha_contabilidad);
agregarEtapa($timeline, $demoras, "Aprobado por Pagos",         $fecha_pagos);


/* =====================================================
   4) SEMÁFORO GENERAL
   ===================================================== */

if (empty($demoras)) {
    $color_final = "green";
    $msg = "Flujo óptimo: sin demoras.";
} else {

    $max = max($demoras);

    if ($max <= 2) {
        $color_final = "green";
        $msg = "Flujo eficiente (1-2 días)";
    }
    elseif ($max <= 4) {
        $color_final = "yellow";
        $msg = "Flujo moderado (3-4 días)";
    }
    else {
        $color_final = "red";
        $msg = "Flujo crítico (5+ días)";
    }
}

$bg = ($color_final === "green" ? "#16a34a" :
      ($color_final === "yellow" ? "#f59e0b" : "#dc2626"));

?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Línea de Tiempo</title>
<link rel="stylesheet" href="../../assets/css/bootstrap.min.css">
<link rel="stylesheet" href="../../assets/css/style.css">

<!-- ===================== CSS PROFESIONAL ====================== -->

<!-- estilos movidos a assets/css/style.css -->

</head>

<body class="direccion-linea-tiempo-page">

<div class="container-timeline">

    <a href="../direccion/index.php" class="btn-volver">&larr; Volver</a>

    <h3><i class="bi bi-clock-history"></i> Línea de Tiempo de la Solicitud #<?= $id ?></h3>

    <!-- ================= DETALLES PROFESIONALES ================= -->
    <div class="card mb-4" style="border-radius:16px; box-shadow:0 5px 18px rgba(0,0,0,0.08);">
        <div class="card-body" style="padding:30px;">

            <h5 style="
                font-weight:800;
                color:#0f4c81;
                margin-bottom:22px;
                font-size:1.25rem;
            ">
                <i class="bi bi-info-circle-fill"></i> Detalles de la Solicitud
            </h5>

            <div class="row gy-3 gx-5">

                <div class="col-md-6">
                    <label class="detalle-label">Solicitante</label>
                    <div class="detalle-valor"><?= htmlspecialchars($data['solicitante']) ?></div>
                </div>

                <div class="col-md-6">
                    <label class="detalle-label">Proveedor</label>
                    <div class="detalle-valor"><?= htmlspecialchars($data['proveedor'] ?? 'N/A') ?></div>
                </div>

                <div class="col-12">
                    <label class="detalle-label">Necesidad</label>
                    <div class="detalle-valor"><?= htmlspecialchars($data['necesidad'] ?? $data['descripcion'] ?? 'N/A') ?></div>
                </div>

                <div class="col-md-6">
                    <label class="detalle-label">Monto / Cantidad Pagada</label>
                    <div class="detalle-valor">
                        <?php 
                        $monto = $data['monto'] ?? $data['valor_pagado'] ?? null;
                        echo $monto ? ('$' . number_format($monto, 0, ',', '.')) : 'N/A';
                        ?>
                    </div>
                </div>

                <div class="col-md-6">
                    <label class="detalle-label">Fecha de Creación</label>
                    <div class="detalle-valor"><?= htmlspecialchars($data[$campo_fecha]) ?></div>
                </div>

                <div class="col-md-6">
                    <label class="detalle-label">Origen</label>
                    <span class="badge bg-primary" 
                          style="font-size:.9rem; padding:6px 12px; border-radius:8px;">
                        <?= ucfirst($origen) ?>
                    </span>
                </div>

            </div>

        </div>
    </div>
    <!-- =================================================== -->

    <!-- =============== TARJETA DE SIGNIFICADO DE COLORES ================= -->
    <div class="card mb-4" style="border-radius:16px; box-shadow:0 5px 18px rgba(0,0,0,0.08);">
        <div class="card-body" style="padding:25px 30px;">

            <h5 style="
                font-weight:800;
                color:#0f4c81;
                margin-bottom:18px;
                font-size:1.20rem;
            ">
                <i class="bi bi-palette-fill"></i> Interpretación del Flujo (Colores)
            </h5>

            <div class="row gy-2">

                <div class="col-md-4 d-flex align-items-center gap-2">
                    <span style="width:18px; height:18px; border-radius:5px; background:#16a34a;"></span>
                    <span style="font-size:.95rem; color:#1f2937;">
                        <strong>Verde:</strong> Aprobación rápida (1–2 días)
                    </span>
                </div>

                <div class="col-md-4 d-flex align-items-center gap-2">
                    <span style="width:18px; height:18px; border-radius:5px; background:#f59e0b;"></span>
                    <span style="font-size:.95rem; color:#1f2937;">
                        <strong>Amarillo:</strong> Lenta (3–4 días)
                    </span>
                </div>

                <div class="col-md-4 d-flex align-items-center gap-2">
                    <span style="width:18px; height:18px; border-radius:5px; background:#dc2626;"></span>
                    <span style="font-size:.95rem; color:#1f2937;">
                        <strong>Rojo:</strong> Muy demorada (5+ días)
                    </span>
                </div>

            </div>

        </div>
    </div>
    <!-- =================================================================== -->

    <div class="semaforo" style="background:<?= $bg ?>;">
        <?= $msg ?>
    </div>

    <!-- Timeline -->
    <?php foreach ($timeline as $t): ?>
        <div class="timeline-item <?= $t['alerta'] ? 'alerta' : '' ?>"
            style="border-left-color:<?= $t['color'] ?>">

            <div class="timeline-title">
                <?= $t['titulo'] ?>
            </div>

            <small><i class="bi bi-calendar3"></i> 
                <?= date("Y-m-d H:i", strtotime($t['fecha'])) ?>
            </small>

        </div>
    <?php endforeach; ?>

</div>



</body>

</html>

