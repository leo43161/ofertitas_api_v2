<?php
namespace Src\Models;

use PDO;

class Company {
    private $db;
    private $table = 'companies';

    public function __construct($db) {
        $this->db = $db;
    }

    public function getAll($user) {
        $query = "SELECT * FROM " . $this->table . " WHERE is_active = 1";
        
        // Si es Owner, solo ve su propia empresa
        if ($user->role === 'owner') {
            $query .= " AND id = :company_id";
        }
        
        $stmt = $this->db->prepare($query);
        
        if ($user->role === 'owner') {
            $stmt->bindParam(':company_id', $user->company_id);
        }
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create($data) {
        $query = "INSERT INTO " . $this->table . " (name, owner_id, is_active) VALUES (:name, :owner_id, 1)";
        $stmt = $this->db->prepare($query);
        
        $stmt->bindParam(':name', $data['name']);
        $stmt->bindParam(':owner_id', $data['owner_id']);
        
        if ($stmt->execute()) {
            return $this->db->lastInsertId();
        }
        return false;
    }
}