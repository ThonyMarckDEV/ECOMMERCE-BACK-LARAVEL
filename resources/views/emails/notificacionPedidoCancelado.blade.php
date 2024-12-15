<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notificación de Pedido Cancelado</title>
    <style>
        body, p, h1, .footer, .highlight {
            color: #ffffff !important; /* Asegura que todo el texto sea blanco */
            font-family: sans-serif;
        }
        body {
            background-color: #000000;
            margin: 0;
            padding: 0;
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
        h3 {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 16px;
            color: #34d399; /* Verde para la sección de detalles */
        }
        p {
            font-size: 18px;
            margin-bottom: 16px;
        }
        .highlight {
            color: #34d399; /* Verde para destacar el nombre y el ID */
        }
        .footer {
            font-size: 14px;
            text-align: center;
            margin-top: 24px;
        }
        .button {
            display: inline-block;
            padding: 12px 24px;
            background-color: #ff9800; /* Naranja para llamar la atención */
            color: #ffffff;
            text-decoration: none;
            border-radius: 5px;
            font-size: 16px;
            margin-top: 20px;
        }
    </style>
</head>
<body>

    <div class="container">
        <h3>¡Su pedido ha sido cancelado!</h3>
        <p>Estimado <span class="highlight">{{ $nombreCompleto }}</span>,</p>
        <p>Le informamos que el pedido con ID <strong class="highlight">{{ $idPedido }}</strong> ha sido cancelado satisfactoriamente.</p>
        <p>Si tiene alguna duda o desea realizar un nuevo pedido, no dude en ponerse en contacto con nosotros.</p>

        <p class="footer">Saludos,<br>El equipo de ECOMMERCE</p>
    </div>

</body>
</html>
