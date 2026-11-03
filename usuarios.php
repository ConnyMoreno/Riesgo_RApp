
<?php
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    http_response_code(405);
    echo json_encode(["error" => "Metodo no permitido"]);
    exit;
}

require_once __DIR__ . "/conexion.php";

try {
    $sql = "SELECT COUNT(*) AS total FROM public.usuarios";
    $consulta = $conexion->query($sql);

    echo json_encode([
        "estado" => "exito",
        "total_usuarios" => (int) $consulta->fetchColumn()
    ]);

} catch (PDOException $e) {
    error_log($e->getMessage());
    http_response_code(500);
    echo json_encode([
        "estado" => "error",
        "mensaje" => "No se pudo consultar usuarios"
    ]);
}
?>
