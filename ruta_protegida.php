<?php
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

function responderProtegida(int $codigo, bool $autorizado, string $mensaje): never {
    http_response_code($codigo);
    echo json_encode(['autorizado' => $autorizado, 'mensaje' => $mensaje], JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    header('Allow: GET');
    responderProtegida(405, false, 'Metodo no permitido.');
}
$autorizacion = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if (!preg_match('/^Bearer ([a-f0-9]{64})$/iD', $autorizacion, $coincidencia)) {
    responderProtegida(401, false, 'Token ausente o invalido.');
}

require_once __DIR__ . '/conexion.php';
try {
    $consulta = $conexion->prepare(
        "SELECT s.id_sesion FROM public.sesiones s
         JOIN public.usuarios u ON u.id_usuario = s.id_usuario
         WHERE s.token_hash = :hash AND s.estado = B'1'
         AND s.fecha_expiracion > (CURRENT_TIMESTAMP AT TIME ZONE 'UTC') LIMIT 1"
    );
    $consulta->execute(['hash' => hash('sha256', $coincidencia[1])]);
    if (!$consulta->fetchColumn()) {
        responderProtegida(401, false, 'Sesion invalida o expirada.');
    }
    responderProtegida(200, true, 'Acceso autorizado.');
} catch (Throwable $e) {
    error_log('ERROR SESION: ' . $e->getMessage());
    responderProtegida(500, false, 'No fue posible validar la sesion.');
}
