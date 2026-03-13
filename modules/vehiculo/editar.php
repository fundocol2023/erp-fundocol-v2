<?php
include '../../includes/navbar.php';
include_once '../../config/db.php';

$id = $_GET['id'] ?? null;
if (!$id) { header("Location: index.php"); exit; }

$mensaje = "";

// Consultar vehículo
$stmt = $pdo->prepare("SELECT * FROM vehiculos WHERE id = ?");
$stmt->execute([$id]);
$vehiculo = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$vehiculo) {
    echo "<div style='color:red;text-align:center;padding:60px'>Vehículo no encontrado.</div>";
    exit;
}

$uploads_dir = '../../uploads/vehiculos/';
if (!file_exists($uploads_dir)) {
    mkdir($uploads_dir, 0777, true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre'] ?? '');
    $placa = strtoupper(trim($_POST['placa'] ?? ''));
    $soat_vigencia = $_POST['soat_vigencia'] ?? '';
    $tecno_vigencia = $_POST['tecno_vigencia'] ?? '';
    $descripcion = trim($_POST['descripcion'] ?? '');
    $foto = $vehiculo['foto'];

    // Si suben nueva foto...
    if (!empty($_FILES['foto']['name'])) {
        $ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
        $permitidas = ['jpg', 'jpeg', 'png', 'webp'];
        if (in_array($ext, $permitidas)) {
            $nombre_foto = 'vehiculo_' . uniqid() . '.' . $ext;
            if (move_uploaded_file($_FILES['foto']['tmp_name'], $uploads_dir . $nombre_foto)) {
                // Borrar anterior si existe
                if ($foto && file_exists($uploads_dir . $foto)) unlink($uploads_dir . $foto);
                $foto = $nombre_foto;
            } else {
                $mensaje = "No se pudo subir la nueva foto.";
            }
        } else {
            $mensaje = "Formato de imagen no permitido. Solo JPG, PNG o WEBP.";
        }
    }

    if (!$nombre || !$placa || !$soat_vigencia || !$tecno_vigencia) {
        $mensaje = "Por favor, complete todos los campos obligatorios.";
    } else if (!$mensaje) {
        $sql = "UPDATE vehiculos SET nombre=?, placa=?, foto=?, soat_vigencia=?, tecno_vigencia=?, descripcion=? WHERE id=?";
        $stmt = $pdo->prepare($sql);
        $ok = $stmt->execute([
            $nombre,
            $placa,
            $foto,
            $soat_vigencia,
            $tecno_vigencia,
            $descripcion,
            $id
        ]);
        if ($ok) {
            header("Location: index.php?msg=vehiculo_editado");
            exit;
        } else {
            $mensaje = "No se pudo actualizar el vehículo. Intente de nuevo.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Vehículo</title>
    <link rel="stylesheet" href="../../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@500;700&display=swap" rel="stylesheet">
    <!-- estilos movidos a assets/css/style.css -->
</head>
<body class="vehiculos-editar-page">
<div class="navbar-spacer"></div>
<div class="form-contenedor">
    <div class="form-titulo">
        <span><i class="bi bi-truck-front"></i></span>
        Editar Vehículo
    </div>
    <div class="form-sub">
        Modifica aquí los datos principales del vehículo institucional.
    </div>
    <?php if($mensaje): ?><div class="msg-error"><?= $mensaje ?></div><?php endif; ?>
    <form method="post" enctype="multipart/form-data" autocomplete="off">
        <div class="form-group">
            <label class="form-label" for="nombre">Nombre del vehículo <span style="color:#ef4444">*</span></label>
            <input type="text" name="nombre" id="nombre" class="form-control" required maxlength="80"
                   placeholder="Ej: Duster Gris" value="<?= htmlspecialchars($_POST['nombre'] ?? $vehiculo['nombre']) ?>">
        </div>
        <div class="form-group">
            <label class="form-label" for="placa">Placa <span style="color:#ef4444">*</span></label>
            <input type="text" name="placa" id="placa" class="form-control" required maxlength="15"
                   placeholder="Ej: HZU-789" style="text-transform:uppercase;"
                   value="<?= htmlspecialchars($_POST['placa'] ?? $vehiculo['placa']) ?>">
        </div>
        <div class="form-group">
            <label class="form-label" for="foto">Foto del vehículo <span class="form-nota">(JPG, PNG, WEBP, opcional)</span></label>
            <?php if ($vehiculo['foto'] && file_exists($uploads_dir . $vehiculo['foto'])): ?>
                <img src="<?= $uploads_dir . $vehiculo['foto'] ?>" class="foto-miniatura" alt="Actual">
            <?php endif; ?>
            <div class="foto-upload-box" id="dropzone-foto">
                <i class="bi bi-cloud-upload"></i>
                <div>Arrastra o haz clic<br>para cambiar imagen</div>
                <input type="file" name="foto" id="foto" accept=".jpg,.jpeg,.png,.webp">
                <span id="nombre-foto"></span>
            </div>
            <div class="form-nota" style="text-align:center;">
                <?= $vehiculo['foto'] ? "Si no seleccionas una nueva imagen se conservará la actual." : "" ?>
            </div>
        </div>
        <div class="form-group">
            <label class="form-label" for="soat_vigencia">Vigencia SOAT <span style="color:#ef4444">*</span></label>
            <input type="date" name="soat_vigencia" id="soat_vigencia" class="form-control" required value="<?= htmlspecialchars($_POST['soat_vigencia'] ?? $vehiculo['soat_vigencia']) ?>">
        </div>
        <div class="form-group">
            <label class="form-label" for="tecno_vigencia">Vigencia Tecnomecánica <span style="color:#ef4444">*</span></label>
            <input type="date" name="tecno_vigencia" id="tecno_vigencia" class="form-control" required value="<?= htmlspecialchars($_POST['tecno_vigencia'] ?? $vehiculo['tecno_vigencia']) ?>">
        </div>
        <div class="form-group">
            <label class="form-label" for="descripcion">Descripción</label>
            <textarea name="descripcion" id="descripcion" class="form-control" maxlength="300" rows="2"
                      placeholder="Observaciones, tipo, uso, etc."><?= htmlspecialchars($_POST['descripcion'] ?? $vehiculo['descripcion']) ?></textarea>
        </div>
        <div class="form-btns">
            <button type="submit" class="btn-principal"><i class="bi bi-check2-circle"></i> Guardar cambios</button>
            <a href="index.php" class="btn-sec">Cancelar</a>
        </div>
    </form>
</div>
<script>
    // Drag & drop para la imagen
    const dropzone = document.getElementById('dropzone-foto');
    const inputFoto = document.getElementById('foto');
    const nombreFoto = document.getElementById('nombre-foto');
    dropzone.addEventListener('click', () => inputFoto.click());
    dropzone.addEventListener('dragover', e => {
        e.preventDefault(); dropzone.classList.add('dragover');
    });
    dropzone.addEventListener('dragleave', () => dropzone.classList.remove('dragover'));
    dropzone.addEventListener('drop', e => {
        e.preventDefault(); dropzone.classList.remove('dragover');
        if (e.dataTransfer.files.length) {
            inputFoto.files = e.dataTransfer.files;
            nombreFoto.textContent = e.dataTransfer.files[0].name;
        }
    });
    inputFoto.addEventListener('change', () => {
        if (inputFoto.files.length) nombreFoto.textContent = inputFoto.files[0].name;
        else nombreFoto.textContent = '';
    });
</script>
</body>
</html>
