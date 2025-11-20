<?php
namespace Src\Utils;

class ImageUploader {
    
    // Guarda la imagen y devuelve la URL relativa o false si falla
    public static function upload($file, $folder = 'offers') {
        // 1. Validaciones básicas
        if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
            return null;
        }

        $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/jpg'];
        if (!in_array($file['type'], $allowedTypes)) {
            throw new \Exception("Tipo de archivo no permitido. Solo JPG, PNG o WEBP.");
        }

        // 2. Preparar directorio (dentro de public/uploads/...)
        // Ajusta esta ruta según donde quieras que se guarden físicamente
        $targetDir = __DIR__ . '/../../public/uploads/' . $folder . '/';
        
        if (!file_exists($targetDir)) {
            mkdir($targetDir, 0777, true);
        }

        // 3. Generar nombre único (evita colisiones y problemas con espacios)
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $fileName = uniqid() . '_' . time() . '.' . $extension;
        $targetFile = $targetDir . $fileName;

        // 4. Mover archivo
        if (move_uploaded_file($file['tmp_name'], $targetFile)) {
            // Devuelve la ruta pública para guardar en BD
            // Asumiendo que tu dominio apunta a public/
            return '/uploads/' . $folder . '/' . $fileName;
        }

        throw new \Exception("Error al mover el archivo subido.");
    }
}