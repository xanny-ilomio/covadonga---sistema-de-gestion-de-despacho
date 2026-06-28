<?php

class TruckController {

    private Truck $truckModel;

    public function __construct() {
        $this->truckModel = new Truck();
    }

    // GET /trucks
    public function index(): void {
        AuthMiddleware::handle();
        Response::success($this->truckModel->findAll());
    }

    // GET /trucks/{id}
    public function show(int $id): void {
        AuthMiddleware::handle();
        $truck = $this->truckModel->findById($id);
        if (!$truck) Response::notFound("Camión con ID {$id} no encontrado");
        Response::success($truck);
    }

    // POST /trucks
    // Body: { "brand": "Ford", "plate": "ABC123", "capacity": 5000 }
    public function store(): void {
        $authUser = AuthMiddleware::handle();
        if ($authUser['rol'] !== 'despacho') {
            Response::forbidden('Solo despacho puede registrar camiones');
        }

        $body     = json_decode(file_get_contents('php://input'), true);
        $brand    = trim($body['brand']    ?? '');
        $plate    = trim($body['plate']    ?? '');
        $capacity = $body['capacity']      ?? null;

        if (empty($brand))    Response::error('La marca es requerida', 422);
        if (empty($plate))    Response::error('La placa es requerida', 422);
        if (!$capacity || $capacity <= 0) Response::error('La capacidad debe ser mayor a 0', 422);

        if ($this->truckModel->plateExists($plate)) {
            Response::error('Ya existe un camión con esa placa', 409);
        }

        $newId = $this->truckModel->create([
            'brand'    => $brand,
            'plate'    => $plate,
            'capacity' => $capacity,
        ]);

        Response::success($this->truckModel->findById($newId), 'Camión registrado correctamente', 201);
    }

    // PUT /trucks/{id}
    public function update(int $id): void {
        $authUser = AuthMiddleware::handle();
        if ($authUser['rol'] !== 'despacho') {
            Response::forbidden('Solo despacho puede modificar camiones');
        }

        if (!$this->truckModel->findById($id)) {
            Response::notFound("Camión con ID {$id} no encontrado");
        }

        $body = json_decode(file_get_contents('php://input'), true);

        if (!empty($body['plate']) && $this->truckModel->plateExists($body['plate'], $id)) {
            Response::error('Ya existe un camión con esa placa', 409);
        }
        if (isset($body['capacity']) && $body['capacity'] <= 0) {
            Response::error('La capacidad debe ser mayor a 0', 422);
        }

        $updated = $this->truckModel->update($id, $body);
        if (!$updated) Response::error('No se realizaron cambios', 400);

        Response::success($this->truckModel->findById($id), 'Camión actualizado correctamente');
    }

    // DELETE /trucks/{id}
    public function destroy(int $id): void {
        $authUser = AuthMiddleware::handle();
        if ($authUser['rol'] !== 'despacho') {
            Response::forbidden('Solo despacho puede eliminar camiones');
        }

        if (!$this->truckModel->findById($id)) {
            Response::notFound("Camión con ID {$id} no encontrado");
        }

        if (!$this->truckModel->delete($id)) {
            Response::serverError('No se pudo eliminar el camión');
        }

        Response::success(null, 'Camión eliminado correctamente');
    }
}