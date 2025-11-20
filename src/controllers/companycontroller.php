<?php

namespace Src\Controllers;

use Src\Models\Company;
use Src\Middleware\Auth;

class CompanyController extends Controller
{

    public function index()
    {
        // 1. PROTECCIÓN: Verificamos el token antes de nada
        $user = Auth::handle(); // Esto devuelve los datos del usuario del token (stdClass)

        // 2. Lógica de Negocio
        $companyModel = new Company($this->db);
        $companies = $companyModel->getAll($user);

        // 3. Respuesta
        $this->jsonResponse($companies);
    }

    public function create()
    {
        // 1. PROTECCIÓN
        $user = Auth::handle();

        // 2. Verificar permiso (Solo Superadmin puede crear empresas)
        if ($user->role !== 'superadmin') {
            $this->jsonResponse(["message" => "No tienes permisos para crear empresas"], 403);
        }

        // 3. Obtener y validar datos
        $data = $this->getBody();
        $this->validateRequired($data, ['name']);

        // 4. Guardar en BD
        $companyModel = new Company($this->db);

        // Asignamos el dueño si viene en el post, o null
        $insertData = [
            'name' => $data['name'],
            'owner_id' => $data['owner_id'] ?? null
        ];

        $id = $companyModel->create($insertData);

        if ($id) {
            $this->jsonResponse(["message" => "Empresa creada", "id" => $id], 201);
        } else {
            $this->jsonResponse(["message" => "Error al crear empresa"], 500);
        }
    }
    public function getOne($id)
    {
        $user = Auth::handle();
        $companyModel = new Company($this->db);
        $company = $companyModel->getOne($id);

        if (!$company) {
            $this->jsonResponse(["message" => "Empresa no encontrada"], 404);
        }

        // Seguridad: Owner solo ve SU empresa
        if ($user->role === 'owner' && $company['id'] != $user->company_id) {
            $this->jsonResponse(["message" => "No autorizado"], 403);
        }

        $this->jsonResponse($company);
    }

    public function update($id)
    {
        $user = Auth::handle();

        // Validar permisos: Solo Superadmin puede editar empresas
        // (Opcional: Podrías dejar que el Owner edite nombre/logo, pero aquí restringimos a Superadmin)
        if ($user->role !== 'superadmin') {
            // Si quisieras permitir al Owner:
            // if ($user->role === 'owner' && $user->company_id != $id) error 403...
            $this->jsonResponse(["message" => "Solo Superadmin puede editar empresas"], 403);
        }

        $companyModel = new Company($this->db);
        $company = $companyModel->getOne($id);

        if (!$company) $this->jsonResponse(["message" => "Empresa no encontrada"], 404);

        $data = $this->getBody();

        // Preparamos datos (manteniendo el nombre anterior si no se envía uno nuevo)
        $updateData = [
            'name' => $data['name'] ?? $company['name'],
            // Solo actualizamos owner_id si se envía explícitamente
            'owner_id' => array_key_exists('owner_id', $data) ? $data['owner_id'] : null
        ];

        // Limpieza si owner_id es null para que no sobreescriba si no existe en el array
        if (!array_key_exists('owner_id', $data)) {
            unset($updateData['owner_id']);
        }

        if ($companyModel->update($id, $updateData)) {
            $this->jsonResponse(["message" => "Empresa actualizada"]);
        } else {
            $this->jsonResponse(["message" => "Error al actualizar"], 500);
        }
    }

    public function delete($id)
    {
        $user = Auth::handle();

        if ($user->role !== 'superadmin') {
            $this->jsonResponse(["message" => "Solo Superadmin puede eliminar empresas"], 403);
        }

        $companyModel = new Company($this->db);

        // Verificar existencia
        if (!$companyModel->getOne($id)) {
            $this->jsonResponse(["message" => "Empresa no encontrada"], 404);
        }

        if ($companyModel->delete($id)) {
            $this->jsonResponse(["message" => "Empresa eliminada"]);
        } else {
            $this->jsonResponse(["message" => "Error al eliminar"], 500);
        }
    }
}
