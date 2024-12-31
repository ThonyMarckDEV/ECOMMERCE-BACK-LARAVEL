<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bienvenido al Backend de Ecommerce</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Iconos de FontAwesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gradient-to-r from-white to-black text-gray-800 font-sans">

    <!-- Contenedor principal -->
    <div class="flex flex-col items-center justify-center min-h-screen text-center space-y-6">
        <h1 class="text-5xl font-bold text-white">Bienvenido al Backend de Ecommerce | ThonyMarckDEV</h1>
        <p class="text-xl text-white">Accede a la documentación de la API para obtener más detalles sobre la integración.</p>
        
        <!-- Iconos de Swagger y Laravel -->
        <div class="flex space-x-8">
            <i class="fab fa-swagger text-white text-6xl"></i>
            <i class="fab fa-laravel text-white text-6xl"></i>
        </div>

        <!-- Información de versión -->
        <p class="text-lg text-white">Laravel 11 | PHP 8.2.12 | Swagger para Laravel 11</p>

        <!-- Botón degradado -->
        <a href="{{ url('/api/documentation') }}" target="_blank" class="px-8 py-4 bg-gradient-to-r from-gray-400 to-black text-white font-semibold rounded-lg shadow-lg hover:from-gray-500 hover:to-black transition-colors duration-300 flex items-center justify-center space-x-3">
            <span>Ver Documentación de Swagger</span>
        </a>
    </div>

</body>
</html>
