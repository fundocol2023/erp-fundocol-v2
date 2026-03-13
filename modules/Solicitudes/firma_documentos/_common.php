<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../includes/phpmailer.php';

function firmaRequireLogin(): void
{
    if (!isset($_SESSION['usuario_id'])) {
        header('Location: ../../../login.php');
        exit();
    }
}

function firmaRequireRol(int $rolPermitido): void
{
    firmaRequireLogin();
    if ((int)($_SESSION['usuario_rol'] ?? 0) !== $rolPermitido) {
        http_response_code(403);
        echo '<div style="padding:18px;font-family:Arial,sans-serif;">Acceso denegado para este m&oacute;dulo.</div>';
        exit();
    }
}

function firmaEnsureStorageDirs(): void
{
    $dirs = [
        __DIR__ . '/../../../uploads/firma_documentos/originales',
        __DIR__ . '/../../../uploads/firma_documentos/firmados',
        __DIR__ . '/../../../uploads/firma_documentos/firmas',
    ];
    foreach ($dirs as $dir) {
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
    }
}

function firmaEnsureTable(PDO $pdo): void
{
    $sql = "CREATE TABLE IF NOT EXISTS documentos_firma (
        id INT AUTO_INCREMENT PRIMARY KEY,
        solicitante_id INT NOT NULL,
        nombre_documento VARCHAR(255) NOT NULL,
        razon TEXT NOT NULL,
        archivo_original VARCHAR(255) NOT NULL,
        archivo_firmado VARCHAR(255) NULL,
        estado ENUM('pendiente','firmado') NOT NULL DEFAULT 'pendiente',
        firmado_por_id INT NULL,
        firma_tipo ENUM('subida','manual') NULL,
        firma_archivo VARCHAR(255) NULL,
        firma_page INT NULL,
        firma_x FLOAT NULL,
        firma_y FLOAT NULL,
        firma_w FLOAT NULL,
        firma_h FLOAT NULL,
        ip_firma VARCHAR(64) NULL,
        user_agent VARCHAR(255) NULL,
        fecha_solicitud TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        fecha_firma DATETIME NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $pdo->exec($sql);
}

function firmaGetRelativeRootPrefix(): string
{
    return '../../../';
}

function firmaAbsFromRelative(string $relativePath): string
{
    return dirname(__DIR__, 3) . '/' . ltrim(str_replace('\\', '/', $relativePath), '/');
}

function firmaPublicUrlFromRelative(string $relativePath): string
{
    return firmaGetRelativeRootPrefix() . ltrim(str_replace('\\', '/', $relativePath), '/');
}

function firmaBuildAbsoluteUrl(string $relativePath): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
    return $scheme . '://' . $host . $basePath . '/' . ltrim($relativePath, '/');
}

function firmaResumen(string $text, int $max = 80): string
{
    $text = trim($text);
    if (mb_strlen($text, 'UTF-8') <= $max) {
        return $text;
    }
    return mb_substr($text, 0, $max - 3, 'UTF-8') . '...';
}

function firmaSanitizeFileName(string $name): string
{
    $name = preg_replace('/[^a-zA-Z0-9_-]/', '_', $name) ?? 'archivo';
    return trim($name, '_') ?: 'archivo';
}

function firmaHandleUploadedSignatureToPng(array $file, string $targetRelativePath): array
{
    $tmp = $file['tmp_name'] ?? '';
    if (!$tmp || !is_uploaded_file($tmp)) {
        return [false, 'No se recibi&oacute; archivo de firma.', ''];
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($tmp) ?: '';
    $targetAbs = firmaAbsFromRelative($targetRelativePath);

    if ($mime === 'image/png') {
        if (!move_uploaded_file($tmp, $targetAbs)) {
            return [false, 'No fue posible guardar la firma PNG.', ''];
        }
        return [true, '', $targetRelativePath];
    }

    if ($mime === 'image/jpeg') {
        if (!function_exists('imagecreatefromjpeg') || !function_exists('imagepng')) {
            return [false, 'No se puede convertir JPG a PNG (falta GD en PHP).', ''];
        }
        $img = @imagecreatefromjpeg($tmp);
        if (!$img) {
            return [false, 'No se pudo leer la imagen JPG de la firma.', ''];
        }
        imagesavealpha($img, true);
        if (!imagepng($img, $targetAbs)) {
            imagedestroy($img);
            return [false, 'No fue posible convertir la firma a PNG.', ''];
        }
        imagedestroy($img);
        return [true, '', $targetRelativePath];
    }

    return [false, 'Tipo de firma no v&aacute;lido. Solo PNG/JPG/JPEG.', ''];
}

function firmaHandleManualSignatureToPng(string $dataUrl, string $targetRelativePath): array
{
    if (!preg_match('/^data:image\/png;base64,/', $dataUrl)) {
        return [false, 'La firma manual debe estar en formato PNG base64.', ''];
    }

    $raw = base64_decode(substr($dataUrl, strpos($dataUrl, ',') + 1), true);
    if ($raw === false || strlen($raw) < 100) {
        return [false, 'Firma manual inv&aacute;lida o vac&iacute;a.', ''];
    }

    if (!function_exists('imagecreatefromstring') || !function_exists('imagepng')) {
        return [false, 'No se puede procesar firma manual (falta GD en PHP).', ''];
    }

    $img = @imagecreatefromstring($raw);
    if (!$img) {
        return [false, 'No se pudo leer la imagen de firma manual.', ''];
    }
    imagesavealpha($img, true);

    $targetAbs = firmaAbsFromRelative($targetRelativePath);
    if (!imagepng($img, $targetAbs)) {
        imagedestroy($img);
        return [false, 'No fue posible guardar la firma manual.', ''];
    }
    imagedestroy($img);
    return [true, '', $targetRelativePath];
}

function firmaRenderEstadoBadge(string $estado): string
{
    if ($estado === 'firmado') {
        return '<span class="badge bg-success-subtle text-success-emphasis border border-success-subtle">Firmado</span>';
    }
    return '<span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle">Pendiente</span>';
}
?>
