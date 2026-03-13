<?php
include '../../../includes/navbar.php';
require_once '../../../config/db.php';

$id = intval($_GET['id'] ?? 0);
if (!$id) {
    die("<div class='alert alert-danger mt-5'>Solicitud no especificada.</div>");
}

// Obtener datos de la compra fija de consorcio
$sql = "
    SELECT cf.*, u.nombre AS solicitante_nombre
    FROM compras_fijas_consorcios cf
    INNER JOIN usuarios u ON cf.solicitante_id = u.id
    WHERE cf.id = ?
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$id]);
$cf = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$cf) {
    die("<div class='alert alert-danger mt-5'>Solicitud no encontrada.</div>");
}

/* ---- Función para mostrar documentos ---- */
function mostrarArchivo($nombre, $archivo, $tipo = "") {
    if (!$archivo) return "";
    $nombreEsc = htmlspecialchars($nombre);
    $archivoEsc = htmlspecialchars($archivo);
    $tipoEsc = htmlspecialchars($tipo);

    return "
        <button class='archivo $tipoEsc' onclick=\"previewArchivo('../../../uploads/compras_fijas_consorcios/$archivoEsc', '$nombreEsc')\">
            <i class='bi bi-paperclip'></i> $nombreEsc
        </button>
    ";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Detalle Compra Fija Consorcio</title>
<link rel="stylesheet" href="../../../assets/css/bootstrap.min.css">
<link rel="stylesheet" href="../../../assets/css/style.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@500;700&display=swap" rel="stylesheet">
</head>

<body class="consorcios-fijas-detalle-page">

<div class="navbar-spacer"></div>

<a href="compras_fijas.php" class="btn-volver">
    <i class="bi bi-arrow-left-circle"></i> Volver
</a>

<div class="detalle-box">

    <h2><i class="bi bi-file-text"></i> Compra Fija Consorcio #<?= intval($cf['id']) ?></h2>

    <div class="dato-label">Consorcio:<span class="dato-valor"><?= htmlspecialchars($cf['consorcio']) ?></span></div>
    <div class="dato-label">Solicitante:<span class="dato-valor"><?= htmlspecialchars($cf['solicitante_nombre']) ?></span></div>
    <div class="dato-label">Categoría:<span class="dato-valor"><?= htmlspecialchars($cf['categoria']) ?></span></div>
    <div class="dato-label">Proveedor:<span class="dato-valor"><?= htmlspecialchars($cf['proveedor']) ?></span></div>
    <div class="dato-label">Monto:<span class="dato-valor">$<?= number_format((float)$cf['monto'], 0, ',', '.') ?></span></div>

    <?php if (!empty($cf['descripcion'])): ?>
        <div class="dato-label">Descripción:<span class="dato-valor"><?= htmlspecialchars($cf['descripcion']) ?></span></div>
    <?php endif; ?>

    <div class="dato-label">Estado:
        <span class="estado <?= htmlspecialchars(strtolower($cf['estado'])) ?>">
            <?= htmlspecialchars(strtoupper($cf['estado'])) ?>
        </span>
    </div>

    <h4 class="docs-title">Tiempos de aprobación</h4>

    <?php
    $fechas = [
        "Aprobación Presupuesto"   => "fecha_aprob_presupuesto",
        "Aprobación Dirección"     => "fecha_aprob_direccion",
        "Aprobación Contabilidad"  => "fecha_aprob_contabilidad",
        "Aprobación Pagos"         => "fecha_aprob_pagos"
    ];
    ?>

    <?php foreach ($fechas as $titulo => $campo): ?>
        <?php if (!empty($cf[$campo])): ?>
            <div class="dato-label"><?= htmlspecialchars($titulo) ?>:
                <span class="dato-valor"><?= htmlspecialchars($cf[$campo]) ?></span>
            </div>
        <?php endif; ?>
    <?php endforeach; ?>

    <?php
    // ✅ Comentarios/observaciones (incluye solicitante)
    $comentarios = [
        [
            'titulo' => 'Observaciones del solicitante',
            'campo'  => 'observaciones_solicitante',
            'clase'  => 'solicitante',
            'icono'  => 'bi-person-lines-fill'
        ],
        [
            'titulo' => 'Observaciones Presupuesto',
            'campo'  => 'observaciones_presupuesto',
            'clase'  => 'presupuesto',
            'icono'  => 'bi-cash-coin'
        ],
        [
            'titulo' => 'Observaciones Dirección',
            'campo'  => 'observaciones_direccion',
            'clase'  => 'direccion',
            'icono'  => 'bi-person-badge'
        ],
        [
            'titulo' => 'Observaciones Contabilidad',
            'campo'  => 'observaciones_contabilidad',
            'clase'  => 'contabilidad',
            'icono'  => 'bi-calculator'
        ],
        [
            'titulo' => 'Observaciones Pagos',
            'campo'  => 'observaciones_pagos',
            'clase'  => 'pagos',
            'icono'  => 'bi-credit-card'
        ]
    ];

    $hay_comentarios = false;
    foreach ($comentarios as $c) {
        if (!empty($cf[$c['campo']])) {
            $hay_comentarios = true;
            break;
        }
    }
    ?>

    <?php if ($hay_comentarios): ?>
        <h4 class="docs-title"><i class="bi bi-chat-left-text"></i> Observaciones del proceso</h4>

        <div class="comentarios-box">
            <?php foreach ($comentarios as $c): ?>
                <?php if (!empty($cf[$c['campo']])): ?>
                    <div class="comentario-area <?= htmlspecialchars($c['clase']) ?>">
                        <div class="comentario-titulo">
                            <i class="bi <?= htmlspecialchars($c['icono']) ?>"></i>
                            <?= htmlspecialchars($c['titulo']) ?>
                        </div>
                        <div class="comentario-texto">
                            <?= nl2br(htmlspecialchars($cf[$c['campo']])) ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>


    <h4 class="docs-title"><i class="bi bi-folder2-open"></i> Documentos adjuntos</h4>

    <?= mostrarArchivo("Cotización", $cf['archivo_cotizacion']) ?>
    <?= mostrarArchivo("RUT", $cf['archivo_rut']) ?>
    <?= mostrarArchivo("Certificación Bancaria", $cf['archivo_certificacion']) ?>

    <?php if (($cf['categoria'] ?? '') === 'Cuenta de cobro'): ?>
        <?= mostrarArchivo("Seguridad Social", $cf['archivo_seguridad_social']) ?>
        <?= mostrarArchivo("Informe / Bitácora", $cf['archivo_bitacora']) ?>
    <?php endif; ?>

    <?= mostrarArchivo("Factura Siigo", $cf['soporte_pago'], "factura") ?>
    <?= mostrarArchivo("Soporte de Pago", $cf['factura_proveedor'], "soporte") ?>

</div>

<!-- Modal -->
<div id="previewModal">
    <div class="preview-content">
        <div class="preview-header">
            <span id="previewTitle">Documento</span>
            <button onclick="closePreview()" class="modal-close-btn">
                &times;
            </button>
        </div>
        <div class="preview-body" id="previewBody"></div>
    </div>
</div>

<script>
function previewArchivo(url, titulo) {
    document.getElementById("previewModal").style.display = "flex";
    document.getElementById("previewTitle").innerText = titulo;

    const ext = url.split('.').pop().toLowerCase();
    const body = document.getElementById("previewBody");

    if (["jpg","jpeg","png","gif","webp"].includes(ext)) {
        body.innerHTML = `<img src="${url}" alt="preview">`;
    } else if (ext === "pdf") {
        body.innerHTML = `<iframe src="${url}"></iframe>`;
    } else {
        body.innerHTML = `
            <div style='padding:20px; text-align:center;'>
                <p>Este archivo no se puede visualizar.</p>
                <a href="${url}" download class='btn btn-primary'>Descargar archivo</a>
            </div>
        `;
    }
}

function closePreview() {
    document.getElementById("previewModal").style.display = "none";
}
</script>

</body>
</html>

