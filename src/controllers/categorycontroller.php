<?php
namespace Src\Controllers;

use Src\Models\Category;
use Src\Middleware\Auth;

class CategoryController extends Controller {

    public function index() {
        // Verificamos autenticación (Cualquier usuario logueado puede ver categorías)
        /* Auth::handle(); */

        $categoryModel = new Category($this->db);
        $categories = $categoryModel->getAll();

        $this->jsonResponse($categories);
    }

    public function getOne($id) {
        Auth::handle();
        
        $categoryModel = new Category($this->db);
        $category = $categoryModel->getOne($id);

        if ($category) {
            $this->jsonResponse($category);
        } else {
            $this->jsonResponse(["message" => "Categoría no encontrada"], 404);
        }
    }
}