<?php
namespace Src\Models;

use PDO;

class Location {
    private $db;
    private $table = 'locations';

    public function __construct($db) {
        $this->db = $db;
    }

    // Leer todos (filtrado por rol y status activo)
    public function getAll($user) {
        $query = "SELECT 
                    l.id, l.address, l.phone, l.latitude, l.longitude, l.company_id,
                    c.name as company_name 
                  FROM " . $this->table . " l
                  JOIN companies c ON l.company_id = c.id
                  WHERE l.active = 1"; // ¡OJO: aquí es 'active', no 'is_active'!

        // Filtros de seguridad
        if ($user->role === 'owner') {
            $query .= " AND l.company_id = :company_id";
        } elseif ($user->role === 'manager') {
            $query .= " AND l.id = :location_id";
        }
        
        $query .= " ORDER BY l.id DESC";

        $stmt = $this->db->prepare($query);

        if ($user->role === 'owner') {
            $stmt->bindParam(':company_id', $user->company_id);
        } elseif ($user->role === 'manager') {
            $stmt->bindParam(':location_id', $user->location_id);
        }

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Leer uno específico (para editar)
    public function getOne($id) {
        $query = "SELECT * FROM " . $this->table . " WHERE id = :id AND active = 1 LIMIT 1";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function create($data) {
        $query = "INSERT INTO " . $this->table . " 
                  (company_id, address, latitude, longitude, phone, active) 
                  VALUES (:company_id, :address, :latitude, :longitude, :phone, 1)";
        
        $stmt = $this->db->prepare($query);
        
        // Sanitización básica
        $address = htmlspecialchars(strip_tags($data['address']));
        $phone = htmlspecialchars(strip_tags($data['phone'] ?? ''));

        $stmt->bindParam(':company_id', $data['company_id']);
        $stmt->bindParam(':address', $address);
        $stmt->bindParam(':latitude', $data['latitude']);
        $stmt->bindParam(':longitude', $data['longitude']);
        $stmt->bindParam(':phone', $phone);

        if ($stmt->execute()) {
            return $this->db->lastInsertId();
        }
        return false;
    }

    public function update($id, $data) {
        $query = "UPDATE " . $this->table . "
                  SET address = :address, latitude = :latitude, longitude = :longitude, phone = :phone, company_id = :company_id
                  WHERE id = :id";
        
        $stmt = $this->db->prepare($query);

        $address = htmlspecialchars(strip_tags($data['address']));
        $phone = htmlspecialchars(strip_tags($data['phone'] ?? ''));

        $stmt->bindParam(':address', $address);
        $stmt->bindParam(':latitude', $data['latitude']);
        $stmt->bindParam(':company_id', $data['company_id']);
        $stmt->bindParam(':longitude', $data['longitude']);
        $stmt->bindParam(':phone', $phone);
        $stmt->bindParam(':id', $id);

        return $stmt->execute();
    }

    // Borrado Lógico (Soft Delete)
    public function delete($id) {
        $query = "UPDATE " . $this->table . " SET active = 0 WHERE id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $id);
        return $stmt->execute();
    }
}