<?php
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

function responderLogin(int $codigo, array $datos): never {
    http_response_code($codigo);
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    responderLogin(405, ['autenticado' => false, 'mensaje' => 'Metodo no permitido.']);
}

try {
    $entrada = json_decode(file_get_contents('php://input'), true, 16, JSON_THROW_ON_ERROR);
} catch (JsonException $e) {
    responderLogin(400, ['autenticado' => false, 'mensaje' => 'JSON invalido.']);
}
if (!is_array($entrada) || !is_string($entrada['identificadorUsuario'] ?? null)
    || !is_string($entrada['contrasena'] ?? null)) {
    responderLogin(400, ['autenticado' => false, 'mensaje' => 'Ingrese identificador y contrasena.']);
}
$identificador = trim($entrada['identificadorUsuario']);
$contrasenaIngresada = $entrada['contrasena'];
if ($identificador === '' || strlen($identificador) > 400 || $contrasenaIngresada === '' || strlen($contrasenaIngresada) > 4096) {
    responderLogin(400, ['autenticado' => false, 'mensaje' => 'Credenciales invalidas.']);
}

require_once __DIR__ . '/conexion.php';
try {
    $consulta = $conexion->prepare(
        'SELECT u.id_usuario, u.identificador_usuario, u.contrasena_hash, r.nombre_rol
         FROM public.usuarios u JOIN public.roles r ON r.id_rol = u.id_rol
         WHERE u.identificador_usuario = :identificador
         OR LOWER(u.correo_electronico) = :correo
         OR u.numero_identificacion = :identificacion LIMIT 1'
    );
    $consulta->execute([
        'identificador' => $identificador,
        'correo' => strtolower($identificador),
        'identificacion' => $identificador
    ]);
    $usuario = $consulta->fetch();
    // El hash auxiliar mantiene la verificacion aunque el identificador no exista.
    $hash = $usuario['contrasena_hash'] ?? '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';
    $valida = password_verify($contrasenaIngresada, $hash);
    if (!$usuario || !$valida) {
        responderLogin(401, ['autenticado' => false, 'mensaje' => 'Identificador o contrasena incorrectos.']);
    }
    $token = bin2hex(random_bytes(32));
    $sesion = $conexion->prepare(
        "INSERT INTO public.sesiones
         (id_usuario, token_hash, fecha_creacion, fecha_expiracion, estado)
         VALUES (:usuario, :hash, CURRENT_TIMESTAMP AT TIME ZONE 'UTC',
                 (CURRENT_TIMESTAMP AT TIME ZONE 'UTC') + INTERVAL '1 hour', B'1')"
    );
    $sesion->execute(['usuario' => $usuario['id_usuario'], 'hash' => hash('sha256', $token)]);
    responderLogin(200, [
        'autenticado' => true,
        'mensaje' => 'Inicio de sesion exitoso.',
        'token' => $token,
        'usuario' => [
            'idUsuario' => (int)$usuario['id_usuario'],
            'identificadorUsuario' => $usuario['identificador_usuario'],
            'rolUsuario' => $usuario['nombre_rol']
        ]
    ]);
} catch (Throwable $e) {
    error_log('ERROR LOGIN: ' . $e->getMessage());
    responderLogin(500, ['autenticado' => false, 'mensaje' => 'No fue posible iniciar sesion.']);
}
