<?php
include '../../../includes/navbar.php';
include_once '../../../config/db.php';

$uploads_dir = '../../../uploads/papeleria/';
if (!file_exists($uploads_dir)) {
    mkdir($uploads_dir, 0777, true);
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$id) {
    header("Location: index.php");
    exit;
}

// Traer datos actuales
$stmt = $pdo->prepare("SELECT * FROM inventario_papeleria WHERE id = ?");
$stmt->execute([$id]);
$prod = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$prod) {
    header("Location: index.php");
    exit;
}

$mensaje = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nombre = trim($_POST['nombre'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $cantidad = intval($_POST['cantidad'] ?? 0);
    $observaciones = trim($_POST['observaciones'] ?? '');
    $imagen = $prod['imagen'];

    // ¿Nueva imagen?
    if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] == 0) {
        $ext = strtolower(pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION));
        $permitidas = ['jpg', 'jpeg', 'png', 'webp'];
        if (in_array($ext, $permitidas)) {
            // Borrar anterior si existe
            if ($imagen && file_exists($uploads_dir.$imagen)) unlink($uploads_dir.$imagen);
            $nombre_archivo = uniqid('papel_').'.'.$ext;
            move_uploaded_file($_FILES['imagen']['tmp_name'], $uploads_dir.$nombre_archivo);
            $imagen = $nombre_archivo;
        }
    }

    // Actualizar BD
    $sql = "UPDATE inventario_papeleria SET nombre=?, descripcion=?, cantidad=?, imagen=?, observaciones=? WHERE id=?";
    $stmt2 = $pdo->prepare($sql);
    $ok = $stmt2->execute([$nombre, $descripcion, $cantidad, $imagen, $observaciones, $id]);

    if ($ok) {
        header("Location: index.php?msg=edit");
        exit;
    } else {
        $mensaje = "Error al guardar los cambios.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Producto - Papelería</title>
    <link rel="stylesheet" href="../../../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@500;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="https://erp-fundocol.fundocol.org/assets/img/Fundocol_favicon.png">
    <!-- estilos movidos a assets/css/style.css -->
    <script>
    function previewImg(input) {
        let prev = document.getElementById('img-prev');
        prev.innerHTML = "";
        if (input.files && input.files[0]) {
            let reader = new FileReader();
            reader.onload = function(e) {
                prev.innerHTML = "<img src='"+e.target.result+"' alt='Preview'>";
            };
            reader.readAsDataURL(input.files[0]);
        }
    }
    </script>
</head>
<body class="inventarios-papeleria-editar-page">
<div class="navbar-spacer"></div>
<div class="form-contenedor">
    <div class="form-titulo">Editar Producto de Papelería</div>
    <?php if($mensaje): ?><div class="msg-error"><?= $mensaje ?></div><?php endif; ?>
    <form method="post" enctype="multipart/form-data" autocomplete="off">
        <div class="form-group">
            <label class="form-label" for="nombre">Nombre *</label>
            <input type="text" name="nombre" id="nombre" class="form-control" required maxlength="100" value="<?= htmlspecialchars($prod['nombre']) ?>">
        </div>
        <div class="form-group">
            <label class="form-label" for="descripcion">Descripción</label>
            <input type="text" name="descripcion" id="descripcion" class="form-control" maxlength="250" value="<?= htmlspecialchars($prod['descripcion']) ?>">
        </div>
        <div class="form-group">
            <label class="form-label" for="cantidad">Cantidad *</label>
            <input type="number" name="cantidad" id="cantidad" class="form-control" required min="0" max="10000" value="<?= intval($prod['cantidad']) ?>">
        </div>
        <div class="form-group">
            <label class="form-label" for="imagen">Imagen <span class="form-nota">(JPG, PNG, WEBP, opcional)</span></label>
            <input type="file" name="imagen" id="imagen" class="form-control" accept="image/*" onchange="previewImg(this)">
            <div class="img-preview" id="img-prev"></div>
            <?php if($prod['imagen'] && file_exists($uploads_dir.$prod['imagen'])): ?>
                <div class="old-img">
                    <span>Actual:</span>
                    <img src="<?= $uploads_dir.$prod['imagen'] ?>" style="max-width:38px;max-height:38px; border-radius:5px; border:1px solid #e0e7ef;">
                </div>
            <?php endif; ?>
        </div>
        <div class="form-group">
            <label class="form-label" for="observaciones">Observaciones</label>
            <textarea name="observaciones" id="observaciones" class="form-control" maxlength="255" rows="2"><?= htmlspecialchars($prod['observaciones']) ?></textarea>
        </div>
        <div class="form-btns">
            <button type="submit" class="btn-principal"><i class="bi bi-check2-circle"></i> Guardar</button>
            <a href="index.php" class="btn-sec">Cancelar</a>
        </div>
    </form>
</div>
</body>
</html>