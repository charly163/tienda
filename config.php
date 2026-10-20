<?php
// Configuracion de conexion. En XAMPP, el usuario root suele no tener clave.
// Para otro entorno, define DB_HOST, DB_NAME, DB_USER y DB_PASS en el servidor.
return [
    'host' => getenv('DB_HOST') ?: '127.0.0.1',
    'name' => getenv('DB_NAME') ?: 'techstore',
    'user' => getenv('DB_USER') ?: 'root',
    'pass' => getenv('DB_PASS') ?: '',
    'charset' => 'utf8mb4',
];
