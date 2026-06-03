<?php

class AuthController{
    private User $userModel;
    public function __construct(){
        $this->userModel = new User();
    }

    public function login(): void{
        #se lee el json enviado desde el body de la petcion
        $body = json_decode(file_get_contents('php://input'), true);

        #extrae y limpiar caampos
        $username = trim($body['username'] ?? '');
        $password = trim($body['password'] ?? '');

        #validar que se recibieron ambos
        if(empty($username) || empty($password)){
            Response::error('Username y contraseña requeridos', 422);
        }

        #buscar por username en bd
        $user = $this->userModel->findByUsername($username);

        if(!$user){
            Response::error('Credenciales incorrectas', 401);
        }

        if(!password_verify($password, $user['PASSWORD'])){
            Response::error('Contraseña incorrecta', 401);
        }

        #generacion de token con datos del user
        #estaran disponibles en cada request sin tener que consultar a bd
        $token = JWTHelper::generate([
            'user_id' => $user['ID_USER'],
            'username' => $user['USERNAME'],
            'rol' => $user['ROL'],
        ]);

        #guardar token en frontend y enviarlo en peticiones
        Response::success([
            'token'=>$token,
            'user'=>[
                'id'=> $user['ID_USER'],
                'username' => $user['USERNAME'],
                'rol' => $user['ROL'],
            ],
        ], 'Login exitoso');
    }
}