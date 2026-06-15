<?php

class Client{
    private PDO $db;
    public function __construct(){
        $this-> db = Database::getConnection();
    }

    #PA TARER TODOS LOS CLIENTES
    public function findAll(): array{
        $stmt=$this->db->prepare("
            SELECT
                C.ID_CLIENT, C.NAME_CLIENT, C.RIF,
                C.PHONE_CLIENT, C.EMAIL_CLIENT, CI.ID_CITY,
                S.NAME_STATE,  R.ID_ROUTE, R.NAME_ROUTE
                FROM CLIENT C 
                JOIN CITY CI ON C.CITY_FK = CI.ID_CITY
                JOIN STATE S ON CI.STATE_FK = S.ID_STATE
                LEFT JOIN ROUTE R ON S.ROUTE_FK = R.ID_ROUTE
                ORDER BY C.NAME_CLIENT ASC
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    }

    #UN cliente por ID
    public function findById(int $id): ?array{
        $stmt= $this->db->prepare("
            SELECT
                C.ID_CLIENT, C.NAME_CLIENT, C.RIF,
                C.PHONE_CLIENT, C.EMAIL_CLIENT, CI.ID_CITY, 
                S.NAME_STATE, R.ID_ROUTE, R.NAME_ROUTE
                FROM CLIENT C
                JOIN CITY CI ON C.CITY_FK = CI.ID_CITY
                JOIN STATE S ON CI.STATE_FK = S.ID_STATE
                LEFT JOIN ROUTE R ON S.ROUTE_FK = R.ID_ROUTE
                WHERE C.ID_CLIENT = :id
                LIMIT 1
        ");

        $stmt->execute([':id'=>$id]);
        $client = $stmt ->fetch();
        return $client ?: null;
    }

    #cliente por RIF
    public function findByRif(string $rif): ?array{
        $stmt = $this->db->prepare("
            SELECT * FROM CLIENT WHERE RIF = :rif LIMIT 1
        ");

        $stmt->execute(['rif'=>$rif]);
        $client=$stmt->fetch();
        return $client ?: null;
    }

    #por nombre
    public function search(string $query): array{
        $stmt= $this->db->prepare("
            SELECT 
                C.ID_CLIENT, C.NAME_CLIENT, C.RIF, S.NAME_STATE,
                C.PHONE_CLIENT, CI.NAME_CITY, R.NAME_ROUTE
                FROM CLIENT C
                JOIN CITY CI ON C.CITY_FK = CI.ID_CITY
                JOIN STATE S ON CI.STATE_FK = S.ID_STATE
                LEFT JOIN ROUTE R ON S.ROUTE_FK = R.ID_ROUTE
                WHERE C.NAME_CLIENT LIKE :query_name
                OR C.RIF LIKE :query_rif
                ORDER BY C.NAME_CLIENT ASC LIMIT 20
        ");
        #% busca texto en cualquier posiciion
        $like = '%' . $query . '%';
        $stmt->execute([
            ':query_name'=> $like,
            ':query_rif'=> $like,
        ]);
        return $stmt -> fetchAll();
    }

    #crear cliente 
    public function create(array $data): int{
        $stmt = $this->db->prepare("
            INSERT INTO CLIENT (NAME_CLIENT,RIF,PHONE_CLIENT,EMAIL_CLIENT,CITY_FK)
            VALUES(:name,:rif,:phone,:email,:city_id)
        ");
        $stmt->execute([
            ':name'=>$data['name'],
            ':rif'=>$data['rif'],
            ':phone'=>(string)$data['phone'],
            ':email'=>$data['email'] ?? null,
            ':city_id'=>$data['city_id'],
        ]);
        return (int) $this->db->lastInsertId();
    }

    #actualizar cliente
    public function update(int $id, array $data): bool{
        $fields = [];
        $params = [':id'=>$id];

        if(!empty($data['name'])){
            $fields[]='NAME_CLIENT = :name';
            $params[':name'] =$data['name'];
        }

        if(!empty($data['rif'])){
            $fields[] = 'RIF = :rif';
            $params[':rif'] = $data['rif'];
        }
        if(!empty($data['phone'])){
            $fields[]='PHONE_CLIENT = :phone';
            $params[':phone'] =$data['phone'];
        }

        if(array_key_exists('email',$data)){
            #esto poq puede que no exitsa
            $fields[]='EMAIL_CLIENT = :email';
            $params[':email'] = $data['email']?:null;
        }

        if(!empty($data['city_id'])){
            $fields[] = 'CITY_FK = :city_id';
            $params[':city_id'] = $data['city_id'];
        }

        if(empty($fields)){
            return false;
        }

        $sql = "UPDATE CLIENT SET " . implode(', ', $fields) . "WHERE ID_CLIENT = :id";
        $stmt = $this->db->prepare($sql);
        $stmt -> execute($params);
        return $stmt->rowCount()>0;
    }

    #eliminar 
    public function delete(int $id): bool{
        $stmt = $this->db->prepare("
            DELETE FROM CLIENT WHERE ID_CLIENT = :id
        ");
        $stmt->execute([':id'=>$id]);
        return $stmt->rowCount()>0;
    }
}