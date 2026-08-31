<?php
$pdo = new PDO('mysql:host=141.98.17.5;port=3306;dbname=pmo_jaidee;charset=utf8mb4', 'jdcloud', 'Look@om30');
$pdo->exec('DELETE FROM schema_migrations WHERE version = "0042"');
echo "Removed 0042 from schema_migrations\n";