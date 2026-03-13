<?php
require_once __DIR__ . '/_common.php';
firmaRequireRol(3);
firmaEnsureStorageDirs();
firmaEnsureTable($pdo);

$docId = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$msg = '';
$err = '';

$stmt = $pdo->prepare("SELECT d.*, u.nombre AS solicitante_nombre, u.email AS solicitante_email
                       FROM documentos_firma d
                       INNER JOIN usuarios u ON d.solicitante_id = u.id
                       WHERE d.id = ?");
$stmt->execute([$docId]);
$doc = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$doc) {
    http_response_code(404);
    echo '<div style="padding:18px;font-family:Arial,sans-serif;">Documento no encontrado.</div>';
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'firmar') {
    if ($doc['estado'] === 'firmado') {
        $err = 'Este documento ya fue firmado.';
    } else {
        $firmaTipo = $_POST['firma_tipo'] ?? '';
        $firmaPage = (int)($_POST['firma_page'] ?? 0);
        $firmaX = (float)($_POST['firma_x'] ?? 0);
        $firmaY = (float)($_POST['firma_y'] ?? 0);
        $firmaW = (float)($_POST['firma_w'] ?? 0);
        $firmaH = (float)($_POST['firma_h'] ?? 0);

        if (!in_array($firmaTipo, ['subida', 'manual'], true)) {
            $err = 'Debe seleccionar una firma subida o manual.';
        } elseif ($firmaPage <= 0 || $firmaW <= 0 || $firmaH <= 0) {
            $err = 'Los datos de ubicaci&oacute;n de la firma son inv&aacute;lidos.';
        } else {
            $firmaRelative = 'uploads/firma_documentos/firmas/firma_' . $docId . '_' . time() . '.png';
            $firmaSave = [false, 'No se pudo guardar la firma.', ''];

            if ($firmaTipo === 'subida') {
                $firmaSave = firmaHandleUploadedSignatureToPng($_FILES['firma_archivo'] ?? [], $firmaRelative);
            } elseif ($firmaTipo === 'manual') {
                $manualData = (string)($_POST['firma_manual_data'] ?? '');
                $firmaSave = firmaHandleManualSignatureToPng($manualData, $firmaRelative);
            }

            if (!$firmaSave[0]) {
                $err = $firmaSave[1];
            } else {
                // Dependencia requerida para estampar firma real dentro del PDF final.
                // Si faltan librerias: composer require setasign/fpdi-tcpdf
                if (!class_exists('\setasign\Fpdi\Tcpdf\Fpdi')) {
                    $err = 'Falta FPDI+TCPDF. Instale con Composer: composer require setasign/fpdi-tcpdf';
                } else {
                    $originalAbs = firmaAbsFromRelative($doc['archivo_original']);
                    if (!is_file($originalAbs)) {
                        $err = 'No existe el PDF original en el servidor.';
                    } else {
                        $firmaAbs = firmaAbsFromRelative($firmaSave[2]);
                        $signedRelative = 'uploads/firma_documentos/firmados/firmado_' . $docId . '_' . time() . '.pdf';
                        $signedAbs = firmaAbsFromRelative($signedRelative);

                        try {
                            $pdf = new \setasign\Fpdi\Tcpdf\Fpdi('P', 'pt');
                            $pdf->setPrintHeader(false);
                            $pdf->setPrintFooter(false);
                            $pdf->SetMargins(0, 0, 0);
                            $pdf->SetAutoPageBreak(false, 0);

                            $pageCount = $pdf->setSourceFile($originalAbs);
                            if ($firmaPage > $pageCount) {
                                throw new RuntimeException('La p&aacute;gina seleccionada no existe en el PDF.');
                            }

                            for ($i = 1; $i <= $pageCount; $i++) {
                                $tpl = $pdf->importPage($i);
                                $size = $pdf->getTemplateSize($tpl);
                                $orientation = ($size['width'] > $size['height']) ? 'L' : 'P';
                                $pdf->AddPage($orientation, [$size['width'], $size['height']]);
                                $pdf->useTemplate($tpl, 0, 0, $size['width'], $size['height'], true);

                                if ($i === $firmaPage) {
                                    $maxX = max(0, $size['width'] - $firmaW);
                                    $maxYBottom = max(0, $size['height'] - $firmaH);

                                    $x = max(0, min($firmaX, $maxX));
                                    $yBottomLeft = max(0, min($firmaY, $maxYBottom));
                                    $yTopLeft = $size['height'] - $yBottomLeft - $firmaH;

                                    $pdf->Image($firmaAbs, $x, $yTopLeft, $firmaW, $firmaH, 'PNG', '', '', true, 300, '', false, false, 0, false, false, false);
                                }
                            }

                            $pdf->Output($signedAbs, 'F');
                        } catch (Throwable $e) {
                            $err = 'Error al generar PDF firmado: ' . htmlspecialchars($e->getMessage());
                        }

                        if ($err === '') {
                            $ip = substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 64);
                            $ua = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
                            $firmadorId = (int)($_SESSION['usuario_id'] ?? 0);

                            $up = $pdo->prepare("UPDATE documentos_firma
                                SET estado='firmado',
                                    archivo_firmado=?,
                                    firmado_por_id=?,
                                    firma_tipo=?,
                                    firma_archivo=?,
                                    firma_page=?,
                                    firma_x=?,
                                    firma_y=?,
                                    firma_w=?,
                                    firma_h=?,
                                    ip_firma=?,
                                    user_agent=?,
                                    fecha_firma=NOW()
                                WHERE id=? AND estado='pendiente'");
                            $up->execute([
                                $signedRelative,
                                $firmadorId,
                                $firmaTipo,
                                $firmaSave[2],
                                $firmaPage,
                                $firmaX,
                                $firmaY,
                                $firmaW,
                                $firmaH,
                                $ip,
                                $ua,
                                $docId,
                            ]);

                            if ($up->rowCount() === 0) {
                                $err = 'No se pudo actualizar el estado. El documento podr&iacute;a estar firmado por otro usuario.';
                            } else {
                                $downloadRelative = 'index.php?download=' . $docId;
                                $downloadUrl = firmaBuildAbsoluteUrl($downloadRelative);
                                $solicitante = (string)($doc['solicitante_nombre'] ?? 'Usuario');
                                $asunto = 'Documento firmado: ' . (string)$doc['nombre_documento'];
                                $body = '<p>Hola <b>' . htmlspecialchars($solicitante) . '</b>,</p>'
                                      . '<p>Tu documento <b>' . htmlspecialchars((string)$doc['nombre_documento']) . '</b> ya fue firmado.</p>'
                                      . '<p><a href="' . htmlspecialchars($downloadUrl) . '" style="display:inline-block;padding:10px 18px;background:#0d6efd;color:#fff;text-decoration:none;border-radius:8px;">Descargar PDF firmado</a></p>'
                                      . '<p><small>Notificaci&oacute;n autom&aacute;tica del ERP.</small></p>';

                                if (!empty($doc['solicitante_email'])) {
                                    enviarCorreoFundocol((string)$doc['solicitante_email'], $solicitante, $asunto, $body);
                                }

                                $msg = 'Documento firmado correctamente y PDF final generado.';
                                $stmt->execute([$docId]);
                                $doc = $stmt->fetch(PDO::FETCH_ASSOC);
                            }
                        }
                    }
                }
            }
        }
    }
}

