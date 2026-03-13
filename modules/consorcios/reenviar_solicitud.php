<?php
session_start();
require_once '../../config/db.php';

$id_cot = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$id_cot) { echo "<div class='alert alert-danger'>ID inválido</div>"; exit(); }

// Traer solicitud y cotización aprobada
$sql = "SELECT sc.*, c.id AS id_cotizacion, c.proveedor, c.precio as cot_precio, c.archivo as cot_archivo
        FROM solicitudes_cotizacion_consorcios sc
        JOIN solicitudes_cotizacion_consorcios_cotizaciones c 
        ON sc.cotizacion_aprobada_id = c.id
        WHERE sc.id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$id_cot]);
$solicitud = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$solicitud) { echo "<div class='alert alert-danger'>No existe cotización aprobada.</div>"; exit(); }

// Base URL para cotizaciones (consistencia en módulos de aprobación)
$cotizacion_base = '../../modules/consorcios/uploads/cotizaciones/';

// Traer productos
$sql = "SELECT * FROM solicitudes_cotizacion_consorcios_productos WHERE solicitud_id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$id_cot]);
$productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$fechaHoy = date('Y-m-d');

// Lógica para definir si requiere documentos
$proveedores_no_requieren = ['homecenter','éxito','exito','yumbo'];
$proveedor = strtolower($solicitud['proveedor'] ?? '');
$requiere_docs = true;
foreach($proveedores_no_requieren as $sin_doc){
    if(strpos($proveedor, $sin_doc)!==false){
        $requiere_docs = false;
        break;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reenviar Solicitud de Compra | ERP Fundocol</title>
    <link rel="stylesheet" href="../../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script>
        function calcularTotal(i) {
            let cantidad = parseFloat(document.getElementById('cantidad_' + i).value);
            let unitario = parseFloat(document.getElementById('precio_unitario_' + i).value);
            let total = isNaN(unitario) ? 0 : (cantidad * unitario).toFixed(2);
            document.getElementById('precio_total_' + i).value = total;
            document.getElementById('precio_total_' + i + '_show').innerText = '$' + Number(total).toLocaleString();
        }
    </script>
</head>
<body class="consorcios-reenviar-solicitud-page">
    <?php include '../../includes/navbar.php'; ?>
    <div class="navbar-spacer"></div>

    <div class="compra-card">
        <div class="compra-title"><i class="bi bi-arrow-repeat"></i> Reenviar Solicitud de Compra</div>

        <form method="post" action="guardar_reenvio_solicitud.php" enctype="multipart/form-data">
            <input type="hidden" name="id_cot" value="<?= $id_cot ?>">
            <input type="hidden" name="id_cotizacion" value="<?= $solicitud['id_cotizacion'] ?>">

            <!-- Datos generales -->
            <div class="data-block">
                <div><span class="data-label">Solicitante:</span> <?= htmlspecialchars($_SESSION['usuario_nombre']) ?></div>
                <div><span class="data-label">Fecha:</span> <?= htmlspecialchars($fechaHoy) ?></div>
                <div><span class="data-label">Consorcio:</span> <?= htmlspecialchars($solicitud['consorcio']) ?></div>
                <div><span class="data-label">Necesidad:</span> <?= htmlspecialchars($solicitud['necesidad']) ?></div>
            </div>

            <!-- Tabla de productos (editable en precios) -->
            <div class="mb-4">
                <strong class="prod-label">Productos a comprar:</strong>
                <div class="table-responsive mt-2">
                    <table class="erp-prod-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Nombre</th>
                                <th>Cantidad</th>
                                <th>Descripción</th>
                                <th>Precio + IVA</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($productos as $i => $prod): ?>
                            <tr>
                                <td><?= $i+1 ?></td>
                                <td>
                                    <?= htmlspecialchars($prod['nombre']) ?>
                                    <input type="hidden" name="productos[<?= $i ?>][nombre]" value="<?= htmlspecialchars($prod['nombre']) ?>">
                                    <input type="hidden" name="productos[<?= $i ?>][descripcion]" value="<?= htmlspecialchars($prod['descripcion']) ?>">
                                </td>
                                <td>
                                    <input type="number" id="cantidad_<?= $i ?>" name="productos[<?= $i ?>][cantidad]" 
                                           value="<?= $prod['cantidad'] ?>" readonly>
                                </td>
                                <td><?= htmlspecialchars($prod['descripcion']) ?></td>
                                <td>
                                    <input type="number" step="0.01" min="0" id="precio_unitario_<?= $i ?>" 
                                           name="productos[<?= $i ?>][precio_unitario]" 
                                           value="<?= $prod['precio_unitario'] ?>" onchange="calcularTotal(<?= $i ?>)">
                                </td>
                                <td>
                                    <span id="precio_total_<?= $i ?>_show">
                                        $<?= number_format($prod['precio_total'],0,',','.') ?>
                                    </span>
                                    <input type="hidden" id="precio_total_<?= $i ?>" 
                                           name="productos[<?= $i ?>][precio_total]" 
                                           value="<?= $prod['precio_total'] ?>">
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Cotización anterior -->
            <div class="cotiz-block">
                <div>
                    <div><b>Proveedor:</b> <?= htmlspecialchars($solicitud['proveedor']) ?></div>
                    <div><b>Precio actual:</b> <span class="precio-aprobado">
                        <?= '$' . number_format($solicitud['cot_precio'],0,',','.') ?>
                    </span></div>
                </div>
                <a href="<?= $cotizacion_base . urlencode($solicitud['cot_archivo']) ?>" target="_blank" class="btn-pdf-pill">
                    <i class="bi bi-file-earmark-pdf"></i> Ver Cotización Anterior
                </a>
            </div>

            <!-- Nueva cotización -->
            <div class="cotiz-block cotiz-block-column">
                <label><b>Subir nueva cotización (PDF):</b></label>
                <input type="file" name="nueva_cotizacion" accept="application/pdf" required>
            </div>

            <!-- Adjuntos -->
            <?php if ($requiere_docs): ?>
            <div class="adjuntos-docs">
                <label><b>Certificación bancaria:</b>
                    <input type="file" name="certificacion_bancaria" accept="application/pdf,image/*" required>
                </label><br><br>
                <label><b>RUT del proveedor:</b>
                    <input type="file" name="rut_proveedor" accept="application/pdf,image/*" required>
                </label>
            </div>
            <?php endif; ?>

            <div class="btn-enviar-wrap">
                <button type="submit" class="btn-enviar"><i class="bi bi-send"></i> Enviar Reenvío</button>
            </div>
        </form>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            <?php foreach ($productos as $i => $prod): ?>
            calcularTotal(<?= $i ?>);
            <?php endforeach; ?>
        });
    </script>
</body>
</html>




