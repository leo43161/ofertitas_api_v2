<?php

namespace Src\Controllers;

use Src\Models\Offer;
use Src\Models\Location;
use Src\Models\Company;
use Src\Middleware\Auth;
use Src\Utils\ImageUploader;

class OfferController extends Controller
{

    public function index()
    {
        /* $headers = apache_request_headers();
        $hasAuth = isset($headers['Authorization']); */
        $user = null;
        if (isset($_COOKIE['token'])) {
            $user = Auth::handle(); // Manejo de error silencioso o explícito
        }
        // Capturar parámetros de URL (Query Params)
        // Ejemplo: /offers?page=1&limit=10
        $params = [
            'page' => $_GET['page'] ?? 1,
            'limit' => $_GET['limit'] ?? 10,
            'paginate' => true, // Activamos paginación para la API
            'search' => $_GET['search'] ?? null,
            'category_id' => $_GET['category_id'] ?? null
        ];

        // Si es el panel de admin (usualmente no manda ?page=), podemos desactivar paginación
        // o adaptarlo. Por seguridad de la APP, forzamos paginación si no hay usuario admin.
        if ($user && ($user->role === 'owner' || $user->role === 'superadmin') && !isset($_GET['page'])) {
            $params['paginate'] = false; // El panel ve todo
        }

        $offerModel = new Offer($this->db);
        $offers = $offerModel->getAll($user, $params);

        $this->jsonResponse($offers);
    }

    public function getOne($id)
    {
        /* $headers = apache_request_headers();
        $hasAuth = isset($headers['Authorization']);
        $user = null;
        if ($hasAuth) {
            $user = Auth::handle(); // Manejo de error silencioso o explícito
        } */
        $offerModel = new Offer($this->db);
        $offer = $offerModel->getOne($id);

        if (!$offer) $this->jsonResponse(["message" => "Oferta no encontrada"], 404);

        // Seguridad
        /* $this->checkPermissions($user, $offer); */

        $this->jsonResponse($offer);
    }

    public function create()
    {
        $user = Auth::handle();
        $data = $_POST;
        $files = $_FILES;

        $this->validateRequired($data, ['title', 'location_id', 'category_id', 'discount_text']);

        // 1. Validar Permisos sobre el Local
        $locationModel = new Location($this->db);
        $location = $locationModel->getOne($data['location_id']);

        if (!$location) $this->jsonResponse(["message" => "Local no válido"], 404);

        if ($user->role === 'owner' && $location['company_id'] != $user->company_id) {
            $this->jsonResponse(["message" => "No puedes crear ofertas en locales ajenos"], 403);
        }
        if ($user->role === 'manager' && $user->location_id != $data['location_id']) {
            $this->jsonResponse(["message" => "Solo puedes crear ofertas en tu local asignado"], 403);
        }

        // 2. Datos de la Empresa y Modelos
        $companyModel = new Company($this->db);
        $company = $companyModel->getOne($location['company_id']); // Usamos ID del local por seguridad
        $offerModel = new Offer($this->db);

        // --- VALIDACIÓN A: LÍMITE DE OFERTAS POR LOCAL (Max 4 para todos) ---
        $activeOffers = $offerModel->countActiveOffersByLocation($data['location_id']);
        $limit = 4; // Default Basic
        if ($company['plan'] === 'premium') {
            $limit = 20; // O infinito (9999)
        }
        if ($activeOffers >= $limit) {
            $this->jsonResponse(["message" => "Este local ha alcanzado el límite de 4 ofertas activas simultáneas."], 403);
            return;
        }

        // --- VALIDACIÓN B: DESTACADOS (Solo Premium, Max 2 por Empresa) ---
        $isFeatured = isset($data['is_featured']) ? (int)$data['is_featured'] : 0;

        if ($isFeatured) {
            // 1. Definir límites según el plan
            $limitFeatured = 1; // Default (Basic)

            if ($company['plan'] === 'premium') {
                $limitFeatured = 2; // Premium tiene más cupo
            } elseif ($company['plan'] === 'enterprise') {
                $limitFeatured = 10; // Enterprise (ejemplo)
            }

            // 2. Contar cuántas tiene activas HOY esa empresa
            $activeFeatured = $offerModel->countActiveFeaturedOffersByCompany($company['id']);

            // 3. Validar
            if ($activeFeatured >= $limitFeatured) {
                $this->jsonResponse([
                    "message" => "Límite alcanzado. Tu plan '{$company['plan']}' permite máximo {$limitFeatured} oferta(s) destacada(s) simultáneas."
                ], 403);
                return;
            }
        }

        // 3. Manejo de Imagen
        $imageUrl = null;
        if (isset($files['image_url'])) {
            try {
                $imageUrl = ImageUploader::upload($files['image_url'], 'offers');
            } catch (\Exception $e) {
                $this->jsonResponse(["message" => $e->getMessage()], 400);
            }
        }

        // 4. Insertar
        $offerData = [
            'location_id' => $data['location_id'],
            'category_id' => $data['category_id'],
            'title' => $data['title'],
            'description' => $data['description'] ?? '',
            'discount_text' => $data['discount_text'],
            'price_normal' => $data['price_normal'] ?? 0,
            'price_offer' => $data['price_offer'] ?? 0,
            'start_date' => $data['start_date'] ?? null,
            'end_date' => $data['end_date'] ?? null,
            'is_visible' => $data['is_visible'] ?? 1,
            'is_featured' => $isFeatured, // Usamos la variable validada
            'image_url' => $imageUrl,
            'promo_type' => $data['promo_type'] ?? 'regular'
        ];

        $id = $offerModel->create($offerData);

        if ($id) {
            $this->jsonResponse(["message" => "Oferta creada exitosamente", "id" => $id], 201);
        } else {
            $this->jsonResponse(["message" => "Error al crear oferta"], 500);
        }
    }