$originalPublicUrl = firmaPublicUrlFromRelative((string)$doc['archivo_original']);
$isSigned = ($doc['estado'] === 'firmado');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Firmar Documento | ERP</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../../assets/css/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="firma-documentos-firmar-page">
<?php include '../../../includes/navbar.php'; ?>
<div class="navbar-spacer"></div>
<div class="container py-4">
    <div class="card border-0 shadow-sm mb-3 firma-sign-top-card">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 firma-sign-head">
                <h1 class="h5 mb-0"><i class="bi bi-vector-pen me-2"></i>Firmar documento #<?= (int)$doc['id'] ?></h1>
                <a href="../../pendientes/index.php?tab=documentos_firma" class="btn btn-outline-secondary btn-sm firma-sign-back-btn">Volver a pendientes</a>
            </div>
            <hr>
            <div class="row g-2 firma-sign-meta">
                <div class="col-md-4"><span class="meta-chip badge bg-light text-dark border">Solicitante: <?= htmlspecialchars((string)$doc['solicitante_nombre']) ?></span></div>
                <div class="col-md-4"><span class="meta-chip badge bg-light text-dark border">Documento: <?= htmlspecialchars((string)$doc['nombre_documento']) ?></span></div>
                <div class="col-md-4"><span class="meta-chip badge bg-light text-dark border">Estado: <?= $doc['estado'] === 'firmado' ? 'Firmado' : 'Pendiente' ?></span></div>
                <div class="col-12"><div class="small text-secondary firma-sign-reason">Raz&oacute;n: <?= nl2br(htmlspecialchars((string)$doc['razon'])) ?></div></div>
            </div>
        </div>
    </div>

    <?php if ($msg): ?>
        <div class="alert alert-success"><?= $msg ?></div>
    <?php endif; ?>
    <?php if ($err): ?>
        <div class="alert alert-danger"><?= $err ?></div>
    <?php endif; ?>

    <?php if ($isSigned): ?>
        <div class="alert alert-warning">
            Este documento ya fue firmado. <a href="index.php" class="alert-link">Ver listado de firmados</a>.
        </div>
    <?php else: ?>
        <form method="post" enctype="multipart/form-data" id="form-firma">
            <input type="hidden" name="action" value="firmar">
            <input type="hidden" name="id" value="<?= (int)$doc['id'] ?>">
            <input type="hidden" name="firma_tipo" id="firma_tipo" value="">
            <input type="hidden" name="firma_manual_data" id="firma_manual_data" value="">
            <input type="hidden" name="firma_page" id="firma_page" value="1">
            <input type="hidden" name="firma_x" id="firma_x" value="">
            <input type="hidden" name="firma_y" id="firma_y" value="">
            <input type="hidden" name="firma_w" id="firma_w" value="">
            <input type="hidden" name="firma_h" id="firma_h" value="">

            <div class="row g-3">
                <div class="col-lg-4">
                    <div class="card border-0 shadow-sm firma-sign-tools-card">
                        <div class="card-body firma-sign-tools-body">
                            <h2 class="h6 mb-3">Firma</h2>

                            <label class="form-label">Subir firma (PNG/JPG)</label>
                            <input type="file" class="form-control mb-3" name="firma_archivo" id="firma_archivo" accept=".png,.jpg,.jpeg,image/png,image/jpeg">

                            <label class="form-label">O dibujar firma</label>
                            <canvas id="drawSignatureCanvas" width="320" height="120" class="w-100 mb-2"></canvas>
                            <div class="d-flex gap-2 mb-3 firma-sign-tools-actions">
                                <button class="btn btn-outline-secondary btn-sm" type="button" id="btnClearCanvas">Limpiar</button>
                                <button class="btn btn-outline-primary btn-sm" type="button" id="btnUseCanvas">Usar firma manual</button>
                            </div>

                            <label class="form-label">P&aacute;gina para firmar</label>
                            <input type="number" class="form-control mb-2" id="pageInput" min="1" value="1">
                            <div class="small text-secondary mb-3" id="pageInfo">P&aacute;gina 1 de ...</div>

                            <label class="form-label">Tama&ntilde;o de firma</label>
                            <input type="range" class="form-range" id="sizeRange" min="90" max="350" value="180">

                            <div class="firma-sign-coords card mt-3 border-0">
                                <div class="card-body p-2">
                                    <div class="small fw-bold mb-2">Coordenadas manuales (PDF)</div>
                                    <div class="row g-2">
                                        <div class="col-6">
                                            <label class="form-label small mb-1">X</label>
                                            <input type="number" step="0.01" min="0" class="form-control form-control-sm" id="coordXInput" value="31.77">
                                        </div>
                                        <div class="col-6">
                                            <label class="form-label small mb-1">Y</label>
                                            <input type="number" step="0.01" min="0" class="form-control form-control-sm" id="coordYInput" value="226.9">
                                        </div>
                                        <div class="col-6">
                                            <label class="form-label small mb-1">W</label>
                                            <input type="number" step="0.01" min="1" class="form-control form-control-sm" id="coordWInput" value="136.17">
                                        </div>
                                        <div class="col-6">
                                            <label class="form-label small mb-1">H</label>
                                            <input type="number" step="0.01" min="1" class="form-control form-control-sm" id="coordHInput" value="33.29">
                                        </div>
                                    </div>
                                    <button type="button" class="btn btn-outline-dark btn-sm w-100 mt-2" id="btnApplyCoords">
                                        Aplicar coordenadas
                                    </button>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary w-100 mt-3 firma-sign-submit-btn">
                                <i class="bi bi-check2-circle me-1"></i>Generar PDF firmado
                            </button>
                            <div class="small text-secondary mt-2 firma-sign-help">El preview solo define posici&oacute;n y p&aacute;gina. El sello final se estampa en el PDF generado.</div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-8">
                    <div class="card border-0 shadow-sm firma-sign-preview-card">
                        <div class="card-body">
                            <h2 class="h6 mb-3">Preview PDF (ubicar firma)</h2>
                            <div id="pdfStage" class="pdf-stage p-2">
                                <canvas id="pdfCanvas"></canvas>
                                <div id="overlayBox"><img id="overlayImage" alt="Firma"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    <?php endif; ?>
