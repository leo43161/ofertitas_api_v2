<?php
namespace Src\Middleware;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class Auth {
    private static $secretKey = 'ofertita-x-2-5-2-6-3'; // Misma clave que en AuthController

    public static function handle() {
        $headers = getallheaders();
        $token = null;

        // 1. Intentar obtener token de la Cookie (Prioridad)
        if (isset($_COOKIE['token'])) {
            $token = $_COOKIE['token'];
        } 
        // 2. Fallback: Intentar obtener del Header Authorization (Bearer ...)
        else if (isset($headers['Authorization'])) {
            $matches = [];
            if (preg_match('/Bearer\s(\S+)/', $headers['Authorization'], $matches)) {
                $token = $matches[1];
            }
        }

        if (!$token) {
            self::unauthorized("No se proporcionó token de autenticación");
        }

        try {
            // Decodificar Token
            $decoded = JWT::decode($token, new Key(self::$secretKey, 'HS256'));
            
            // Guardar datos del usuario en una variable global o de sesión para usarla en el Controller
            // (En este framework simple, podemos retornarlo o asignarlo al $_SERVER/Globals)
            return $decoded->data;

        } catch (\Exception $e) {
            // Token expirado o inválido
            self::unauthorized("Token inválido o expirado: " . $e->getMessage());
        }
    }

    private static function unauthorized($message) {
        http_response_code(401);
        echo json_encode(["message" => $message]);
        exit(); // Detiene la ejecución, no llega al controlador
    }
}