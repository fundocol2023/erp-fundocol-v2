<?php
session_start();
include '../../includes/navbar.php';
require_once '../../config/db.php';

/* ===============================================
   1) ACTIVAR MODO DEPURACIÓN SQL
   =============================================== */
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

function mostrarError($mensaje, $sql = null, $error = null) {
    echo "<div style='
        margin:30px auto;
        width:90%;
        background:#ffe2e2;
        padding:25px;
        border-left:8px solid #dc2626;
        border-radius:8px;
        font-family:Arial;
        color:#7a1717;
        font-size:16px;
        line-height:1.5;
    '>";

    echo "<strong style='font-size:18px;'>⚠ ERROR DETECTADO EN EL MÓDULO</strong><br><br>";
    echo "<strong>Descripción:</strong><br>" . htmlspecialchars($mensaje) . "<br><br>";

    if ($sql) {
        echo "<strong>Consulta Ejecutada:</strong><br>
              <pre style='white-space:pre-wrap; background:#fff; padding:10px; border-radius:6px;'>
" . htmlspecialchars($sql) . "
              </pre><br>";
    }

    if ($error) {
        echo "<strong>Detalles del Error:</strong><br>
              <pre style='white-space:pre-wrap; background:#fff; padding:10px; border-radius:6px;'>
" . htmlspecialchars($error) . "
              </pre><br>";
    }

    echo "</div>";
}

/* ===============================================
   2) VARIABLES DE SESIÓN
   =============================================== */
$rol        = $_SESSION['usuario_rol'] ?? 0;
$id_usuario = $_SESSION['usuario_id'] ?? 0;

/* ===============================================
   3) CONDICIONES SEGÚN ROL
   =============================================== */
if (in_array($rol, [4, 10, 5])) {

    // Presupuesto, Sistemas y Contabilidad ven todo
    $cond_cf  = "1=1";
    $cond_sc  = "1=1";
    $cond_cfc = "1=1";
    $cond_scc = "1=1";

} elseif ($rol == 6) {

    // Pagos solo ve finalizadas
    $cond_cf  = "cf.estado IN ('aprobado_pagos')";
    $cond_sc  = "sc.estado IN ('pago_confirmado')";
    $cond_cfc = "cfc.estado IN ('aprobado_pagos')";
    $cond_scc = "scc.estado IN ('pago_confirmado')";

} elseif ($rol == 13) {

    // Rol 13 solo consorcios Aburra
    $cond_cf  = "1=0";
    $cond_sc  = "1=0";
    $cond_cfc = "cfc.consorcio = 'Consorcio Aburra 2025'";
    $cond_scc = "scc.consorcio = 'Consorcio Aburra 2025'";

} else {

    // Otros roles: solo sus solicitudes
    $cond_cf  = "cf.solicitante_id = " . intval($id_usuario);
    $cond_sc  = "sc.solicitante_id = " . intval($id_usuario);
    $cond_cfc = "cfc.solicitante_id = " . intval($id_usuario);
    $cond_scc = "scc.solicitante_id = " . intval($id_usuario);
}

/* ===============================================
   4) SQL FUNDOCOL
   =============================================== */
$sql_fundocol = "
    SELECT 
        'Fundocol - Compra Fija' AS tipo,
        cf.id,
        u.nombre AS solicitante,
        cf.fecha_solicitud AS fecha,
        cf.categoria,
        cf.proveedor,
        cf.monto,
        cf.estado,
        'Fundocol' AS proyecto_o_consorcio
    FROM compras_fijas cf
    INNER JOIN usuarios u ON cf.solicitante_id = u.id
    WHERE $cond_cf

    UNION ALL

    SELECT 
        'Fundocol - Solicitud de Compra' AS tipo,
        sc.id,
        u.nombre AS solicitante,
        sc.fecha AS fecha,
        sc.necesidad AS categoria,
        sc.proveedor,
        NULL AS monto,
        sc.estado,
        CASE 
            WHEN sc.proyecto_oficina IS NULL OR TRIM(sc.proyecto_oficina) = '' THEN 'Fundocol'
            ELSE sc.proyecto_oficina
        END AS proyecto_o_consorcio
    FROM solicitudes_compra sc
    INNER JOIN usuarios u ON sc.solicitante_id = u.id
    WHERE $cond_sc
";

/* ===============================================
   5) SQL CONSORCIOS
   =============================================== */
