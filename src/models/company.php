<?php

namespace Src\Models;

use PDO;

class Company
{
    private $db;
    private $table = 'companies';

    public function __construct($db)
    {
        $this->db = $db;
    }

    public function getAll($user)
    {
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

    public function create($data)
    {
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

    public function getOne($id)
    {
        $query = "SELECT * FROM " . $this->table . " WHERE id = :id AND is_active = 1 LIMIT 1";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function update($id, $data)
    {
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

    public function delete($id)
    {
        // Borrado lógico
        $query = "UPDATE " . $this->table . " SET is_active = 0 WHERE id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $id);
        return $stmt->execute();
    }

    /* OTHERRRSSSS */

    public function getRecentActivityWithOffers()
    {
        $sql = "SELECT 
                    c.id as company_id, 
                    c.name as company_name, 
                    c.logo_url as company_logo,
                    MAX(o.created_at) as last_update, 
                    COUNT(o.id) as new_offers_count,
                    
                    -- BANDERA FLASH: 1 si tiene oferta flash VIGENTE AHORA (no solo creada hoy)
                    MAX(CASE 
                        WHEN o.promo_type = 'flash' 
                             AND (o.end_date >= NOW() OR o.end_date IS NULL)
                             AND (o.start_date <= NOW() OR o.start_date IS NULL)
                        THEN 1 
                        ELSE 0 
                    END) as has_flash_offer,

                    -- Subquery: ID de la oferta para navegar
                    (
                        SELECT o2.id 
                        FROM offers o2 
                        JOIN locations l2 ON o2.location_id = l2.id 
                        WHERE l2.company_id = c.id 
                        AND o2.status = 'active'
                        -- Validamos vigencia en la subquery también
                        AND (o2.end_date >= NOW() OR o2.end_date IS NULL)
                        AND (o2.start_date <= NOW() OR o2.start_date IS NULL)
                        -- Prioridad: Flash primero, luego la más nueva por creación
                        ORDER BY (o2.promo_type = 'flash') DESC, o2.created_at DESC 
                        LIMIT 1
                    ) as latest_offer_id

                FROM companies c
                JOIN locations l ON c.id = l.company_id
                JOIN offers o ON l.id = o.location_id
                
                WHERE o.status = 'active'
                AND c.is_active = 1
                
                -- FILTRO DE VIGENCIA: Solo mostramos empresas con ofertas válidas EN ESTE MOMENTO
                -- (Si la oferta venció hace 1 hora, desaparece. Si empieza mañana, no sale todavía)
                AND (o.end_date >= NOW() OR o.end_date IS NULL)
                AND (o.start_date <= NOW() OR o.start_date IS NULL)

                GROUP BY c.id
                
                -- ORDEN FINAL (La magia del 'Always On'):
                -- 1. Primero las que tienen Flash Activo (has_flash_offer = 1)
                -- 2. Luego las que publicaron algo más recientemente (last_update)
                ORDER BY has_flash_offer DESC, last_update DESC
                LIMIT 15";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    public function canCreateLocation($companyId)
    {
        $company = $this->getOne($companyId);
        if (!$company) return false;

        // 1. Determinar el límite
        $limit = 3; // Default Basic
        if ($company['plan'] === 'premium') $limit = 15;
        if (!is_null($company['custom_branch_limit'])) {
            $limit = $company['custom_branch_limit'];
        }

        // 2. Contar locales actuales (asumiendo tabla 'locations')
        $query = "SELECT COUNT(*) as total FROM locations WHERE company_id = :id AND active = 1"; // Ajusta 'active' si usas borrado lógico diferente
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $companyId);
        $stmt->execute();
        $current = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

        return $current < $limit;
    }
}
