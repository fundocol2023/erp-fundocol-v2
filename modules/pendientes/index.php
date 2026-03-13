<?php
session_start();
require_once '../../config/db.php';

if (!isset($_SESSION['usuario_id'])) { 
    header('Location: ../../login.php'); 
    exit(); 
}

$rol = $_SESSION['usuario_rol'];
$usuario_id = $_SESSION['usuario_id'];

// ✅ CAMBIO: consorcio especial y rol especial
$CONSORCIO_ESPECIAL = "Consorcio Aburra 2025";
$ROL_ING_CIVIL = 13;

// Tab por defecto dinámico según rol
if(isset($_GET['tab'])){
    $tab = $_GET['tab']; 
} else {
    // ✅ CAMBIO: incluir rol 11 como aprobador
    if(in_array($rol, [3,4,5,6,8,13])){
        $tab = 'aprobaciones'; // Dirección · Presupuesto · Contabilidades · Pagos · Ing civil
    } else {
        $tab = 'solicitudes'; // Otros roles
    }
}

// -------------------------------------------------
// Definición de tabs (ya unificados)
// -------------------------------------------------
$tabs = [
    [
        'id'    => 'aprobaciones',
        'label' => 'Pendientes de Compras',
        // ✅ CAMBIO: incluir rol 11
        'show'  => in_array($rol, [3, 4, 5, 6, 8, 13])
    ],
    [
        'id'    => 'comprasfijas',
        'label' => 'Pendientes de Compras Fijas',
        // ✅ CAMBIO: incluir rol 11
        'show'  => in_array($rol, [3, 4, 5, 6, 8, 13])
    ],
    [
        'id'    => 'solicitudes',
        'label' => 'Mis Solicitudes de Compra',
        'show'  => ($rol != 3)
    ],
    [
        'id'    => 'vehiculos',
        'label' => 'Préstamo de Vehículos',
        'show'  => in_array($rol, [4])
    ],
    [
        'id'    => 'documentos_firma',
        'label' => 'Firma Documentos',
        'show'  => in_array($rol, [3])
    ],
    [
        'id'    => 'admin',
        'label' => 'Panel Administrador',
        'show'  => ($rol == 1)
    ],
];

// -------------------------------------------------
// 1. Pendientes de Compras (UNIFICADO Fundocol + Consorcios)
// -------------------------------------------------
$aprobaciones = [];
$titulo_aprobaciones = "";
$texto_btn = "";
$etapa = "";

