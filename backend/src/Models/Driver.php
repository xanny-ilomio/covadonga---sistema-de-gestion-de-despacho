<?php

class Driver {

    private PDO $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    public function findAll(): array {
        $stmt = $this->db->prepare("
            SELECT ID_DRIVER, NAME_DRIVER, LASTNAME, CI, PHONE_DRIVER
            FROM DRIVER
            ORDER BY NAME_DRIVER ASC
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array {
        $stmt = $this->db->prepare("
            SELECT ID_DRIVER, NAME_DRIVER, LASTNAME, CI, PHONE_DRIVER
            FROM DRIVER WHERE ID_DRIVER = :id LIMIT 1
        ");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function ciExists(int $ci, ?int $excludeId = null): bool {
        if ($excludeId) {
            $stmt = $this->db->prepare("SELECT ID_DRIVER FROM DRIVER WHERE CI = :ci AND ID_DRIVER != :id LIMIT 1");
            $stmt->execute([':ci' => $ci, ':id' => $excludeId]);
        } else {
            $stmt = $this->db->prepare("SELECT ID_DRIVER FROM DRIVER WHERE CI = :ci LIMIT 1");
            $stmt->execute([':ci' => $ci]);
        }
        return $stmt->fetch() !== false;
    }

    public function create(array $data): int {
        $stmt = $this->db->prepare("
            INSERT INTO DRIVER (NAME_DRIVER, LASTNAME, CI, PHONE_DRIVER)
            VALUES (:name, :lastname, :ci, :phone)
        ");
        $stmt->execute([
            ':name'     => $data['name'],
            ':lastname' => $data['lastname'],
            ':ci'       => (int) $data['ci'],
            ':phone'    => (int) $data['phone'],
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool {
        $fields = [];
        $params = [':id' => $id];

        if (!empty($data['name'])) {
            $fields[] = 'NAME_DRIVER = :name';
            $params[':name'] = $data['name'];
        }
        if (!empty($data['lastname'])) {
            $fields[] = 'LASTNAME = :lastname';
            $params[':lastname'] = $data['lastname'];
        }
        if (isset($data['ci'])) {
            $fields[] = 'CI = :ci';
            $params[':ci'] = (int) $data['ci'];
        }
        if (isset($data['phone'])) {
            $fields[] = 'PHONE_DRIVER = :phone';
            $params[':phone'] = (int) $data['phone'];
        }

        if (empty($fields)) return false;

        $stmt = $this->db->prepare("UPDATE DRIVER SET " . implode(', ', $fields) . " WHERE ID_DRIVER = :id");
        $stmt->execute($params);
        return $stmt->rowCount() > 0;
    }

    public function delete(int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM DRIVER WHERE ID_DRIVER = :id");
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    }
}