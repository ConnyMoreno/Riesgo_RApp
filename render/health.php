<?php
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
require_once __DIR__ . '/conexion.php';
try {
    $conexion->query('SELECT 1')->fetchColumn();
    echo json_encode(['estado' => 'ok']);
} catch (Throwable $e) {
    error_log('ERROR HEALTH: ' . $e->getMessage());
    http_response_code(503);
    echo json_encode(['estado' => 'error']);
}
