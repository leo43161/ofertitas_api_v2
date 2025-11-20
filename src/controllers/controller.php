<?php
namespace Src\Controllers;

class Controller {
    protected $db;
    protected $requestMethod;
    protected $userId;

    public function __construct() {
        // Usamos la variable global $db definida en index.php o instanciamos una nueva
        global $db; 
        if (!$db) {
            // Fallback por si no se pasó la global (útil para testing)
            $database = new \Config\Database();
            $db = $database->getConnection();
        }
        $this->db = $db;
        $this->requestMethod = $_SERVER["REQUEST_METHOD"];
    }

    // Helper para enviar respuestas JSON
    protected function jsonResponse($data, $code = 200) {
        // Limpiamos buffers previos por si hubo algún echo accidental
        if (ob_get_length()) ob_clean();
        
        /* header_remove();  */
        http_response_code($code);
        header("Content-Type: application/json; charset=UTF-8");
        
        echo json_encode($data);
        exit();
    }

    // Helper para obtener datos del Body (POST/PUT)
    protected function getBody() {
        $data = json_decode(file_get_contents('php://input'), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return $_POST; // Fallback para Form Data normal
        }
        return $data ?? [];
    }
    
    // Validador simple: Verifica que existan campos en el array
    protected function validateRequired($data, $fields) {
        $missing = [];
        foreach ($fields as $field) {
            if (!isset($data[$field]) || $data[$field] === '') {
                $missing[] = $field;
            }
        }
        
        if (!empty($missing)) {
            $this->jsonResponse([
                "status" => "error",
                "message" => "Faltan campos obligatorios",
                "missing_fields" => $missing
            ], 400);
        }
    }
}