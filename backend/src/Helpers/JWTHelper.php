<?php

class JWTHelper{
    private static function getSecret(): string{
        return $_ENV['JWT_SECRET'];
    }

    private static function base64UrlEncode(string $data): string{
        return rtrim(strtr(base64_encode($data), '+/','-_'),'=');
    }

    private static function base64UrlDecode(string $data): string{
        return base64_decode(strtr($data, '-_','+/'));
    }

    #GENERACION token 
    #header.payload.signature
    public static function generate(array $payload): string{
        #crea la primera parte del token
        $expiration = (int)($_ENV['JWT_EXPIRATION'] ?? 86400);

        $header = self::base64UrlEncode(json_encode([
            'alg' => 'HS256',
            'typ'=>'JWT',
        ]));

        $payload['iat']=time(); #cuando de screo el token
        $payload['exp']=time()+$expiration; #cuando se acaba

        $payloadEncoded = self::base64UrlEncode(json_encode($payload));

        $signature = self::base64UrlEncode(
            hash_hmac('sha256', "{$header}.{$payloadEncoded}", self::getSecret(), true)
        );

        return "{$header}.{$payloadEncoded}.{$signature}";
    }

    #VERIFICACION DEL TOKEN
    public static function verify(string $token): ?array{
        $parts = explode('.', $token);
        if(count($parts)!==3){
            return null;
        }

        [$header, $payload, $signature] = $parts;

        #verificar firma
        $expectedSignature = self::base64UrlEncode(
            hash_hmac('sha256', "{$header}.{$payload}", self::getSecret(), true)
        );

        if(!hash_equals($expectedSignature, $signature)){
            return null; #token manipulado
        }

        $decoded = json_decode(self::base64UrlDecode($payload), true);

        #verificar expiracion
        if(!isset($decoded['exp']) || $decoded['exp'] <time()){
            return null;
        }

        return $decoded;
    }

    #EXTRAER TOKER DE HEADER AUTHORIZATION
    public static function extractFromHeader(): ?string{
        $authHeader = $_SERVER['HTTP_AUTHORIZATION']??'';

        if(empty($authHeader)){
            #si apache no paso el header
            $authHeader = apache_request_headers()['Authorization']??'';
        }

        #e corta los primeros 7 caracteres "Bearer " para token limpio
        if(str_starts_with(($authHeader), 'Bearer ')){
            return substr($authHeader,7);
        }

        return null;
    }
}