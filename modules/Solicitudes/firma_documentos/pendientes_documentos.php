<?php
require_once __DIR__ . '/_common.php';
firmaRequireRol(3);
firmaEnsureStorageDirs();
firmaEnsureTable($pdo);

$sql = "SELECT d.id, d.nombre_documento, d.razon, d.fecha_solicitud, u.nombre AS solicitante
        FROM documentos_firma d
        INNER JOIN usuarios u ON d.solicitante_id = u.id
        WHERE d.estado = 'pendiente'
        ORDER BY d.fecha_solicitud DESC";
$rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pendientes Firma Documentos</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../../assets/css/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="firma-documentos-pendientes-page">
<?php include '../../../includes/navbar.php'; ?>
<div class="container py-4">
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="h5 mb-0"><i class="bi bi-pen me-2"></i>Pendientes de firma de documentos</h2>
                <a href="../../pendientes/index.php?tab=documentos_firma" class="btn btn-outline-secondary btn-sm">Volver a Pendientes</a>
            </div>

            <?php if (!$rows): ?>
                <div class="alert alert-info mb-0">No hay documentos pendientes por firmar.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Solicitante</th>
                            <th>Documento</th>
                            <th>Raz&oacute;n</th>
                            <th>Fecha</th>
                            <th>Acci&oacute;n</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($rows as $doc): ?>
                            <tr>
                                <td><?= (int)$doc['id'] ?></td>
                                <td><?= htmlspecialchars($doc['solicitante']) ?></td>
                                <td><?= htmlspecialchars($doc['nombre_documento']) ?></td>
                                <td><?= htmlspecialchars(firmaResumen($doc['razon'])) ?></td>
                                <td><?= htmlspecialchars((string)$doc['fecha_solicitud']) ?></td>
                                <td>
                                    <a class="btn btn-primary btn-sm" href="firmar_documento.php?id=<?= (int)$doc['id'] ?>">
                                        <i class="bi bi-vector-pen me-1"></i>Firmar
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>
