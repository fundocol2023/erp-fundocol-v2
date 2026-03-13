<<?php
session_start();
require_once '../../config/db.php';

// ✅ NUEVO: Graph mailer
require_once '../../includes/mailer.php';

if (!isset($_SESSION['usuario_id'])) { header('Location: ../../login.php'); exit(); }

$rol = $_SESSION['usuario_rol'];
$id_usuario = $_SESSION['usuario_id'];
$id_solicitud = intval($_POST['id_solicitud'] ?? 0);

if (!$id_solicitud) { echo "ID invalido"; exit(); }

// Traer la solicitud + datos solicitante
$sql = "SELECT sc.*, 
               u.email AS correo_solicitante, 
               u.nombre AS nombre_solicitante, 
               sc.proyecto_oficina, 
               sc.necesidad
        FROM solicitudes_compra sc
        JOIN usuarios u ON sc.solicitante_id = u.id
        WHERE sc.id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$id_solicitud]);
$solicitud = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$solicitud) { echo "Solicitud no encontrada"; exit(); }

// Traer productos de la solicitud
$sql = "SELECT nombre, cantidad, descripcion, precio_unitario, precio_total 
        FROM solicitudes_compra_productos 
        WHERE solicitud_compra_id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$id_solicitud]);
$productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Armar tabla HTML para los correos
$tabla = '<table style="width:100%;border-collapse:collapse;font-size:1rem;margin-top:8px;">
  <thead>
    <tr style="background:#eaf3ff;color:#183357;">
      <th style="padding:8px 5px;border:1px solid #dde7f6;">Nombre</th>
      <th style="padding:8px 5px;border:1px solid #dde7f6;">Cantidad</th>
      <th style="padding:8px 5px;border:1px solid #dde7f6;">Descripción</th>
      <th style="padding:8px 5px;border:1px solid #dde7f6;">Precio unitario</th>
      <th style="padding:8px 5px;border:1px solid #dde7f6;">Precio total</th>
    </tr>
  </thead>
  <tbody>';

foreach ($productos as $prod) {
  $tabla .= '<tr>
    <td style="padding:8px 6px;border:1px solid #dde7f6;">'.htmlspecialchars($prod['nombre']).'</td>
    <td style="padding:8px 6px;border:1px solid #dde7f6;text-align:center">'.intval($prod['cantidad']).'</td>
    <td style="padding:8px 6px;border:1px solid #dde7f6;">'.htmlspecialchars($prod['descripcion']).'</td>
    <td style="padding:8px 6px;border:1px solid #dde7f6;text-align:right;">$'.number_format($prod['precio_unitario'],0,',','.').'</td>
    <td style="padding:8px 6px;border:1px solid #dde7f6;text-align:right;">$'.number_format($prod['precio_total'],0,',','.').'</td>
  </tr>';
}
$tabla .= '</tbody></table>';

// ----------------------------------------------------
// ACCIONES
// ----------------------------------------------------