</div>

<?php if (!$isSigned): ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
<script>
const pdfUrl = <?= json_encode($originalPublicUrl) ?>;
pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

const pdfCanvas = document.getElementById('pdfCanvas');
const pdfStage = document.getElementById('pdfStage');
const overlayBox = document.getElementById('overlayBox');
const overlayImage = document.getElementById('overlayImage');
const fileInput = document.getElementById('firma_archivo');
const pageInput = document.getElementById('pageInput');
const pageInfo = document.getElementById('pageInfo');
const sizeRange = document.getElementById('sizeRange');
const form = document.getElementById('form-firma');

const hiddenTipo = document.getElementById('firma_tipo');
const hiddenManual = document.getElementById('firma_manual_data');
const hiddenPage = document.getElementById('firma_page');
const hiddenX = document.getElementById('firma_x');
const hiddenY = document.getElementById('firma_y');
const hiddenW = document.getElementById('firma_w');
const hiddenH = document.getElementById('firma_h');
const coordXInput = document.getElementById('coordXInput');
const coordYInput = document.getElementById('coordYInput');
const coordWInput = document.getElementById('coordWInput');
const coordHInput = document.getElementById('coordHInput');
const btnApplyCoords = document.getElementById('btnApplyCoords');
const defaultCoords = { x: 31.77, y: 226.9, w: 136.17, h: 33.29 };

