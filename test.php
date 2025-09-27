<?php
try {
    $db = new PDO('mysql:host=127.0.0.1;dbname=login_system;charset=utf8mb4', 'root', '');
    echo "Connected!";
} catch (PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
}
