<?php
namespace Src\Controllers;

use Src\Models\Offer;
use Src\Models\Location;
use Src\Middleware\Auth;
use Src\Utils\ImageUploader;

class OfferController extends Controller {

    public function index() {
        $user = Auth::handle();
        $offerModel = new Offer($this->db);
        $offers = $offerModel->getAll($user);
        $this->jsonResponse($offers);
    }

    public function getOne($id) {
        $user = Auth::handle();
        $offerModel = new Offer($this->db);
        $offer = $offerModel->getOne($id);

        if (!$offer) $this->jsonResponse(["message" => "Oferta no encontrada"], 404);

        // Seguridad
        $this->checkPermissions($user, $offer);

        $this->jsonResponse($offer);
    }

    public function create() {
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

        // Manejo de Imagen
        $imageUrl = null;
        if (isset($files['image'])) {
            try {
                $imageUrl = ImageUploader::upload($files['image'], 'offers');
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

        $offerModel = new Offer($this->db);
        $id = $offerModel->create($offerData);

        if ($id) {
            $this->jsonResponse(["message" => "Oferta creada", "id" => $id], 201);
        } else {
            $this->jsonResponse(["message" => "Error al crear oferta"], 500);
        }
    }

    public function delete($id) {
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
    private function checkPermissions($user, $offer) {
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