<?php
class RouteController {
    private PDO $db;
    public function __construct() {
        $this->db = Database::getConnection();
    }

    #GET /routes
    public function index(): void {
        AuthMiddleware::handle();
        $stmt = $this->db->prepare("
            SELECT
                R.ID_ROUTE,R.NAME_ROUTE,
                COUNT(DISTINCT S.ID_STATE)  AS total_states,
                COUNT(DISTINCT O.ID_ORDER)  AS total_orders_assigned
            FROM ROUTE R
            LEFT JOIN STATE S ON S.ROUTE_FK= R.ID_ROUTE
            LEFT JOIN ORDERS O ON O.ROUTE_FK = R.ID_ROUTE AND O.STATUS = 'Asignado'
            GROUP BY R.ID_ROUTE, R.NAME_ROUTE
            ORDER BY R.NAME_ROUTE ASC
        ");
        $stmt->execute();
        Response::success($stmt->fetchAll());
    }

    #GET /routes/{id}
    #ruta con sus estados ciudades y pedidos asignados
    public function show(int $id): void {
        AuthMiddleware::handle();

        $stmt = $this->db->prepare("SELECT ID_ROUTE, NAME_ROUTE FROM ROUTE WHERE ID_ROUTE = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $route = $stmt->fetch();
        if (!$route) Response::notFound("Ruta con ID {$id} no encontrada");

        #estados que pertenecen a esta ruta con sus ciudades
        $stmtStates = $this->db->prepare("
            SELECT s.ID_STATE, s.NAME_STATE,
            GROUP_CONCAT(ci.NAME_CITY ORDER BY ci.NAME_CITY SEPARATOR ', ') AS cities
            FROM STATE s
            LEFT JOIN CITY ci ON ci.STATE_FK = s.ID_STATE
            WHERE s.ROUTE_FK = :route_id
            GROUP BY s.ID_STATE, s.NAME_STATE
            ORDER BY s.NAME_STATE ASC
        ");
        $stmtStates->execute([':route_id' => $id]);
        $route['states'] = $stmtStates->fetchAll();
        
        #pedidos asignados pa despachar
        $stmtOrders = $this->db->prepare("
            SELECT o.ID_ORDER, o.DATE_ORDERED, o.WEIGHT_REAL, o.STATUS,
                   c.NAME_CLIENT, c.RIF
            FROM ORDERS o
            JOIN CLIENT c ON o.CLIENT_FK = c.ID_CLIENT
            WHERE o.ROUTE_FK = :route_id AND o.STATUS = 'Asignado'
            ORDER BY o.DATE_ORDERED ASC
        ");
    
        $stmtOrders->execute([':route_id' => $id]);
        $route['pending_orders'] = $stmtOrders->fetchAll();

        Response::success($route);
    }

    #POST /routes
    #creacion de rutas
    public function store(): void {
        $authUser = AuthMiddleware::handle();
        if ($authUser['rol'] !== 'despacho') {
            Response::forbidden('Solo despacho puede crear rutas');
        }

        $body = json_decode(file_get_contents('php://input'), true);
        $name = trim($body['name'] ?? '');
        if (empty($name)) Response::error('El nombre de la ruta es requerido', 422);

        $stmt = $this->db->prepare("INSERT INTO ROUTE (NAME_ROUTE) VALUES (:name)");
        $stmt->execute([':name' => $name]);
        $newId = (int) $this->db->lastInsertId();

        $stmt2 = $this->db->prepare("SELECT * FROM ROUTE WHERE ID_ROUTE = :id");
        $stmt2->execute([':id' => $newId]);
        Response::success($stmt2->fetch(), 'Ruta creada correctamente', 201);
    }

    #PUT /routes/{id}
    #act ruta
    public function update(int $id): void {
        $authUser = AuthMiddleware::handle();
        if ($authUser['rol'] !== 'despacho') {
            Response::forbidden('Solo despacho puede modificar rutas');
        }

        $stmt = $this->db->prepare("SELECT * FROM ROUTE WHERE ID_ROUTE = :id");
        $stmt->execute([':id' => $id]);
        if (!$stmt->fetch()) Response::notFound("Ruta con ID {$id} no encontrada");

        $body = json_decode(file_get_contents('php://input'), true);
        $name = trim($body['name'] ?? '');
        if (empty($name)) Response::error('El nombre de la ruta es requerido', 422);

        $stmt2 = $this->db->prepare("UPDATE ROUTE SET NAME_ROUTE = :name WHERE ID_ROUTE = :id");
        $stmt2->execute([':name' => $name, ':id' => $id]);

        $stmt3 = $this->db->prepare("SELECT * FROM ROUTE WHERE ID_ROUTE = :id");
        $stmt3->execute([':id' => $id]);
        Response::success($stmt3->fetch(), 'Ruta actualizada correctamente');
    }

    #PUT /routes/{id}/assign-state
    #despacho asigna un estado completo a una ruta
    #todas las ciudades de ese estado quedan en la ruta
    public function assignState(int $id): void {
        $authUser = AuthMiddleware::handle();
        if ($authUser['rol'] !== 'despacho') {
            Response::forbidden('Solo despacho puede asignar estados a rutas');
        }

        $body= json_decode(file_get_contents('php://input'), true);
        $stateId = $body['state_id'] ?? null;
        if (!$stateId) Response::error('state_id es requerido', 422);

        #ruta existe? 
        $stmt = $this->db->prepare("SELECT ID_ROUTE FROM ROUTE WHERE ID_ROUTE = :id");
        $stmt->execute([':id'=> $id]);
        if (!$stmt->fetch()) Response::notFound("Ruta con ID {$id} no encontrada");

        #asignar el estado a la ruta
        $stmt2 = $this->db->prepare("UPDATE STATE SET ROUTE_FK = :route_id WHERE ID_STATE = :state_id");
        $stmt2->execute([':route_id' => $id,':state_id' =>$stateId]);

        if ($stmt2->rowCount() === 0) Response::notFound('Estado no encontrado');
        Response::success(null, 'Estado asignado a la ruta correctamente');
    }

    #GET /states — lista todos los estados con su ruta asignada
    #El frontend lo usa para el dropdown al configurar rutas
    public function states(): void {
        AuthMiddleware::handle();

        $stmt = $this->db->prepare("
            SELECT s.ID_STATE, s.NAME_STATE,
                   r.ID_ROUTE, r.NAME_ROUTE
            FROM STATE s
            LEFT JOIN ROUTE r ON s.ROUTE_FK = r.ID_ROUTE
            ORDER BY s.NAME_STATE ASC
        ");
        $stmt->execute();
        Response::success($stmt->fetchAll());
    }
}