let pdfDoc = null;
let currentPage = 1;
let currentScale = 1;
let currentPagePdfHeight = 0;
let overlayRatio = 2.8;
let dragging = false;
let dragOffsetX = 0;
let dragOffsetY = 0;

async function renderPage(pageNum) {
    const page = await pdfDoc.getPage(pageNum);
    const baseViewport = page.getViewport({ scale: 1 });
    const maxWidth = Math.max(400, pdfStage.clientWidth - 16);
    const scale = Math.min(2.2, maxWidth / baseViewport.width);
    const viewport = page.getViewport({ scale });

    const ctx = pdfCanvas.getContext('2d');
    pdfCanvas.width = viewport.width;
    pdfCanvas.height = viewport.height;
    await page.render({ canvasContext: ctx, viewport }).promise;

    currentScale = viewport.scale;
    currentPagePdfHeight = baseViewport.height;
    pageInfo.textContent = `P\u00e1gina ${pageNum} de ${pdfDoc.numPages}`;
    pageInput.value = String(pageNum);
    hiddenPage.value = String(pageNum);

    overlayBox.style.maxWidth = `${pdfCanvas.width}px`;
    overlayBox.style.maxHeight = `${pdfCanvas.height}px`;
    clampOverlay();
    updateHiddenCoords();
}

