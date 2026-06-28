<?php

class ProductController {

    private Product $productModel;

    public function __construct() {
        $this->productModel = new Product();
    }

    public function index(): void {
        AuthMiddleware::handle();

        if (!empty($_GET['search'])) {
            Response::success($this->productModel->search(trim($_GET['search'])));
        }

        Response::success($this->productModel->findAll());
    }

    public function show(int $id): void {
        AuthMiddleware::handle();

        $product = $this->productModel->findById($id);
        if (!$product) Response::notFound("Producto con ID {$id} no encontrado");

        Response::success($product);
    }

    public function store(): void {
        $authUser = AuthMiddleware::handle();

        if ($authUser['rol'] !== 'facturacion') {
            Response::forbidden('Solo facturación puede registrar productos');
        }

        $body   = json_decode(file_get_contents('php://input'), true);
        $name   = trim($body['name']   ?? '');
        $weight = $body['weight']      ?? null;
        $price  = $body['price']       ?? null;

        if (empty($name))         Response::error('El nombre del producto es requerido', 422);
        if ($weight === null)     Response::error('El peso aproximado es requerido', 422);
        if ($price === null)      Response::error('El precio es requerido', 422);
        if ($weight <= 0)         Response::error('El peso debe ser mayor a 0', 422);
        if ($price < 0)           Response::error('El precio no puede ser negativo', 422);

        $newId   = $this->productModel->create(['name' => $name, 'weight' => $weight, 'price' => $price]);
        $product = $this->productModel->findById($newId);

        Response::success($product, 'Producto creado correctamente', 201);
    }

    public function update(int $id): void {
        $authUser = AuthMiddleware::handle();

        if ($authUser['rol'] !== 'facturacion') {
            Response::forbidden('Solo facturación puede modificar productos');
        }

        if (!$this->productModel->findById($id)) {
            Response::notFound("Producto con ID {$id} no encontrado");
        }

        $body = json_decode(file_get_contents('php://input'), true);

        if (isset($body['weight']) && $body['weight'] <= 0) {
            Response::error('El peso debe ser mayor a 0', 422);
        }
        if (isset($body['price']) && $body['price'] < 0) {
            Response::error('El precio no puede ser negativo', 422);
        }

        $updated = $this->productModel->update($id, $body);
        if (!$updated) Response::error('No se realizaron cambios', 400);

        Response::success($this->productModel->findById($id), 'Producto actualizado correctamente');
    }

    public function destroy(int $id): void {
        $authUser = AuthMiddleware::handle();

        if ($authUser['rol'] !== 'facturacion') {
            Response::forbidden('Solo facturación puede eliminar productos');
        }

        if (!$this->productModel->findById($id)) {
            Response::notFound("Producto con ID {$id} no encontrado");
        }

        if (!$this->productModel->delete($id)) {
            Response::serverError('No se pudo eliminar el producto');
        }

        Response::success(null, 'Producto eliminado correctamente');
    }
}