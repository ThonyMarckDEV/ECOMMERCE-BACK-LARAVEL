<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificación de Correo</title>
    <style>
        body {
            background-color: #000000;
            color: #ffffff;
            font-family: sans-serif;
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
            background-color: #4CAF50;
            color: white;
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
        <h1>¡Hola, {{ $user->nombres }}!</h1>
        <p>Gracias por registrarte en nuestra plataforma. Por favor, haz clic en el botón de abajo para verificar tu correo electrónico:</p>
        <a href="{{ $url }}">
            Verificar correo
        </a>
        <p>Si no solicitaste esta verificación, ignora este correo.</p>

        <p class="footer">Saludos,<br>El equipo de ECOMMERCE</p>
    </div>

</body>
</html>
