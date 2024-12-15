<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cuenta Verificada</title>
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
            text-align: center;
        }
        h1 {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 16px;
        }
        p {
            font-size: 18px;
            margin-bottom: 16px;
        }
        a {
            display: inline-block;
            padding: 10px 20px;
            background-color: #34d399; /* Verde para el botón */
            color: #ffffff;
            text-decoration: none;
            border-radius: 5px;
            font-size: 18px;
            margin-bottom: 16px;
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
        <h1>Cuenta Verificada</h1>
        <p>¡Hola, {{ $usuario->nombres }}!</p>
        <p>Tu correo ha sido verificado exitosamente. Ahora puedes acceder a todas las funcionalidades de tu cuenta.</p>

        <p>Gracias por registrarte en nuestro sitio.</p>

        <p class="footer">Saludos,<br>El equipo de ECOMMERCE</p>
    </div>

</body>
</html>
