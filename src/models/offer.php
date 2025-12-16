<?php

namespace Src\Models;

use PDO;

class Offer
{
    private $db;
    private $table = 'offers';

    public function __construct($db)
    {
        $this->db = $db;
    }


    public function countActiveOffersByLocation($locationId)
    {
        // Contamos ofertas con status 'active' Y que la fecha de fin sea mayor o igual a hoy
        $query = "SELECT COUNT(*) as total FROM " . $this->table . " 
                  WHERE location_id = :location_id 
                  AND status = 'active' ";
        /* AND end_date >= CURDATE()"; */

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':location_id', $locationId);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)$row['total'];
    }

    public function getAll($user = null, $params = [])
    {
        // Configuración de Paginación
        $page = isset($params['page']) ? (int)$params['page'] : 1;
        $limit = isset($params['limit']) ? (int)$params['limit'] : 20;
        $offset = ($page - 1) * $limit;

        // 1. Query Base
        $query = "SELECT 
                  o.*,
                  l.phone,
                  l.address as location_address,
                  l.latitude,
                  l.longitude,
                  c.name as company_name,
                  c.logo_url as company_logo,
                  cat.name as category_name,
                  cat.icon_name as category_icon
                FROM " . $this->table . " o
                JOIN locations l ON o.location_id = l.id
                JOIN companies c ON l.company_id = c.id
                LEFT JOIN categories cat ON o.category_id = cat.id
                WHERE o.active = 1 AND l.active = 1";

        // 2. Filtros según Usuario (Admin vs Público)
        if (!$user) {
            // MODO PÚBLICO
            $query .= " AND o.is_visible = 1 
                        AND (o.end_date IS NULL OR o.end_date >= NOW()) 
                        AND (o.start_date IS NULL OR o.start_date <= NOW())";
        } else {
            // MODO ADMIN
            if ($user->role === 'owner') {
                $query .= " AND c.id = :company_id";
            } elseif ($user->role === 'manager') {
                $query .= " AND l.id = :location_id";
            }
        }

        if (isset($params['search']) && !empty($params['search'])) {
            // Buscamos en Título, Descripción o Nombre de Empresa
            $query .= " AND (o.title LIKE :search OR o.description LIKE :search OR c.name LIKE :search)";
        }

        // --- NUEVO: FILTRO DE CATEGORÍA (Para hacerlo server-side también) ---
        if (isset($params['category_id']) && !empty($params['category_id']) && $params['category_id'] != '0') {
            $query .= " AND o.category_id = :category_id";
        }

        // 3. ORDENAMIENTO (¡Una sola vez!)
        $query .= " ORDER BY o.is_featured DESC, o.id DESC";

        // 4. PAGINACIÓN (Opcional)
        if (isset($params['paginate']) && $params['paginate'] === true) {
            $query .= " LIMIT :limit OFFSET :offset";
        }
        var_dump($query);
        // 5. Preparar Sentencia
        $stmt = $this->db->prepare($query);

        // 6. Bind de parámetros
        if ($user) {
            if ($user->role === 'owner') {
                $stmt->bindParam(':company_id', $user->company_id);
            } elseif ($user->role === 'manager') {
                $stmt->bindParam(':location_id', $user->location_id);
            }
        }

        if (isset($params['paginate']) && $params['paginate'] === true) {
            $stmt->bindParam(':limit', $limit);
            $stmt->bindParam(':offset', $offset);
        }

        if (isset($params['search']) && !empty($params['search'])) {
            $searchTerm = "%" . $params['search'] . "%";
            $stmt->bindParam(':search', $searchTerm);
        }

        // --- NUEVO: BIND DE CATEGORÍA ---
        if (isset($params['category_id']) && !empty($params['category_id']) && $params['category_id'] != '0') {
            $stmt->bindParam(':category_id', $params['category_id']);
        }

        // 7. Ejecutar
        $stmt->execute();
        /* return $query; */
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getOne($id)
    {
        // Traemos también el owner_id de la empresa para validar propiedad
        $query = "SELECT o.*, c.owner_id, l.company_id, c.name as company_name, l.address as location_address
                  FROM " . $this->table . " o
                  JOIN locations l ON o.location_id = l.id
                  JOIN companies c ON l.company_id = c.id
                  WHERE o.id = :id AND o.active = 1 LIMIT 1";

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function create($data)
    {
        $query = "INSERT INTO " . $this->table . " 
    (location_id, category_id, promo_type, title, description, discount_text, 
     price_normal, price_offer, image_url, start_date, end_date, 
     is_visible, is_featured, active)
    VALUES 
    (:location_id, :category_id, :promo_type, :title, :description, :discount_text, 
     :price_normal, :price_offer, :image_url, :start_date, :end_date, 
     :is_visible, :is_featured, 1)";

        $stmt = $this->db->prepare($query);

        // Sanitización básica de strings
        $title = htmlspecialchars(strip_tags($data['title']));
        $desc = htmlspecialchars(strip_tags($data['description']));
        $disc = htmlspecialchars(strip_tags($data['discount_text']));
        $promoType = $data['promo_type'] ?? 'regular';

        $stmt->bindParam(':promo_type', $promoType);
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

    public function update($id, $data)
    {
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
                    location_id = :location_id,
                    category_id = :category_id,
                    promo_type = :promo_type";

        // Solo actualizamos imagen si se envió una nueva URL
        if (!empty($data['image_url'])) {
            $query .= ", image_url = :image_url";
        }

        $query .= " WHERE id = :id";

        $stmt = $this->db->prepare($query);

        $stmt->bindParam(':promo_type', $data['promo_type']);
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
        $stmt->bindParam(':location_id', $data['location_id']);
        $stmt->bindParam(':category_id', $data['category_id']);

        if (!empty($data['image_url'])) {
            $stmt->bindParam(':image_url', $data['image_url']);
        }

        $stmt->bindParam(':id', $id);

        return $stmt->execute();
    }

    public function delete($id)
    {
        $query = "UPDATE " . $this->table . " SET active = 0 WHERE id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $id);
        return $stmt->execute();
    }

    public function countActiveFeaturedOffersByCompany($companyId)
    {
        $query = "SELECT COUNT(o.id) as total 
                  FROM " . $this->table . " o
                  JOIN locations l ON o.location_id = l.id
                  WHERE l.company_id = :company_id 
                  AND o.status = 'active' 
                  AND o.is_featured = 1
                  AND (o.end_date >= NOW() OR o.end_date IS NULL)";

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':company_id', $companyId);
        $stmt->execute();
        return (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'];
    }

    // Nuevo: Feed Vertical ordenado para 'Historias'
    public function getCompanyStoryFeed($companyId)
    {
        $query = "SELECT 
                  o.*,
                  l.address as location_address,
                  l.latitude, l.longitude,
                  c.name as company_name,
                  c.logo_url as company_logo
                FROM " . $this->table . " o
                JOIN locations l ON o.location_id = l.id
                JOIN companies c ON l.company_id = c.id
                WHERE c.id = :company_id
                AND o.status = 'active'
                AND (o.end_date >= NOW() OR o.end_date IS NULL)
                AND (o.start_date <= NOW() OR o.start_date IS NULL)
                
                ORDER BY 
                    -- Lógica de Prioridad de Historias
                    CASE o.promo_type 
                        WHEN 'flash' THEN 1 
                        WHEN 'day' THEN 2 
                        WHEN 'week' THEN 3 
                        ELSE 4 
                    END ASC,
                    -- Secundario: Destacados primero dentro de su categoría
                    o.is_featured DESC,
                    -- Terciario: Más nuevos primero
                    o.created_at DESC";

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':company_id', $companyId);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
