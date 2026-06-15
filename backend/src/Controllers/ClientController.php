<?php
class ClientController{
    private Client $clientModel;
    public function __construct(){
        $this->clientModel=new Client();
    }

    #solo facturacion pued gestionar clientes
    public function index(): void{
        $authUser = AuthMiddleware::handle();
        #Si viene el parámetro ?search= en la URL se busca
        #$_GET contiene los parámetros de la URL (?key=value)
        if (!empty($_GET['search'])){
            $results=$this-> clientModel->search(trim($_GET['search']));
            Response::success($results);
        }

        $clients = $this->clientModel->findAll();
        Response::success($clients);
    }

    #PAL GET
    public function show(int $id): void{
        $authUser = AuthMiddleware::handle();
        $client=$this->clientModel->findById($id);
        if(!$client){
            Response::notFound("Cliente con ID {$id} no encontrado");
        }
        Response::success($client);
    }

    #PAL POST
    public function store():void{
        $authUser = AuthMiddleware::handle();

        #solo facturacion crea clientes yupi
        if ($authUser['rol']!=='facturacion'){
            Response::forbidden('Solo facturacion puede registrar clientes');
        }

        $body=json_decode(file_get_contents('php://input'),true);

        #validando campos requeridos
        $name=trim($body['name']??'');
        $rif=trim($body['rif']??'');
        $phone=$body['phone']??null;
        $city_id=$body['city_id']??null;
        $email=trim($body['email']??'');

        if(empty($name)){
            Response::error('El nombre del cliente es requerido', 422);
        }
        if(empty($rif)){
            Response::error('El RIF es requerido', 422);
        }
        if(empty($phone)){
            Response::error('Eltelefono es requerido', 422);
        }
        if(empty($city_id)){
            Response::error('La ciudad es requerida', 422);
        }

        #verificar q no exista otro cliet con el mismo RIF
        if (!empty($email)&&!filter_var($email, FILTER_VALIDATE_EMAIL)){
            Response::error('El email no tieneformato valido', 422);
        }

        $newId=$this->clientModel->create([
            'name' => $name,
            'rif' => $rif,
            'phone'=>$phone,
            'email'=>$email?:null,
            'city_id'=>(int)$city_id,
        ]);

        $client=$this->clientModel->findById($newId);
        Response::success($client,'Cliente registrado correctamente',201);
    }

    public function update(int $id): void{
        $authUser=AuthMiddleware::handle();
        if($authUser['rol']!=='facturacion'){
            Response::forbidden('Solo facturacion puede modificar clientes');
        }

        $existing=$this->clientModel->findById($id);
        if(!$existing){
            Response::notFound('Clente con ID {$id} no encontrado');
        }

        $body=json_decode(file_get_contents('php://input'), true);
        #si es un nuevo RIF revisar que ya no existe
        if(!empty($body['rif'])){
            $rifOwner = $this->clientModel->findByRif($body['rif']);
            #si existe y no es el q se edita act
            if ($rifOwner && $rifOwner['ID_CLIENT']!==$id){
                Response::error('Ya existe un cliente con ese RIF',409);
            }
        }

        if(!empty($body['email'])&& !filter_var($body['$email'], FILTER_VALIDATE_EMAIL)){
            Response::error('El email no tiene un formato valido', 422);
        }

        $updated=$this->clientModel->update($id, $body);
        if(!$updated){
            Response::error('No se realizaron cambios',400);
        }

        $client=$this->clientModel->findById($id);
        Response::success($client,'Cliente actualizado correctamente');
    }

    #ELIMINAR XFIN
    public function destroy(int $id):void{
        $authUser=AuthMiddleware::handle();
        if($authUser['rol']!=='facturacion'){
            Response::forbidden('Solo facturacion puede eleiminar un cliente');
        }

        $existing=$this->clientModel->findById($id);
        if(!$existing){
            Response::notFound("Cliente con ID {$id} no existe");
        }

        $deleted=$this->clientModel->delete($id);
        if(!$deleted){
            Response::serverError('No se pudo elimir al cliente');
        }

        Response::success(null,'Cliente eliminado correctamente');
    }
}