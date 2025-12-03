<?php

namespace Src\Controllers;

use Src\Models\Company;
use Src\Middleware\Auth;
use Src\Utils\ImageUploader;

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

    // Helper privado para generar slugs (Ej: "Mi Pizzería" -> "mi-pizzeria")
    private function createSlug($text)
    {
        $text = preg_replace('~[^\pL\d]+~u', '-', $text);
        $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
        $text = preg_replace('~[^-\w]+~', '', $text);
        $text = trim($text, '-');
        $text = strtolower($text);
        return empty($text) ? 'n-a' : $text;
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

    public function create()
    {
        // 1. PROTECCIÓN
        $user = Auth::handle();

        if ($user->role !== 'superadmin') {
            $this->jsonResponse(["message" => "No tienes permisos para crear empresas"], 403);
        }

        // 2. Obtener datos (Usamos POST y FILES para soportar FormData e imágenes)
        $data = $_POST;
        $files = $_FILES;

        $this->validateRequired($data, ['name']);

        // 3. Manejo de Imágenes
        $logoUrl = null;
        if (isset($files['logo']) && $files['logo']['size'] > 0) {
            $logoUrl = ImageUploader::upload($files['logo'], 'companies/logos');
        }

        $coverUrl = null;
        if (isset($files['cover']) && $files['cover']['size'] > 0) {
            $coverUrl = ImageUploader::upload($files['cover'], 'companies/covers');
        }

        // 4. Preparar datos
        $companyModel = new Company($this->db);

        $insertData = [
            'name' => $data['name'],
            'owner_id' => !empty($data['owner_id']) ? $data['owner_id'] : null,
            'slug' => $this->createSlug($data['name']),
            'description' => $data['description'] ?? null,
            'website' => $data['website'] ?? null,
            'logo_url' => $logoUrl,
            'cover_url' => $coverUrl
        ];

        $id = $companyModel->create($insertData);

        if ($id) {
            $this->jsonResponse(["message" => "Empresa creada", "id" => $id], 201);
        } else {
            $this->jsonResponse(["message" => "Error al crear empresa"], 500);
        }
    }

    public function update($id)
    {
        $user = Auth::handle();


        $companyModel = new Company($this->db);
        $company = $companyModel->getOne($id);

        if (!$company) $this->jsonResponse(["message" => "Empresa no encontrada"], 404);


        if ($user->role !== 'superadmin' && $user->role === 'manager' && $company['id'] != $user->company_id) {
            $this->jsonResponse(["message" => "Solo Superadmin puede editar empresas"], 403);
        }
        // Usamos POST/FILES para update también
        $data = $_POST;
        $files = $_FILES;

        // Manejo de nuevas imágenes (si no se envían, quedan null y el modelo no las toca)
        $logoUrl = null;
        if (isset($files['logo']) && $files['logo']['size'] > 0) {
            $logoUrl = ImageUploader::upload($files['logo'], 'companies/logos');
        }

        $coverUrl = null;
        if (isset($files['cover']) && $files['cover']['size'] > 0) {
            $coverUrl = ImageUploader::upload($files['cover'], 'companies/covers');
        }

        $updateData = [
            'name' => $data['name'] ?? $company['name'],
            'description' => $data['description'] ?? $company['description'],
            'website' => $data['website'] ?? $company['website'],
            'owner_id' => !empty($data['owner_id']) ? $data['owner_id'] : null
        ];

        // Si cambia el nombre, actualizamos el slug
        if (isset($data['name']) && $data['name'] !== $company['name']) {
            $updateData['slug'] = $this->createSlug($data['name']);
        }

        // Solo agregamos URLs si se subieron archivos nuevos
        if ($logoUrl) $updateData['logo_url'] = $logoUrl;
        if ($coverUrl) $updateData['cover_url'] = $coverUrl;

        // Limpieza de owner_id si no venía en el request original para evitar sobreescritura accidental
        if (!isset($data['owner_id'])) unset($updateData['owner_id']);

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
