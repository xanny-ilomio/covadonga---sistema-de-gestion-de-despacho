<?php

class StatsController {

    private PDO $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    // GET /stats
    // Retorna estadísticas según el rol del usuario autenticado y el período recibido
    public function index(): void {
        $authUser = AuthMiddleware::handle();

        // 1. Obtener y validar el período desde el query string (por defecto 30 días)
        $period = isset($_GET['period']) ? (int)$_GET['period'] : 30;
        
        // Evitamos valores extraños o negativos
        if ($period <= 0) {
            $period = 30;
        }

        if ($authUser['rol'] === 'facturacion') {
            Response::success($this->statsFacturacion($period));
        } else {
            Response::success($this->statsDespacho($period));
        }
    }

    // ─── Estadísticas para FACTURACIÓN ───────────────────────────────────────
    private function statsFacturacion(int $period): array {

        // 1. Pedidos por estado creados dentro del período seleccionado
        $stmtStatus = $this->db->prepare("
            SELECT STATUS, COUNT(*) AS total
            FROM ORDERS
            WHERE STATUS != 'Cancelado'
              AND DATE_ORDERED >= DATE_SUB(CURDATE(), INTERVAL :period DAY)
            GROUP BY STATUS
        ");
        $stmtStatus->bindParam(':period', $period, PDO::PARAM_INT);
        $stmtStatus->execute();
        $byStatus = $stmtStatus->fetchAll();

        // 2. Total de pedidos registrados en el período (KPI 1)
        $stmtTotalPeriod = $this->db->prepare("
            SELECT COUNT(*) AS total FROM ORDERS
            WHERE DATE_ORDERED >= DATE_SUB(CURDATE(), INTERVAL :period DAY)
        ");
        $stmtTotalPeriod->bindParam(':period', $period, PDO::PARAM_INT);
        $stmtTotalPeriod->execute();
        $totalOrdersPeriod = (int) $stmtTotalPeriod->fetch()['total'];

        // 3. Clientes que compraron / activos en el período (KPI 2)
        $stmtClientsActive = $this->db->prepare("
            SELECT COUNT(DISTINCT CLIENT_FK) AS total FROM ORDERS
            WHERE DATE_ORDERED >= DATE_SUB(CURDATE(), INTERVAL :period DAY)
        ");
        $stmtClientsActive->bindParam(':period', $period, PDO::PARAM_INT);
        $stmtClientsActive->execute();
        $activeClients = (int) $stmtClientsActive->fetch()['total'];

        // 4. Peso real total vendido/facturado en el período (KPI 3)
        $stmtWeightPeriod = $this->db->prepare("
            SELECT COALESCE(SUM(WEIGHT_REAL), 0) AS total_weight 
            FROM ORDERS
            WHERE DATE_ORDERED >= DATE_SUB(CURDATE(), INTERVAL :period DAY)
        ");
        $stmtWeightPeriod->bindParam(':period', $period, PDO::PARAM_INT);
        $stmtWeightPeriod->execute();
        $totalWeightPeriod = (float) $stmtWeightPeriod->fetch()['total_weight'];

        // 5. Carga Promedio por Pedido (KPI 4)
        $avgWeightPerOrder = $totalOrdersPeriod > 0 ? ($totalWeightPeriod / $totalOrdersPeriod) : 0;

        // Top 5 productos más pedidos en este período específico
        $stmtTopProducts = $this->db->prepare("
            SELECT p.NAME_PRODUCT,
                   SUM(op.AMOUNT) AS total_amount
            FROM ORDER_PRODUCT op
            JOIN PRODUCT p ON op.PRODUCT_FK = p.ID_PRODUCT
            JOIN ORDERS o  ON op.ORDER_FK = o.ID_ORDER
            WHERE o.DATE_ORDERED >= DATE_SUB(CURDATE(), INTERVAL :period DAY)
            GROUP BY p.ID_PRODUCT, p.NAME_PRODUCT
            ORDER BY total_amount DESC
            LIMIT 5
        ");
        $stmtTopProducts->bindParam(':period', $period, PDO::PARAM_INT);
        $stmtTopProducts->execute();
        $topProducts = $stmtTopProducts->fetchAll();

        // Top 5 clientes con más pedidos en este período
        $stmtTopClients = $this->db->prepare("
            SELECT c.NAME_CLIENT, COUNT(o.ID_ORDER) AS total_orders
            FROM ORDERS o
            JOIN CLIENT c ON o.CLIENT_FK = c.ID_CLIENT
            WHERE o.DATE_ORDERED >= DATE_SUB(CURDATE(), INTERVAL :period DAY)
            GROUP BY c.ID_CLIENT, c.NAME_CLIENT
            ORDER BY total_orders DESC
            LIMIT 5
        ");
        $stmtTopClients->bindParam(':period', $period, PDO::PARAM_INT);
        $stmtTopClients->execute();
        $topClients = $stmtTopClients->fetchAll();

        // Pedidos por día del período seleccionado
        $stmtDailyOrders = $this->db->prepare("
            SELECT DATE_ORDERED AS date, COUNT(*) AS total
            FROM ORDERS
            WHERE DATE_ORDERED >= DATE_SUB(CURDATE(), INTERVAL :period DAY)
            GROUP BY DATE_ORDERED
            ORDER BY DATE_ORDERED ASC
        ");
        $stmtDailyOrders->bindParam(':period', $period, PDO::PARAM_INT);
        $stmtDailyOrders->execute();
        $dailyOrders = $stmtDailyOrders->fetchAll();

        return [
            'orders' => [
                'total_period'       => $totalOrdersPeriod,
                'by_status'          => $byStatus,
                'daily_history'      => $dailyOrders,
            ],
            'clients' => [
                'active_this_period' => $activeClients,
                'top_5'              => $topClients,
            ],
            'products' => [
                'top_5_most_ordered' => $topProducts,
            ],
            'metrics' => [
                'total_weight_kg'    => $totalWeightPeriod,
                'avg_weight_per_order'=> $avgWeightPerOrder,
            ]
        ];
    }
    
    // ─── Estadísticas para DESPACHO ──────────────────────────────────────────
    private function statsDespacho(int $period): array {

        // Pedidos creados en el período que siguen 'Pendiente' de pesaje
        $stmtPending = $this->db->prepare("
            SELECT COUNT(*) AS total FROM ORDERS 
            WHERE STATUS = 'Pendiente' 
              AND DATE_ORDERED >= DATE_SUB(CURDATE(), INTERVAL :period DAY)
        ");
        $stmtPending->bindParam(':period', $period, PDO::PARAM_INT);
        $stmtPending->execute();
        $pending = (int) $stmtPending->fetch()['total'];

        // Pedidos creados en el período que están 'Asignado' (Listos para despachar)
        $stmtAssigned = $this->db->prepare("
            SELECT COUNT(*) AS total FROM ORDERS 
            WHERE STATUS = 'Asignado' 
              AND DATE_ORDERED >= DATE_SUB(CURDATE(), INTERVAL :period DAY)
        ");
        $stmtAssigned->bindParam(':period', $period, PDO::PARAM_INT);
        $stmtAssigned->execute();
        $assigned = (int) $stmtAssigned->fetch()['total'];

        // Pedidos con estado 'Despachado' dentro del período seleccionado
        $stmtDispatchedPeriod = $this->db->prepare("
            SELECT COUNT(*) AS total FROM ORDERS
            WHERE STATUS = 'Despachado' 
              AND DATE_ORDERED >= DATE_SUB(CURDATE(), INTERVAL :period DAY)
        ");
        $stmtDispatchedPeriod->bindParam(':period', $period, PDO::PARAM_INT);
        $stmtDispatchedPeriod->execute();
        $dispatchedPeriod = (int) $stmtDispatchedPeriod->fetch()['total'];

        // Total guías emitidas históricamente
        $stmtGuides = $this->db->prepare("SELECT COUNT(*) AS total FROM GUIDE");
        $stmtGuides->execute();
        $totalGuides = (int) $stmtGuides->fetch()['total'];

        // Guías emitidas en el período seleccionado
        $stmtGuidesPeriod = $this->db->prepare("
            SELECT COUNT(*) AS total FROM GUIDE
            WHERE EMISSION_DATE >= DATE_SUB(CURDATE(), INTERVAL :period DAY)
        ");
        $stmtGuidesPeriod->bindParam(':period', $period, PDO::PARAM_INT);
        $stmtGuidesPeriod->execute();
        $guidesPeriod = (int) $stmtGuidesPeriod->fetch()['total'];

        // Peso total despachado en el período seleccionado
        $stmtWeight = $this->db->prepare("
            SELECT COALESCE(SUM(TOTAL_WEIGHT), 0) AS total
            FROM GUIDE
            WHERE EMISSION_DATE >= DATE_SUB(CURDATE(), INTERVAL :period DAY)
        ");
        $stmtWeight->bindParam(':period', $period, PDO::PARAM_INT);
        $stmtWeight->execute();
        $weightPeriod = (float) $stmtWeight->fetch()['total'];

        // Pedidos por ruta en estado Asignado (filtrado al período de creación del pedido)
        $stmtByRoute = $this->db->prepare("
            SELECT r.NAME_ROUTE AS route_name, 
                   COUNT(o.ID_ORDER) AS total_orders,
                   COALESCE(SUM(o.WEIGHT_REAL), 0) AS total_weight
            FROM ORDERS o
            JOIN ROUTE r ON o.ROUTE_FK = r.ID_ROUTE
            WHERE o.DATE_ORDERED >= DATE_SUB(CURDATE(), INTERVAL :period DAY)
            GROUP BY r.ID_ROUTE, r.NAME_ROUTE
            ORDER BY total_orders DESC
        ");
        $stmtByRoute->bindParam(':period', $period, PDO::PARAM_INT);
        $stmtByRoute->execute();
        $byRoute = $stmtByRoute->fetchAll();

        // Rendimiento de Conductores en el Período
        $stmtDriversPerf = $this->db->prepare("
            SELECT CONCAT(d.NAME_DRIVER, ' ', d.LASTNAME) AS driver_name,
                   COALESCE(SUM(g.TOTAL_WEIGHT), 0) AS total_weight
            FROM DRIVER d
            LEFT JOIN GUIDE g ON g.DRIVER_FK = d.ID_DRIVER AND g.EMISSION_DATE >= DATE_SUB(CURDATE(), INTERVAL :period DAY)
            GROUP BY d.ID_DRIVER, d.NAME_DRIVER, d.LASTNAME
            ORDER BY total_weight DESC
        ");
        $stmtDriversPerf->bindParam(':period', $period, PDO::PARAM_INT);
        $stmtDriversPerf->execute();
        $driversPerformance = $stmtDriversPerf->fetchAll();

        // Uso / Frecuencia de Camiones en el Período
        $stmtTrucksUsage = $this->db->prepare("
            SELECT t.PLATE, COUNT(g.ID_GUIDE) AS trips_count
            FROM TRUCK t
            LEFT JOIN GUIDE g ON g.TRUCK_FK = t.ID_TRUCK AND g.EMISSION_DATE >= DATE_SUB(CURDATE(), INTERVAL :period DAY)
            GROUP BY t.ID_TRUCK, t.PLATE
            ORDER BY trips_count DESC
        ");
        $stmtTrucksUsage->bindParam(':period', $period, PDO::PARAM_INT);
        $stmtTrucksUsage->execute();
        $trucksUsage = $stmtTrucksUsage->fetchAll();

        // Camiones y conductores totales disponibles
        $stmtTrucks = $this->db->prepare("SELECT COUNT(*) AS total FROM TRUCK");
        $stmtTrucks->execute();
        $totalTrucks = (int) $stmtTrucks->fetch()['total'];

        $stmtDrivers = $this->db->prepare("SELECT COUNT(*) AS total FROM DRIVER");
        $stmtDrivers->execute();
        $totalDrivers = (int) $stmtDrivers->fetch()['total'];

        return [
            'orders' => [
                'pending'            => $pending,
                'assigned'           => $assigned,
                'dispatched_period'  => $dispatchedPeriod,
                'assigned_by_route'  => $byRoute,
            ],
            'guides' => [
                'total'        => $totalGuides,
                'this_period'  => $guidesPeriod,
            ],
            'weight' => [
                'dispatched_period_kg' => $weightPeriod,
            ],
            'fleet' => [
                'total_trucks'  => $totalTrucks,
                'total_drivers' => $totalDrivers,
            ],
            'drivers' => [
                'by_performance' => $driversPerformance,
            ],
            'trucks' => [
                'by_usage' => $trucksUsage,
            ],
        ];
    }
}