// ✅ CAMBIO: permitir rol 13 entrar al tab aprobaciones
if ($tab === 'aprobaciones' && in_array($rol, [3, 4, 5, 6, 8, 13])) {

    // Dirección → Cotizaciones Fundocol + Consorcios
    if ($rol == 3) {
        $titulo_aprobaciones = "Pendientes de Compras (Dirección - Cotizaciones Fundocol y Consorcios)";
        $texto_btn = "Ver / Aprobar";
        $etapa = "Cotización";

        // Fundocol
        $sql_f = "SELECT sc.id, sc.fecha_creacion, sc.proyecto_oficina, sc.necesidad,
                         u.nombre AS solicitante,
                         'Fundocol' AS origen,
                         '../compras/aprobacion_cotizacion.php?id=' AS base_link
                  FROM solicitudes_cotizacion sc
                  JOIN usuarios u ON sc.solicitante_id = u.id
                  WHERE sc.estado = 'cotizacion'
                  ORDER BY sc.fecha_creacion DESC";
        $rows_f = $pdo->query($sql_f)->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows_f as $row) {
            $aprobaciones[] = [
                'id'               => $row['id'],
                'fecha'            => date('Y-m-d H:i', strtotime($row['fecha_creacion'])),
                'solicitante'      => $row['solicitante'],
                'proyecto_oficina' => $row['proyecto_oficina'],
                'necesidad'        => $row['necesidad'],
                'origen'           => 'Fundocol',
                'etapa'            => $etapa,
                'link'             => $row['base_link'] . $row['id'],
            ];
        }

        // Consorcios
        $sql_c = "SELECT sc.id, sc.fecha_creacion, sc.consorcio, sc.necesidad,
                         u.nombre AS solicitante,
                         'Consorcio' AS origen,
                         '../consorcios/aprobacion_cotizacion.php?id=' AS base_link
                  FROM solicitudes_cotizacion_consorcios sc
                  JOIN usuarios u ON sc.solicitante_id = u.id
                  WHERE sc.estado = 'cotizacion'
                  ORDER BY sc.fecha_creacion DESC";
        $rows_c = $pdo->query($sql_c)->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows_c as $row) {
            $aprobaciones[] = [
                'id'               => $row['id'],
                'fecha'            => date('Y-m-d H:i', strtotime($row['fecha_creacion'])),
                'solicitante'      => $row['solicitante'],
                'proyecto_oficina' => $row['consorcio'],
                'necesidad'        => $row['necesidad'],
                'origen'           => 'Consorcio',
                'etapa'            => $etapa,
                'link'             => $row['base_link'] . $row['id'],
            ];
        }
    }

    // Presupuesto → Solicitudes Compra Fundocol + Consorcios
    if ($rol == 4) {
        $titulo_aprobaciones = "Pendientes de Compras (Presupuesto - Solicitudes)";
        $texto_btn = "Ver / Aprobar";
        $etapa = "Presupuesto";

        // Fundocol
        $sql_f = "SELECT sp.id, sp.fecha_creacion, sp.proyecto_oficina, sc.necesidad,
                         u.nombre AS solicitante,
                         'Fundocol' AS origen,
                         '../compras/ver_solicitud_compra.php?id=' AS base_link
                  FROM solicitudes_compra sp
                  JOIN solicitudes_cotizacion sc ON sp.solicitud_cotizacion_id = sc.id
                  JOIN usuarios u ON sc.solicitante_id = u.id
                  WHERE sp.estado = 'pendiente'
                  ORDER BY sp.fecha DESC";
        $rows_f = $pdo->query($sql_f)->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows_f as $row) {
            $aprobaciones[] = [
                'id'               => $row['id'],
                'fecha'            => date('Y-m-d H:i', strtotime($row['fecha_creacion'])),
                'solicitante'      => $row['solicitante'],
                'proyecto_oficina' => $row['proyecto_oficina'],
                'necesidad'        => $row['necesidad'],
                'origen'           => 'Fundocol',
                'etapa'            => $etapa,
                'link'             => $row['base_link'] . $row['id']
            ];
        }

        // Consorcios
        $sql_c = "SELECT sp.id, sp.fecha_creacion, sp.consorcio, sp.necesidad,
                         u.nombre AS solicitante,
                         'Consorcio' AS origen,
                         '../consorcios/ver_solicitud_compra.php?id=' AS base_link
                  FROM solicitudes_compra_consorcios sp
                  JOIN usuarios u ON sp.solicitante_id = u.id
                  WHERE sp.estado = 'pendiente'
                  ORDER BY sp.fecha DESC";
        $rows_c = $pdo->query($sql_c)->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows_c as $row) {
            $aprobaciones[] = [
                'id'               => $row['id'],
                'fecha'            => date('Y-m-d H:i', strtotime($row['fecha_creacion'])),
                'solicitante'      => $row['solicitante'],
                'proyecto_oficina' => $row['consorcio'],
                'necesidad'        => $row['necesidad'],
                'origen'           => 'Consorcio',
                'etapa'            => $etapa,
                'link'             => $row['base_link'] . $row['id']
            ];
        }
    }

    // ✅ NUEVO: Rol 13 aprueba como Presupuesto SOLO solicitudes de compra de Consorcio Aburra 2025
    if ($rol == 13) {
        $titulo_aprobaciones = "Pendientes de Compras (Presupuesto - Consorcio Aburra 2025)";
        $texto_btn = "Ver / Aprobar";
        $etapa = "Presupuesto";

        $sql_c13 = "SELECT sp.id, sp.fecha_creacion, sp.consorcio, sp.necesidad,
                           u.nombre AS solicitante,
                           'Consorcio' AS origen,
                           '../consorcios/ver_solicitud_compra.php?id=' AS base_link
                    FROM solicitudes_compra_consorcios sp
                    JOIN usuarios u ON sp.solicitante_id = u.id
                    WHERE sp.estado = 'pendiente'
                      AND sp.consorcio = 'Consorcio Aburra 2025'
                    ORDER BY sp.fecha DESC";
        $rows_c13 = $pdo->query($sql_c13)->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows_c13 as $row) {
            $aprobaciones[] = [
                'id'               => $row['id'],
                'fecha'            => date('Y-m-d H:i', strtotime($row['fecha_creacion'])),
                'solicitante'      => $row['solicitante'],
                'proyecto_oficina' => $row['consorcio'],
                'necesidad'        => $row['necesidad'],
                'origen'           => 'Consorcio',
                'etapa'            => $etapa,
                'link'             => $row['base_link'] . $row['id']
            ];
        }
    }

    // ... aquí dejas tu código de rol 5, 8 y 6 igual ...

    // Ordenar por fecha DESC
    if (!empty($aprobaciones)) {
        usort($aprobaciones, function($a, $b) {
            return strcmp($b['fecha'], $a['fecha']);
        });
    }
}

// -------------------------------------------------
// 2. MIS SOLICITUDES
// -------------------------------------------------
$mis_solicitudes = [];

