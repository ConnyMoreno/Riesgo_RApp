<?php
header('Content-Type: text/plain; charset=UTF-8');
require_once __DIR__ . '/conexion.php';
echo "CONEXION EXITOSA\n";
echo 'Base: ' . $conexion->query('SELECT current_database()')->fetchColumn() . "\n";
