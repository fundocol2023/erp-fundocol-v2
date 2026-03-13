<?php
include '../../includes/navbar.php';
include_once '../../config/db.php';

/* =========================================================
   1) CONSULTA UNIFICADA DE TODAS LAS COMPRAS
   - Si no hay proyecto/consorcio en Fundocol -> mostrar Fundocol
   ========================================================= */
$sql = "
    SELECT 
        sc.id,
        sc.fecha_creacion,
        sc.necesidad COLLATE utf8mb4_general_ci AS necesidad,
        CASE
            WHEN sc.proyecto_oficina IS NULL OR TRIM(sc.proyecto_oficina) = '' THEN 'Fundocol'
            ELSE sc.proyecto_oficina
        END COLLATE utf8mb4_general_ci AS proyecto_oficina,
        sc.estado COLLATE utf8mb4_general_ci AS estado,
        u.nombre COLLATE utf8mb4_general_ci AS solicitante,
        'fundocol' COLLATE utf8mb4_general_ci AS origen,
        'normal' COLLATE utf8mb4_general_ci AS tipo_compra
    FROM solicitudes_compra sc
    INNER JOIN usuarios u ON sc.solicitante_id = u.id
    WHERE sc.estado IN ('aprobado_pagos', 'pago_confirmado')

    UNION ALL

    SELECT 
        cf.id,
        cf.fecha_solicitud AS fecha_creacion,
        cf.categoria COLLATE utf8mb4_general_ci AS necesidad,
        'Fundocol' COLLATE utf8mb4_general_ci AS proyecto_oficina,
        cf.estado COLLATE utf8mb4_general_ci AS estado,
        u.nombre COLLATE utf8mb4_general_ci AS solicitante,
        'fundocol' COLLATE utf8mb4_general_ci AS origen,
        'fija' COLLATE utf8mb4_general_ci AS tipo_compra
    FROM compras_fijas cf
    INNER JOIN usuarios u ON cf.solicitante_id = u.id
    WHERE cf.estado IN ('aprobado_pagos', 'pago_confirmado')

    UNION ALL

    SELECT 
        scc.id,
        scc.fecha_creacion,
        scc.necesidad COLLATE utf8mb4_general_ci AS necesidad,
        scc.consorcio COLLATE utf8mb4_general_ci AS proyecto_oficina,
        scc.estado COLLATE utf8mb4_general_ci AS estado,
        u.nombre COLLATE utf8mb4_general_ci AS solicitante,
        'consorcio' COLLATE utf8mb4_general_ci AS origen,
        'normal' COLLATE utf8mb4_general_ci AS tipo_compra
    FROM solicitudes_compra_consorcios scc
    INNER JOIN usuarios u ON scc.solicitante_id = u.id
    WHERE scc.estado IN ('aprobado_pagos', 'pago_confirmado')

    UNION ALL

    SELECT
        cfc.id,
        cfc.fecha AS fecha_creacion,
        cfc.categoria COLLATE utf8mb4_general_ci AS necesidad,
        cfc.consorcio COLLATE utf8mb4_general_ci AS proyecto_oficina,
        cfc.estado COLLATE utf8mb4_general_ci AS estado,
        u.nombre COLLATE utf8mb4_general_ci AS solicitante,
        'consorcio' COLLATE utf8mb4_general_ci AS origen,
        'fija' COLLATE utf8mb4_general_ci AS tipo_compra
    FROM compras_fijas_consorcios cfc
    INNER JOIN usuarios u ON cfc.solicitante_id = u.id
    WHERE cfc.estado IN ('aprobado_pagos', 'pago_confirmado')

    ORDER BY fecha_creacion DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute();
$result = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* =========================================================
   2) LISTAS PARA FILTROS
   ========================================================= */
$lista_proyectos = [];
$lista_estados   = [];

foreach ($result as $row) {
    $proyecto = trim($row['proyecto_oficina'] ?? '');
    if ($proyecto === '') {
        $proyecto = 'Fundocol';
    }
    $lista_proyectos[] = $proyecto;

    if (!empty($row['estado'])) {
        $lista_estados[] = strtolower(trim($row['estado']));
    }
}

$lista_proyectos = array_unique($lista_proyectos);
sort($lista_proyectos);