function clampOverlay() {
    const maxLeft = Math.max(0, pdfCanvas.width - overlayBox.offsetWidth);
    const maxTop = Math.max(0, pdfCanvas.height - overlayBox.offsetHeight);
    const currentLeft = parseFloat(overlayBox.style.left || '0');
    const currentTop = parseFloat(overlayBox.style.top || '0');
    overlayBox.style.left = Math.max(0, Math.min(currentLeft, maxLeft)) + 'px';
    overlayBox.style.top = Math.max(0, Math.min(currentTop, maxTop)) + 'px';
}

function updateOverlaySizeByRange() {
    const w = parseFloat(sizeRange.value);
    const h = w / overlayRatio;
    overlayBox.style.width = `${w}px`;
    overlayBox.style.height = `${h}px`;
    clampOverlay();
    updateHiddenCoords();
}

function updateHiddenCoords() {
    if (overlayBox.style.display === 'none') {
        hiddenX.value = '';
        hiddenY.value = '';
        hiddenW.value = '';
        hiddenH.value = '';
        return;
    }

    const leftPx = parseFloat(overlayBox.style.left || '0');
    const topPx = parseFloat(overlayBox.style.top || '0');
    const wPx = overlayBox.offsetWidth;
    const hPx = overlayBox.offsetHeight;

    const pdfW = wPx / currentScale;
    const pdfH = hPx / currentScale;
    const pdfX = leftPx / currentScale;
    const pdfY = currentPagePdfHeight - (topPx / currentScale) - pdfH;

    hiddenPage.value = String(currentPage);
    hiddenX.value = pdfX.toFixed(2);
    hiddenY.value = pdfY.toFixed(2);
    hiddenW.value = pdfW.toFixed(2);
    hiddenH.value = pdfH.toFixed(2);
    updateManualInputsFromHidden();
}

function updateManualInputsFromHidden() {
    if (hiddenX.value !== '') coordXInput.value = hiddenX.value;
    if (hiddenY.value !== '') coordYInput.value = hiddenY.value;
    if (hiddenW.value !== '') coordWInput.value = hiddenW.value;
    if (hiddenH.value !== '') coordHInput.value = hiddenH.value;
}

function parseCoord(value, fallback) {
    const normalized = String(value ?? '').trim().replace(',', '.');
    const parsed = parseFloat(normalized);
    return Number.isNaN(parsed) ? fallback : parsed;
}

function applyManualCoords() {
    const pdfX = parseCoord(coordXInput.value || hiddenX.value, defaultCoords.x);
    const pdfY = parseCoord(coordYInput.value || hiddenY.value, defaultCoords.y);
    const pdfW = parseCoord(coordWInput.value || hiddenW.value, defaultCoords.w);
    const pdfH = parseCoord(coordHInput.value || hiddenH.value, defaultCoords.h);

    if ([pdfX, pdfY, pdfW, pdfH].some(v => Number.isNaN(v)) || pdfW <= 0 || pdfH <= 0) {
        alert('Coordenadas inv\u00e1lidas. Verifica X, Y, W y H.');
        return;
    }

    overlayBox.style.display = 'block';
    overlayBox.style.left = `${pdfX * currentScale}px`;
    overlayBox.style.top = `${(currentPagePdfHeight - pdfY - pdfH) * currentScale}px`;
    overlayBox.style.width = `${pdfW * currentScale}px`;
    overlayBox.style.height = `${pdfH * currentScale}px`;
    clampOverlay();
    updateHiddenCoords();
}

overlayBox.addEventListener('pointerdown', (e) => {
    dragging = true;
    const rect = overlayBox.getBoundingClientRect();
    dragOffsetX = e.clientX - rect.left;
    dragOffsetY = e.clientY - rect.top;
    overlayBox.setPointerCapture(e.pointerId);
});

overlayBox.addEventListener('pointermove', (e) => {
    if (!dragging) return;
    const stageRect = pdfStage.getBoundingClientRect();
    const newLeft = e.clientX - stageRect.left - dragOffsetX - 8;
    const newTop = e.clientY - stageRect.top - dragOffsetY - 8;
    overlayBox.style.left = `${newLeft}px`;
    overlayBox.style.top = `${newTop}px`;
    clampOverlay();
    updateHiddenCoords();
});

