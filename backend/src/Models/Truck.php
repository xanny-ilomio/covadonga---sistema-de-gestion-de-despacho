<?php

class Truck {

    private PDO $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    public function findAll(): array {
        $stmt = $this->db->prepare("
            SELECT ID_TRUCK, BRAND, PLATE, CAPACITY
            FROM TRUCK
            ORDER BY BRAND ASC
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array {
        $stmt = $this->db->prepare("
            SELECT ID_TRUCK, BRAND, PLATE, CAPACITY
            FROM TRUCK WHERE ID_TRUCK = :id LIMIT 1
        ");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function plateExists(string $plate, ?int $excludeId = null): bool {
        if ($excludeId) {
            $stmt = $this->db->prepare("SELECT ID_TRUCK FROM TRUCK WHERE PLATE = :plate AND ID_TRUCK != :id LIMIT 1");
            $stmt->execute([':plate' => $plate, ':id' => $excludeId]);
        } else {
            $stmt = $this->db->prepare("SELECT ID_TRUCK FROM TRUCK WHERE PLATE = :plate LIMIT 1");
            $stmt->execute([':plate' => $plate]);
        }
        return $stmt->fetch() !== false;
    }

    public function create(array $data): int {
        $stmt = $this->db->prepare("
            INSERT INTO TRUCK (BRAND, PLATE, CAPACITY)
            VALUES (:brand, :plate, :capacity)
        ");
        $stmt->execute([
            ':brand'    => $data['brand'],
            ':plate'    => strtoupper($data['plate']),
            ':capacity' => (int) $data['capacity'],
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool {
        $fields = [];
        $params = [':id' => $id];

        if (!empty($data['brand'])) {
            $fields[] = 'BRAND = :brand';
            $params[':brand'] = $data['brand'];
        }
        if (!empty($data['plate'])) {
            $fields[] = 'PLATE = :plate';
            $params[':plate'] = strtoupper($data['plate']);
        }
        if (isset($data['capacity'])) {
            $fields[] = 'CAPACITY = :capacity';
            $params[':capacity'] = (int) $data['capacity'];
        }

        if (empty($fields)) return false;

        $stmt = $this->db->prepare("UPDATE TRUCK SET " . implode(', ', $fields) . " WHERE ID_TRUCK = :id");
        $stmt->execute($params);
        return $stmt->rowCount() > 0;
    }

    public function delete(int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM TRUCK WHERE ID_TRUCK = :id");
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    }
}