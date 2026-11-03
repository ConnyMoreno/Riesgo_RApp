<?php
// En el servidor publico, las credenciales proceden solo del entorno privado.
$host = getenv('NEON_HOST');
$puerto = getenv('NEON_PORT') ?: '5432';
$nombre_bd = getenv('NEON_DATABASE') ?: 'neondb';
$usuario = getenv('NEON_USER');
$contrasena = getenv('NEON_PASSWORD');

try {
    if (!$host || !$usuario || !$contrasena) {
        throw new RuntimeException('Faltan NEON_HOST, NEON_USER o NEON_PASSWORD');
    }
    // En clientes libpq anteriores a 14, Neon necesita el endpoint explicito.
    $endpoint = preg_replace('/-pooler$/', '', explode('.', $host)[0]);
    if (!preg_match('/^ep-[a-z0-9-]+$/D', $endpoint)) {
        throw new RuntimeException('NEON_HOST no contiene un endpoint valido de Neon');
    }
    foreach ([$host, $puerto, $nombre_bd] as $valor) {
        if (!preg_match('/^[a-zA-Z0-9_.-]+$/D', $valor)) {
            throw new RuntimeException('Parametro de conexion invalido');
        }
    }
    $dsn = "pgsql:host=$host;port=$puerto;dbname=$nombre_bd;" .
           "sslmode=verify-full;sslrootcert=/etc/ssl/certs/ca-certificates.crt;" .
           "connect_timeout=10;options='endpoint=$endpoint'";
    $conexion = new PDO($dsn, $usuario, $contrasena, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (Throwable $e) {
    error_log('ERROR POSTGRESQL: ' . $e->getMessage());
    http_response_code(500);
    if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'prueba_conexion.php') {
        exit("CONEXION FALLIDA\nConsulta ERROR POSTGRESQL en la terminal PHP.\n");
    }
    header('Content-Type: application/json; charset=UTF-8');
    exit(json_encode(['estado' => 'error', 'mensaje' => 'Error de conexion con Neon']));
}
