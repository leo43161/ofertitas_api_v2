<?php
// config/Database.php
namespace Config;

use PDO;
use PDOException;

class Database {
    private $host = "localhost"; // Ajusta si es necesario
    private $db_name = "ofertitas";
    private $username = "root";
    private $password = "";
    public $conn;

    public function getConnection() {
        $this->conn = null;

        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4",
                $this->username,
                $this->password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, false); // Seguridad extra
        } catch(PDOException $exception) {
            echo "Error de conexión: " . $exception->getMessage();
            exit; // Detener ejecución si no hay DB
        }

        return $this->conn;
    }
}