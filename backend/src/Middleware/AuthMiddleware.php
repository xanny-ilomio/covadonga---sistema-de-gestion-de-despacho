<?php

class AuthMiddleware{
    #verifica el token del equest
    #si es valido devuelve el payload del user
    #no valido da 401

    public static function handle(): array{
        $token = JWTHelper::extractFromHeader();

        if(!$token){
            Response::unauthorized('Token no proporcionado');
        }

        $payload = JWTHelper::verify($token);

        if(!$payload){
            Response::unauthorized("Token inválido o ya expiró");
        }

        return $payload;
    }

    #VERIFICACION token y user rol requerido
    public static function requireRole(string $role): array{
        $payload = self::handle();

        if (($payload['role']??'') !== $role){
            Response::forbidden('No tienes permisos para esta acción');
        }

        return $payload;
    }
}