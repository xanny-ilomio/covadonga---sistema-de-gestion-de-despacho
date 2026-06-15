<?php
class CityController{
    private PDO $db;
    public function __construct(){
        $this->db = Database::getConnection();
    }
    
    #lista todas las ciudades con su estado y ruta asociada
    #psl dorpdown al registrar cliente
    public function index(): void {
        AuthMiddleware::handle();
 
        $stmt = $this->db->prepare("
            SELECT
                CI.ID_CITY,
                CI.NAME_CITY,
                S.ID_STATE,
                S.NAME_STATE,
                R.ID_ROUTE,
                R.NAME_ROUTE
            FROM CITY CI
            JOIN STATE S ON CI.STATE_FK = S.ID_STATE
            LEFT JOIN ROUTE R ON S.ROUTE_FK = R.ID_ROUTE
            ORDER BY S.NAME_STATE ASC, CI.NAME_CITY ASC
        ");
        $stmt->execute();
 
        Response::success($stmt->fetchAll());
    }
}