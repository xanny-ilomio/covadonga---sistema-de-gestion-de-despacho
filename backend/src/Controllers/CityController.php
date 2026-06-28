<?php

class CityController {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    // GET /cities
    // GET /cities?search=Maracay
    public function index(): void {
        AuthMiddleware::handle();

        if (!empty($_GET['search'])) {
            $query = trim($_GET['search']);
            $stmt  = $this->db->prepare("
                SELECT
                    ci.ID_CITY, ci.NAME_CITY,
                    s.ID_STATE, s.NAME_STATE,
                    r.ID_ROUTE, r.NAME_ROUTE
                FROM CITY ci
                JOIN STATE s      ON ci.STATE_FK = s.ID_STATE
                LEFT JOIN ROUTE r ON s.ROUTE_FK  = r.ID_ROUTE
                WHERE ci.NAME_CITY LIKE :query
                ORDER BY ci.NAME_CITY ASC
                LIMIT 10
            ");
            $stmt->execute([':query' => '%' . $query . '%']);
            Response::success($stmt->fetchAll());
        }

        $stmt = $this->db->prepare("
            SELECT
                ci.ID_CITY, ci.NAME_CITY,
                s.ID_STATE, s.NAME_STATE,
                r.ID_ROUTE, r.NAME_ROUTE
            FROM CITY ci
            JOIN STATE s      ON ci.STATE_FK = s.ID_STATE
            LEFT JOIN ROUTE r ON s.ROUTE_FK  = r.ID_ROUTE
            ORDER BY s.NAME_STATE ASC, ci.NAME_CITY ASC
        ");
        $stmt->execute();
        Response::success($stmt->fetchAll());
    }

    // POST /cities
    // Body: { "name": "Maracay", "state_id": 1 }
    public function store(): void {
        AuthMiddleware::handle();

        $body     = json_decode(file_get_contents('php://input'), true);
        $name     = trim($body['name']     ?? '');
        $state_id = $body['state_id']      ?? null;

        if (empty($name))     Response::error('El nombre de la ciudad es requerido', 422);
        if (empty($state_id)) Response::error('El estado es requerido', 422);

        // Verificar que el estado existe
        $stmtCheck = $this->db->prepare("SELECT ID_STATE FROM STATE WHERE ID_STATE = :id LIMIT 1");
        $stmtCheck->execute([':id' => (int) $state_id]);
        if (!$stmtCheck->fetch()) Response::notFound('Estado no encontrado');

        // Verificar que no exista ya esa ciudad en ese estado
        $stmtDup = $this->db->prepare("
            SELECT ID_CITY FROM CITY
            WHERE NAME_CITY = :name AND STATE_FK = :state_id LIMIT 1
        ");
        $stmtDup->execute([':name' => $name, ':state_id' => (int) $state_id]);
        if ($stmtDup->fetch()) Response::error('Ya existe esa ciudad en ese estado', 409);

        $stmt = $this->db->prepare("INSERT INTO CITY (NAME_CITY, STATE_FK) VALUES (:name, :state_id)");
        $stmt->execute([':name' => $name, ':state_id' => (int) $state_id]);
        $newId = (int) $this->db->lastInsertId();

        $stmtGet = $this->db->prepare("
            SELECT ci.ID_CITY, ci.NAME_CITY, s.ID_STATE, s.NAME_STATE, r.ID_ROUTE, r.NAME_ROUTE
            FROM CITY ci
            JOIN STATE s      ON ci.STATE_FK = s.ID_STATE
            LEFT JOIN ROUTE r ON s.ROUTE_FK  = r.ID_ROUTE
            WHERE ci.ID_CITY = :id
        ");
        $stmtGet->execute([':id' => $newId]);
        Response::success($stmtGet->fetch(), 'Ciudad creada correctamente', 201);
    }
}