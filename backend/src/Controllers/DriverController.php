<?php

class DriverController {

    private Driver $driverModel;

    public function __construct() {
        $this->driverModel = new Driver();
    }

    // GET /drivers
    public function index(): void {
        AuthMiddleware::handle();
        Response::success($this->driverModel->findAll());
    }

    // GET /drivers/{id}
    public function show(int $id): void {
        AuthMiddleware::handle();
        $driver = $this->driverModel->findById($id);
        if (!$driver) Response::notFound("Conductor con ID {$id} no encontrado");
        Response::success($driver);
    }

    // POST /drivers
    // Body: { "name": "Juan", "lastname": "Pérez", "ci": 12345678, "phone": 4141234567 }
    public function store(): void {
        $authUser = AuthMiddleware::handle();
        if ($authUser['rol'] !== 'despacho') {
            Response::forbidden('Solo despacho puede registrar conductores');
        }

        $body     = json_decode(file_get_contents('php://input'), true);
        $name     = trim($body['name']     ?? '');
        $lastname = trim($body['lastname'] ?? '');
        $ci       = $body['ci']            ?? null;
        $phone    = $body['phone']         ?? null;

        if (empty($name))     Response::error('El nombre es requerido', 422);
        if (empty($lastname)) Response::error('El apellido es requerido', 422);
        if (!$ci)             Response::error('La cédula es requerida', 422);
        if (!$phone)          Response::error('El teléfono es requerido', 422);

        if ($this->driverModel->ciExists((int) $ci)) {
            Response::error('Ya existe un conductor con esa cédula', 409);
        }

        $newId = $this->driverModel->create([
            'name'     => $name,
            'lastname' => $lastname,
            'ci'       => $ci,
            'phone'    => $phone,
        ]);

        Response::success($this->driverModel->findById($newId), 'Conductor registrado correctamente', 201);
    }

    // PUT /drivers/{id}
    public function update(int $id): void {
        $authUser = AuthMiddleware::handle();
        if ($authUser['rol'] !== 'despacho') {
            Response::forbidden('Solo despacho puede modificar conductores');
        }

        if (!$this->driverModel->findById($id)) {
            Response::notFound("Conductor con ID {$id} no encontrado");
        }

        $body = json_decode(file_get_contents('php://input'), true);

        if (isset($body['ci']) && $this->driverModel->ciExists((int) $body['ci'], $id)) {
            Response::error('Ya existe un conductor con esa cédula', 409);
        }

        $updated = $this->driverModel->update($id, $body);
        if (!$updated) Response::error('No se realizaron cambios', 400);

        Response::success($this->driverModel->findById($id), 'Conductor actualizado correctamente');
    }

    // DELETE /drivers/{id}
    public function destroy(int $id): void {
        $authUser = AuthMiddleware::handle();
        if ($authUser['rol'] !== 'despacho') {
            Response::forbidden('Solo despacho puede eliminar conductores');
        }

        if (!$this->driverModel->findById($id)) {
            Response::notFound("Conductor con ID {$id} no encontrado");
        }

        if (!$this->driverModel->delete($id)) {
            Response::serverError('No se pudo eliminar el conductor');
        }

        Response::success(null, 'Conductor eliminado correctamente');
    }
}