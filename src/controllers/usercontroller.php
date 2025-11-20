<?php
namespace Src\Controllers;

use Src\Models\User;
use Src\Middleware\Auth;

class UserController extends Controller {

    public function index() {
        $user = Auth::handle();
        // Managers no ven lista de usuarios
        if ($user->role === 'manager') {
            $this->jsonResponse(["message" => "Acceso denegado"], 403);
        }

        $userModel = new User($this->db);
        $users = $userModel->getAll($user);
        $this->jsonResponse($users);
    }

    public function getOne($id) {
        $currentUser = Auth::handle();
        $userModel = new User($this->db);
        $targetUser = $userModel->getOne($id);

        if (!$targetUser) $this->jsonResponse(["message" => "Usuario no encontrado"], 404);

        // Seguridad: Owner solo puede ver usuarios de su empresa
        if ($currentUser->role === 'owner') {
            if ($targetUser['company_id'] != $currentUser->company_id) {
                $this->jsonResponse(["message" => "No autorizado"], 403);
            }
        }

        $this->jsonResponse($targetUser);
    }

    public function create() {
        $currentUser = Auth::handle();

        // Validación de permisos inicial
        if ($currentUser->role === 'manager') {
            $this->jsonResponse(["message" => "No puedes crear usuarios"], 403);
        }

        $data = $this->getBody();
        $this->validateRequired($data, ['email', 'password', 'role']);

        $userModel = new User($this->db);

        // 1. Validar Email único
        if ($userModel->checkEmailExists($data['email'])) {
            $this->jsonResponse(["message" => "El email ya está registrado"], 400);
        }

        // 2. Lógica según ROL
        $newUser = [
            'email' => $data['email'],
            'password' => $data['password'],
            'role' => $data['role'],
            'company_id' => null,
            'location_id' => null
        ];

        if ($currentUser->role === 'superadmin') {
            // Superadmin decide todo
            $newUser['company_id'] = $data['company_id'] ?? null;
            $newUser['location_id'] = $data['location_id'] ?? null;
        } 
        else if ($currentUser->role === 'owner') {
            // Owner SOLO puede crear Managers para SUS locales
            if ($data['role'] !== 'manager') {
                $this->jsonResponse(["message" => "Solo puedes crear Encargados (Managers)"], 403);
            }
            // Asignación forzada a su empresa
            $newUser['company_id'] = $currentUser->company_id;
            
            // Validar que envíe un local y sea suyo (podríamos validar extra que el local pertenezca a la company, 
            // pero por ahora confiamos en el select del frontend filtrado)
            if (empty($data['location_id'])) {
                $this->jsonResponse(["message" => "Debes asignar un local al Manager"], 400);
            }
            $newUser['location_id'] = $data['location_id'];
        }

        $id = $userModel->create($newUser);

        if ($id) {
            $this->jsonResponse(["message" => "Usuario creado exitosamente", "id" => $id], 201);
        } else {
            $this->jsonResponse(["message" => "Error al crear usuario"], 500);
        }
    }

    public function update($id) {
        $currentUser = Auth::handle();
        $userModel = new User($this->db);
        
        $targetUser = $userModel->getOne($id);
        if (!$targetUser) $this->jsonResponse(["message" => "Usuario no encontrado"], 404);

        // Seguridad Owner
        if ($currentUser->role === 'owner' && $targetUser['company_id'] != $currentUser->company_id) {
            $this->jsonResponse(["message" => "No autorizado"], 403);
        }

        $data = $this->getBody();

        // Si cambia email, verificar unicidad
        if (!empty($data['email']) && $data['email'] !== $targetUser['email']) {
            if ($userModel->checkEmailExists($data['email'], $id)) {
                $this->jsonResponse(["message" => "El email ya está en uso"], 400);
            }
        }

        // Preparar datos para update
        $updateData = [
            'email' => $data['email'] ?? $targetUser['email'],
            'role' => $data['role'] ?? $targetUser['role'],
            'password' => $data['password'] ?? null // Si viene vacío, el modelo lo ignora
        ];

        // Lógica IDs (similar al create)
        if ($currentUser->role === 'superadmin') {
            if (array_key_exists('company_id', $data)) $updateData['company_id'] = $data['company_id'];
            if (array_key_exists('location_id', $data)) $updateData['location_id'] = $data['location_id'];
        } elseif ($currentUser->role === 'owner') {
            // Owner no puede cambiar el rol a superadmin ni cambiar company
            $updateData['role'] = 'manager'; 
            $updateData['company_id'] = $currentUser->company_id;
            if (array_key_exists('location_id', $data)) $updateData['location_id'] = $data['location_id'];
        }

        if ($userModel->update($id, $updateData)) {
            $this->jsonResponse(["message" => "Usuario actualizado"]);
        } else {
            $this->jsonResponse(["message" => "Error al actualizar"], 500);
        }
    }

    public function delete($id) {
        $currentUser = Auth::handle();
        $userModel = new User($this->db);
        
        $targetUser = $userModel->getOne($id);
        if (!$targetUser) $this->jsonResponse(["message" => "Usuario no encontrado"], 404);

        // Evitar auto-borrado
        if ($currentUser->id == $id) {
            $this->jsonResponse(["message" => "No puedes eliminarte a ti mismo"], 400);
        }

        if ($currentUser->role === 'owner' && $targetUser['company_id'] != $currentUser->company_id) {
            $this->jsonResponse(["message" => "No autorizado"], 403);
        }

        if ($userModel->delete($id)) {
            $this->jsonResponse(["message" => "Usuario eliminado"]);
        } else {
            $this->jsonResponse(["message" => "Error al eliminar"], 500);
        }
    }
}