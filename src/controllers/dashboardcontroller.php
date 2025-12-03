<?php
namespace Src\Controllers;

use Src\Middleware\Auth;
use PDO;

class DashboardController extends Controller {

    public function getStats() {
        $user = Auth::handle(); // Obtener usuario logueado con sus datos (incluyendo company_id)
        
        $response = [
            'total_offers' => 0,
            'active_offers' => 0,
            'total_locations' => 0,
            'recent_offers' => [],
            'top_categories' => []
        ];

        // --- FILTROS DE SEGURIDAD ---
        $whereOffers = "";
        $whereLocations = "";
        $params = [];

        if ($user->role === 'owner') {
            // CORRECCIÓN: Usamos directamente el company_id del usuario logueado.
            // Si el usuario tiene company_id (como se ve en tu SQL), usamos ese.
            // Si no, intentamos buscar si es dueño en la tabla companies.
            $companyId = $user->company_id;

            if (!$companyId) {
                // Fallback: Si no tiene company_id asignado, buscamos si posee alguna empresa
                $stmt = $this->db->prepare("SELECT id FROM companies WHERE owner_id = :uid LIMIT 1");
                $stmt->execute([':uid' => $user->id]);
                $res = $stmt->fetch(PDO::FETCH_ASSOC);
                $companyId = $res ? $res['id'] : null;
            }

            if ($companyId) {
                // Filtramos las ofertas que pertenecen a locales de ESTA empresa
                $whereOffers = " WHERE location_id IN (SELECT id FROM locations WHERE company_id = :company_id)";
                $whereLocations = " WHERE company_id = :company_id";
                $params[':company_id'] = $companyId;
            } else {
                // Si es owner pero no tiene empresa asignada, devolvemos ceros
                $this->jsonResponse($response);
                return;
            }

        } elseif ($user->role === 'manager') {
            if ($user->location_id) {
                $whereOffers = " WHERE location_id = :location_id";
                $whereLocations = " WHERE id = :location_id";
                $params[':location_id'] = $user->location_id;
            } else {
                // Manager sin local asignado
                $this->jsonResponse($response);
                return;
            }
        }

        // 1. Contadores Básicos
        // Total Ofertas
        $queryTotal = "SELECT COUNT(*) as total FROM offers $whereOffers";
        $stmt = $this->db->prepare($queryTotal);
        $stmt->execute($params);
        $response['total_offers'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

        // Ofertas Activas
        $activeClause = empty($whereOffers) ? "WHERE is_visible = 1" : "$whereOffers AND is_visible = 1";
        $stmt = $this->db->prepare("SELECT COUNT(*) as total FROM offers $activeClause");
        $stmt->execute($params);
        $response['active_offers'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

        // Total Locales
        $stmt = $this->db->prepare("SELECT COUNT(*) as total FROM locations $whereLocations");
        $stmt->execute($params);
        $response['total_locations'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

        // 2. Ofertas Recientes (Últimas 5)
        // Nota: Ajustamos el JOIN para que funcione con los filtros
        $queryRecent = "
            SELECT o.id, o.title, o.price_offer, o.created_at, c.name as company_name 
            FROM offers o
            JOIN locations l ON o.location_id = l.id
            JOIN companies c ON l.company_id = c.id
            $whereOffers
            ORDER BY o.created_at DESC
            LIMIT 5
        ";
        $stmt = $this->db->prepare($queryRecent);
        $stmt->execute($params);
        $response['recent_offers'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 3. Categorías (Solo mostramos gráfica global para Superadmin, o filtrada si quisieras)
        // Para simplificar y evitar errores de SQL GROUP BY con filtros complejos, 
        // la gráfica de categorías la dejamos global o solo para superadmin por ahora.
        if ($user->role === 'superadmin') {
            $queryCats = "
                SELECT cat.name, COUNT(o.id) as count 
                FROM categories cat
                LEFT JOIN offers o ON cat.id = o.category_id
                GROUP BY cat.id
                ORDER BY count DESC
                LIMIT 4
            ";
            $stmt = $this->db->prepare($queryCats);
            $stmt->execute();
            $response['top_categories'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            // Para Owners/Managers, podríamos hacer una query filtrada, 
            // pero devolvemos vacío por ahora para asegurar rendimiento
            $response['top_categories'] = []; 
        }

        $this->jsonResponse($response);
    }
}