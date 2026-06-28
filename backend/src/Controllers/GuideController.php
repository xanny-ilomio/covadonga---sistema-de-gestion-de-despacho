<?php

class GuideController {

    private Guide $guideModel;

    public function __construct() {
        $this->guideModel = new Guide();
    }

    // GET /guides
    public function index(): void {
        AuthMiddleware::handle();
        Response::success($this->guideModel->findAll());
    }

    // GET /guides/{id}
    public function show(int $id): void {
        AuthMiddleware::handle();
        $guide = $this->guideModel->findById($id);
        if (!$guide) Response::notFound("Guía con ID {$id} no encontrada");
        Response::success($guide);
    }

    // POST /guides
    // Despacho genera la guía para una ruta
    // Body: { "route_id": 1, "driver_id": 2, "truck_id": 1 }
    // El sistema toma automáticamente todos los pedidos 'Asignado' de esa ruta
    public function store(): void {
        $authUser = AuthMiddleware::handle();
        if ($authUser['rol'] !== 'despacho') {
            Response::forbidden('Solo despacho puede generar guías de despacho');
        }

        $body     = json_decode(file_get_contents('php://input'), true);
        $routeId  = $body['route_id']  ?? null;
        $driverId = $body['driver_id'] ?? null;
        $truckId  = $body['truck_id']  ?? null;

        if (!$routeId)  Response::error('route_id es requerido', 422);
        if (!$driverId) Response::error('driver_id es requerido', 422);
        if (!$truckId)  Response::error('truck_id es requerido', 422);

        // Verificar que hay pedidos asignados en la ruta
        $orders = $this->guideModel->getAssignedOrdersByRoute((int) $routeId);
        if (empty($orders)) {
            Response::error('No hay pedidos en estado Asignado para esta ruta', 422);
        }

        try {
            $guideId = $this->guideModel->create((int) $routeId, (int) $driverId, (int) $truckId);
            $guide   = $this->guideModel->findById($guideId);
            Response::success($guide, 'Guía de despacho generada correctamente', 201);
        } catch (Exception $e) {
            Response::serverError('Error al generar la guía: ' . $e->getMessage());
        }
    }
}