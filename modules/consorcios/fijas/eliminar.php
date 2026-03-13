<?php
session_start();
require_once '../../../config/db.php';

// ================================
// 1️⃣ Validar sesion y rol
// ================================
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../../../login.php');
    exit;
}

if ($_SESSION['usuario_rol'] != 7) {
    echo "Sin permisos para eliminar registros.";
    exit;
}

// ================================
// 2️⃣ Validar ID
// ================================
$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    echo "ID invalido.";
    exit;
}

// ================================
// 3️⃣ Traer registro para eliminar archivos
// ================================
$stmt = $pdo->prepare("
    SELECT archivo_cotizacion, archivo_rut, archivo_certificacion
    FROM compras_fijas_consorcios
    WHERE id = ?
");
$stmt->execute([$id]);
$compra = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$compra) {
    echo "Registro no encontrado.";
    exit;
}

// ================================
// 4️⃣ Eliminar archivos fisicos (si existen)
// ================================
$ruta_base = '../../../uploads/compras_fijas_consorcios/';

$archivos = [
    $compra['archivo_cotizacion'],
    $compra['archivo_rut'],
    $compra['archivo_certificacion']
];

foreach ($archivos as $archivo) {
    if ($archivo && file_exists($ruta_base . $archivo)) {
        unlink($ruta_base . $archivo);
    }
}

// ================================
// 5️⃣ Eliminar registro de BD
// ================================
$stmt = $pdo->prepare("DELETE FROM compras_fijas_consorcios WHERE id = ?");
$stmt->execute([$id]);

// ================================
// 6️⃣ Redireccion
// ================================
header("Location: compras_fijas.php?msg=eliminado_ok");
exit;
