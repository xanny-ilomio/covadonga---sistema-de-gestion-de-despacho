<?php
class Order {

    private PDO $db;
    public function __construct() {
        $this->db = Database::getConnection();
    }

    #muestra all pedidos con datos del cliente y ruta
    public function findAll(?string $status = null): array {
        $where = $status ? "WHERE O.STATUS = :status" : "";
        $stmt= $this->db->prepare("
            SELECT
                O.ID_ORDER, O.DATE_ORDERED,O.WEIGHT,
                O.WEIGHT_REAL,O.STATUS,C.ID_CLIENT,
                C.NAME_CLIENT,C.RIF,R.ID_ROUTE,R.NAME_ROUTE
            FROM ORDERS O
            JOIN CLIENT C ON O.CLIENT_FK = C.ID_CLIENT
            LEFT JOIN ROUTE R ON O.ROUTE_FK = R.ID_ROUTE
            {$where} ORDER BY O.DATE_ORDERED DESC
        ");
        if ($status) $stmt->execute([':status' => $status]);
        else $stmt->execute();
        return $stmt->fetchAll();
    }

    #pedido con todos los productos
    public function findById(int $id): ?array {
        $stmt = $this->db->prepare("
            SELECT
                O.ID_ORDER,O.DATE_ORDERED,O.WEIGHT,O.WEIGHT_REAL,
                O.STATUS,C.ID_CLIENT, C.NAME_CLIENT,C.RIF,
                C.PHONE_CLIENT,CI.NAME_CITY, S.NAME_STATE,
                R.ID_ROUTE,R.NAME_ROUTE
            FROM ORDERS O
            JOIN CLIENT C ON O.CLIENT_FK = C.ID_CLIENT
            JOIN CITY CI ON C.CITY_FK = CI.ID_CITY
            JOIN STATE S ON CI.STATE_FK = S.ID_STATE
            LEFT JOIN ROUTE R ON O.ROUTE_FK = R.ID_ROUTE
            WHERE O.ID_ORDER = :id LIMIT 1
        ");
        $stmt->execute([':id' => $id]);
        $order = $stmt->fetch();
        if (!$order) return null;

        #traer productos x separado
        $stmt2 = $this->db->prepare("
            SELECT
                OP.ID_OP,OP.AMOUNT,OP.WEIGHT_APROX,OP.WEIGHT_REAL,
                OP.PRICE_AT_PURCHASE,P.ID_PRODUCT,P.NAME_PRODUCT
            FROM ORDER_PRODUCT OP
            JOIN PRODUCT P ON OP.PRODUCT_FK = P.ID_PRODUCT
            WHERE OP.ORDER_FK = :order_id
        ");
        $stmt2->execute([':order_id' => $id]);
        $order['items'] = $stmt2->fetchAll();

        return $order;
    }

    #asignacion deruta a la q pertenece el cliente
    public function getRouteByClient(int $clientId): ?int {
        $stmt = $this->db->prepare("
            SELECT S.ROUTE_FK FROM CLIENT C
            JOIN CITY CI ON C.CITY_FK= CI.ID_CITY
            JOIN STATE S ON CI.STATE_FK = S.ID_STATE
            WHERE C.ID_CLIENT = :client_id
            LIMIT 1
        ");
        $stmt->execute([':client_id' => $clientId]);
        $row = $stmt->fetch();
        return $row ? ($row['ROUTE_FK'] ?? null) : null;
    }

    // Crea el pedido SIN ruta — queda Pendiente hasta que despacho actualice los pesos
    public function create(int $clientId, array $items): int {
        $this->db->beginTransaction();
        try {
            $totalWeight = 0;
            foreach ($items as $item) {
                $totalWeight += $item['amount'] * $item['unit_weight'];
            }
 
            // ROUTE_FK = NULL intencionalmente — se asigna cuando despacho actualiza pesos
            $stmt = $this->db->prepare("
                INSERT INTO ORDERS (CLIENT_FK, ROUTE_FK, WEIGHT, STATUS, DATE_ORDERED)
                VALUES (:client_id, NULL, :weight, 'Pendiente', CURDATE())
            ");
            $stmt->execute([
                ':client_id' => $clientId,
                ':weight'    => $totalWeight,
            ]);
            $orderId = (int) $this->db->lastInsertId();
 
            $stmtItem = $this->db->prepare("
                INSERT INTO ORDER_PRODUCT (ORDER_FK, PRODUCT_FK, AMOUNT, WEIGHT_APROX, WEIGHT_REAL, PRICE_AT_PURCHASE)
                VALUES (:order_id, :product_id, :amount, :weight_aprox, NULL, :price)
            ");
            foreach ($items as $item) {
                $stmtItem->execute([
                    ':order_id' => $orderId,
                    ':product_id'=> $item['product_id'],
                    ':amount'=> $item['amount'],
                    ':weight_aprox' => $item['amount'] * $item['unit_weight'],
                    ':price'=> $item['price_at_purchase'],
                ]);
            }
 
            $this->db->commit();
            return $orderId;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
 
    #actualizacion pesos reales y asignacion de ruta x ubicación del cliente
    public function updateWeights(int $orderId, int $clientId, array $items): bool {
        $this->db->beginTransaction();
        try {
            #ruta del cliente en este momento
            $routeId = $this->getRouteByClient($clientId);
            if (!$routeId) {
                throw new Exception('El estado del cliente no tiene una ruta asignada. Despacho debe configurarla primero');
            }
 
            $stmtItem = $this->db->prepare("
                UPDATE ORDER_PRODUCT SET WEIGHT_REAL = :weight_real
                WHERE ID_OP = :id_op AND ORDER_FK = :order_id
            ");
            $totalReal = 0;
            foreach ($items as $item) {
                $stmtItem->execute([
                    ':weight_real' => $item['weight_real'],
                    ':id_op'=> $item['id_op'],
                    ':order_id'=> $orderId,
                ]);
                $totalReal += $item['weight_real'];
            }
            #actualizacion pesos reales y asignacion de ruta x ubicación del cliente
            $stmtOrder = $this->db->prepare("
                UPDATE ORDERS
                SET WEIGHT_REAL= :weight_real,ROUTE_FK = :route_id, STATUS= 'Asignado'
                WHERE ID_ORDER = :id
            ");
            $stmtOrder->execute([
                ':weight_real' => $totalReal,
                ':route_id'=> $routeId,
                ':id'=>$orderId,
            ]);

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
 
    public function updateStatus(int $id, string $status): bool {
        $stmt = $this->db->prepare("UPDATE ORDERS SET STATUS = :status WHERE ID_ORDER = :id");
        $stmt->execute([':status' => $status, ':id' => $id]);
        return $stmt->rowCount() > 0;
    }
}