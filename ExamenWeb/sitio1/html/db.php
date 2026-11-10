<?php
// Credenciales leidas del entorno (docker-compose y .env). No se escriben en el codigo.
$db_host = getenv('DB_HOST');
$db_user = getenv('DB_USER');
$db_pass = getenv('DB_PASS');
$db_name = getenv('DB_NAME');
if ($db_host === false || $db_user === false || $db_pass === false || $db_name === false) {
    die("Configuracion de base de datos incompleta");
}
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
