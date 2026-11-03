

<?php
require_once __DIR__ . '/conexion.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $consulta = $conexion->query(
        "SELECT COUNT(*) AS total FROM public.usuarios"
    );

    $resultado = $consulta->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        "estado" => "conectado",
        "mensaje" => "Conexion exitosa con PostgreSQL",
        "total_usuarios" => (int)$resultado["total"]
    ]);

} catch (PDOException $e) {
    error_log($e->getMessage());
    http_response_code(500);

    echo json_encode([
        "estado" => "error",
        "mensaje" => "Error al consultar usuarios"
    ]);
}
?>
