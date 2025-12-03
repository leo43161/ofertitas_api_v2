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
        $user = Auth::handle();
        $offerModel = new Offer($this->db);
        $offers = $offerModel->getAll($user);
        $this->jsonResponse($offers);
    }

    public function getOne($id)
    {
        $user = Auth::handle();
        $offerModel = new Offer($this->db);
        $offer = $offerModel->getOne($id);

        if (!$offer) $this->jsonResponse(["message" => "Oferta no encontrada"], 404);

        // Seguridad
        $this->checkPermissions($user, $offer);

        $this->jsonResponse($offer);
    }

    public function create()
    {
        $user = Auth::handle();

        // Nota: Al usar FormData, los datos vienen en $_POST, no en php://input
        $data = $_POST;
        $files = $_FILES;

        $this->validateRequired($data, ['title', 'location_id', 'category_id', 'discount_text']);

        // Validar permiso sobre el Local
        // Necesitamos saber si el usuario tiene permiso de publicar en ESE local
        $locationModel = new Location($this->db);
        $location = $locationModel->getOne($data['location_id']);

        if (!$location) $this->jsonResponse(["message" => "Local no válido"], 404);

        if ($user->role === 'owner' && $location['company_id'] != $user->company_id) {
            $this->jsonResponse(["message" => "No puedes crear ofertas en locales ajenos"], 403);
        }
        if ($user->role === 'manager' && $user->location_id != $data['location_id']) {
            $this->jsonResponse(["message" => "Solo puedes crear ofertas en tu local asignado"], 403);
        }

        $companyModel = new Company($this->db);
        $company = $companyModel->getOne($user->company_id);

        if (!$company) {
            $this->jsonResponse(["message" => "La empresa no existe", "company_id" => $data], 404);
            return;
        }
        $offerModel = new Offer($this->db);

        $activeOffers = $offerModel->countActiveOffersByLocation($data['location_id']);
        if ($company['plan'] === 'basic') {
            $activeOffers = $offerModel->countActiveOffersByLocation($data['location_id']);

            // Límite: 5 ofertas
            if ($activeOffers >= 5) {
                $this->jsonResponse(["message" => "Límite alcanzado. El plan Básico permite máximo 5 ofertas activas por local. Actualiza a Premium para más."], 403);
                return;
            }
        }

        // Manejo de Imagen
        $imageUrl = null;
        if (isset($files['image_url'])) {
            try {
                $imageUrl = ImageUploader::upload($files['image_url'], 'offers');
            } catch (\Exception $e) {
                $this->jsonResponse(["message" => $e->getMessage()], 400);
            }
        }

        // Preparar Array final
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
            'is_featured' => $data['is_featured'] ?? 0,
            'image_url' => $imageUrl
        ];

        $id = $offerModel->create($offerData);

        if ($id) {
            $this->jsonResponse(["message" => "Oferta creada", "id" => $id, "activeOffers" => $activeOffers], 201);
        } else {
            $this->jsonResponse(["message" => "Error al crear oferta"], 500);
        }
    }

    public function update($id)
    {
        $user = Auth::handle();
        $offerModel = new Offer($this->db);
        $offer = $offerModel->getOne($id);

        if (!$offer) $this->jsonResponse(["message" => "Oferta no encontrada"], 404);

        // Seguridad: Verificar permisos
        $this->checkPermissions($user, $offer);

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
            'image_url' => $imageUrl
        ];

        if ($offerModel->update($id, $updateData)) {
            $this->jsonResponse(["message" => "Oferta actualizada"]);
        } else {
            $this->jsonResponse(["message" => "Error al actualizar"], 500);
        }
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