    public function update($id)
    {
        $user = Auth::handle();
        $offerModel = new Offer($this->db);
        $offer = $offerModel->getOne($id);
        $companyModel = new Company($this->db);
        $company = $companyModel->getOne($offer['company_id']);

        $newIsFeatured = isset($data['is_featured']) ? (int)$data['is_featured'] : $offer['is_featured'];

        if ($newIsFeatured == 1 && $offer['is_featured'] == 0) {

            $limitFeatured = 1; // Límite Basic
            if ($company['plan'] === 'premium') $limitFeatured = 3;
            if ($company['plan'] === 'enterprise') $limitFeatured = 10;

            $activeFeatured = $offerModel->countActiveFeaturedOffersByCompany($company['id']);

            if ($activeFeatured >= $limitFeatured) {
                $this->jsonResponse([
                    "message" => "No puedes destacar esta oferta. Tu plan '{$company['plan']}' ya tiene {$limitFeatured} destacada(s) activa(s)."
                ], 403);
                return;
            }
        }

        if (!$offer) $this->jsonResponse(["message" => "Oferta no encontrada"], 404);

        // Seguridad: Verificar permisos
        $this->checkPermissions($user, $offer);

        $isMakingVisible = isset($data['is_visible']) && (int)$data['is_visible'] === 1;

        // Si la oferta estaba oculta/vencida y ahora la quieren activar...
        if ($isMakingVisible && $offer['is_visible'] == 0) {
            $activeOffers = $offerModel->countActiveOffersByLocation($offer['location_id']);
            // Chequeamos límite (Hardcodeado a 4 o dinámico según plan)
            // Ojo: countActiveOffersByLocation cuenta las activas actuales.
            if ($activeOffers >= 4) {
                $this->jsonResponse(["message" => "No puedes activar esta oferta. Límite de 4 activas alcanzado."], 403);
                return;
            }
        }

        // Datos (POST y FILES)
        $data = $_POST;
        $files = $_FILES;

        // Manejo de Imagen (Solo si se sube una nueva)
        $imageUrl = null;
        if (isset($files['image_url']) && $files['image_url']['size'] > 0) {
            try {
                $imageUrl = ImageUploader::upload($files['image_url'], 'offers');
            } catch (\Exception $e) {
                $this->jsonResponse(["message" => $e->getMessage()], 400);
            }
        }

        // Preparar datos para el Modelo
        // Nota: Usamos el operador ?? o verificamos isset para permitir actualizaciones parciales
        $updateData = [
            'title' => $data['title'] ?? $offer['title'],
            'description' => $data['description'] ?? $offer['description'],
            'discount_text' => $data['discount_text'] ?? $offer['discount_text'],
            'price_normal' => $data['price_normal'] ?? $offer['price_normal'],
            'price_offer' => $data['price_offer'] ?? $offer['price_offer'],
            'start_date' => !empty($data['start_date']) ? $data['start_date'] : null,
            'end_date' => !empty($data['end_date']) ? $data['end_date'] : null,
            'is_visible' => isset($data['is_visible']) ? (int)$data['is_visible'] : $offer['is_visible'],
            'is_featured' => isset($data['is_featured']) ? (int)$data['is_featured'] : $offer['is_featured'],
            'category_id' => $data['category_id'] ?? $offer['category_id'],
            'location_id' => $data['location_id'] ?? $offer['location_id'],
            // Si $imageUrl es null, el modelo debería ignorarlo o manejarlo (verificaremos el modelo abajo)
            'image_url' => $imageUrl,
            'promo_type' => $data['promo_type'] ?? $offer['promo_type'] ?? 'regular'
        ];

        if ($offerModel->update($id, $updateData)) {
            $this->jsonResponse(["message" => "Oferta actualizada"]);
        } else {
            $this->jsonResponse(["message" => "Error al actualizar"], 500);
        }
    }

    public function getCompanyFeed($companyId)
    {
        $offerModel = new Offer($this->db);
        $feed = $offerModel->getCompanyStoryFeed($companyId);
        $this->jsonResponse($feed);
    }
    public function getCompanyFeedAll()
    {
        $companyModel = new Company($this->db);
        $companies = $companyModel->getAll(null);
        $this->jsonResponse($companies);
    }

    public function delete($id)
    {
        $user = Auth::handle();
        $offerModel = new Offer($this->db);
        $offer = $offerModel->getOne($id);

        if (!$offer) $this->jsonResponse(["message" => "Oferta no encontrada"], 404);

        $this->checkPermissions($user, $offer);

        if ($offerModel->delete($id)) {
            $this->jsonResponse(["message" => "Oferta eliminada"]);
        } else {
            $this->jsonResponse(["message" => "Error al eliminar"], 500);
        }
    }

    // Helper privado para chequear permisos de edición/borrado
    private function checkPermissions($user, $offer)
    {
        if ($user->role === 'superadmin') return true;

        // Si es manager, debe coincidir el location_id de la oferta con el suyo
        if ($user->role === 'manager') {
            if ($offer['location_id'] != $user->location_id) {
                $this->jsonResponse(["message" => "No autorizado (Manager)"], 403);
            }
        }
        // Si es owner, debe coincidir el company_id (que trajimos en getOne) con el suyo
        if ($user->role === 'owner') {
            // Nota: en create/update validamos antes, aquí validamos sobre la oferta existente
            if ($offer['company_id'] != $user->company_id) {
                $this->jsonResponse(["message" => "No autorizado (Owner)"], 403);
            }
        }
    }
}
