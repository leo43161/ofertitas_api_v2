<?php
namespace Src\Controllers;

use Src\Models\Location;
use Src\Middleware\Auth;

class LocationController extends Controller {

    public function index() {
        $user = Auth::handle();
        $locationModel = new Location($this->db);
        $locations = $locationModel->getAll($user);
        $this->jsonResponse($locations);
    }

    public function getOne($id) {
        $user = Auth::handle();
        $locationModel = new Location($this->db);
        $location = $locationModel->getOne($id);

        if (!$location) {
            $this->jsonResponse(["message" => "Local no encontrado"], 404);
        }

        // Seguridad: Verificar que el Owner sea dueño de este local
        if ($user->role === 'owner' && $location['company_id'] != $user->company_id) {
            $this->jsonResponse(["message" => "No autorizado"], 403);
        }

        $this->jsonResponse($location);
    }

    public function create() {
        $user = Auth::handle();
        
        // Managers no pueden crear locales
        if ($user->role === 'manager') {
            $this->jsonResponse(["message" => "Permiso denegado"], 403);
        }

        $data = $this->getBody();
        $this->validateRequired($data, ['address', 'latitude', 'longitude']);

        // Lógica de Company ID
        $companyId = null;
        if ($user->role === 'superadmin') {
            if (empty($data['company_id'])) {
                $this->jsonResponse(["message" => "Superadmin debe especificar company_id"], 400);
            }
            $companyId = $data['company_id'];
        } else {
            // Owner: Forzamos su ID
            $companyId = $user->company_id;
        }

        $locationModel = new Location($this->db);
        $insertData = array_merge($data, ['company_id' => $companyId]);

        $id = $locationModel->create($insertData);

        if ($id) {
            $this->jsonResponse(["message" => "Local creado", "id" => $id], 201);
        } else {
            $this->jsonResponse(["message" => "Error al crear local"], 500);
        }
    }

    public function update($id) {
        $user = Auth::handle();
        $locationModel = new Location($this->db);
        
        // Verificar existencia y propiedad
        $location = $locationModel->getOne($id);
        if (!$location) $this->jsonResponse(["message" => "Local no encontrado"], 404);
        
        if ($user->role === 'owner' && $location['company_id'] != $user->company_id) {
            $this->jsonResponse(["message" => "No autorizado"], 403);
        }
        if ($user->role === 'manager') { // Managers no editan locales, solo ofertas
             $this->jsonResponse(["message" => "Permiso denegado"], 403);
        }

        $data = $this->getBody();
        // Validamos mínimos para update
        if(empty($data['address'])) $data['address'] = $location['address'];
        if(empty($data['latitude'])) $data['latitude'] = $location['latitude'];
        if(empty($data['longitude'])) $data['longitude'] = $location['longitude'];

        if ($locationModel->update($id, $data)) {
            $this->jsonResponse(["message" => "Local actualizado"]);
        } else {
            $this->jsonResponse(["message" => "Error al actualizar"], 500);
        }
    }

    public function delete($id) {
        $user = Auth::handle();
        $locationModel = new Location($this->db);
        
        $location = $locationModel->getOne($id);
        if (!$location) $this->jsonResponse(["message" => "Local no encontrado"], 404);

        if ($user->role === 'owner' && $location['company_id'] != $user->company_id) {
            $this->jsonResponse(["message" => "No autorizado"], 403);
        }
        if ($user->role === 'manager') {
             $this->jsonResponse(["message" => "Permiso denegado"], 403);
        }

        if ($locationModel->delete($id)) {
            $this->jsonResponse(["message" => "Local eliminado (Lógico)"]);
        } else {
            $this->jsonResponse(["message" => "Error al eliminar"], 500);
        }
    }
}