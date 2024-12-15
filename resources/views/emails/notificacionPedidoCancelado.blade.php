<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notificación de Pedido Cancelado</title>
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
            color: #34d399; /* green-400 */
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
        <h1>Su pedido ha sido cancelado con exito!!</h1>
        <p>Estimado <span class="highlight">{{ $nombreCompleto }}</span>,</p>
        <p>Su pedido con ID <strong class="highlight">{{ $idPedido }}</strong> ha sido cancelado satisfactoriamente.</p>
  
        <p class="footer">Saludos,<br>El equipo de ECOMMERCE</p>
    </div>

</body>
</html>
