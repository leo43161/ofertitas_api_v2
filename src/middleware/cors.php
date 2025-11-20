<?php
namespace src\middleware;

class Cors {
    public static function handle() {
        // Ajusta el origen a tu frontend específico en producción
        // header("Access-Control-Allow-Origin: http://10.20.20.5:3000"); 
        
        // Para desarrollo, a veces es útil reflejar el origen (o dejar el específico que ya tenías)
        if (isset($_SERVER['HTTP_ORIGIN'])) {
            header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
        }

        header("Access-Control-Allow-Credentials: true");
        header("Access-Control-Max-Age: 86400"); // Cachear preflight por 1 día

        // Headers permitidos
        if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'])) {
            header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");
        } else {
            header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With, X-API-KEY");
        }

        // Métodos permitidos
        header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");

        // Si es una petición OPTIONS (Preflight), terminamos aquí
        if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
            if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'])) {
                header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
            }
            exit(0);
        }
    }
}