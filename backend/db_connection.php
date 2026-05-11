<?php
//connection variables
$host = 'db';
$db = getenv('sql_namedb');
$user = 'root';
$pass = getenv('sql_pass');
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

//options array for debbuging
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, //php shows error when and where its there a mistake
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, //associative data
    PDO::ATTR_EMULATE_PREPARES => false, //sends sql and data separately, prevents injections
];

// creating the connection
try {
    $pdo = new PDO($dsn, $user, $pass, $options);
}catch(\PDOException $e){
    throw new \PDOException($e->getMessage(), (int)$e->getCode()); //$e error message
}