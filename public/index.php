<?php
// public/index.php

// 1. Configuración de Errores (Desactivar en Producción)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 2. Cargas Principales
require_once __DIR__ . '/../autoload.php';
require_once __DIR__ . '/../config/database.php'; // Ojo: nombre en minúscula según tu estructura

// 3. CORS
use Src\Middleware\Cors;
Cors::handle();

// 4. Inicializar Base de Datos Global
use Config\Database;
$database = new Database();
$db = $database->getConnection();

// 5. Inicializar Router
use Src\Router\Router;
$router = new Router();

// --- DEFINICIÓN DE RUTAS ---

// Ruta de prueba básica
$router->get('/', function() {
    echo json_encode(["message" => "API Ofertitas v2 funcionando correctamente"]);
});

$router->post('/auth/login', ['Src\Controllers\AuthController', 'login']);
$router->post('/auth/logout', ['Src\Controllers\AuthController', 'logout']);
// Rutas de Empresas (Protegidas internamente por el controlador)
// Rutas de Empresas
$router->get('/companies', ['Src\Controllers\CompanyController', 'index']);
$router->get('/companies/{id}', ['Src\Controllers\CompanyController', 'getOne']);
$router->post('/companies', ['Src\Controllers\CompanyController', 'create']);
$router->put('/companies/{id}', ['Src\Controllers\CompanyController', 'update']);
$router->delete('/companies/{id}', ['Src\Controllers\CompanyController', 'delete']);
// Rutas de Locales (Locations)
$router->get('/locations', ['Src\Controllers\LocationController', 'index']);
$router->get('/locations/{id}', ['Src\Controllers\LocationController', 'getOne']);
$router->post('/locations', ['Src\Controllers\LocationController', 'create']);
$router->put('/locations/{id}', ['Src\Controllers\LocationController', 'update']);
$router->delete('/locations/{id}', ['Src\Controllers\LocationController', 'delete']);
// Rutas de Usuarios
$router->get('/users', ['Src\Controllers\UserController', 'index']);
$router->get('/users/{id}', ['Src\Controllers\UserController', 'getOne']);
$router->post('/users', ['Src\Controllers\UserController', 'create']);
$router->put('/users/{id}', ['Src\Controllers\UserController', 'update']);
$router->delete('/users/{id}', ['Src\Controllers\UserController', 'delete']);
// Rutas de Ofertas
$router->get('/offers', ['Src\Controllers\OfferController', 'index']);
$router->get('/offers/{id}', ['Src\Controllers\OfferController', 'getOne']);
$router->post('/offers', ['Src\Controllers\OfferController', 'create']);
// Nota: Para editar con archivos en PHP, a veces POST es más fácil que PUT.
// Pero si tu frontend envía PUT, asegúrate de manejar el "method spoofing" o FormData manual.
// Por simplicidad y compatibilidad con archivos, usaremos POST para crear y DELETE para borrar.
// Si necesitas UPDATE, avísame para darte el truco del "_method: PUT" en FormData.
$router->delete('/offers/{id}', ['Src\Controllers\OfferController', 'delete']);

// 6. Despachar la petición
$router->dispatch();