$sql_consorcios = "
    SELECT 
        'Consorcio - Compra Fija' AS tipo,
        cfc.id,
        u.nombre AS solicitante,
        cfc.fecha AS fecha,
        cfc.categoria,
        cfc.proveedor,
        cfc.monto,
        cfc.estado,
        cfc.consorcio AS proyecto_o_consorcio
    FROM compras_fijas_consorcios cfc
    INNER JOIN usuarios u ON cfc.solicitante_id = u.id
    WHERE $cond_cfc

    UNION ALL

    SELECT 
        'Consorcio - Solicitud de Compra' AS tipo,
        scc.id,
        u.nombre AS solicitante,
        scc.fecha AS fecha,
        scc.necesidad AS categoria,
        scc.proveedor,
        COALESCE(prod.total_monto, 0) AS monto,
        scc.estado,
        scc.consorcio AS proyecto_o_consorcio
    FROM solicitudes_compra_consorcios scc
    INNER JOIN usuarios u ON scc.solicitante_id = u.id
    LEFT JOIN (
        SELECT 
            solicitud_compra_id,
            SUM(precio_total) AS total_monto
        FROM solicitudes_compra_consorcios_productos
        GROUP BY solicitud_compra_id
    ) prod ON prod.solicitud_compra_id = scc.id
    WHERE $cond_scc
";

/* ===============================================
   6) EJECUTAR CONSULTAS
   =============================================== */
try {
    $sol_fundocol = $pdo->query($sql_fundocol)->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    mostrarError("Error ejecutando consulta FUNDOCOL", $sql_fundocol, $e->getMessage());
    exit;
}

try {
    $sol_consorcios = $pdo->query($sql_consorcios)->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    mostrarError("Error ejecutando consulta CONSORCIOS", $sql_consorcios, $e->getMessage());
    exit;
}

$solicitudes = array_merge($sol_fundocol, $sol_consorcios);

/* ===============================================
   7) ORDENAR POR FECHA DESC
   =============================================== */
usort($solicitudes, function($a, $b) {
    return strcmp($b['fecha'], $a['fecha']);
});

/* ===============================================
   8) EXTRAER LISTA DE PROYECTOS/CONSORCIOS
   =============================================== */
$lista_proyectos = [];

foreach ($solicitudes as $s) {
    $valor = trim($s['proyecto_o_consorcio'] ?? '');
    if ($valor === '') {
        $valor = 'Fundocol';
    }
    $lista_proyectos[] = $valor;
}

$lista_proyectos = array_unique($lista_proyectos);
sort($lista_proyectos);

/* ===============================================
   9) EXTRAER LISTA DE ESTADOS REALES
   =============================================== */
$lista_estados = [];

foreach ($solicitudes as $s) {
    if (!empty($s['estado'])) {
        $lista_estados[] = strtolower(trim($s['estado']));
    }
}

