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
        // Agregamos slug, description, website, logo y cover
        $query = "INSERT INTO " . $this->table . " 
                  (name, owner_id, slug, description, website, logo_url, cover_url, is_active) 
                  VALUES (:name, :owner_id, :slug, :description, :website, :logo_url, :cover_url, 1)";
        
        $stmt = $this->db->prepare($query);
        
        $stmt->bindParam(':name', $data['name']);
        $stmt->bindParam(':owner_id', $data['owner_id']);
        $stmt->bindParam(':slug', $data['slug']);
        $stmt->bindParam(':description', $data['description']);
        $stmt->bindParam(':website', $data['website']);
        $stmt->bindParam(':logo_url', $data['logo_url']);
        $stmt->bindParam(':cover_url', $data['cover_url']);
        
        if ($stmt->execute()) {
            return $this->db->lastInsertId();
        }
        return false;
    }

    public function getOne($id) {
        $query = "SELECT * FROM " . $this->table . " WHERE id = :id AND is_active = 1 LIMIT 1";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function update($id, $data) {
        $query = "UPDATE " . $this->table . " SET name = :name";
        
        // Construcción dinámica de la query
        if (array_key_exists('owner_id', $data)) $query .= ", owner_id = :owner_id";
        if (isset($data['slug'])) $query .= ", slug = :slug";
        if (isset($data['description'])) $query .= ", description = :description";
        if (isset($data['website'])) $query .= ", website = :website";
        if (isset($data['logo_url'])) $query .= ", logo_url = :logo_url";
        if (isset($data['cover_url'])) $query .= ", cover_url = :cover_url";

        $query .= " WHERE id = :id";

        $stmt = $this->db->prepare($query);

        $stmt->bindParam(':name', $data['name']);
        $stmt->bindParam(':id', $id);
        
        if (array_key_exists('owner_id', $data)) $stmt->bindParam(':owner_id', $data['owner_id']);
        if (isset($data['slug'])) $stmt->bindParam(':slug', $data['slug']);
        if (isset($data['description'])) $stmt->bindParam(':description', $data['description']);
        if (isset($data['website'])) $stmt->bindParam(':website', $data['website']);
        if (isset($data['logo_url'])) $stmt->bindParam(':logo_url', $data['logo_url']);
        if (isset($data['cover_url'])) $stmt->bindParam(':cover_url', $data['cover_url']);

        return $stmt->execute();
    }

    public function delete($id) {
        // Borrado lógico
        $query = "UPDATE " . $this->table . " SET is_active = 0 WHERE id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $id);
        return $stmt->execute();
    }
}