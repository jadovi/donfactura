<?php

// Autoloader básico para DonFactura DTE API
// Este es un autoloader temporal hasta que se instale Composer

spl_autoload_register(function ($class) {
    // Convertir namespace a path
    $prefix = 'DonFactura\\DTE\\';
    $base_dir = __DIR__ . '/../src/';

    // Verificar si la clase usa nuestro namespace
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    // Obtener el nombre relativo de la clase
    $relative_class = substr($class, $len);

    // Convertir a path del sistema de archivos
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    // Si el archivo existe, incluirlo
    if (file_exists($file)) {
        require $file;
    }
});
