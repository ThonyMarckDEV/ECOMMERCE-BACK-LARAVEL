<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dirección Eliminada</title>
    <style>
        body, p, h1, .footer {
            color: #ffffff !important; /* Asegura que todo el texto sea blanco */
            font-family: sans-serif;
        }
        body {
            background-color: #000000;
        }
        .container {
            max-width: 672px;
            margin: 0 auto;
            padding: 24px;
            background-color: #2d2d2d;
            border-radius: 12px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.3);
        }
        h1 {
            font-size: 24px;
            font-weight: 600;
            text-align: center;
            margin-bottom: 16px;
        }
        p {
            font-size: 18px;
            margin-bottom: 16px;
        }
        .highlight {
            color: #34d399; /* Verde para destacar */
        }
        .footer {
            font-size: 12px;
            text-align: center;
            margin-top: 24px;
        }
    </style>
</head>
<body>

    <div class="container">
        <h1>Dirección Eliminada</h1>
        <p>La siguiente dirección ha sido eliminada de tu cuenta:</p>
        
        <p><strong class="highlight">Dirección completa:</strong> {{ $direccion->direccion }}</p>
        
        <p>Si no reconoces esta acción, por favor contacta con el soporte.</p>

        <p class="footer">Saludos,<br>El equipo de ECOMMERCE</p>
    </div>

</body>
</html>
