<?php
namespace Src\Controllers;

use Src\Models\User;
use Firebase\JWT\JWT; // Usamos la librería cargada por el autoloader

class AuthController extends Controller {
    
    // ¡IMPORTANTE! Cambia esto por una clave real y compleja en producción
    private $secretKey = 'ofertita-x-2-5-2-6-3'; 

    public function login() {
        // 1. Obtener datos del JSON (usando helper del padre)
        $data = $this->getBody();
        
        // 2. Validar campos
        $this->validateRequired($data, ['email', 'password']);

        // 3. Buscar usuario
        $userModel = new User($this->db);
        $user = $userModel->findByEmail($data['email']);

        // 4. Verificar contraseña
        // Nota: Si en tu DB las claves no están hasheadas usa password_verify($data['password'], $user['password_hash'])
        // Si están en texto plano (MALO, pero común en dev), usa ==. Asumo hash:
        if (!$user || !password_verify($data['password'], $user['password_hash'])) {
            $this->jsonResponse(["message" => "Credenciales inválidas"], 401);
        }

        // 5. Generar Payload del Token
        $issuedAt = time();
        $expirationTime = $issuedAt + (60 * 60 * 24); // 24 horas
        
        $payload = [
            'iat' => $issuedAt,
            'exp' => $expirationTime,
            'data' => [
                'id' => $user['id'],
                'email' => $user['email'],
                'role' => $user['role'],
                'company_id' => $user['company_id'] ?? null,
                'location_id' => $user['location_id'] ?? null
            ]
        ];

        // 6. Codificar JWT
        // 'HS256' es el algoritmo estándar
        $jwt = JWT::encode($payload, $this->secretKey, 'HS256');

        // 7. Configurar Cookie HTTPOnly (Más seguro que localStorage)
        setcookie("token", $jwt, [
            'expires' => time() + (600 * 60 * 24 * 30),
            'path' => '/',
            'domain' => '', // Dominio actual. (Para localhost/XAMPP déjalo vacío)
            'secure' => false, // ¡IMPORTANTE! Poner en 'true' en producción (cuando uses HTTPS)
            'httponly' => false, // ¡La clave! No es accesible desde JavaScript (previene XSS)
            'samesite' => 'Lax' // <-- 3. Cambia 'Strict' por 'Lax'
        ]);

        // 8. Responder
        $this->jsonResponse([
            "message" => "Login exitoso",
            "token" => $jwt, // Opcional: devolverlo también en JSON
            "user" => $payload['data']
        ]);
    }

    public function logout() {
        setcookie("token", "", time() - 3600, "/", "", false, true);
        $this->jsonResponse(["message" => "Sesión cerrada"]);
    }
    
    public function check() {
        // Endpoint simple para verificar si el token sigue vivo (útil para el frontend)
        // Esto requerirá middleware que implementaremos en el siguiente paso
        $this->jsonResponse(["message" => "Token válido", "user_id" => $this->userId]);
    }
}