<?php
class Database {
    private static ?PDO $instance = null;

    public static function getConnection(): PDO {
        if (self::$instance === null) {
            $host = $_ENV['DB_HOST'] ?? 'db';
            $port = $_ENV['DB_PORT'] ?? '3306';
            $name = $_ENV['DB_NAME'];
            $user = $_ENV['DB_USER'] ?? 'root';
            $pass = $_ENV['DB_PASS'];

            $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";

            self::$instance = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, //php muestfa error when and where its there a mistake
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, //associative data
                PDO::ATTR_EMULATE_PREPARES=> false,//sends sql and data separately no inyecciones
            ]);
        }

        return self::$instance;
    }

    #pa evitar clonacion e instanciacion directa
    private function __construct() {}
    private function __clone() {}
}
