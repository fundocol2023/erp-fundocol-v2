<?php
include '../../../includes/navbar.php';
include_once '../../../config/db.php';

$mensaje = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tipo = trim($_POST['tipo'] ?? '');
    $marca = trim($_POST['marca'] ?? '');
    $modelo = trim($_POST['modelo'] ?? '');
    $serial = trim($_POST['serial'] ?? '');
    $hostname = trim($_POST['hostname'] ?? '');
    $so = trim($_POST['so'] ?? '');
    $procesador = trim($_POST['procesador'] ?? '');
    $ram = trim($_POST['ram'] ?? '');
    $almacenamiento = trim($_POST['almacenamiento'] ?? '');
    $ubicacion = trim($_POST['ubicacion'] ?? '');
    $area = trim($_POST['area'] ?? '');
    $responsable = trim($_POST['responsable'] ?? '');
    $estado = $_POST['estado'] ?? 'disponible';
    $fecha_compra = $_POST['fecha_compra'] ?? null;
    $garantia_fin = $_POST['garantia_fin'] ?? null;
    $notas = trim($_POST['notas'] ?? '');

    if ($tipo === '') {
        $mensaje = 'El tipo de equipo es obligatorio.';
    } else {
        $sql = "INSERT INTO inventario_equipos (tipo, marca, modelo, serial, hostname, so, procesador, ram, almacenamiento, ubicacion, area, responsable, estado, fecha_compra, garantia_fin, notas)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $ok = $stmt->execute([
            $tipo,
            $marca ?: null,
            $modelo ?: null,
            $serial ?: null,
            $hostname ?: null,
            $so ?: null,
            $procesador ?: null,
            $ram ?: null,
            $almacenamiento ?: null,
            $ubicacion ?: null,
            $area ?: null,
            $responsable ?: null,
            $estado,
            $fecha_compra ?: null,
            $garantia_fin ?: null,
            $notas ?: null
        ]);
        if ($ok) {
            header('Location: index.php?msg=ok');
            exit;
        }
        $mensaje = 'Error al guardar el equipo.';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Registrar Equipo</title>
    <link rel="stylesheet" href="../../../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@500;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="https://erp-fundocol.fundocol.org/assets/img/Fundocol_favicon.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="inventarios-equipos-page">
<div class="navbar-spacer"></div>
<div class="form-contenedor equipos-form">
    <div class="equipos-form-head">
        <div class="equipos-form-icon"><i class="bi bi-cpu"></i></div>
        <div>
            <div class="form-titulo">Registrar equipo</div>
            <div class="equipos-form-sub">Ficha tecnica y estado operativo del equipo.</div>
        </div>
    </div>
    <?php if ($mensaje): ?><div class="msg-error"><?= htmlspecialchars($mensaje) ?></div><?php endif; ?>
    <form method="post" autocomplete="off" class="form-grid">
        <div class="form-section-title">Identificacion</div>
        <div class="form-group span-2">
            <label class="form-label" for="tipo">Tipo *</label>
            <input type="text" name="tipo" id="tipo" class="form-control" required maxlength="120" placeholder="Portatil, Desktop, Servidor, NAS...">
        </div>
        <div class="form-group">
            <label class="form-label" for="marca">Marca</label>
            <input type="text" name="marca" id="marca" class="form-control" maxlength="120" placeholder="Dell, HP, Lenovo...">
        </div>
        <div class="form-group">
            <label class="form-label" for="modelo">Modelo</label>
            <input type="text" name="modelo" id="modelo" class="form-control" maxlength="120" placeholder="Latitude 5420">
        </div>
        <div class="form-group">
            <label class="form-label" for="serial">Serial</label>
            <input type="text" name="serial" id="serial" class="form-control" maxlength="120" placeholder="Serial del fabricante">
        </div>
        <div class="form-group">
            <label class="form-label" for="hostname">Hostname</label>
            <input type="text" name="hostname" id="hostname" class="form-control" maxlength="120" placeholder="PC-AREA-001">
        </div>

        <div class="form-section-title">Especificaciones</div>
        <div class="form-group">
            <label class="form-label" for="so">Sistema operativo</label>
            <input type="text" name="so" id="so" class="form-control" maxlength="120" placeholder="Windows 11 Pro / Ubuntu 22.04">
        </div>
        <div class="form-group">
            <label class="form-label" for="procesador">Procesador</label>
            <input type="text" name="procesador" id="procesador" class="form-control" maxlength="160" placeholder="Intel i5 11400 / Ryzen 5">
        </div>
        <div class="form-group">
            <label class="form-label" for="ram">RAM</label>
            <input type="text" name="ram" id="ram" class="form-control" maxlength="60" placeholder="16 GB">
        </div>
        <div class="form-group">
            <label class="form-label" for="almacenamiento">Almacenamiento</label>
            <input type="text" name="almacenamiento" id="almacenamiento" class="form-control" maxlength="120" placeholder="512 GB SSD">
        </div>

        <div class="form-section-title">Gestion</div>
        <div class="form-group">
            <label class="form-label" for="responsable">Responsable</label>
            <input type="text" name="responsable" id="responsable" class="form-control" maxlength="120" placeholder="Nombre del responsable">
        </div>
        <div class="form-group">
            <label class="form-label" for="area">Area</label>
            <input type="text" name="area" id="area" class="form-control" maxlength="120" placeholder="Area o proceso">
        </div>
        <div class="form-group">
            <label class="form-label" for="ubicacion">Ubicacion</label>
            <input type="text" name="ubicacion" id="ubicacion" class="form-control" maxlength="120" placeholder="Sede, oficina, rack...">
        </div>
        <div class="form-group">
            <label class="form-label" for="estado">Estado</label>
            <select name="estado" id="estado" class="form-control">
                <option value="disponible">Disponible</option>
                <option value="asignado">Asignado</option>
                <option value="mantenimiento">Mantenimiento</option>
                <option value="baja">Baja</option>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label" for="fecha_compra">Fecha de compra</label>
            <input type="date" name="fecha_compra" id="fecha_compra" class="form-control">
        </div>
        <div class="form-group">
            <label class="form-label" for="garantia_fin">Garantia hasta</label>
            <input type="date" name="garantia_fin" id="garantia_fin" class="form-control">
        </div>

        <div class="form-section-title">Notas</div>
        <div class="form-group form-group-full">
            <label class="form-label" for="notas">Observaciones</label>
            <textarea name="notas" id="notas" class="form-control" rows="3" maxlength="700" placeholder="Licencias, estado fisico, configuracion, etc."></textarea>
        </div>
        <div class="form-btns form-group-full">
            <button type="submit" class="btn-principal"><i class="bi bi-check2-circle"></i> Guardar</button>
            <a href="index.php" class="btn-sec">Cancelar</a>
        </div>
    </form>
</div>
</body>
</html>