overlayBox.addEventListener('pointerup', (e) => {
    dragging = false;
    overlayBox.releasePointerCapture(e.pointerId);
    updateHiddenCoords();
});

fileInput.addEventListener('change', () => {
    const file = fileInput.files[0];
    if (!file) return;
    if (!/^image\/(png|jpeg)$/.test(file.type)) {
        alert('La firma subida debe ser PNG o JPG/JPEG.');
        fileInput.value = '';
        return;
    }
    hiddenTipo.value = 'subida';
    hiddenManual.value = '';
    const url = URL.createObjectURL(file);
    overlayImage.onload = () => {
        overlayRatio = overlayImage.naturalWidth / Math.max(1, overlayImage.naturalHeight);
        overlayBox.style.display = 'block';
        updateOverlaySizeByRange();
        updateManualInputsFromHidden();
    };
    overlayImage.src = url;
});

const drawCanvas = document.getElementById('drawSignatureCanvas');
const dctx = drawCanvas.getContext('2d');
dctx.lineWidth = 2;
dctx.lineCap = 'round';
dctx.strokeStyle = '#111827';
let drawing = false;

function pointFromEvent(ev) {
    const r = drawCanvas.getBoundingClientRect();
    return { x: ev.clientX - r.left, y: ev.clientY - r.top };
}

drawCanvas.addEventListener('pointerdown', (ev) => {
    drawing = true;
    const p = pointFromEvent(ev);
    dctx.beginPath();
    dctx.moveTo(p.x, p.y);
});
drawCanvas.addEventListener('pointermove', (ev) => {
    if (!drawing) return;
    const p = pointFromEvent(ev);
    dctx.lineTo(p.x, p.y);
    dctx.stroke();
});
drawCanvas.addEventListener('pointerup', () => { drawing = false; });
drawCanvas.addEventListener('pointerleave', () => { drawing = false; });

document.getElementById('btnClearCanvas').addEventListener('click', () => {
    dctx.clearRect(0, 0, drawCanvas.width, drawCanvas.height);
});

document.getElementById('btnUseCanvas').addEventListener('click', () => {
    const dataUrl = drawCanvas.toDataURL('image/png');
    hiddenTipo.value = 'manual';
    hiddenManual.value = dataUrl;
    fileInput.value = '';
    overlayImage.onload = () => {
        overlayRatio = overlayImage.naturalWidth / Math.max(1, overlayImage.naturalHeight);
        overlayBox.style.display = 'block';
        updateOverlaySizeByRange();
        updateManualInputsFromHidden();
    };
    overlayImage.src = dataUrl;
});

sizeRange.addEventListener('input', updateOverlaySizeByRange);
btnApplyCoords.addEventListener('click', applyManualCoords);

pageInput.addEventListener('change', async () => {
    let p = parseInt(pageInput.value || '1', 10);
    if (Number.isNaN(p)) p = 1;
    p = Math.max(1, Math.min(p, pdfDoc.numPages));
    currentPage = p;
    await renderPage(currentPage);
});

form.addEventListener('submit', (e) => {
    updateHiddenCoords();
    if (!hiddenTipo.value) {
        e.preventDefault();
        alert('Debe cargar o dibujar una firma antes de firmar.');
        return;
    }
    if (!hiddenX.value || !hiddenY.value || !hiddenW.value || !hiddenH.value) {
        e.preventDefault();
        alert('No se pudo calcular la posici\u00f3n de la firma.');
    }
});

(async function initPdf() {
    try {
        pdfDoc = await pdfjsLib.getDocument(pdfUrl).promise;
        pageInput.max = String(pdfDoc.numPages);
        currentPage = 1;
        hiddenX.value = defaultCoords.x.toFixed(2);
        hiddenY.value = defaultCoords.y.toFixed(2);
        hiddenW.value = defaultCoords.w.toFixed(2);
        hiddenH.value = defaultCoords.h.toFixed(2);
        await renderPage(currentPage);
        updateManualInputsFromHidden();
    } catch (err) {
        alert('No se pudo cargar el preview del PDF.');
    }
})();
</script>
<?php endif; ?>
</body>
</html>
