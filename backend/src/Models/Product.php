<?php

class Product {

    private PDO $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    public function findAll(): array {
        $stmt = $this->db->prepare("
            SELECT ID_PRODUCT, NAME_PRODUCT, WEIGHT_APROX, PRICE
            FROM PRODUCT
            ORDER BY NAME_PRODUCT ASC
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array {
        $stmt = $this->db->prepare("
            SELECT ID_PRODUCT, NAME_PRODUCT, WEIGHT_APROX, PRICE
            FROM PRODUCT
            WHERE ID_PRODUCT = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $id]);
        $product = $stmt->fetch();
        return $product ?: null;
    }

    public function search(string $query): array {
        $stmt = $this->db->prepare("
            SELECT ID_PRODUCT, NAME_PRODUCT, WEIGHT_APROX, PRICE
            FROM PRODUCT
            WHERE NAME_PRODUCT LIKE :query
            ORDER BY NAME_PRODUCT ASC
            LIMIT 20
        ");
        $stmt->execute([':query' => '%' . $query . '%']);
        return $stmt->fetchAll();
    }

    public function create(array $data): int {
        $stmt = $this->db->prepare("
            INSERT INTO PRODUCT (NAME_PRODUCT, WEIGHT_APROX, PRICE)
            VALUES (:name, :weight, :price)
        ");
        $stmt->execute([
            ':name'   => $data['name'],
            ':weight' => $data['weight'],
            ':price'  => $data['price'],
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool {
        $fields = [];
        $params = [':id' => $id];

        if (!empty($data['name'])) {
            $fields[] = 'NAME_PRODUCT = :name';
            $params[':name'] = $data['name'];
        }
        if (isset($data['weight'])) {
            $fields[] = 'WEIGHT_APROX = :weight';
            $params[':weight'] = $data['weight'];
        }
        if (isset($data['price'])) {
            $fields[] = 'PRICE = :price';
            $params[':price'] = $data['price'];
        }

        if (empty($fields)) return false;

        $stmt = $this->db->prepare("UPDATE PRODUCT SET " . implode(', ', $fields) . " WHERE ID_PRODUCT = :id");
        $stmt->execute($params);
        return $stmt->rowCount() > 0;
    }

    public function delete(int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM PRODUCT WHERE ID_PRODUCT = :id");
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    }
}