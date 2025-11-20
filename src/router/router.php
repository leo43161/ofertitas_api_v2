<?php
namespace Src\Router;

class Router {
    private $routes = [];

    // Métodos para registrar rutas
    public function get($path, $handler) { $this->addRoute('GET', $path, $handler); }
    public function post($path, $handler) { $this->addRoute('POST', $path, $handler); }
    public function put($path, $handler) { $this->addRoute('PUT', $path, $handler); }
    public function delete($path, $handler) { $this->addRoute('DELETE', $path, $handler); }

    private function addRoute($method, $path, $handler) {
        // Convertimos {id} en regex
        $pathRegex = preg_replace('/\{[a-zA-Z0-9_]+\}/', '([^/]+)', $path);
        $pathRegex = "#^" . $pathRegex . "$#";
        
        $this->routes[] = [
            'method' => $method,
            'path' => $pathRegex,
            'handler' => $handler
        ];
    }

    public function dispatch() {
        $requestMethod = $_SERVER['REQUEST_METHOD'];
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        
        // Limpieza de la URI para subcarpetas
        $scriptName = dirname($_SERVER['SCRIPT_NAME']); 
        $scriptName = str_replace('\\', '/', $scriptName);
        
        if (strpos($uri, $scriptName) === 0) {
            $uri = substr($uri, strlen($scriptName));
        }
        $uri = '/' . ltrim($uri, '/');

        foreach ($this->routes as $route) {
            if ($route['method'] === $requestMethod && preg_match($route['path'], $uri, $matches)) {
                array_shift($matches); // Quitamos la coincidencia completa
                
                $handler = $route['handler'];

                // CASO 1: Es una función anónima (Closure) -> function() { ... }
                // Esto soluciona tu error actual
                if (is_callable($handler)) {
                    call_user_func_array($handler, $matches);
                    return;
                }

                // CASO 2: Es un Array -> ['Namespace\Clase', 'metodo']
                if (is_array($handler)) {
                    [$controllerClass, $method] = $handler;
                    
                    if (class_exists($controllerClass)) {
                        $controller = new $controllerClass();
                        if (method_exists($controller, $method)) {
                            call_user_func_array([$controller, $method], $matches);
                            return;
                        }
                    }
                }
                
                $this->sendNotFound("Handler no válido o Clase/Método no encontrado.");
                return;
            }
        }

        $this->sendNotFound("Ruta no encontrada: $requestMethod $uri");
    }

    private function sendNotFound($message) {
        header("HTTP/1.0 404 Not Found");
        header("Content-Type: application/json");
        echo json_encode(["message" => $message]);
        exit();
    }
}