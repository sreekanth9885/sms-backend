<?php

try {
    $pdo = new PDO(
        "mysql:host=localhost;dbname=testdb;charset=utf8mb4",
        "root",
        "password" // put password if exists
    );
    echo "DB CONNECTED";
} catch (PDOException $e) {
    echo $e->getMessage();
}