$lista_estados = array_unique($lista_estados);
sort($lista_estados);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel General de Solicitudes</title>

    <link rel="stylesheet" href="../../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <style>
        .filtros-panel{
            background:#fff;
            border:1px solid #e5e7eb;
            border-radius:16px;
            padding:18px;
            box-shadow:0 8px 24px rgba(15,23,42,.05);
            margin-bottom:18px;
        }
        .filtros-grid{
            display:grid;
            grid-template-columns: 1.2fr 1fr 1fr auto;
            gap:14px;
            align-items:end;
        }
        .filtro-box label{
            display:block;
            font-size:13px;
            font-weight:700;
            color:#334155;
            margin-bottom:6px;
        }
        .filtro-box input,
        .filtro-box select{
            width:100%;
            border:1px solid #cbd5e1;
            border-radius:10px;
            padding:10px 12px;
            font-size:14px;
            background:#fff;
        }
        .filtro-box input:focus,
        .filtro-box select:focus{
            outline:none;
            border-color:#2563eb;
            box-shadow:0 0 0 3px rgba(37,99,235,.12);
        }
        .tipos-wrap{
            display:flex;
            flex-wrap:wrap;
            gap:8px;
            margin-top:14px;
        }
        .tipos-wrap .filtro-btn{
            border-radius:999px;
            padding:8px 14px;
            font-weight:600;
        }
        .panel-tools{
            display:flex;
            justify-content:space-between;
            align-items:center;
            gap:14px;
            margin-bottom:10px;
            flex-wrap:wrap;
        }
        .contador-resultados{
            font-size:14px;
            color:#475569;
            font-weight:600;
            background:#f8fafc;
            border:1px solid #e2e8f0;
            padding:8px 12px;
            border-radius:999px;
        }
        .btn-limpiar{
            border:none;
            background:#0f172a;
            color:#fff;
            padding:10px 14px;
            border-radius:10px;
            font-weight:600;
        }
        .btn-limpiar:hover{
            opacity:.92;
        }
        @media (max-width: 992px){
            .filtros-grid{
                grid-template-columns: 1fr 1fr;
            }
        }
        @media (max-width: 640px){
            .filtros-grid{
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body class="admin-index-page">

<div class="navbar-spacer"></div>

<div class="panel-container">

    <div class="panel-title mb-4">
        <i class="bi bi-clipboard-data"></i> Panel General de Solicitudes
    </div>

    <div class="panel-tools">
        <div class="contador-resultados" id="contadorResultados">
            Mostrando <?= count($solicitudes) ?> solicitudes
        </div>
        <button type="button" class="btn-limpiar" id="btnLimpiarFiltros">
            <i class="bi bi-eraser"></i> Limpiar filtros
        </button>
    </div>

    <!-- =================== FILTROS =================== -->
    <div class="filtros-panel">

        <div class="filtros-grid">
            <div class="filtro-box">
                <label for="searchInput">Buscador general</label>
                <input id="searchInput" type="text" placeholder="Buscar por solicitante, proveedor, categoría, tipo...">
            </div>

            <div class="filtro-box">
                <label for="filtroProyecto">Proyecto / Consorcio</label>
                <select id="filtroProyecto">
                    <option value="todos">Todos</option>
                    <?php foreach ($lista_proyectos as $p): ?>
                        <option value="<?= htmlspecialchars(strtolower($p)) ?>">
                            <?= htmlspecialchars($p) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filtro-box">
                <label for="filtroEstado">Estado</label>
                <select id="filtroEstado">
                    <option value="todos">Todos los estados</option>
                    <?php foreach ($lista_estados as $estado): ?>
                        <option value="<?= htmlspecialchars($estado) ?>">
                            <?= htmlspecialchars(strtoupper(str_replace('_', ' ', $estado))) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filtro-box">
                <label for="filtroFecha">Fecha</label>
                <select id="filtroFecha">
                    <option value="todos">Todas</option>
                    <option value="hoy">Hoy</option>
                    <option value="7dias">Últimos 7 días</option>
                    <option value="30dias">Últimos 30 días</option>
                </select>
            </div>
        </div>

        <div class="tipos-wrap">
            <button class="btn btn-outline-primary btn-sm filtro-btn active" data-filter="todos">Todas</button>
            <button class="btn btn-outline-primary btn-sm filtro-btn" data-filter="Fundocol - Compra Fija">Compras Fijas</button>
            <button class="btn btn-outline-primary btn-sm filtro-btn" data-filter="Consorcio - Compra Fija">Compras Fijas Consorcio</button>
            <button class="btn btn-outline-primary btn-sm filtro-btn" data-filter="Fundocol - Solicitud de Compra">Compras</button>
            <button class="btn btn-outline-primary btn-sm filtro-btn" data-filter="Consorcio - Solicitud de Compra">Compras Consorcio</button>
        </div>

    </div>

    <!-- =================== TABLA =================== -->
    <div class="table-responsive tabla-grande mt-4 admin-table-wrap">
        <table class="table table-striped admin-table" id="tablaSolicitudes">
            <thead>
                <tr>
                    <th>Tipo</th>
                    <th>Proyecto / Consorcio</th>
                    <th>Solicitante</th>
                    <th>Fecha</th>
                    <th>Categoría</th>
                    <th>Proveedor</th>
                    <th>Monto</th>
                    <th>Estado</th>
                    <th>Acción</th>
                </tr>
            </thead>

            <tbody>
                <?php foreach ($solicitudes as $s): ?>
                    <?php
                        $estadoTexto = strtoupper($s['estado']);
                        if ($s['estado'] === 'pago_confirmado' || $s['estado'] === 'aprobado_pagos') {
                            $estadoTexto = "💰 " . $estadoTexto;
                        }

                        $proyectoMostrar = trim($s['proyecto_o_consorcio'] ?? '');
                        if ($proyectoMostrar === '') {
                            $proyectoMostrar = 'Fundocol';
                        }

                        $fechaMostrar = !empty($s['fecha']) ? date('Y-m-d', strtotime($s['fecha'])) : '';
                    ?>
                    <tr
                        data-tipo="<?= htmlspecialchars($s['tipo']) ?>"
                        data-proy="<?= htmlspecialchars(strtolower($proyectoMostrar)) ?>"
                        data-estado="<?= htmlspecialchars(strtolower($s['estado'])) ?>"
                        data-fecha="<?= htmlspecialchars($fechaMostrar) ?>"
                    >
                        <td><?= htmlspecialchars($s['tipo']) ?></td>
                        <td><?= htmlspecialchars($proyectoMostrar) ?></td>
                        <td><?= htmlspecialchars($s['solicitante']) ?></td>
                        <td><?= htmlspecialchars($fechaMostrar) ?></td>
                        <td><?= htmlspecialchars($s['categoria']) ?></td>
                        <td><?= htmlspecialchars($s['proveedor']) ?></td>
                        <td><?= $s['monto'] ? '$' . number_format($s['monto'], 0, ',', '.') : '-' ?></td>
                        <td>
                            <span class="estado <?= htmlspecialchars(strtolower($s['estado'])) ?>">
                                <?= htmlspecialchars($estadoTexto) ?>
                            </span>
                        </td>
                        <td>
                            <?php
                                if ($s['tipo'] === 'Fundocol - Compra Fija') {
                                    $link = "detalle_fija.php?id=" . $s['id'];
                                } elseif ($s['tipo'] === 'Fundocol - Solicitud de Compra') {
                                    $link = "detalle_compra.php?id=" . $s['id'];
                                } elseif ($s['tipo'] === 'Consorcio - Compra Fija') {
                                    $link = "detalle_fija_consorcio.php?id=" . $s['id'];
                                } else {
                                    $link = "detalle_compra_consorcio.php?id=" . $s['id'];
                                }
                            ?>
                            <a href="<?= htmlspecialchars($link) ?>" class="btn-ver">
                                <i class="bi bi-eye-fill"></i> Ver
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

</div>

<script>
const filtroEstado = document.getElementById("filtroEstado");
const filtroProyecto = document.getElementById("filtroProyecto");
const filtroFecha = document.getElementById("filtroFecha");
const searchInput = document.getElementById("searchInput");
const contadorResultados = document.getElementById("contadorResultados");
const btnLimpiarFiltros = document.getElementById("btnLimpiarFiltros");

document.querySelectorAll(".filtro-btn").forEach(btn => {
    btn.addEventListener("click", () => {
        document.querySelectorAll(".filtro-btn").forEach(x => x.classList.remove("active"));
        btn.classList.add("active");
        filtrar();
    });
});

filtroEstado.addEventListener("change", filtrar);
filtroProyecto.addEventListener("change", filtrar);
filtroFecha.addEventListener("change", filtrar);
searchInput.addEventListener("keyup", filtrar);

btnLimpiarFiltros.addEventListener("click", () => {
    document.querySelectorAll(".filtro-btn").forEach(x => x.classList.remove("active"));
    document.querySelector('.filtro-btn[data-filter="todos"]').classList.add("active");

    filtroProyecto.value = "todos";
    filtroEstado.value = "todos";
    filtroFecha.value = "todos";
    searchInput.value = "";

    filtrar();
});

function cumpleFiltroFecha(fechaTexto, filtroFechaValor) {
    if (filtroFechaValor === "todos") return true;
    if (!fechaTexto) return false;

    const fechaFila = new Date(fechaTexto + "T00:00:00");
    const hoy = new Date();
    hoy.setHours(0, 0, 0, 0);

    const diffMs = hoy - fechaFila;
    const diffDias = diffMs / (1000 * 60 * 60 * 24);

    if (filtroFechaValor === "hoy") {
        return diffDias === 0;
    }
    if (filtroFechaValor === "7dias") {
        return diffDias >= 0 && diffDias <= 7;
    }
    if (filtroFechaValor === "30dias") {
        return diffDias >= 0 && diffDias <= 30;
    }

    return true;
}

function filtrar() {
    const tipoFiltro = document.querySelector(".filtro-btn.active").dataset.filter;
    const proyectoFiltro = filtroProyecto.value.toLowerCase();
    const estadoFiltro = filtroEstado.value.toLowerCase();
    const fechaFiltro = filtroFecha.value.toLowerCase();
    const busqueda = searchInput.value.toLowerCase().trim();

    let visibles = 0;

    document.querySelectorAll("#tablaSolicitudes tbody tr").forEach(row => {
        const tipo = row.dataset.tipo;
        const proy = row.dataset.proy;
        const estado = row.dataset.estado;
        const fecha = row.dataset.fecha;
        const texto = row.textContent.toLowerCase();

        const mostrar =
            (tipoFiltro === "todos" || tipoFiltro === tipo) &&
            (proyectoFiltro === "todos" || proyectoFiltro === proy) &&
            (estadoFiltro === "todos" || estadoFiltro === estado) &&
            cumpleFiltroFecha(fecha, fechaFiltro) &&
            (busqueda === "" || texto.includes(busqueda));

        row.style.display = mostrar ? "" : "none";

        if (mostrar) visibles++;
    });

    contadorResultados.textContent = "Mostrando " + visibles + " solicitudes";
}

filtrar();
</script>

</body>
</html>