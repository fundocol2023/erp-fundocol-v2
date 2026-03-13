<?php
include '../../../includes/navbar.php';
include_once '../../../config/db.php';

$uploads_dir = '../../../uploads/papeleria/';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$id) {
    header("Location: index.php");
    exit;
}

// Obtener producto
$stmt = $pdo->prepare("SELECT * FROM inventario_papeleria WHERE id = ?");
$stmt->execute([$id]);
$prod = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$prod) {
    header("Location: index.php");
    exit;
}

// Eliminar imagen física si existe
if ($prod['imagen'] && file_exists($uploads_dir.$prod['imagen'])) {
    unlink($uploads_dir.$prod['imagen']);
}

// Eliminar producto de la base de datos
$stmt2 = $pdo->prepare("DELETE FROM inventario_papeleria WHERE id = ?");
$stmt2->execute([$id]);

header("Location: index.php?msg=eliminado");
exit;
