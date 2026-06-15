<?php

class Guide {

    private PDO $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    // Lista todas las guías generadas
    public function findAll(): array {
        $stmt = $this->db->prepare("
            SELECT
                g.ID_GUIDE,
                g.GUIDE_NUMBER,
                g.EMISSION_DATE,
                g.TOTAL_WEIGHT,
                r.ID_ROUTE,
                r.NAME_ROUTE,
                CONCAT(d.NAME_DRIVER, ' ', d.LASTNAME) AS driver_name,
                d.CI                                   AS driver_ci,
                t.PLATE,
                t.BRAND,
                t.CAPACITY,
                COUNT(go.ORDER_FK)                     AS total_orders
            FROM GUIDE g
            JOIN ROUTE r       ON g.ROUTE_FK  = r.ID_ROUTE
            JOIN DRIVER d      ON g.DRIVER_FK = d.ID_DRIVER
            JOIN TRUCK t       ON g.TRUCK_FK  = t.ID_TRUCK
            LEFT JOIN GUIDE_ORDER go ON go.GUIDE_FK = g.ID_GUIDE
            GROUP BY g.ID_GUIDE
            ORDER BY g.EMISSION_DATE DESC
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    }

    // Trae una guía completa con todos sus pedidos y productos
    public function findById(int $id): ?array {
        $stmt = $this->db->prepare("
            SELECT
                g.ID_GUIDE,
                g.GUIDE_NUMBER,
                g.EMISSION_DATE,
                g.TOTAL_WEIGHT,
                r.ID_ROUTE,
                r.NAME_ROUTE,
                CONCAT(d.NAME_DRIVER, ' ', d.LASTNAME) AS driver_name,
                d.CI                                   AS driver_ci,
                d.PHONE_DRIVER                         AS driver_phone,
                t.ID_TRUCK,
                t.PLATE,
                t.BRAND,
                t.CAPACITY
            FROM GUIDE g
            JOIN ROUTE r  ON g.ROUTE_FK  = r.ID_ROUTE
            JOIN DRIVER d ON g.DRIVER_FK = d.ID_DRIVER
            JOIN TRUCK t  ON g.TRUCK_FK  = t.ID_TRUCK
            WHERE g.ID_GUIDE = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $id]);
        $guide = $stmt->fetch();
        if (!$guide) return null;

        // Traer los pedidos incluidos en la guía con sus productos
        $stmtOrders = $this->db->prepare("
            SELECT
                o.ID_ORDER,
                o.DATE_ORDERED,
                o.WEIGHT_REAL,
                c.NAME_CLIENT,
                c.RIF,
                c.PHONE_CLIENT,
                ci.NAME_CITY,
                s.NAME_STATE
            FROM GUIDE_ORDER go
            JOIN ORDERS o  ON go.ORDER_FK   = o.ID_ORDER
            JOIN CLIENT c  ON o.CLIENT_FK   = c.ID_CLIENT
            JOIN CITY ci   ON c.CITY_FK     = ci.ID_CITY
            JOIN STATE s   ON ci.STATE_FK   = s.ID_STATE
            WHERE go.GUIDE_FK = :guide_id
            ORDER BY c.NAME_CLIENT ASC
        ");
        $stmtOrders->execute([':guide_id' => $id]);
        $orders = $stmtOrders->fetchAll();

        // Para cada pedido, traer sus productos
        $stmtItems = $this->db->prepare("
            SELECT
                p.NAME_PRODUCT,
                op.AMOUNT,
                op.WEIGHT_REAL,
                op.PRICE_AT_PURCHASE
            FROM ORDER_PRODUCT op
            JOIN PRODUCT p ON op.PRODUCT_FK = p.ID_PRODUCT
            WHERE op.ORDER_FK = :order_id
        ");

        foreach ($orders as &$order) {
            $stmtItems->execute([':order_id' => $order['ID_ORDER']]);
            $order['items'] = $stmtItems->fetchAll();
        }

        $guide['orders'] = $orders;
        return $guide;
    }

    // Genera el número correlativo de guía: GUIA-0001, GUIA-0002, etc.
    private function generateGuideNumber(): string {
        $stmt = $this->db->prepare("SELECT COUNT(*) AS total FROM GUIDE");
        $stmt->execute();
        $count = (int) $stmt->fetch()['total'];
        return 'GUIA-' . str_pad($count + 1, 4, '0', STR_PAD_LEFT);
    }

    // Verifica que la ruta tenga pedidos en estado Asignado para despachar
    public function getAssignedOrdersByRoute(int $routeId): array {
        $stmt = $this->db->prepare("
            SELECT ID_ORDER, WEIGHT_REAL
            FROM ORDERS
            WHERE ROUTE_FK = :route_id AND STATUS = 'Asignado'
        ");
        $stmt->execute([':route_id' => $routeId]);
        return $stmt->fetchAll();
    }

    // Crea la guía, vincula los pedidos y los marca como Despachado
    // Todo en una transacción
    public function create(int $routeId, int $driverId, int $truckId): int {
        $this->db->beginTransaction();
        try {
            #pedidod asignados
            $orders = $this->getAssignedOrdersByRoute($routeId);
            if (empty($orders)) {
                throw new Exception('No hay pedidos asignados a esta ruta para despachar');
            }
           #suma de peso all pedidos
            $totalWeight = array_sum(array_column($orders, 'WEIGHT_REAL'));
            $guideNumber = $this->generateGuideNumber();

            #guia como tal
            $stmt = $this->db->prepare("
                INSERT INTO GUIDE (GUIDE_NUMBER, EMISSION_DATE, TOTAL_WEIGHT, ROUTE_FK, DRIVER_FK, TRUCK_FK)
                VALUES (:guide_number, CURDATE(), :total_weight, :route_id, :driver_id, :truck_id)
            ");
            $stmt->execute([
                ':guide_number'=> $guideNumber,
                ':total_weight' => $totalWeight,
                ':route_id' => $routeId,
                ':driver_id'=> $driverId,
                ':truck_id'=> $truckId,
            ]);
            $guideId = (int) $this->db->lastInsertId();
            #link pedido a guia y estado de despachado
            $stmtLink = $this->db->prepare("
                INSERT INTO GUIDE_ORDER (GUIDE_FK, ORDER_FK) VALUES (:guide_id, :order_id)
            ");
            $stmtStatus = $this->db->prepare("
                UPDATE ORDERS SET STATUS = 'Despachado' WHERE ID_ORDER = :order_id
            ");

            foreach ($orders as $order) {
                $stmtLink->execute([':guide_id' => $guideId, ':order_id' => $order['ID_ORDER']]);
                $stmtStatus->execute([':order_id' => $order['ID_ORDER']]);
            }
            $this->db->commit();
            return $guideId;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
}