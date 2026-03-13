<?php
session_start();
require_once '../../../config/db.php';

// ===============================
// VALIDACIONES DE SEGURIDAD
// ===============================
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../../../login.php');
    exit();
}

$rol = $_SESSION['usuario_rol'] ?? null;

// SOLO SISTEMAS (ROL 7)
if ($rol != 7) {
    echo "<h3 style='color:red;text-align:center;margin-top:50px;'>Acceso denegado</h3>";
    exit();
}

// ===============================
// VALIDAR ID
// ===============================
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id <= 0) {
    header("Location: index.php?msg=id_invalido");
    exit();
}

// ===============================
// VERIFICAR EXISTENCIA
// ===============================
$stmt = $pdo->prepare("SELECT id FROM compras_fijas WHERE id = ?");
$stmt->execute([$id]);
$existe = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$existe) {
    header("Location: index.php?msg=no_existe");
    exit();
}

// ===============================
// ELIMINAR REGISTRO
// ===============================
try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("DELETE FROM compras_fijas WHERE id = ?");
    $stmt->execute([$id]);

    // (Opcional) Aquí puedes agregar LOG de auditoría
    /*
    $pdo->prepare("INSERT INTO logs_sistema 
        (usuario_id, accion, referencia_id, modulo, fecha)
        VALUES (?, 'ELIMINAR_COMPRA_FIJA', ?, 'compras_fijas', NOW())")
        ->execute([$_SESSION['usuario_id'], $id]);
    */

    $pdo->commit();

    header("Location: index.php?msg=eliminado_ok");
    exit();

} catch (Exception $e) {
    $pdo->rollBack();
    header("Location: index.php?msg=error_eliminar");
    exit();
}
