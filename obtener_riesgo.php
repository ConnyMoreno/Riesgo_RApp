
<?php

header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . "/conexion.php";

try {
    $sql = "SELECT *
            FROM public.informacion_riesgos
            ORDER BY id ASC";

    $stmt = $conexion->prepare($sql);
    $stmt->execute();

    $riesgos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "success" => true,
        "data" => $riesgos
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {

    error_log($e->getMessage());

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Error al consultar los riesgos"
    ]);
}
