<?php
namespace Src\Models;

use PDO;

class User {
    private $db;
    private $table = 'admin_users';

    public function __construct($db) {
        $this->db = $db;
    }

    public function findByEmail($email) {
        $query = "SELECT * FROM " . $this->table . " WHERE email = :email LIMIT 1";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // --- NUEVOS MÉTODOS ---

    public function getAll($currentUser) {
        // Traemos datos útiles (nombre de empresa y local)
        $query = "SELECT 
                    u.id, u.email, u.role, u.is_active, u.created_at,
                    c.name as company_name,
                    l.address as location_address
                  FROM " . $this->table . " u
                  LEFT JOIN companies c ON u.company_id = c.id
                  LEFT JOIN locations l ON u.location_id = l.id
                  WHERE u.is_active = 1"; // Usamos is_active (según tu SQL)

        // Regla: Owner solo ve usuarios de SU empresa (sus managers)
        if ($currentUser->role === 'owner') {
            $query .= " AND u.company_id = :company_id AND u.role = 'manager'";
        }

        $query .= " ORDER BY u.id DESC";
        
        $stmt = $this->db->prepare($query);
        
        if ($currentUser->role === 'owner') {
            $stmt->bindParam(':company_id', $currentUser->company_id);
        }

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getOne($id) {
        $query = "SELECT id, email, role, company_id, location_id FROM " . $this->table . " WHERE id = :id AND is_active = 1";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function create($data) {
        $query = "INSERT INTO " . $this->table . " 
                  (email, password_hash, role, company_id, location_id, is_active) 
                  VALUES (:email, :password_hash, :role, :company_id, :location_id, 1)";

        $stmt = $this->db->prepare($query);

        // Encriptar contraseña
        $hashedPassword = password_hash($data['password'], PASSWORD_BCRYPT);

        $stmt->bindParam(':email', $data['email']);
        $stmt->bindParam(':password_hash', $hashedPassword);
        $stmt->bindParam(':role', $data['role']);
        $stmt->bindParam(':company_id', $data['company_id']); // Puede ser null
        $stmt->bindParam(':location_id', $data['location_id']); // Puede ser null

        if ($stmt->execute()) {
            return $this->db->lastInsertId();
        }
        return false;
    }

    public function update($id, $data) {
        // Construcción dinámica de la query (porque password es opcional)
        $query = "UPDATE " . $this->table . " SET email = :email, role = :role";
        
        if (!empty($data['password'])) {
            $query .= ", password_hash = :password_hash";
        }
        if (array_key_exists('company_id', $data)) { // Usamos array_key_exists para permitir NULL
            $query .= ", company_id = :company_id";
        }
        if (array_key_exists('location_id', $data)) {
            $query .= ", location_id = :location_id";
        }
        
        $query .= " WHERE id = :id";

        $stmt = $this->db->prepare($query);

        $stmt->bindParam(':email', $data['email']);
        $stmt->bindParam(':role', $data['role']);
        
        if (!empty($data['password'])) {
            $hashedPassword = password_hash($data['password'], PASSWORD_BCRYPT);
            $stmt->bindParam(':password_hash', $hashedPassword);
        }
        if (array_key_exists('company_id', $data)) {
            $stmt->bindParam(':company_id', $data['company_id']);
        }
        if (array_key_exists('location_id', $data)) {
            $stmt->bindParam(':location_id', $data['location_id']);
        }
        
        $stmt->bindParam(':id', $id);

        return $stmt->execute();
    }

    public function delete($id) {
        $query = "UPDATE " . $this->table . " SET is_active = 0 WHERE id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $id);
        return $stmt->execute();
    }

    // Helper para evitar duplicados
    public function checkEmailExists($email, $excludeId = null) {
        $query = "SELECT id FROM " . $this->table . " WHERE email = :email AND is_active = 1";
        if ($excludeId) {
            $query .= " AND id != :id";
        }
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':email', $email);
        if ($excludeId) $stmt->bindParam(':id', $excludeId);
        
        $stmt->execute();
        return $stmt->rowCount() > 0;
    }
}