<?php
class UserController{
    private User $userModel;
    public function __construct(){
        $this->userModel= new User();
    }

    #devuelve los datos del user ya identificado | asi el front sabe el rol y cual unterfaz mostrar
    public function show(int $id): void{
        #solo user autenticado puede consultar
        $authUser = AuthMiddleware::handle();

        #user solo ver su info
        if($authUser['user_id']!==$id){
            Response::error('Solo puedes consultar tu propia informacion');
        }

        $user = $this->userModel->findById($id);
        if(!$user){
            Response::notFound('Usuario no encontrado');
        }
        Response::success($user);
    }

}