<?php
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

function responderRegistro(int $codigo, bool $registrado, string $mensaje): never {
    http_response_code($codigo);
    echo json_encode(['registrado' => $registrado, 'mensaje' => $mensaje], JSON_UNESCAPED_UNICODE);
    exit;
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    responderRegistro(405, false, 'Metodo no permitido.');
}
$cuerpo = file_get_contents('php://input', false, null, 0, 16385);
if (strlen($cuerpo) > 16384) responderRegistro(413, false, 'Solicitud demasiado grande.');
try {
    $entrada = json_decode($cuerpo, true, 16, JSON_THROW_ON_ERROR);
} catch (JsonException $e) {
    responderRegistro(400, false, 'JSON invalido.');
}
foreach (['nombreApellido', 'numeroIdentificacion', 'correoElectronico', 'contrasena'] as $campo) {
    if (!is_array($entrada) || !is_string($entrada[$campo] ?? null)) {
        responderRegistro(400, false, 'Complete todos los campos del registro.');
    }
}
$nombreRegistro = trim($entrada['nombreApellido']);
$identificacionRegistro = trim($entrada['numeroIdentificacion']);
$correoRegistro = strtolower(trim($entrada['correoElectronico']));
$claveRegistro = $entrada['contrasena'];
if ($nombreRegistro === '' || preg_match_all('/./us', $nombreRegistro) > 150
    || !preg_match('/^[a-zA-Z0-9-]{4,30}$/D', $identificacionRegistro)
    || strlen($correoRegistro) > 254 || !filter_var($correoRegistro, FILTER_VALIDATE_EMAIL)
    || preg_match_all('/./us', $claveRegistro) < 6 || strlen($claveRegistro) > 72
    || str_contains($claveRegistro, "\0")) {
    responderRegistro(400, false, 'Revise nombre, identificacion, correo y contrasena (6 caracteres como minimo y 72 bytes como maximo).');
}
require_once __DIR__ . '/conexion.php';
try {
    $hashRegistro = password_hash($claveRegistro, PASSWORD_BCRYPT);
    $conexion->beginTransaction();
    // Serializa los registros para comprobar tambien los identificadores antiguos.
    $conexion->query('SELECT pg_advisory_xact_lock(728304612)');
    $duplicado = $conexion->prepare(
        'SELECT 1 FROM public.usuarios WHERE LOWER(identificador_usuario) = :correo_identificador
         OR identificador_usuario = :identificador_antiguo
         OR LOWER(correo_electronico) = :correo OR numero_identificacion = :identificacion LIMIT 1'
    );
    $duplicado->execute([
        'correo_identificador' => $correoRegistro, 'identificador_antiguo' => $identificacionRegistro,
        'correo' => $correoRegistro, 'identificacion' => $identificacionRegistro
    ]);
    if ($duplicado->fetchColumn()) {
        $conexion->rollBack();
        responderRegistro(409, false, 'Ya existe una cuenta con ese correo o identificacion.');
    }
    $consultaRegistro = $conexion->prepare(
        "INSERT INTO public.usuarios
         (identificador_usuario, contrasena_hash, id_rol, nombre_apellido, numero_identificacion, correo_electronico)
         VALUES (:identificador, :hash, (SELECT id_rol FROM public.roles WHERE nombre_rol = 'estudiante' ORDER BY id_rol LIMIT 1),
                 :nombre, :identificacion, :correo)"
    );
    $consultaRegistro->execute([
        'identificador' => $correoRegistro, 'hash' => $hashRegistro, 'nombre' => $nombreRegistro,
        'identificacion' => $identificacionRegistro, 'correo' => $correoRegistro
    ]);
    $conexion->commit();
    responderRegistro(201, true, 'Cuenta creada. Ya puedes iniciar sesion con tu correo o identificacion.');
} catch (Throwable $e) {
    if ($conexion->inTransaction()) $conexion->rollBack();
    if ($e instanceof PDOException && $e->getCode() === '23505') {
        responderRegistro(409, false, 'Ya existe una cuenta con ese correo o identificacion.');
    }
    error_log('ERROR REGISTRO: ' . $e->getMessage());
    responderRegistro(500, false, 'No fue posible crear la cuenta.');
}
