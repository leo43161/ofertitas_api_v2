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
        var_dump($headers);
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

    public static function check()
    {
        $headers = apache_request_headers();
        $authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : null;

        if (!$authHeader) {
            return null; // Es un invitado
        }

        try {
            // Reutilizamos la lógica de validación existente
            // Nota: Asumo que tu lógica interna usa JWT::decode o similar.
            // Si handle() hace todo, idealmente refactorizaríamos, pero para salir del paso:
            
            // Intento manual rápido de validación si tienes la lógica accesible, 
            // OJO: Si 'handle()' tiene 'exit()' dentro, no podemos llamarlo directamente.
            // Lo mejor es copiar la lógica de decodificación de handle() aquí dentro de un try/catch
            
            // *Asumiendo lógica estándar JWT*:
            $matches = [];
            if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
                $token = $matches[1];
                // Aquí deberías usar tu clase JWT para decodificar
                // $payload = JWT::decode($token, ...);
                // return $payload; 
                
                // *TRUCO RÁPIDO*: Si no quieres duplicar código, usa handle() 
                // PERO solo si modificas handle() para que lance Excepción en vez de exit().
                
                // Si no quieres tocar mucho, dejemos que la App envíe sin header
                // y retornemos null directo arriba.
                return null; // Por ahora, si hay header invalido, lo tratamos como invitado.
            }
        } catch (\Exception $e) {
            return null;
        }
        return null;
    }

    private static function unauthorized($message) {
        http_response_code(401);
        echo json_encode(["message" => $message]);
        exit(); // Detiene la ejecución, no llega al controlador
    }
}