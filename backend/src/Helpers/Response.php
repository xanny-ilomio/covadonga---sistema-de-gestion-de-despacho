<?php
class Response{
    #Respuesta exitosa
    public static function success(mixed $data = null, ?string $message =null, int $code = 200): void{
        #mixed cualquier dato | $data = null parametro detemrinado. Aqui se pasa la info solicitada
        #?string solo texto o nulo
        #: void le dice a php que la funcion realiza accions

        http_response_code($code);
        echo json_encode([
            'status' => 'success',
            'data' => $data,
            'message' => $message,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    #RESPUESTAS DE ERROR
    public static function error(string $message, int $code=400, mixed $data = null): void{
        http_response_code($code);
        echo json_encode([
            'status' => 'error',
            'data' => $data,
            'message' => $message,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function notFound(string $message = 'Recurso no encontrado'): void{
        self::error($message, 404);
    }

    public static function unauthorized(string $message = 'No autorizado'): void{
        self::error($message, 401);
    }

    public static function forbidden(string $message = 'Acceso denegado'): void{
        self::error($message, 403);
    }

    public static function serverError(string $message = 'Error interno del servidor'): void{
        self::error($message, 500);
    }

}