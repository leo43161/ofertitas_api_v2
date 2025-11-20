<?php
namespace Src\Models;

use PDO;

class Offer {
    private $db;
    private $table = 'offers';

    public function __construct($db) {
        $this->db = $db;
    }

    public function getAll($user) {
        $query = "SELECT 
                    o.*,
                    l.address as location_address,
                    c.name as company_name,
                    cat.name as category_name
                  FROM " . $this->table . " o
                  JOIN locations l ON o.location_id = l.id
                  JOIN companies c ON l.company_id = c.id
                  LEFT JOIN categories cat ON o.category_id = cat.id
                  WHERE o.active = 1 AND l.active = 1"; // Solo ofertas de locales activos

        // Filtros por Rol
        if ($user->role === 'owner') {
            $query .= " AND c.id = :company_id";
        } elseif ($user->role === 'manager') {
            $query .= " AND l.id = :location_id";
        }

        $query .= " ORDER BY o.id DESC";

        $stmt = $this->db->prepare($query);

        if ($user->role === 'owner') {
            $stmt->bindParam(':company_id', $user->company_id);
        } elseif ($user->role === 'manager') {
            $stmt->bindParam(':location_id', $user->location_id);
        }

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getOne($id) {
        // Traemos también el owner_id de la empresa para validar propiedad
        $query = "SELECT o.*, c.owner_id, l.company_id 
                  FROM " . $this->table . " o
                  JOIN locations l ON o.location_id = l.id
                  JOIN companies c ON l.company_id = c.id
                  WHERE o.id = :id AND o.active = 1 LIMIT 1";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function create($data) {
        $query = "INSERT INTO " . $this->table . " 
                  (location_id, category_id, title, description, discount_text, 
                   price_normal, price_offer, image_url, start_date, end_date, 
                   is_visible, is_featured, active)
                  VALUES 
                  (:location_id, :category_id, :title, :description, :discount_text, 
                   :price_normal, :price_offer, :image_url, :start_date, :end_date, 
                   :is_visible, :is_featured, 1)";

        $stmt = $this->db->prepare($query);

        // Sanitización básica de strings
        $title = htmlspecialchars(strip_tags($data['title']));
        $desc = htmlspecialchars(strip_tags($data['description']));
        $disc = htmlspecialchars(strip_tags($data['discount_text']));

        $stmt->bindParam(':location_id', $data['location_id']);
        $stmt->bindParam(':category_id', $data['category_id']);
        $stmt->bindParam(':title', $title);
        $stmt->bindParam(':description', $desc);
        $stmt->bindParam(':discount_text', $disc);
        $stmt->bindParam(':price_normal', $data['price_normal']);
        $stmt->bindParam(':price_offer', $data['price_offer']);
        $stmt->bindParam(':image_url', $data['image_url']);
        
        // Fechas (pueden ser null)
        $start = !empty($data['start_date']) ? $data['start_date'] : null;
        $end = !empty($data['end_date']) ? $data['end_date'] : null;
        $stmt->bindParam(':start_date', $start);
        $stmt->bindParam(':end_date', $end);

        // Booleanos (0 o 1)
        $visible = isset($data['is_visible']) ? (int)$data['is_visible'] : 1;
        $featured = isset($data['is_featured']) ? (int)$data['is_featured'] : 0;
        $stmt->bindParam(':is_visible', $visible);
        $stmt->bindParam(':is_featured', $featured);

        if ($stmt->execute()) {
            return $this->db->lastInsertId();
        }
        return false;
    }

    public function update($id, $data) {
        // Construimos la query dinámicamente para no borrar datos si no se envían
        // (Aunque para simplificar este ejemplo, asumiremos que se envía todo o validaremos en controller)
        
        $query = "UPDATE " . $this->table . " SET 
                    title = :title, 
                    description = :description,
                    discount_text = :discount_text,
                    price_normal = :price_normal,
                    price_offer = :price_offer,
                    start_date = :start_date,
                    end_date = :end_date,
                    is_visible = :is_visible,
                    is_featured = :is_featured,
                    category_id = :category_id";

        // Solo actualizamos imagen si se envió una nueva URL
        if (!empty($data['image_url'])) {
            $query .= ", image_url = :image_url";
        }

        $query .= " WHERE id = :id";

        $stmt = $this->db->prepare($query);

        $stmt->bindParam(':title', $data['title']);
        $stmt->bindParam(':description', $data['description']);
        $stmt->bindParam(':discount_text', $data['discount_text']);
        $stmt->bindParam(':price_normal', $data['price_normal']);
        $stmt->bindParam(':price_offer', $data['price_offer']);
        
        $start = !empty($data['start_date']) ? $data['start_date'] : null;
        $end = !empty($data['end_date']) ? $data['end_date'] : null;
        $stmt->bindParam(':start_date', $start);
        $stmt->bindParam(':end_date', $end);
        
        $stmt->bindParam(':is_visible', $data['is_visible']);
        $stmt->bindParam(':is_featured', $data['is_featured']);
        $stmt->bindParam(':category_id', $data['category_id']);

        if (!empty($data['image_url'])) {
            $stmt->bindParam(':image_url', $data['image_url']);
        }

        $stmt->bindParam(':id', $id);

        return $stmt->execute();
    }

    public function delete($id) {
        $query = "UPDATE " . $this->table . " SET active = 0 WHERE id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $id);
        return $stmt->execute();
    }
}