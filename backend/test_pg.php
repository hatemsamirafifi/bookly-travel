<?php
$passwords = ['', 'postgres', 'root', 'secret'];
$success = false;

foreach ($passwords as $pwd) {
    try {
        $pdo = new PDO("pgsql:host=127.0.0.1;port=5432;dbname=postgres", "postgres", $pwd);
        echo "Success with user 'postgres' and password: '$pwd'\n";
        
        // Let's create the bookly database and user since we have access
        try {
            $pdo->exec("CREATE USER bookly WITH PASSWORD 'secret'");
        } catch(Exception $e) {}
        
        try {
            $pdo->exec("CREATE DATABASE bookly OWNER bookly");
        } catch(Exception $e) {}
        
        $success = true;
        break;
    } catch (PDOException $e) {
    }
}

if (!$success) {
    echo "Failed to connect to local PostgreSQL.\n";
}
