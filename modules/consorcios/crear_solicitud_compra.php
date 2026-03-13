<?php
session_start();
require_once '../../config/db.php';

$id_cot = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$id_cot) { echo "<div class='alert alert-danger'>ID inválido</div>"; exit(); }

// Traer solicitud y cotización aprobada
$sql = "SELECT sc.*, c.proveedor, c.precio as cot_precio, c.archivo as cot_archivo
        FROM solicitudes_cotizacion_consorcios sc
        JOIN solicitudes_cotizacion_consorcios_cotizaciones c ON sc.cotizacion_aprobada_id = c.id
        WHERE sc.id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$id_cot]);
$solicitud = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$solicitud) { echo "<div class='alert alert-danger'>No existe cotización aprobada.</div>"; exit(); }

// Traer productos de la solicitud
$sql = "SELECT * FROM solicitudes_cotizacion_consorcios_productos WHERE solicitud_id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$id_cot]);
$productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fecha de hoy (para la solicitud de compra)
$fechaHoy = date('Y-m-d');

// Base URL para cotizaciones (consistencia en módulos de aprobación)
$cotizacion_base = '../../modules/consorcios/uploads/cotizaciones/';

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
    <title>Solicitud de Compra | ERP Fundocol</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- estilos movidos a assets/css/style.css -->
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
<body class="consorcios-crear-compra-page">
    <?php include '../../includes/navbar.php'; ?>
    <div class="navbar-spacer"></div>
    <div class="compra-card">
        <div class="compra-title"><i class="bi bi-file-earmark-plus"></i> Solicitud de Compra</div>
        <form method="post" action="guardar_solicitud_compra.php" enctype="multipart/form-data">
            <!-- Input oculto -->
            <input type="hidden" name="id_cot" value="<?= $id_cot ?>">
            <!-- Info general -->
            <div class="data-block">
                <div><span class="data-label">Solicitante:</span> <?= htmlspecialchars($_SESSION['usuario_nombre']) ?></div>
                <div>
                    <span class="data-label">Fecha:</span>
                    <input type="date" name="fecha" value="<?= $fechaHoy ?>" style="border:1.2px solid #e1eafd; border-radius:8px; padding:4px 10px; background:#f4f8fe; font-size:1.06rem;" readonly>
                </div>
                <div><span class="data-label">consorcio:</span> <?= htmlspecialchars($solicitud['consorcio']) ?></div>
                <div><span class="data-label">Necesidad:</span> <?= htmlspecialchars($solicitud['necesidad']) ?></div>
            </div>
            <!-- Tabla de productos -->
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
                                <th>Precio + iva</th>
                                <th>Precio Total</th>
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
                                    <input type="number" id="cantidad_<?= $i ?>" name="productos[<?= $i ?>][cantidad]" value="<?= $prod['cantidad'] ?>" readonly>
                                </td>
                                <td><?= htmlspecialchars($prod['descripcion']) ?></td>
                                <td>
                                    <input type="number" step="0.01" min="0" id="precio_unitario_<?= $i ?>" name="productos[<?= $i ?>][precio_unitario]" required onchange="calcularTotal(<?= $i ?>)">
                                </td>
                                <td>
                                    <span id="precio_total_<?= $i ?>_show">$0</span>
                                    <input type="hidden" id="precio_total_<?= $i ?>" name="productos[<?= $i ?>][precio_total]" value="0">
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <!-- Cotización aprobada -->
            <div class="cotiz-block">
                <div>
                    <div><b>Proveedor:</b> <?= htmlspecialchars($solicitud['proveedor']) ?></div>
                    <div><b>Precio:</b> <span style="color: #15a156; font-weight:600">
                        <?= '$' . number_format($solicitud['cot_precio'],0,',','.') ?>
                    </span></div>
                </div>
                <a href="<?= $cotizacion_base . urlencode($solicitud['cot_archivo']) ?>" target="_blank" class="btn-pdf-pill">
                    <i class="bi bi-file-earmark-pdf"></i> Ver PDF Cotización
                </a>
            </div>

            <!-- Bloque para adjuntar certificación bancaria y RUT si aplica -->
            <?php if ($requiere_docs): ?>
            <div id="adjuntosDocs" style="margin-top:18px;">
                <div style="margin-bottom:12px;">
                    <label><b>Adjuntar certificación bancaria del proveedor:</b> 
                        <input type="file" name="certificacion_bancaria" accept="application/pdf,image/*" required>
                    </label>
                </div>
                <div style="margin-bottom:14px;">
                    <label><b>Adjuntar RUT del proveedor:</b>
                        <input type="file" name="rut_proveedor" accept="application/pdf,image/*" required>
                    </label>
                </div>
                <div style="font-size:0.92rem;color:#4b689a;">
                    *Estos documentos son obligatorios solo para proveedores diferentes de Homecenter, Éxito o Yumbo.
                    <br>
                    *Si el dinero se te va a enviar a ti debes adjuntar tu certificacion bancaria
                </div>
            </div>
            <?php endif; ?>
            <br>

            <div class="mb-3">
    <label for="observaciones_compra" style="font-weight: 600; color: #2176ff;">Observaciones (opcional):</label>
    <textarea name="observaciones_compra" id="observaciones_compra" rows="2" maxlength="255" class="form-control" placeholder="Ejemplo: Solo se paga el 50%, entregar en dos partes, etc." style="border-radius:9px; border:1.4px solid #e1ecfc; font-size:1.05em; margin-top:4px;"></textarea>
    <div style="font-size:0.93em;color:#64748b;margin-top:2px;">
        Ingrese cualquier observación relevante sobre el pago o condiciones especiales.
    </div>
</div>


            <!-- Botón enviar -->
            <div style="text-align:center;">
                <button type="submit" class="btn-enviar">
                    <i class="bi bi-send"></i> Enviar Solicitud de Compra
                </button>
            </div>
        </form>
    </div>
    <script>
        // Inicializa los cálculos en todos los inputs de precio_unitario
        document.addEventListener('DOMContentLoaded', function() {
            <?php foreach ($productos as $i => $prod): ?>
            document.getElementById('precio_unitario_<?= $i ?>').addEventListener('change', function() {
                calcularTotal(<?= $i ?>);
            });
            <?php endforeach; ?>
        });
    </script>
</body>
</html>