$lista_estados = array_unique($lista_estados);
sort($lista_estados);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="../../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="https://erp-fundocol.fundocol.org/assets/img/Fundocol_favicon.png">
    <title>Historial de Solicitudes (Fundocol + Consorcios)</title>

    <style>
        .historial-shell{
            max-width: 1400px;
            margin: 24px auto;
            padding: 0 18px 40px;
        }
        .historial-card{
            background:#fff;
            border:1px solid #e5e7eb;
            border-radius:18px;
            box-shadow:0 10px 30px rgba(15,23,42,.06);
            overflow:hidden;
        }
        .historial-head{
            padding:22px 24px 14px;
            border-bottom:1px solid #eef2f7;
        }
        .historial-head h2{
            margin:0;
            font-size:1.5rem;
            font-weight:700;
            color:#0f172a;
        }
        .historial-tools{
            display:flex;
            justify-content:space-between;
            align-items:center;
            gap:14px;
            flex-wrap:wrap;
            padding:18px 24px 0;
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
        .btn-limpiar-filtros{
            border:none;
            background:#0f172a;
            color:#fff;
            padding:10px 14px;
            border-radius:10px;
            font-weight:600;
        }
        .btn-limpiar-filtros:hover{
            opacity:.92;
        }
        .filtros-panel{
            padding:18px 24px 18px;
        }
        .filtros-grid{
            display:grid;
            grid-template-columns: 1.4fr 1fr 1fr 1fr;
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
        .tabla-wrap{
            padding:0 24px 24px;
        }
        .tabla-historial{
            width:100%;
            border-collapse:separate;
            border-spacing:0;
            overflow:hidden;
        }
        .tabla-historial thead th{
            background:#f8fafc;
            color:#334155;
            font-size:13px;
            font-weight:700;
            padding:14px 12px;
            border-bottom:1px solid #e5e7eb;
            white-space:nowrap;
        }
        .tabla-historial tbody td{
            padding:14px 12px;
            border-bottom:1px solid #eef2f7;
            vertical-align:middle;
            font-size:14px;
            color:#1f2937;
        }
        .badge-origen{
            display:inline-block;
            padding:6px 10px;
            border-radius:999px;
            font-size:12px;
            font-weight:700;
        }
        .badge-origen-fundocol{
            background:#e0f2fe;
            color:#0369a1;
        }
        .badge-origen-consorcio{
            background:#ede9fe;
            color:#6d28d9;
        }
        .badge-tipo{
            display:inline-block;
            padding:6px 10px;
            border-radius:999px;
            font-size:12px;
            font-weight:700;
        }
        .badge-tipo-fija{
            background:#dcfce7;
            color:#166534;
        }
        .badge-tipo-normal{
            background:#fef3c7;
            color:#92400e;
        }
        .btn-linea-tiempo{
            display:inline-block;
            padding:9px 14px;
            border-radius:10px;
            text-decoration:none;
            font-weight:700;
            font-size:13px;
            background:#0f4a83;
            color:#fff;
        }
        .btn-linea-tiempo:hover{
            color:#fff;
            opacity:.92;
        }
        @media (max-width: 1100px){
            .filtros-grid{
                grid-template-columns: 1fr 1fr;
            }
        }
        @media (max-width: 640px){
            .filtros-grid{
                grid-template-columns: 1fr;
            }
            .historial-tools{
                align-items:stretch;
            }
        }
    </style>
</head>
<body class="direccion-historial-page">
<div class="navbar-spacer"></div>

<div class="historial-shell">
    <div class="historial-card">

        <div class="historial-head">
            <h2>Historial de Solicitudes (Fundocol + Consorcios)</h2>
        </div>

        <div class="historial-tools">
            <div class="contador-resultados" id="contadorResultados">
                Mostrando <?= count($result) ?> solicitudes
            </div>

            <button type="button" class="btn-limpiar-filtros" id="btnLimpiarFiltros">
                <i class="bi bi-eraser"></i> Limpiar filtros
            </button>
        </div>

        <div class="filtros-panel">
            <div class="filtros-grid">
                <div class="filtro-box">
                    <label for="searchInput">Buscador general</label>
                    <input type="text" id="searchInput" placeholder="Buscar por ID, necesidad, solicitante, proyecto, estado...">
                </div>

                <div class="filtro-box">
                    <label for="filtroOrigen">Origen</label>
                    <select id="filtroOrigen">
                        <option value="todos">Todos</option>
                        <option value="fundocol">Fundocol</option>
                        <option value="consorcio">Consorcio</option>
                    </select>
                </div>

                <div class="filtro-box">
                    <label for="filtroTipo">Tipo</label>
                    <select id="filtroTipo">
                        <option value="todos">Todos</option>
                        <option value="normal">Normal</option>
                        <option value="fija">Fija</option>
                    </select>
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
                        <option value="todos">Todos</option>
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
        </div>

        <div class="tabla-wrap">
            <div class="table-responsive">
                <table class="tabla-historial" id="tablaHistorial">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Fecha</th>
                            <th>Origen</th>
                            <th>Tipo</th>
                            <th>Proyecto / Consorcio</th>
                            <th>Necesidad</th>
                            <th>Solicitante</th>
                            <th>Estado</th>
                            <th class="col-linea-tiempo">Línea de Tiempo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($result as $row): 
                            $origen = strtolower($row['origen']);
                            $badgeOrigenClass = $origen === 'fundocol' ? 'badge-origen-fundocol' : 'badge-origen-consorcio';

                            $tipo = strtolower($row['tipo_compra']);
                            $badgeTipoClass = $tipo === 'fija' ? 'badge-tipo-fija' : 'badge-tipo-normal';

                            $proyectoMostrar = trim($row['proyecto_oficina'] ?? '');
                            if ($proyectoMostrar === '') {
                                $proyectoMostrar = 'Fundocol';
                            }

                            $fechaMostrar = !empty($row['fecha_creacion']) ? date('Y-m-d H:i', strtotime($row['fecha_creacion'])) : '';
                            $fechaFiltro = !empty($row['fecha_creacion']) ? date('Y-m-d', strtotime($row['fecha_creacion'])) : '';
                        ?>
                        <tr
                            data-origen="<?= htmlspecialchars($origen) ?>"
                            data-tipo="<?= htmlspecialchars($tipo) ?>"
                            data-proyecto="<?= htmlspecialchars(strtolower($proyectoMostrar)) ?>"
                            data-estado="<?= htmlspecialchars(strtolower($row['estado'])) ?>"
                            data-fecha="<?= htmlspecialchars($fechaFiltro) ?>"
                        >
                            <td><?= intval($row['id']) ?></td>
                            <td><?= htmlspecialchars($fechaMostrar) ?></td>
                            <td>
                                <span class="badge-origen <?= $badgeOrigenClass ?>">
                                    <?= strtoupper(htmlspecialchars($row['origen'])) ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge-tipo <?= $badgeTipoClass ?>">
                                    <?= strtoupper(htmlspecialchars($row['tipo_compra'])) ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($proyectoMostrar) ?></td>
                            <td><?= htmlspecialchars($row['necesidad']) ?></td>
                            <td><?= htmlspecialchars($row['solicitante']) ?></td>
                            <td>
                                <span class="estado <?= htmlspecialchars($row['estado']) ?>">
                                    <?= ucwords(str_replace('_',' ', htmlspecialchars($row['estado']))) ?>
                                </span>
                            </td>
                            <td>
                                <a class="btn-linea-tiempo"
                                   href="modulo_linea_tiempo.php?id=<?= intval($row['id']) ?>&origen=<?= urlencode($row['origen']) ?>&tipo=<?= urlencode($row['tipo_compra']) ?>">
                                    Ver línea de tiempo
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>

                        <?php if (empty($result)): ?>
                        <tr>
                            <td colspan="9" style="text-align:center;color:#64748b;padding:24px;">
                                No hay solicitudes finalizadas para mostrar.
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<script>
const filtroOrigen = document.getElementById("filtroOrigen");
const filtroTipo = document.getElementById("filtroTipo");
const filtroProyecto = document.getElementById("filtroProyecto");
const filtroEstado = document.getElementById("filtroEstado");
const filtroFecha = document.getElementById("filtroFecha");
const searchInput = document.getElementById("searchInput");
const contadorResultados = document.getElementById("contadorResultados");
const btnLimpiarFiltros = document.getElementById("btnLimpiarFiltros");

filtroOrigen.addEventListener("change", filtrar);
filtroTipo.addEventListener("change", filtrar);
filtroProyecto.addEventListener("change", filtrar);
filtroEstado.addEventListener("change", filtrar);
filtroFecha.addEventListener("change", filtrar);
searchInput.addEventListener("keyup", filtrar);

btnLimpiarFiltros.addEventListener("click", () => {
    filtroOrigen.value = "todos";
    filtroTipo.value = "todos";
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
    const origen = filtroOrigen.value.toLowerCase();
    const tipo = filtroTipo.value.toLowerCase();
    const proyecto = filtroProyecto.value.toLowerCase();
    const estado = filtroEstado.value.toLowerCase();
    const fecha = filtroFecha.value.toLowerCase();
    const busqueda = searchInput.value.toLowerCase().trim();

    let visibles = 0;

    document.querySelectorAll("#tablaHistorial tbody tr").forEach(row => {
        const rowOrigen = row.dataset.origen || "";
        const rowTipo = row.dataset.tipo || "";
        const rowProyecto = row.dataset.proyecto || "";
        const rowEstado = row.dataset.estado || "";
        const rowFecha = row.dataset.fecha || "";
        const texto = row.textContent.toLowerCase();

        const mostrar =
            (origen === "todos" || origen === rowOrigen) &&
            (tipo === "todos" || tipo === rowTipo) &&
            (proyecto === "todos" || proyecto === rowProyecto) &&
            (estado === "todos" || estado === rowEstado) &&
            cumpleFiltroFecha(rowFecha, fecha) &&
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
