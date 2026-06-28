<?php
class OrderController{
    private Order $orderModel;
    private Client $clientModel;
    private Product $productModel;

    public function __construct(){
        $this->orderModel = new Order();
        $this->clientModel = new Client();
        $this->productModel = new Product();
    }

    #GET /orders | /orders?status=Pendiente
    public function index(): void{
        AuthMiddleware::handle();
        $status = $_GET['status'] ?? null;
        Response::success($this->orderModel->findAll($status));
    }

    #GET /orders/{id}
    public function show(int $id): void{
        AuthMiddleware::handle();
        $order = $this->orderModel->findById($id);
        if(!$order) Response::notFound("Pedido con ID {$id} no encontrado");
        Response::success($order);
    }

    #POST en /orders solo lo crea facturacion
    #Body: { "client_id": 1, "items": [{ "product_id": 1, "amount": 2 }] }
    public function store(): void{
        $authUser = AuthMiddleware::handle();
        if ($authUser['rol']!== 'facturacion'){
            Response::forbidden('Solo facturacion puede crear pedidos');
        }

        $body = json_decode(file_get_contents('php://input'),true);
        $clientId = $body['client_id'] ?? null;
        $items = $body['items'] ?? [];

        if(!$clientId) Response::error('El cliente es requerido', 422);
        if (empty ($items)) Response::error('El pedido debe tener por lo menos 1 producto',422);

        $client = $this->clientModel->findById((int)$clientId);
        if(!$client)Response::notFound('Cliente no encontrado');

        #completacion del pedido (calculos y esas vainas)
        #array vacio de la lista de productos pero con todos sus campos
        $itemsCompletos = [];
        foreach($items as $index => $item){
            $productId = $item['product_id'] ?? null;
            $amount = $item['amount'] ?? null;

            #validacion de lo que nos manda el fronto
            if(!$productId)Response::error("El item #{$index} no tiene product_id",422);
            if(!$amount || $amount <=0) Response::error("El item #{$index} debe tener cantidad mayor a 0",422);

            #trae info del produtcp
            $product = $this->productModel->findById((int)$productId);
            if(!$product)Response::error("Producto con ID {$productId} no encontrado",404 );

            $itemsCompletos[]=[
                'product_id'=>(int)$productId,
                'amount'=>(float)$amount,
                'unit_weight'=>(float)$product['WEIGHT_APROX'],
                'price_at_purchase'=>(float)$product['PRICE'],
            ];
        }

        try{
            #se guarda sin ruta hasta que lo haga despacho
            $orderId = $this->orderModel->create((int)$clientId, $itemsCompletos);
            Response::success($this->orderModel->findById($orderId),'Pedido creado correctamente',201);
        }catch(Exception $e){
            Response::serverError('Error al crear el pedido'.$e->getMessage());
        }
    }

    #ACTUALIZAR PEDIDO X DESPACHO (pesos reales)
    #PUT /orders/{id}/weights
    #aqui SI se le asigna la ruta y cambia a ASIGANDO
    #Body: { "items": [{ "id_op": 1, "weight_real": 12.5 }] }
    public function updateWeights(int $id): void{
        $authUser= AuthMiddleware::handle();
        if($authUser['rol']!=='despacho'){
            Response::forbidden('Solo despacho puede actualizar el pedido');
        }
    
        $order = $this->orderModel->findById($id);
        #validaciones
        if(!$order)Response::notFound("pedido con ID {$id} no enocntrado");
    
        if($order['STATUS']!=='Pendiente'){
            Response::error('Solo se pueden actualizar pesos de pedidos en estado pendiente',422);
        }
    
        #treames datso brrr
        $body=json_decode(file_get_contents('php://input'),true);
        $items=$body['items']??[];
    
        if(empty($items))Response::error('Debes ingresar los pesos',422);
    
        foreach($items as $index => $item){
            if (!isset($item['id_op'])){
                Response::error("El item #{$index} no se le especificó el código (id_op) de enlace interno para asignar el peso real.",422);
            }
            if(!isset($item['weight_real'])|| $item['weight_real']<=0){
                Response::error("el item #{$index} debe tener un peso mayor a 0",422);
            }
        }
        try{
            #le pasamos el id del cliente pa que el model le asigne la ruta correspondientre
            $this->orderModel->updateWeights($id,(int)$order['ID_CLIENT'],$items);
            Response::success(
                $this->orderModel->findById($id),
                'Pesos actualizados. Pedido asignado a ruta'
            );
        }catch(Exception $e){
            Response::serverError($e->getMessage());
        }
    }

    #PUT -orders/{id}/status
    #AQUI DEBO CAMBIAF A QUE EL ESTADO DEL PEDIDO CAMBIE A DESPACHADO AL GENERARSE LA GUIA DE DESPACHO
    public function updateStatus(int $id): void{
        $authUser = AuthMiddleware::handle();
        if($authUser['rol']!=='despacho'){
            Response::forbidden('Solo despacho puede cambiar el estado de un pedido');
        }

        $body = json_decode(file_get_contents('php://input'),true);
        $status = trim($body['status']??'');
        $allowed=['Pendiente', 'Asignado','Despachado', 'Cancelado'];

        if(!in_array($status,$allowed)){
            Response::error('Estado no valido. Use '.implode(', ',$allowed),422);
        }
        if(!$this->orderModel->findById($id)){
            Response::notFound("Pedido con ID {$id} no encontrado");
        }

        $this->orderModel->updateStatus($id,$status);
        Response::success(null, "pedido actyalizado a estado: {$status}");
    }

}