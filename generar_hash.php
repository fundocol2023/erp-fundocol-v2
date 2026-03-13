<?php
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $clave = $_POST["clave"] ?? "";
    if ($clave) {
        $hash = password_hash($clave, PASSWORD_DEFAULT);
        echo "<b>Contraseña:</b> " . htmlspecialchars($clave) . "<br>";
        echo "<b>Hash:</b> <input style='width:400px' value='".htmlspecialchars($hash)."' readonly>";
        echo "<br><br><a href='generar_hash.php'>Volver</a>";
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Generar hash de contraseña</title>
    <style>
        body { background: #f7f7f7; font-family: Arial; display:flex;justify-content:center;align-items:center;height:100vh;}
        form { background: #fff; padding: 30px; border-radius: 10px; box-shadow:0 0 12px #bbb; }
        input[type=password] { padding: 8px 14px; border-radius: 6px; border:1px solid #ccc; font-size:16px; width:240px;}
        button { padding: 8px 20px; border-radius:6px; border:none; background:#2980b9; color:#fff; font-size:16px;}
    </style>
</head>
<body>
    <form method="post">
        <h2>Generar Hash de Contraseña</h2>
        <input type="password" name="clave" placeholder="Contraseña" required>
        <button type="submit">Generar</button>
    </form>
</body>
</html>
