<?php

class StatsController {

    private PDO $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    // GET /stats
    // Retorna estadísticas según el rol del usuario autenticado
    public function index(): void {
        $authUser = AuthMiddleware::handle();

        if ($authUser['rol'] === 'facturacion') {
            Response::success($this->statsFacturacion());
        } else {
            Response::success($this->statsDespacho());
        }
    }

    // ─── Estadísticas para FACTURACIÓN ───────────────────────────────────────
    private function statsFacturacion(): array {

        // Total de pedidos por estado
        $stmtStatus = $this->db->prepare("
            SELECT STATUS, COUNT(*) AS total
            FROM ORDERS
            GROUP BY STATUS
        ");
        $stmtStatus->execute();
        $byStatus = $stmtStatus->fetchAll();

        // Total de pedidos hoy
        $stmtToday = $this->db->prepare("
            SELECT COUNT(*) AS total FROM ORDERS
            WHERE DATE_ORDERED = CURDATE()
        ");
        $stmtToday->execute();
        $today = (int) $stmtToday->fetch()['total'];

        // Total de pedidos esta semana
        $stmtWeek = $this->db->prepare("
            SELECT COUNT(*) AS total FROM ORDERS
            WHERE DATE_ORDERED >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        ");
        $stmtWeek->execute();
        $week = (int) $stmtWeek->fetch()['total'];

        // Total de clientes registrados
        $stmtClients = $this->db->prepare("SELECT COUNT(*) AS total FROM CLIENT");
        $stmtClients->execute();
        $totalClients = (int) $stmtClients->fetch()['total'];

        // Clientes nuevos este mes
        $stmtNewClients = $this->db->prepare("
            SELECT COUNT(*) AS total FROM CLIENT
            WHERE MONTH(created_at) = MONTH(CURDATE())
              AND YEAR(created_at)  = YEAR(CURDATE())
        ");
        // created_at puede no existir — usamos try/catch silencioso
        try {
            $stmtNewClients->execute();
            $newClients = (int) $stmtNewClients->fetch()['total'];
        } catch (Exception $e) {
            $newClients = null;
        }

        // Top 5 productos más pedidos
        $stmtTopProducts = $this->db->prepare("
            SELECT p.NAME_PRODUCT,
                   SUM(op.AMOUNT) AS total_amount
            FROM ORDER_PRODUCT op
            JOIN PRODUCT p ON op.PRODUCT_FK = p.ID_PRODUCT
            GROUP BY p.ID_PRODUCT, p.NAME_PRODUCT
            ORDER BY total_amount DESC
            LIMIT 5
        ");
        $stmtTopProducts->execute();
        $topProducts = $stmtTopProducts->fetchAll();

        // Top 5 clientes con más pedidos
        $stmtTopClients = $this->db->prepare("
            SELECT c.NAME_CLIENT, COUNT(o.ID_ORDER) AS total_orders
            FROM ORDERS o
            JOIN CLIENT c ON o.CLIENT_FK = c.ID_CLIENT
            GROUP BY c.ID_CLIENT, c.NAME_CLIENT
            ORDER BY total_orders DESC
            LIMIT 5
        ");
        $stmtTopClients->execute();
        $topClients = $stmtTopClients->fetchAll();

        // Pedidos por día de los últimos 7 días
        $stmtDailyOrders = $this->db->prepare("
            SELECT DATE_ORDERED AS date, COUNT(*) AS total
            FROM ORDERS
            WHERE DATE_ORDERED >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
            GROUP BY DATE_ORDERED
            ORDER BY DATE_ORDERED ASC
        ");
        $stmtDailyOrders->execute();
        $dailyOrders = $stmtDailyOrders->fetchAll();

        return [
            'orders' => [
                'today'     => $today,
                'this_week' => $week,
                'by_status' => $byStatus,
                'daily_last_7_days' => $dailyOrders,
            ],
            'clients' => [
                'total'     => $totalClients,
                'new_this_month' => $newClients,
                'top_5'     => $topClients,
            ],
            'products' => [
                'top_5_most_ordered' => $topProducts,
            ],
        ];
    }

    // ─── Estadísticas para DESPACHO ──────────────────────────────────────────
    private function statsDespacho(): array {

        // Pedidos pendientes de actualizar pesos
        $stmtPending = $this->db->prepare("
            SELECT COUNT(*) AS total FROM ORDERS WHERE STATUS = 'Pendiente'
        ");
        $stmtPending->execute();
        $pending = (int) $stmtPending->fetch()['total'];

        // Pedidos asignados listos para despachar
        $stmtAssigned = $this->db->prepare("
            SELECT COUNT(*) AS total FROM ORDERS WHERE STATUS = 'Asignado'
        ");
        $stmtAssigned->execute();
        $assigned = (int) $stmtAssigned->fetch()['total'];

        // Pedidos despachados hoy
        $stmtDispatchedToday = $this->db->prepare("
            SELECT COUNT(*) AS total FROM ORDERS
            WHERE STATUS = 'Despachado' AND DATE_ORDERED = CURDATE()
        ");
        $stmtDispatchedToday->execute();
        $dispatchedToday = (int) $stmtDispatchedToday->fetch()['total'];

        // Total guías emitidas
        $stmtGuides = $this->db->prepare("SELECT COUNT(*) AS total FROM GUIDE");
        $stmtGuides->execute();
        $totalGuides = (int) $stmtGuides->fetch()['total'];

        // Guías emitidas esta semana
        $stmtGuidesWeek = $this->db->prepare("
            SELECT COUNT(*) AS total FROM GUIDE
            WHERE EMISSION_DATE >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        ");
        $stmtGuidesWeek->execute();
        $guidesWeek = (int) $stmtGuidesWeek->fetch()['total'];

        // Peso total despachado esta semana
        $stmtWeight = $this->db->prepare("
            SELECT COALESCE(SUM(TOTAL_WEIGHT), 0) AS total
            FROM GUIDE
            WHERE EMISSION_DATE >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        ");
        $stmtWeight->execute();
        $weightWeek = (float) $stmtWeight->fetch()['total'];

        // Pedidos por ruta en estado Asignado
        $stmtByRoute = $this->db->prepare("
            SELECT r.NAME_ROUTE, COUNT(o.ID_ORDER) AS total_orders,
                   COALESCE(SUM(o.WEIGHT_REAL), 0) AS total_weight
            FROM ORDERS o
            JOIN ROUTE r ON o.ROUTE_FK = r.ID_ROUTE
            WHERE o.STATUS = 'Asignado'
            GROUP BY r.ID_ROUTE, r.NAME_ROUTE
            ORDER BY total_orders DESC
        ");
        $stmtByRoute->execute();
        $byRoute = $stmtByRoute->fetchAll();

        // Últimas 5 guías emitidas
        $stmtLastGuides = $this->db->prepare("
            SELECT g.ID_GUIDE, g.GUIDE_NUMBER, g.EMISSION_DATE,
                   g.TOTAL_WEIGHT, r.NAME_ROUTE,
                   CONCAT(d.NAME_DRIVER, ' ', d.LASTNAME) AS driver_name,
                   t.PLATE
            FROM GUIDE g
            JOIN ROUTE r  ON g.ROUTE_FK  = r.ID_ROUTE
            JOIN DRIVER d ON g.DRIVER_FK = d.ID_DRIVER
            JOIN TRUCK t  ON g.TRUCK_FK  = t.ID_TRUCK
            ORDER BY g.EMISSION_DATE DESC
            LIMIT 5
        ");
        $stmtLastGuides->execute();
        $lastGuides = $stmtLastGuides->fetchAll();

        // Camiones y conductores disponibles
        $stmtTrucks = $this->db->prepare("SELECT COUNT(*) AS total FROM TRUCK");
        $stmtTrucks->execute();
        $totalTrucks = (int) $stmtTrucks->fetch()['total'];

        $stmtDrivers = $this->db->prepare("SELECT COUNT(*) AS total FROM DRIVER");
        $stmtDrivers->execute();
        $totalDrivers = (int) $stmtDrivers->fetch()['total'];

        return [
            'orders' => [
                'pending'          => $pending,
                'assigned'         => $assigned,
                'dispatched_today' => $dispatchedToday,
                'assigned_by_route'=> $byRoute,
            ],
            'guides' => [
                'total'      => $totalGuides,
                'this_week'  => $guidesWeek,
                'last_5'     => $lastGuides,
            ],
            'weight' => [
                'dispatched_this_week_kg' => $weightWeek,
            ],
            'fleet' => [
                'total_trucks'  => $totalTrucks,
                'total_drivers' => $totalDrivers,
            ],
        ];
    }
}