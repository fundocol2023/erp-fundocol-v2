<?php
include_once '../../../config/db.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare("DELETE FROM inventario_correos WHERE id = ?");
$stmt->execute([$id]);

header('Location: index.php?msg=eliminado');
exit;
