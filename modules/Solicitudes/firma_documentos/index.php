<?php
require_once __DIR__ . '/_common.php';
firmaRequireLogin();
firmaEnsureStorageDirs();
firmaEnsureTable($pdo);

$usuarioId = (int)($_SESSION['usuario_id'] ?? 0);
$msg = '';
$err = '';

if (isset($_GET['download'])) {
    $docId = (int)$_GET['download'];
    $stmt = $pdo->prepare("SELECT id, archivo_firmado, nombre_documento, solicitante_id, estado
                           FROM documentos_firma
                           WHERE id = ? AND solicitante_id = ?");
    $stmt->execute([$docId, $usuarioId]);
    $doc = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$doc || $doc['estado'] !== 'firmado' || empty($doc['archivo_firmado'])) {
        $err = 'No se encontr&oacute; el archivo firmado para descarga.';
    } else {
        $abs = firmaAbsFromRelative($doc['archivo_firmado']);
        if (!is_file($abs)) {
            $err = 'El archivo firmado no est&aacute; disponible en el servidor.';
        } else {
            $downloadName = firmaSanitizeFileName($doc['nombre_documento']) . '_firmado.pdf';
            header('Content-Type: application/pdf');
            header('Content-Length: ' . filesize($abs));
            header('Content-Disposition: attachment; filename="' . $downloadName . '"');
            header('X-Content-Type-Options: nosniff');
            readfile($abs);
            exit();
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'crear') {
    $nombreDocumento = trim((string)($_POST['nombre_documento'] ?? ''));
    $razon = trim((string)($_POST['razon'] ?? ''));
    $archivo = $_FILES['archivo_pdf'] ?? null;

    if ($nombreDocumento === '' || $razon === '' || !$archivo) {
        $err = 'Debe completar nombre, raz&oacute;n y PDF.';
    } else {
        $tmp = $archivo['tmp_name'] ?? '';
        if (!is_uploaded_file($tmp)) {
            $err = 'No se recibi&oacute; el archivo PDF.';
        } else {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($tmp) ?: '';
            $ext = strtolower(pathinfo($archivo['name'] ?? '', PATHINFO_EXTENSION));
            if ($mime !== 'application/pdf' || $ext !== 'pdf') {
                $err = 'Archivo inv&aacute;lido. Solo se permiten PDF reales.';
            } else {
                $safe = firmaSanitizeFileName(pathinfo($archivo['name'], PATHINFO_FILENAME));
                $relative = 'uploads/firma_documentos/originales/doc_' . $usuarioId . '_' . time() . '_' . $safe . '.pdf';
                $destino = firmaAbsFromRelative($relative);

                if (!move_uploaded_file($tmp, $destino)) {
                    $err = 'No fue posible guardar el PDF original.';
                } else {
                    $stmt = $pdo->prepare("INSERT INTO documentos_firma
                        (solicitante_id, nombre_documento, razon, archivo_original, estado)
                        VALUES (?, ?, ?, ?, 'pendiente')");
                    $ok = $stmt->execute([$usuarioId, $nombreDocumento, $razon, $relative]);
                    if ($ok) {
                        $msg = 'Documento registrado correctamente para firma.';
                    } else {
                        $err = 'No se pudo registrar el documento en base de datos.';
                    }
                }
            }
        }
    }
}

$stmtPend = $pdo->prepare("SELECT id, nombre_documento, razon, fecha_solicitud
                           FROM documentos_firma
                           WHERE solicitante_id = ? AND estado = 'pendiente'
                           ORDER BY fecha_solicitud DESC");
$stmtPend->execute([$usuarioId]);
$pendientes = $stmtPend->fetchAll(PDO::FETCH_ASSOC);

$stmtFirm = $pdo->prepare("SELECT id, nombre_documento, razon, fecha_solicitud, fecha_firma, archivo_firmado
                           FROM documentos_firma
                           WHERE solicitante_id = ? AND estado = 'firmado'
                           ORDER BY fecha_firma DESC");
$stmtFirm->execute([$usuarioId]);
$firmados = $stmtFirm->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Firma de Documentos | ERP</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../../assets/css/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="firma-documentos-index-page">
<?php include '../../../includes/navbar.php'; ?>
<div class="container py-4">
    <div class="firma-doc-shell card border-0 shadow-sm">
        <div class="card-body p-4 p-lg-5">
            <div class="firma-doc-head d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
                <div>
                    <h1 class="h4 mb-1"><i class="bi bi-file-earmark-check me-2"></i>Firma de Documentos</h1>
                    <div class="text-muted small">Sube tus PDF y consulta su estado de firma en un solo panel.</div>
                </div>
                <a href="../index.php" class="btn btn-outline-secondary btn-sm">Volver a Solicitudes</a>
            </div>

            <div class="firma-doc-stats mb-3">
                <div class="firma-doc-stat-item">
                    <span class="label">Pendientes</span>
                    <span class="value"><?= count($pendientes) ?></span>
                </div>
                <div class="firma-doc-stat-item">
                    <span class="label">Firmados</span>
                    <span class="value"><?= count($firmados) ?></span>
                </div>
            </div>

            <?php if ($msg): ?>
                <div class="alert alert-success"><?= $msg ?></div>
            <?php endif; ?>
            <?php if ($err): ?>
                <div class="alert alert-danger"><?= $err ?></div>
            <?php endif; ?>

            <ul class="nav nav-tabs firma-doc-tabs" id="firmaTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-nuevo" type="button">Nuevo</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-sin-firmar" type="button">Sin firmar</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-firmados" type="button">Firmados</button>
                </li>
            </ul>

            <div class="tab-content pt-3 firma-doc-tabs-content">
                <div class="tab-pane fade show active" id="tab-nuevo">
                    <form method="post" enctype="multipart/form-data" class="row g-3 firma-doc-form">
                        <input type="hidden" name="action" value="crear">
                        <div class="col-md-6">
                            <label class="form-label">Nombre del documento</label>
                            <input type="text" class="form-control" name="nombre_documento" maxlength="255" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">PDF original</label>
                            <input type="file" class="form-control" name="archivo_pdf" accept="application/pdf,.pdf" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Raz&oacute;n</label>
                            <textarea class="form-control" name="razon" rows="3" required></textarea>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary firma-doc-primary-btn">
                                <i class="bi bi-cloud-upload me-1"></i>Enviar para firma
                            </button>
                        </div>
                    </form>
                </div>

                <div class="tab-pane fade" id="tab-sin-firmar">
                    <?php if (!$pendientes): ?>
                        <div class="alert alert-info mb-0">No tienes documentos pendientes por firmar.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle firma-doc-table">
                                <thead class="table-light">
                                <tr>
                                    <th>ID</th>
                                    <th>Documento</th>
                                    <th>Raz&oacute;n</th>
                                    <th>Fecha solicitud</th>
                                    <th>Estado</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($pendientes as $doc): ?>
                                    <tr>
                                        <td><?= (int)$doc['id'] ?></td>
                                        <td><?= htmlspecialchars($doc['nombre_documento']) ?></td>
                                        <td><?= htmlspecialchars(firmaResumen($doc['razon'])) ?></td>
                                        <td><?= htmlspecialchars((string)$doc['fecha_solicitud']) ?></td>
                                        <td><?= firmaRenderEstadoBadge('pendiente') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="tab-pane fade" id="tab-firmados">
                    <?php if (!$firmados): ?>
                        <div class="alert alert-info mb-0">A&uacute;n no tienes documentos firmados.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle firma-doc-table">
                                <thead class="table-light">
                                <tr>
                                    <th>ID</th>
                                    <th>Documento</th>
                                    <th>Raz&oacute;n</th>
                                    <th>Fecha solicitud</th>
                                    <th>Fecha firma</th>
                                    <th>Descarga</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($firmados as $doc): ?>
                                    <tr>
                                        <td><?= (int)$doc['id'] ?></td>
                                        <td><?= htmlspecialchars($doc['nombre_documento']) ?></td>
                                        <td><?= htmlspecialchars(firmaResumen($doc['razon'])) ?></td>
                                        <td><?= htmlspecialchars((string)$doc['fecha_solicitud']) ?></td>
                                        <td><?= htmlspecialchars((string)$doc['fecha_firma']) ?></td>
                                        <td>
                                            <a class="btn btn-success btn-sm firma-doc-download-btn" href="?download=<?= (int)$doc['id'] ?>">
                                                <i class="bi bi-download me-1"></i>Descargar PDF firmado
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
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