if ($tab === 'solicitudes') {
    $sql_solicitudes = "SELECT sc.id, sc.fecha, sc.proyecto_oficina, sc.necesidad, sc.estado, c.proveedor
                        FROM solicitudes_compra sc
                        JOIN solicitudes_cotizacion_cotizaciones c ON sc.cotizacion_aprobada_id = c.id
                        WHERE sc.solicitante_id = ?
                        ORDER BY sc.fecha DESC";

    $stmt = $pdo->prepare($sql_solicitudes);
    $stmt->execute([$usuario_id]);
    $mis_solicitudes = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// -------------------------------------------------
// 3. COMPRAS FIJAS
// -------------------------------------------------
$compras_fijas = [];
$titulo_estado_fijas = "";

// ✅ CAMBIO: incluir rol 13
if ($tab === 'comprasfijas' && in_array($rol, [3, 4, 5, 6, 8, 13])) {

    $estado_filtrar = "";

    // ✅ CAMBIO: rol 13 también aprueba en etapa "pendiente_presupuesto" (pero SOLO Aburra)
    if ($rol == 4) {
        $estado_filtrar   = 'pendiente_presupuesto';
        $titulo_estado_fijas = 'Pendiente aprobación Presupuesto';

    } elseif ($rol == 13) {
        $estado_filtrar   = 'pendiente_presupuesto';
        $titulo_estado_fijas = 'Pendiente aprobación Ing. Civil (Aburrá)';

    } elseif ($rol == 3) {
        $estado_filtrar   = 'aprobado_presupuesto';
        $titulo_estado_fijas = 'Pendiente aprobación Dirección';

    } elseif (in_array($rol, [5, 8])) {
        $estado_filtrar   = 'aprobado_direccion';
        $titulo_estado_fijas = 'Pendiente aprobación Contabilidad';

    } elseif ($rol == 6) {
        $estado_filtrar   = 'aprobado_contabilidad';
        $titulo_estado_fijas = 'Pendiente aprobación Pagos';
    }

    if ($estado_filtrar !== "") {

        // ---------------------------------------------------------
        // FUNDOCOL (solo roles normales, NO rol 13)
        // ---------------------------------------------------------
        if (in_array($rol, [3, 4, 5, 6])) {

            if ($rol == 6) {
                $sql_cf = "SELECT cf.*, u.nombre AS solicitante,
                                  'Fundocol' AS origen
                           FROM compras_fijas cf
                           INNER JOIN usuarios u ON cf.solicitante_id = u.id
                           WHERE cf.estado = ?
                           ORDER BY cf.fecha_aprobacion_contabilidad DESC";
            } else {
                $sql_cf = "SELECT cf.*, u.nombre AS solicitante,
                                  'Fundocol' AS origen
                           FROM compras_fijas cf
                           INNER JOIN usuarios u ON cf.solicitante_id = u.id
                           WHERE cf.estado = ?
                           ORDER BY cf.fecha_solicitud DESC";
            }

            $stmt_cf = $pdo->prepare($sql_cf);
            $stmt_cf->execute([$estado_filtrar]);
            $rows_cf = $stmt_cf->fetchAll(PDO::FETCH_ASSOC);

            $link_base_fundocol = '';

            if ($rol == 4) {
                $link_base_fundocol = "../compras/fijas/aprobar_fija_presupuesto.php?id=";
            } elseif ($rol == 3) {
                $link_base_fundocol = "../compras/fijas/aprobar_fija_direccion.php?id=";
            } elseif ($rol == 5) {
                $link_base_fundocol = "../compras/fijas/aprobar_fija_contabilidad.php?id=";
            } elseif ($rol == 6) {
                $link_base_fundocol = "../compras/fijas/aprobar_fija_pagos.php?id=";
            }

            foreach ($rows_cf as $cf) {

                $fecha_direccion = null;
                $fecha_contabilidad = null;

                // ✅ FIX: en compras_fijas (Fundocol) la columna es fecha_aprobacion_direccion
                if (in_array($rol, [5, 8])) {
                    $fecha_direccion = $cf['fecha_aprobacion_direccion'] ?? null;
                }

                if ($rol == 6) {
                    $fecha_contabilidad = $cf['fecha_aprobacion_contabilidad'] ?? null;
                }

                $compras_fijas[] = [
                    'id'          => $cf['id'],
                    'fecha'       => $cf['fecha_solicitud'],
                    'categoria'   => $cf['categoria'],
                    'proveedor'   => $cf['proveedor'],
                    'monto'       => $cf['monto'],
                    'solicitante' => $cf['solicitante'],
                    'origen'      => 'Fundocol',
                    'fecha_aprob_direccion'     => $fecha_direccion,
                    'fecha_aprob_contabilidad'  => $fecha_contabilidad,
                    'link'        => $link_base_fundocol . $cf['id'],
                ];
            }
        }

        // ---------------------------------------------------------
        // CONSORCIOS
        // ---------------------------------------------------------
        // ✅ CAMBIO: incluir rol 11 aquí
        if (in_array($rol, [3, 4, 6, 8, 13])) {

            // ✅ CAMBIO: filtros por rol:
            // - rol 4: excluir Aburra 2025
            // - rol 13: solo Aburra 2025
            $filtroConsorcioSQL = "";
            $params = [$estado_filtrar];

            if ($rol == 4) {
                $filtroConsorcioSQL = " AND cfc.consorcio <> ? ";
                $params[] = $CONSORCIO_ESPECIAL;
            } elseif ($rol == 13) {
                $filtroConsorcioSQL = " AND cfc.consorcio = ? ";
                $params[] = $CONSORCIO_ESPECIAL;
            }

            if ($rol == 6) {
                $sql_cfc = "SELECT cfc.*, u.nombre AS solicitante,
                                   'Consorcio' AS origen
                            FROM compras_fijas_consorcios cfc
                            INNER JOIN usuarios u ON cfc.solicitante_id = u.id
                            WHERE cfc.estado = ?
                            $filtroConsorcioSQL
                            ORDER BY cfc.fecha_aprob_contabilidad DESC";
            } else {
                $sql_cfc = "SELECT cfc.*, u.nombre AS solicitante,
                                   'Consorcio' AS origen
                            FROM compras_fijas_consorcios cfc
                            INNER JOIN usuarios u ON cfc.solicitante_id = u.id
                            WHERE cfc.estado = ?
                            $filtroConsorcioSQL
                            ORDER BY cfc.fecha DESC";
            }

            $stmt_cfc = $pdo->prepare($sql_cfc);
            $stmt_cfc->execute($params);
            $rows_cfc = $stmt_cfc->fetchAll(PDO::FETCH_ASSOC);

            $link_base_cons = '';

            // ✅ CAMBIO: rol 11 usa la misma pantalla de aprobación que presupuesto en consorcios
            if ($rol == 4 || $rol == 13) {
                $link_base_cons = "../consorcios/fijas/aprobar_fija_presupuesto.php?id=";
            } elseif ($rol == 3) {
                $link_base_cons = "../consorcios/fijas/aprobar_fija_direccion.php?id=";
            } elseif ($rol == 8) {
                $link_base_cons = "../consorcios/fijas/aprobar_fija_contabilidad.php?id=";
            } elseif ($rol == 6) {
                $link_base_cons = "../consorcios/fijas/aprobar_fija_pagos.php?id=";
            }

            foreach ($rows_cfc as $cf) {

                $fecha_direccion = null;
                $fecha_contabilidad = null;

                // ✅ OK: en compras_fijas_consorcios la columna es fecha_aprob_direccion
                if (in_array($rol, [5, 8])) {
                    $fecha_direccion = $cf['fecha_aprob_direccion'] ?? null;
                }

                if ($rol == 6) {
                    $fecha_contabilidad = $cf['fecha_aprob_contabilidad'] ?? null;
                }

                $compras_fijas[] = [
                    'id'          => $cf['id'],
                    'fecha'       => $cf['fecha'],
                    'categoria'   => $cf['categoria'],
                    'proveedor'   => $cf['proveedor'],
                    'monto'       => $cf['monto'],
                    'solicitante' => $cf['solicitante'],
                    'origen'      => 'Consorcio',
                    'fecha_aprob_direccion'     => $fecha_direccion,
                    'fecha_aprob_contabilidad'  => $fecha_contabilidad,
                    'link'        => $link_base_cons . $cf['id'],
                ];
            }
        }

        // ---------------------------------------------------------
        // ORDENAR SEGÚN ROL
        // ---------------------------------------------------------
        if (!empty($compras_fijas)) {

            if ($rol == 6) {
                usort($compras_fijas, function($a, $b) {
                    return strcmp(
                        $b['fecha_aprob_contabilidad'] ?? '0000-00-00 00:00:00',
                        $a['fecha_aprob_contabilidad'] ?? '0000-00-00 00:00:00'
                    );
                });

            } else {
                usort($compras_fijas, function($a, $b) {
                    return strcmp($b['fecha'], $a['fecha']);
                });
            }
        }
    }
}
// -------------------------------------------------
// 4. PRESTAMO DE VEHICULOS
// -------------------------------------------------
$solicitudes_vehiculos = [];
$link_aprobar_vehiculo = "";

if ($tab === 'vehiculos' && in_array($rol, [4])) {

    $sql = "SELECT vs.id, vs.fecha_solicitud, vs.fecha_inicio, vs.fecha_fin, vs.motivo, vs.estado,
                   v.nombre AS nombre_vehiculo,
                   u.nombre AS nombre_solicitante
            FROM vehiculos_solicitudes vs
            INNER JOIN vehiculos v ON vs.vehiculo_id = v.id
            INNER JOIN usuarios u ON vs.solicitante_id = u.id
            WHERE vs.estado = 'pendiente'
            ORDER BY vs.fecha_solicitud DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $solicitudes_vehiculos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $link_aprobar_vehiculo = "../Solicitudes/vehiculo/aprobar_solicitud.php?id=";
}

// -------------------------------------------------
// 5. FIRMA DE DOCUMENTOS
// -------------------------------------------------
$documentos_firma = [];
$link_firmar_documento = "../Solicitudes/firma_documentos/firmar_documento.php?id=";

if ($tab === 'documentos_firma' && in_array($rol, [3])) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS documentos_firma (
        id INT AUTO_INCREMENT PRIMARY KEY,
        solicitante_id INT NOT NULL,
        nombre_documento VARCHAR(255) NOT NULL,
        razon TEXT NOT NULL,
        archivo_original VARCHAR(255) NOT NULL,
        archivo_firmado VARCHAR(255) NULL,
        estado ENUM('pendiente','firmado') NOT NULL DEFAULT 'pendiente',
        firmado_por_id INT NULL,
        firma_tipo ENUM('subida','manual') NULL,
        firma_archivo VARCHAR(255) NULL,
        firma_page INT NULL,
        firma_x FLOAT NULL,
        firma_y FLOAT NULL,
        firma_w FLOAT NULL,
        firma_h FLOAT NULL,
        ip_firma VARCHAR(64) NULL,
        user_agent VARCHAR(255) NULL,
        fecha_solicitud TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        fecha_firma DATETIME NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $sql = "SELECT d.id, d.nombre_documento, d.razon, d.fecha_solicitud, u.nombre AS solicitante
            FROM documentos_firma d
            INNER JOIN usuarios u ON d.solicitante_id = u.id
            WHERE d.estado = 'pendiente'
            ORDER BY d.fecha_solicitud DESC";
    $stmt = $pdo->query($sql);
    $documentos_firma = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// -------------------------------------------------
// 6. ADMIN
// -------------------------------------------------
$admin_aprobaciones = [];
$titulo_admin_aprobaciones = "";
$enlace_admin = "";
$texto_btn_admin = "";

if ($tab === 'admin' && $rol == 1) {

    $titulo_admin_aprobaciones = "Solicitudes de Cotización Consorcios (Vista Dirección)";
    $texto_btn_admin = "Ver / Aprobar";
    $enlace_admin = "../consorcios/aprobacion_cotizacion.php?id=";

    $sql = "SELECT sc.id, sc.consorcio, sc.necesidad, sc.fecha, u.nombre AS solicitante
            FROM solicitudes_cotizacion_consorcios sc
            JOIN usuarios u ON sc.solicitante_id = u.id
            WHERE sc.estado = 'cotizacion'
            ORDER BY sc.fecha_creacion DESC";

    try {
        $stmt = $pdo->query($sql);
        $admin_aprobaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {

        echo '<div style="background:#fee2e2;color:#b91c1c;padding:10px;border-radius:8px;">
                <b>Error en consulta de Admin:</b><br>' . 
                htmlspecialchars($e->getMessage()) . '
              </div>';
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Pendientes | ERP Fundocol</title>
    <link rel="stylesheet" href="../../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="icon" type="image/x-icon" href="https://erp-fundocol.fundocol.org/assets/img/Fundocol_favicon.png">
    <!-- estilos movidos a assets/css/style.css -->
</head>

<body class="pendientes-index-page">
<?php include '../../includes/navbar.php'; ?>
<div class="container-fluid">
    <div class="erp-card">

        <!-- Menú de Tabs -->
        <div class="pendientes-tabs-wrap">
        <div class="pendientes-tabs-menu">
            <?php foreach ($tabs as $t): ?>
                <?php if ($t['show']): ?>
                    <a href="?tab=<?= $t['id'] ?>" class="pendientes-tab <?= $tab === $t['id'] ? 'active' : '' ?>">
                        <?= htmlspecialchars($t['label']) ?>
                    </a>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
        </div>

        <!-- TAB: Pendientes de Compras (UNIFICADO) -->
        <?php if ($tab === 'aprobaciones' && in_array($rol, [3,4,5,6,8,13])): ?>
            <h4 class="pendientes-section-title">
                <i class="bi bi-cart-check"></i> <?= htmlspecialchars($titulo_aprobaciones) ?>
            </h4>

            <?php if (empty($aprobaciones)): ?>
                <div class="empty-msg">No tienes pendientes de compras para tu rol.</div>
            <?php else: ?>

                <div class="filtro-origen-container">
                    <label for="filtro-origen-compras" class="form-label mb-0" style="font-weight:600;color:#174a7c;">
                        Origen:
                    </label>
                    <select id="filtro-origen-compras" class="form-select form-select-sm" style="max-width:230px;">
                        <option value="todos">Todas (Fundocol y Consorcios)</option>
                        <option value="fundocol">Solo Fundocol</option>
                        <option value="consorcio">Solo Consorcios</option>
                    </select>
                </div>

                <div class="table-responsive">
                    <table class="erp-table" id="tabla-compras-pendientes">
                        <thead>
                        <tr>
                            <th>ID</th>
                            <th style="width:120px">Fecha</th>
                            <th>Origen</th>
                            <th>Solicitante</th>
                            <th>Proyecto / Consorcio</th>
                            <th>Necesidad</th>

                            <?php if ($rol == 5 || $rol == 8): ?>
                                <th>Fecha Aprob. Presupuesto</th>
                            <?php endif; ?>
                            <?php if ($rol == 6): ?>
                                <th>Fecha Aprob. Contabilidad</th>
                            <?php endif; ?>

                            <th>Etapa</th>
                            <th>Acción</th>
                        </tr>
                        </thead>

                        <tbody>
                        <?php foreach ($aprobaciones as $a): 
                            $origenLower = strtolower($a['origen']);
                            $badgeClass = $origenLower === 'fundocol' ? 'badge-origen-fundocol' : 'badge-origen-consorcio';
                        ?>
                            <tr data-origen="<?= $origenLower ?>">
                                <td><?= $a['id'] ?></td>
                                <td><?= htmlspecialchars($a['fecha']) ?></td>

                                <td>
                                    <span class="badge badge-origen <?= $badgeClass ?>">
                                        <?= htmlspecialchars($a['origen']) ?>
                                    </span>
                                </td>

                                <td><?= htmlspecialchars($a['solicitante'] ?? '') ?></td>
                                <td><?= htmlspecialchars($a['proyecto_oficina'] ?? '') ?></td>
                                <td><?= htmlspecialchars($a['necesidad']) ?></td>

                                <?php if ($rol == 5 || $rol == 8): ?>
                                    <td>
                                        <?= $a['fecha_aprob_presupuesto'] 
                                            ? htmlspecialchars(date('Y-m-d H:i', strtotime($a['fecha_aprob_presupuesto'])))
                                            : '-' ?>
                                    </td>
                                <?php endif; ?>

                                <?php if ($rol == 6): ?>
                                    <td>
                                        <?= !empty($a['fecha_aprob_contabilidad'])
                                        ? htmlspecialchars(date('Y-m-d H:i', strtotime($a['fecha_aprob_contabilidad'])))
                                        : '-'?>
                                    </td>
                                <?php endif; ?>

                                <td><?= htmlspecialchars($a['etapa']) ?></td>

                                <td>
                                    <a href="<?= htmlspecialchars($a['link']) ?>" class="btn btn-erp">
                                        <i class="bi bi-search"></i> <?= htmlspecialchars($texto_btn) ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>

                    </table>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <!-- TAB: Mis solicitudes -->
        <?php if ($tab === 'solicitudes'): ?>
            <h4 class="pendientes-section-title">
                <i class="bi bi-cart-check"></i> Tus Solicitudes de Compra
            </h4>

            <?php if (empty($mis_solicitudes)): ?>
                <div class="empty-msg">No has realizado solicitudes de compra.</div>
            <?php else: ?>

                <div class="table-responsive">
                    <table class="erp-table">
                        <thead>
                        <tr>
                            <th>ID</th>
                            <th>Fecha</th>
                            <th>Proyecto/Oficina</th>
                            <th>Necesidad</th>
                            <th>Proveedor</th>
                            <th>Estado</th>
                            <th>Acción</th>
                        </tr>
                        </thead>

                        <tbody>
                        <?php foreach ($mis_solicitudes as $s): ?>
                            <tr>
                                <td><?= $s['id'] ?></td>
                                <td><?= htmlspecialchars(date('Y-m-d', strtotime($s['fecha']))) ?></td>
                                <td><?= htmlspecialchars($s['proyecto_oficina']) ?></td>
                                <td><?= htmlspecialchars($s['necesidad']) ?></td>
                                <td><?= htmlspecialchars($s['proveedor']) ?></td>
                                <td><?= strtoupper($s['estado']) ?></td>

                                <td>
                                    <?php if ($s['estado'] === 'pago_confirmado'): ?>
                                        <a href="../compras/ver_solicitud_subir_factura.php?id=<?= $s['id'] ?>" class="btn btn-erp btn-sm">
                                            <i class="bi bi-upload"></i> Subir factura proveedor
                                        </a>
                                    <?php else: ?>
                                        <span style="color:#888;font-size:1.1em;">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>

                    </table>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <!-- TAB Fijas -->
        <?php if ($tab === 'comprasfijas' && in_array($rol, [3,4,5,6,8,13])): ?>
            <h4 class="pendientes-section-title">
                <i class="bi bi-archive"></i> <?= htmlspecialchars($titulo_estado_fijas ?: 'Pendientes de Compras Fijas') ?>
            </h4>

            <?php if (empty($compras_fijas)): ?>
                <div class="empty-msg">No tienes compras fijas pendientes por aprobar.</div>
            <?php else: ?>

                <div class="filtro-origen-container">
                    <label for="filtro-origen-fijas" class="form-label mb-0" style="font-weight:600;color:#174a7c;">
                        Origen:
                    </label>
                    <select id="filtro-origen-fijas" class="form-select form-select-sm" style="max-width:230px;">
                        <option value="todos">Todas (Fundocol y Consorcios)</option>
                        <option value="fundocol">Solo Fundocol</option>
                        <option value="consorcio">Solo Consorcios</option>
                    </select>
                </div>

                <div class="table-responsive">
                    <table class="erp-table" id="tabla-compras-fijas">
                        <thead>
                        <tr>
                            <th>ID</th>
                            <th>Fecha</th>
                            <th>Origen</th>
                            <th>Categoría</th>
                            <th>Proveedor</th>
                            <th>Monto</th>
                            <th>Solicitante</th>

                            <?php if ($rol == 5 || $rol == 8): ?>
                                <th>Aprobado Dirección</th>
                            <?php endif; ?>

                            <?php if ($rol == 6): ?>
                                <th>Aprobado Contabilidad</th>
                            <?php endif; ?>

                            <th>Acción</th>
                        </tr>
                        </thead>

                        <tbody>
                        <?php foreach ($compras_fijas as $cf):
                            $badgeClass = strtolower($cf['origen']) === 'fundocol'
                                ? 'badge-origen-fundocol'
                                : 'badge-origen-consorcio';
                        ?>
                            <tr data-origen="<?= strtolower($cf['origen']) ?>">
                                <td><?= $cf['id'] ?></td>
                                <td><?= htmlspecialchars($cf['fecha']) ?></td>
                                <td>
                                    <span class="badge badge-origen <?= $badgeClass ?>">
                                        <?= htmlspecialchars($cf['origen']) ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($cf['categoria']) ?></td>
                                <td><?= htmlspecialchars($cf['proveedor']) ?></td>
                                <td>$<?= number_format($cf['monto'], 0, ',', '.') ?></td>
                                <td><?= htmlspecialchars($cf['solicitante']) ?></td>

                                <?php if ($rol == 5 || $rol == 8): ?>
                                    <td>
                                        <?= ($cf['fecha_aprob_direccion'] ?? null)
                                        ? date('Y-m-d H:i', strtotime($cf['fecha_aprob_direccion']))
                                        : '-' ?>
                                    </td>
                                <?php endif; ?>

                                <?php if ($rol == 6): ?>
                                    <td>
                                        <?= ($cf['fecha_aprob_contabilidad'] ?? null)
                                        ? date('Y-m-d H:i', strtotime($cf['fecha_aprob_contabilidad']))
                                        : '-' ?>
                                    </td>
                                <?php endif; ?>

                                <td>
                                    <a href="<?= htmlspecialchars($cf['link']) ?>" class="btn btn-erp">
                                        <i class="bi bi-search"></i> Ver / Aprobar
                                    </a>
                                </td>

                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

            <?php endif; ?>
        <?php endif; ?>


        <!-- TAB Firma Documentos -->
        <?php if ($tab === 'documentos_firma' && $rol == 3): ?>
            <h4 class="pendientes-section-title">
                <i class="bi bi-pen"></i> Pendientes de firma de documentos
            </h4>

            <?php if (empty($documentos_firma)): ?>
                <div class="empty-msg">No tienes documentos pendientes por firmar.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="erp-table">
                        <thead>
                        <tr>
                            <th>ID</th>
                            <th>Solicitante</th>
                            <th>Documento</th>
                            <th>Razón</th>
                            <th>Fecha solicitud</th>
                            <th>Acción</th>
                        </tr>
                        </thead>

                        <tbody>
                        <?php foreach ($documentos_firma as $doc): ?>
                            <tr>
                                <td><?= (int)$doc['id'] ?></td>
                                <td><?= htmlspecialchars($doc['solicitante']) ?></td>
                                <td><?= htmlspecialchars($doc['nombre_documento']) ?></td>
                                <td><?= htmlspecialchars(mb_strlen($doc['razon']) > 80 ? mb_substr($doc['razon'], 0, 77) . '...' : $doc['razon']) ?></td>
                                <td><?= htmlspecialchars((string)$doc['fecha_solicitud']) ?></td>
                                <td>
                                    <a href="<?= htmlspecialchars($link_firmar_documento . (int)$doc['id']) ?>" class="btn btn-erp">
                                        <i class="bi bi-vector-pen"></i> Firmar
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <!-- TAB Vehículos -->
        <?php if ($tab === 'vehiculos' && $rol == 4): ?>
            <h4 class="pendientes-section-title">
                <i class="bi bi-truck"></i> Pendientes de préstamo de vehículos
            </h4>

            <?php if (empty($solicitudes_vehiculos)): ?>
                <div class="empty-msg">No tienes solicitudes pendientes por aprobar.</div>
            <?php else: ?>

                <div class="table-responsive">
                    <table class="erp-table">
                        <thead>
                        <tr>
                            <th>ID</th>
                            <th>Vehículo</th>
                            <th>Solicitante</th>
                            <th>Fecha de la solicitud</th>
                            <th>Fecha Inicio</th>
                            <th>Fecha Final</th>
                            <th>Estado</th>
                            <th>Acción</th>
                        </tr>
                        </thead>

                        <tbody>
                        <?php foreach ($solicitudes_vehiculos as $cf): ?>
                            <tr>
                                <td><?= $cf['id'] ?></td>
                                <td><?= htmlspecialchars($cf['nombre_vehiculo']) ?></td>
                                <td><?= htmlspecialchars($cf['nombre_solicitante']) ?></td>
                                <td><?= htmlspecialchars($cf['fecha_solicitud']) ?></td>
                                <td><?= htmlspecialchars($cf['fecha_inicio']) ?></td>
                                <td><?= htmlspecialchars($cf['fecha_fin']) ?></td>
                                <td><span class="badge bg-warning"><?= htmlspecialchars($cf['estado']) ?></span></td>

                                <td>
                                    <a href="<?= $link_aprobar_vehiculo . $cf['id'] ?>" class="btn btn-erp">
                                        <i class="bi bi-search"></i> Ver / Aprobar
                                    </a>
                                </td>

                            </tr>
                        <?php endforeach; ?>
                        </tbody>

                    </table>
                </div>

            <?php endif; ?>
        <?php endif; ?>

        <!-- TAB Admin -->
        <?php if ($tab === 'admin' && $rol == 1): ?>
            <h4 class="pendientes-section-title">
                <i class="bi bi-shield-lock"></i> <?= htmlspecialchars($titulo_admin_aprobaciones) ?>
            </h4>

            <?php if (empty($admin_aprobaciones)): ?>
                <div class="empty-msg">No hay solicitudes pendientes de cotización.</div>
            <?php else: ?>

                <div class="table-responsive">
                    <table class="erp-table">
                        <thead>
                        <tr>
                            <th>ID</th>
                            <th>Consorcio</th>
                            <th>Solicitante</th>
                            <th>Necesidad</th>
                            <th>Fecha</th>
                            <th>Acción</th>
                        </tr>
                        </thead>

                        <tbody>
                        <?php foreach ($admin_aprobaciones as $row): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['id']) ?></td>
                                <td><?= htmlspecialchars($row['consorcio']) ?></td>
                                <td><?= htmlspecialchars($row['solicitante']) ?></td>
                                <td><?= htmlspecialchars($row['necesidad']) ?></td>
                                <td><?= htmlspecialchars($row['fecha']) ?></td>

                                <td>
                                    <a href="<?= $enlace_admin . $row['id'] ?>" class="btn btn-erp">
                                        <i class="bi bi-eye"></i> <?= htmlspecialchars($texto_btn_admin) ?>
                                    </a>
                                </td>

                            </tr>
                        <?php endforeach; ?>
                        </tbody>

                    </table>
                </div>

            <?php endif; ?>
        <?php endif; ?>

    </div>
</div>

<script>
// Filtro de origen para compras normales
(function() {
    const select = document.getElementById('filtro-origen-compras');
    const tabla = document.getElementById('tabla-compras-pendientes');
    if (!select || !tabla) return;

    select.addEventListener('change', function() {
        const valor = this.value;
        const filas = tabla.querySelectorAll('tbody tr');
        filas.forEach(tr => {
            const origen = tr.getAttribute('data-origen');
            tr.style.display = (valor === 'todos' || origen === valor) ? '' : 'none';
        });
    });
})();

// Filtro de origen para compras fijas
(function() {
    const select = document.getElementById('filtro-origen-fijas');
    const tabla = document.getElementById('tabla-compras-fijas');
    if (!select || !tabla) return;

    select.addEventListener('change', function() {
        const valor = this.value;
        const filas = tabla.querySelectorAll('tbody tr');
        filas.forEach(tr => {
            const origen = tr.getAttribute('data-origen');
            tr.style.display = (valor === 'todos' || origen === valor) ? '' : 'none';
        });
    });
})();
</script>

</body>
</html>