// 1) RECHAZAR (cualquier rol que use este endpoint)
if (isset($_POST['rechazar'])) {
    $comentario = trim($_POST['comentario'] ?? '');
    if (!$comentario) { echo "Debe ingresar un comentario de rechazo."; exit(); }

    // Cambiar estado a rechazado y guardar comentario
    $sql = "UPDATE solicitudes_compra 
            SET estado='rechazada', comentario_rechazo=? 
            WHERE id=?";
    $pdo->prepare($sql)->execute([$comentario, $id_solicitud]);

    // INSERTAR TRACKING
    $stmt = $pdo->prepare("INSERT INTO solicitudes_tracking 
                           (solicitud_compra_id, estado, usuario_id, fecha_hora, comentario)
                           VALUES (?, ?, ?, NOW(), ?)");
    $stmt->execute([
        $id_solicitud,
        'rechazada',
        $id_usuario,
        $comentario
    ]);

    // Correo al solicitante
    if (!empty($solicitud['correo_solicitante'])) {
        $body = '
        <div style="font-family:Segoe UI,Arial,sans-serif;background:#f8fafd;padding:26px;">
          <div style="max-width:620px;margin:0 auto;background:#ffffff;border-radius:19px;box-shadow:0 8px 32px #e3262616;padding:33px 32px 26px 32px;">
            
            <div style="font-size:1.34rem;font-weight:700;color:#d82626;margin-bottom:15px;">
              Solicitud de compra rechazada
            </div>

            <div style="font-size:1.06rem;color:#24374e;margin-bottom:14px;line-height:1.5;">
              Hola <b>'.htmlspecialchars($solicitud['nombre_solicitante']).'</b>,<br>
              Tu solicitud de compra ha sido <b>rechazada</b>. A continuación encuentras el detalle de la solicitud y el motivo del rechazo.
            </div>

            <div style="background:#fff7f7;border-radius:12px;border:1px solid #f3c5c5;padding:14px 16px;margin-bottom:18px;">
              <div style="font-size:0.96rem;color:#a12626;font-weight:600;margin-bottom:6px;">Motivo del rechazo</div>
              <div style="font-size:0.98rem;color:#c12b2b;line-height:1.45;">'.htmlspecialchars($comentario).'</div>
            </div>

            <div style="background:#f6fafd;border-radius:12px;padding:14px 16px;margin-bottom:18px;border:1px solid #dde7f6;">
              <div style="font-size:0.96rem;color:#506484;font-weight:600;margin-bottom:8px;">Información de la solicitud</div>
              <div style="font-size:0.98rem;color:#24374e;line-height:1.45;">
                <div><span style="font-weight:600;color:#194574;">Solicitante:</span> '.htmlspecialchars($solicitud['nombre_solicitante']).'</div>
                <div><span style="font-weight:600;color:#194574;">Proyecto / Oficina:</span> '.htmlspecialchars($solicitud['proyecto_oficina']).'</div>
                <div><span style="font-weight:600;color:#194574;">Necesidad:</span> '.htmlspecialchars($solicitud['necesidad']).'</div>
                <div><span style="font-weight:600;color:#194574;">ID de solicitud:</span> '.intval($solicitud['id']).'</div>
                <div><span style="font-weight:600;color:#194574;">Fecha de creación:</span> '.htmlspecialchars($solicitud['fecha'] ?? '').'</div>
              </div>
            </div>

            <div style="font-size:0.99rem;color:#24374e;font-weight:600;margin-bottom:8px;">
              Productos de la solicitud
            </div>
            '.$tabla.'

            <div style="margin-top:24px;font-size:0.93rem;color:#9ea7b4;">
              Este mensaje es informativo. No respondas este correo.<br>
              ERP Fundocol.
            </div>
          </div>
        </div>';

        enviarCorreoFundocol(
            $solicitud['correo_solicitante'],
            $solicitud['nombre_solicitante'],
            'Tu solicitud de compra fue rechazada',
            $body
        );
    }

    header("Location: ../pendientes/index.php?msg=rechazada");
    exit();
}

// 2) APROBAR
if (isset($_POST['aprobar'])) {
    $nuevo_estado = '';
    $factura_guardada = false;

    // ---- APROBACION DE PRESUPUESTO (ROL 4) ----
    if ($rol == 4) {
        $nuevo_estado = 'aprobado_presupuesto';

        // Cambia el estado
        $sql = "UPDATE solicitudes_compra 
                SET estado=?, comentario_rechazo=NULL 
                WHERE id=?";
        $pdo->prepare($sql)->execute([$nuevo_estado, $id_solicitud]);

        // TRACKING
        $stmt = $pdo->prepare("INSERT INTO solicitudes_tracking 
                               (solicitud_compra_id, estado, usuario_id, fecha_hora, comentario)
                               VALUES (?, ?, ?, NOW(), ?)");
        $stmt->execute([
            $id_solicitud,
            'aprobado_presupuesto',
            $id_usuario,
            'Aprobado por Presupuesto'
        ]);

        // 1) Correo al solicitante
        if (!empty($solicitud['correo_solicitante'])) {
            $body_solicitante = '
            <div style="font-family:Segoe UI,Arial,sans-serif;background:#f5fafd;padding:26px;">
              <div style="max-width:620px;margin:0 auto;background:#ffffff;border-radius:18px;box-shadow:0 8px 32px #2176ff16;padding:33px 32px 26px 32px;">

                <div style="font-size:1.34rem;font-weight:700;color:#2176ff;margin-bottom:15px;">
                  Solicitud de compra aprobada por Presupuesto
                </div>

                <div style="font-size:1.05rem;color:#24374e;margin-bottom:16px;line-height:1.55;">
                  Hola <b>'.htmlspecialchars($solicitud['nombre_solicitante']).'</b>,<br>
                  Tu solicitud de compra fue <b>aprobada por el área de Presupuesto</b>.
                  Ahora se encuentra <b>pendiente de aprobación por Contabilidad</b>.
                </div>

                <div style="background:#f6fafd;border-radius:12px;padding:14px 16px;margin-bottom:18px;border:1px solid #dde7f6;">
                  <div style="font-size:0.96rem;color:#506484;font-weight:600;margin-bottom:8px;">Información de la solicitud</div>
                  <div style="font-size:0.98rem;color:#24374e;line-height:1.45;">
                    <div><span style="font-weight:600;color:#194574;">ID de solicitud:</span> '.intval($solicitud['id']).'</div>
                    <div><span style="font-weight:600;color:#194574;">Proyecto / Oficina:</span> '.htmlspecialchars($solicitud['proyecto_oficina']).'</div>
                    <div><span style="font-weight:600;color:#194574;">Necesidad:</span> '.htmlspecialchars($solicitud['necesidad']).'</div>
                    <div><span style="font-weight:600;color:#194574;">Estado actual:</span> Aprobado por Presupuesto</div>
                  </div>
                </div>

                <div style="font-size:0.99rem;color:#24374e;font-weight:600;margin-bottom:8px;">
                  Productos de la solicitud
                </div>
                '.$tabla.'

                <div style="margin-top:24px;font-size:0.93rem;color:#93a3ba;">
                  Este mensaje es informativo. No respondas este correo.
                </div>
              </div>
            </div>';

            enviarCorreoFundocol(
                $solicitud['correo_solicitante'],
                $solicitud['nombre_solicitante'],
                'Solicitud de compra aprobada por Presupuesto',
                $body_solicitante
            );
        }

        // 2) Correo a Contabilidad
        $linkAprobacion = "https://erp.fundocol.org/modules/pendientes/index.php";
        $body_contabilidad = '
<div style="font-family:\'Segoe UI\', Arial, sans-serif; background:#f5f7fb; padding:30px 0;">
  <div style="max-width:540px;margin:0 auto;background:#ffffff;border-radius:22px;box-shadow:0 8px 32px #2176ff14;padding:35px 38px 28px 38px;">
    <div style="font-size:1.38rem;font-weight:700;color:#183357;margin-bottom:17px;">
      Nueva solicitud de compra pendiente de aprobación
    </div>
    <div style="font-size:1.11rem;color:#24374e;margin-bottom:18px;">
      Hola <b>Contabilidad</b>,<br>
      Tienes una nueva solicitud de compra aprobada por Presupuesto.
    </div>

    <div style="background:#f6fafd;border-radius:11px;padding:18px 22px 14px 22px;margin-bottom:18px;">
      <div><b>ID:</b> '.intval($solicitud['id']).'</div>
      <div><b>Solicitante:</b> '.htmlspecialchars($solicitud['nombre_solicitante']).'</div>
      <div><b>Proyecto / Oficina:</b> '.htmlspecialchars($solicitud['proyecto_oficina']).'</div>
      <div><b>Necesidad:</b> '.htmlspecialchars($solicitud['necesidad']).'</div>
    </div>

    <div style="margin-bottom:20px;">
      <b style="color:#174a7c;">Productos de la solicitud:</b>
      '.$tabla.'
    </div>

    <div style="text-align:center;margin:23px 0 5px 0;">
      <a href="'.$linkAprobacion.'" style="
        display:inline-block;background:#2176ff;color:#fff;font-weight:700;
        font-size:1.12rem;text-decoration:none;padding:15px 46px;border-radius:13px;">
        Ir a la aplicación para aprobar
      </a>
    </div>

    <div style="margin-top:30px;font-size:0.95rem;color:#9aa8bc;">
      No respondas este correo. ERP Fundocol.
    </div>
  </div>
</div>';

        enviarCorreoFundocol(
            'analista.contable@fundocol.org',
            'Contabilidad Fundocol',
            'Nueva solicitud de compra pendiente de aprobación',
            $body_contabilidad
        );

        header("Location: ../pendientes/index.php?msg=aprobada");
        exit();
    }

    // ---- APROBACION DE CONTABILIDAD (ROL 5) ----
    elseif ($rol == 5) {
        $nuevo_estado = 'aprobado_contabilidad';

        // Si se adjunto factura SIGGO, guardarla
        if (isset($_FILES['factura_siggo']) && $_FILES['factura_siggo']['error'] == UPLOAD_ERR_OK) {
            $dir_subida = "../../uploads/facturas_siggo/";
            if (!is_dir($dir_subida)) { mkdir($dir_subida, 0777, true); }
            $ext = pathinfo($_FILES['factura_siggo']['name'], PATHINFO_EXTENSION);
            $archivo_nombre = "siggo_" . $id_solicitud . "_" . time() . "." . $ext;
            $ruta = $dir_subida . $archivo_nombre;
            if (move_uploaded_file($_FILES['factura_siggo']['tmp_name'], $ruta)) {
                $sql = "UPDATE solicitudes_compra SET factura_siggo=? WHERE id=?";
                $pdo->prepare($sql)->execute([$archivo_nombre, $id_solicitud]);
                $factura_guardada = true;
            }
        }

        // Actualizar estado
        $sql = "UPDATE solicitudes_compra 
                SET estado=?, comentario_rechazo=NULL 
                WHERE id=?";
        $pdo->prepare($sql)->execute([$nuevo_estado, $id_solicitud]);

        // TRACKING
        $stmt = $pdo->prepare("INSERT INTO solicitudes_tracking 
                               (solicitud_compra_id, estado, usuario_id, fecha_hora, comentario)
                               VALUES (?, ?, ?, NOW(), ?)");
        $stmt->execute([
            $id_solicitud,
            'aprobado_contabilidad',
            $id_usuario,
            'Aprobado por Contabilidad'
        ]);

        // 1) Correo al solicitante
        if (!empty($solicitud['correo_solicitante'])) {
            $body_solicitante = '
            <div style="font-family:Segoe UI,Arial,sans-serif;background:#f6fafd;padding:26px;">
              <div style="max-width:620px;margin:0 auto;background:#ffffff;border-radius:18px;box-shadow:0 8px 32px #2176ff16;padding:33px 32px 26px 32px;">
                <div style="font-size:1.30rem;font-weight:700;color:#15a156;margin-bottom:15px;">
                  Solicitud de compra aprobada por Contabilidad
                </div>
                <div style="font-size:1.05rem;color:#24374e;margin-bottom:16px;line-height:1.55;">
                  Hola <b>'.htmlspecialchars($solicitud['nombre_solicitante']).'</b>,<br>
                  Tu solicitud fue <b>aprobada por Contabilidad</b> y queda <b>lista para pago</b>.
                </div>

                <div style="font-size:0.99rem;color:#24374e;font-weight:600;margin-bottom:8px;">
                  Productos de la solicitud
                </div>
                '.$tabla.'

                <div style="margin-top:24px;font-size:0.93rem;color:#93a3ba;">
                  No respondas este correo. ERP Fundocol.
                </div>
              </div>
            </div>';

            enviarCorreoFundocol(
                $solicitud['correo_solicitante'],
                $solicitud['nombre_solicitante'],
                'Solicitud de compra aprobada por Contabilidad',
                $body_solicitante
            );
        }

        // 2) Correo a Pagos (adjuntando factura_siggo si existe)
        $linkAprobacion = "https://erp.fundocol.org/modules/pendientes/index.php";
        $body_pagos = '
<div style="font-family:\'Segoe UI\', Arial, sans-serif; background:#f5f7fb; padding:30px 0;">
  <div style="max-width:540px;margin:0 auto;background:#ffffff;border-radius:22px;box-shadow:0 8px 32px #15a15616;padding:35px 38px 28px 38px;">
    <div style="font-size:1.32rem;font-weight:700;color:#174a7c;margin-bottom:17px;">
      Solicitud de compra aprobada y lista para pago
    </div>
    <div style="font-size:1.11rem;color:#24374e;margin-bottom:18px;">
      Hola <b>Pagos</b>,<br>
      Tienes una solicitud aprobada por Contabilidad y lista para pago.
    </div>

    <div style="background:#f6fafd;border-radius:11px;padding:18px 22px 14px 22px;margin-bottom:18px;">
      <div><b>ID:</b> '.intval($solicitud['id']).'</div>
      <div><b>Solicitante:</b> '.htmlspecialchars($solicitud['nombre_solicitante']).'</div>
      <div><b>Proyecto / Oficina:</b> '.htmlspecialchars($solicitud['proyecto_oficina']).'</div>
      <div><b>Necesidad:</b> '.htmlspecialchars($solicitud['necesidad']).'</div>
    </div>

    <div style="margin-bottom:20px;">
      <b style="color:#174a7c;">Productos de la solicitud:</b>
      '.$tabla.'
    </div>

    <div style="text-align:center;margin:23px 0 5px 0;">
      <a href="'.$linkAprobacion.'" style="
        display:inline-block;background:#15a156;color:#fff;font-weight:700;
        font-size:1.13rem;text-decoration:none;padding:14px 44px;border-radius:13px;">
        Ir a la aplicación para pago
      </a>
    </div>

    <div style="margin-top:30px;font-size:0.95rem;color:#93a3ba;">
      No respondas este correo. ERP Fundocol.
    </div>
  </div>
</div>';

        $adjuntos_pagos = [];

        // Adjuntar factura siggo si existe
        if (!empty($solicitud['factura_siggo'])) {
            $ruta_siggo = __DIR__ . "/../../uploads/facturas_siggo/" . $solicitud['factura_siggo'];
            if (file_exists($ruta_siggo)) $adjuntos_pagos[] = $ruta_siggo;
        }

        enviarCorreoFundocol(
            'pagos@fundocol.org',
            'Pagos Fundocol',
            'Solicitud de compra aprobada y lista para pago',
            $body_pagos,
            $adjuntos_pagos
        );

        header("Location: ../pendientes/index.php?msg=aprobada");
        exit();
    }

    // ---- CONFIRMACION DE PAGO (ROL 6) ----
    elseif ($rol == 6) {
        $nuevo_estado = 'pago_confirmado';

        // (Opcional) subir soporte de pago si lo mandas por form
        // Si tu formulario NO envía soporte, esto no rompe.
        $soporte_pago_nombre = null;
        if (isset($_FILES['soporte_pago']) && $_FILES['soporte_pago']['error'] == UPLOAD_ERR_OK) {
            $dir_subida = "../../uploads/soportes_pago/";
            if (!is_dir($dir_subida)) { mkdir($dir_subida, 0777, true); }
            $ext = strtolower(pathinfo($_FILES['soporte_pago']['name'], PATHINFO_EXTENSION));
            $permitidas = ['pdf','jpg','jpeg','png'];
            if (in_array($ext, $permitidas)) {
                $soporte_pago_nombre = "pago_" . $id_solicitud . "_" . time() . "." . $ext;
                $ruta = $dir_subida . $soporte_pago_nombre;
                move_uploaded_file($_FILES['soporte_pago']['tmp_name'], $ruta);

                // Guardar en BD si tienes campo (ajusta nombre de columna si aplica)
                // Si no tienes columna, comenta este bloque.
                // $pdo->prepare("UPDATE solicitudes_compra SET soporte_pago=? WHERE id=?")
                //     ->execute([$soporte_pago_nombre, $id_solicitud]);
            }
        }

        // Actualizar estado
        $sql = "UPDATE solicitudes_compra 
                SET estado=?, comentario_rechazo=NULL 
                WHERE id=?";
        $pdo->prepare($sql)->execute([$nuevo_estado, $id_solicitud]);

        // TRACKING
        $stmt = $pdo->prepare("INSERT INTO solicitudes_tracking 
                               (solicitud_compra_id, estado, usuario_id, fecha_hora, comentario)
                               VALUES (?, ?, ?, NOW(), ?)");
        $stmt->execute([
            $id_solicitud,
            'pago_confirmado',
            $id_usuario,
            'Pago confirmado por Pagos'
        ]);

        // ✅ Adjuntos soporte pago (si existe)
        $adjuntos_soporte = [];
        if (!empty($soporte_pago_nombre)) {
            $ruta_soporte = __DIR__ . "/../../uploads/soportes_pago/" . $soporte_pago_nombre;
            if (file_exists($ruta_soporte)) $adjuntos_soporte[] = $ruta_soporte;
        }

        // 1) Correo al solicitante
        if (!empty($solicitud['correo_solicitante'])) {
            $body_pago = '
            <div style="font-family:Segoe UI,Arial,sans-serif;background:#f6fafd;padding:26px;">
              <div style="max-width:620px;margin:0 auto;background:#ffffff;border-radius:18px;box-shadow:0 8px 32px #2176ff16;padding:33px 32px 26px 32px;">

                <div style="font-size:1.29rem;font-weight:700;color:#15a156;margin-bottom:15px;">
                  Pago de solicitud de compra confirmado
                </div>

                <div style="font-size:1.05rem;color:#24374e;margin-bottom:16px;line-height:1.55;">
                  Hola <b>'.htmlspecialchars($solicitud['nombre_solicitante']).'</b>,<br>
                  El área de Pagos ha confirmado el pago de tu solicitud de compra.
                </div>

                <div style="background:#f6fafd;border-radius:12px;padding:14px 16px;margin-bottom:18px;border:1px solid #dde7f6;">
                  <div style="font-size:0.96rem;color:#506484;font-weight:600;margin-bottom:8px;">Información de la solicitud</div>
                  <div style="font-size:0.98rem;color:#24374e;line-height:1.45;">
                    <div><span style="font-weight:600;color:#194574;">ID de solicitud:</span> '.intval($solicitud['id']).'</div>
                    <div><span style="font-weight:600;color:#194574;">Proyecto / Oficina:</span> '.htmlspecialchars($solicitud['proyecto_oficina']).'</div>
                    <div><span style="font-weight:600;color:#194574;">Necesidad:</span> '.htmlspecialchars($solicitud['necesidad']).'</div>
                    <div><span style="font-weight:600;color:#194574;">Estado actual:</span> Pago confirmado</div>
                  </div>
                </div>

                <div style="font-size:0.99rem;color:#24374e;font-weight:600;margin-bottom:8px;">
                  Productos de la solicitud
                </div>
                '.$tabla.'

                <div style="margin-top:24px;font-size:0.93rem;color:#93a3ba;">
                  Este mensaje es informativo. No respondas este correo.
                </div>
              </div>
            </div>';

            enviarCorreoFundocol(
                $solicitud['correo_solicitante'],
                $solicitud['nombre_solicitante'],
                'Pago de solicitud de compra confirmado',
                $body_pago,
                $adjuntos_soporte
            );
        }

        // ✅ 2) Correo a Presupuesto notificando pago + soporte
        $correo_presupuesto = "presupuesto@fundocol.org";
        $nombre_presupuesto = "Presupuesto Fundocol";

        $body_presupuesto = '
        <div style="font-family:Segoe UI,Arial,sans-serif;background:#f5f7fb;padding:26px;">
          <div style="max-width:620px;margin:0 auto;background:#ffffff;border-radius:18px;box-shadow:0 8px 32px #15a15616;padding:33px 32px 26px 32px;">

            <div style="font-size:1.30rem;font-weight:700;color:#15a156;margin-bottom:15px;">
              Pago confirmado (Notificación a Presupuesto)
            </div>

            <div style="font-size:1.05rem;color:#24374e;margin-bottom:16px;line-height:1.55;">
              Se confirmó el pago de una solicitud de compra.
              Se adjunta el soporte de pago (si fue cargado).
            </div>

            <div style="background:#f6fafd;border-radius:12px;padding:14px 16px;margin-bottom:18px;border:1px solid #dde7f6;">
              <div style="font-size:0.96rem;color:#506484;font-weight:600;margin-bottom:8px;">Detalle</div>
              <div style="font-size:0.98rem;color:#24374e;line-height:1.45;">
                <div><b>ID solicitud:</b> '.intval($solicitud['id']).'</div>
                <div><b>Solicitante:</b> '.htmlspecialchars($solicitud['nombre_solicitante']).'</div>
                <div><b>Proyecto / Oficina:</b> '.htmlspecialchars($solicitud['proyecto_oficina']).'</div>
                <div><b>Necesidad:</b> '.htmlspecialchars($solicitud['necesidad']).'</div>
                <div><b>Estado:</b> Pago confirmado</div>
              </div>
            </div>

            <div style="font-size:0.99rem;color:#24374e;font-weight:600;margin-bottom:8px;">
              Productos
            </div>
            '.$tabla.'

            <div style="margin-top:24px;font-size:0.93rem;color:#93a3ba;">
              ERP Fundocol. Mensaje automático.
            </div>
          </div>
        </div>';

        enviarCorreoFundocol(
            $correo_presupuesto,
            $nombre_presupuesto,
            'Pago confirmado - Solicitud de compra #'.intval($solicitud['id']),
            $body_presupuesto,
            $adjuntos_soporte
        );

        header("Location: ../pendientes/index.php?msg=pago_confirmado");
        exit();
    }

    echo "No tienes permisos para aprobar esta solicitud.";
    exit();
}

echo "Acción no reconocida.";
exit();
?>
