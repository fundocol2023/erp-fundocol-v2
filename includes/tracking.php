<?php
require_once __DIR__ . '/../config/db.php';

/**
 * Registra un evento en la línea de tiempo/tracking de procesos.
 *
 * @param array $data [
 *   'solicitud_cotizacion_id' => (int|null),
 *   'solicitud_compra_id' => (int|null),
 *   'estado' => (string),
 *   'usuario_id' => (int|null),
 *   'comentario' => (string|null)
 * ]
 * @return void
 */
function registrar_tracking($data) {
    global $pdo;
    $sql = "INSERT INTO solicitudes_tracking
        (solicitud_cotizacion_id, solicitud_compra_id, estado, usuario_id, comentario)
        VALUES (:scot, :scomp, :estado, :usuario, :comentario)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':scot'      => $data['solicitud_cotizacion_id'] ?? null,
        ':scomp'     => $data['solicitud_compra_id'] ?? null,
        ':estado'    => $data['estado'],
        ':usuario'   => $data['usuario_id'] ?? null,
        ':comentario'=> $data['comentario'] ?? null
    ]);
}
?>
