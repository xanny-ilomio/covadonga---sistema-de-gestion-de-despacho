<?php
class User{
    private PDO $db;

    public function __construct(){
        $this->db = Database::getConnection();
    }

    public function findByUsername(string $username): ?array{
        $stmt = $this->db->prepare("
            SELECT * FROM USER WHERE USERNAME =:username LIMIT 1
        ");
        $stmt->execute(([':username' => $username]));
        #:username es un placeholder pa evitar inyeccion
        $user = $stmt->fetch();
        return $user ?:null;
    }

    public function findById(int $id): ?array{
        $stmt = $this->db->prepare("
            SELECT ID_USER, USERNAME, ROL FROM USER WHERE ID_USER = :id LIMIT 1
        ");
        $stmt->execute([':id' => $id]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

}