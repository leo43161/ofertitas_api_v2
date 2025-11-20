<?php
spl_autoload_register(function ($className) {
    // Definimos un mapa de "Prefijo Namespace" => "Carpeta Base"
    $namespaces = [
        'Src\\' => __DIR__ . '/src/',
        'Firebase\\JWT\\' => __DIR__ . '/src/lib/jwt/' // Mapeo manual para la librería
    ];

    foreach ($namespaces as $prefix => $base_dir) {
        // ¿La clase usa este prefijo?
        $len = strlen($prefix);
        if (strncmp($prefix, $className, $len) === 0) {
            
            // Obtener nombre relativo
            $relative_class = substr($className, $len);
            
            // Preparar ruta archivo
            $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
            
            // Truco para Windows/Linux: Intentar tal cual (Mayúsculas) O en minúsculas
            if (file_exists($file)) {
                require $file;
                return;
            }
            
            // Intento en minúsculas (para tu estructura actual)
            $lowerFile = $base_dir . strtolower(str_replace('\\', '/', $relative_class)) . '.php';
            if (file_exists($lowerFile)) {
                require $lowerFile;
                return;
            }
        }
    }
});