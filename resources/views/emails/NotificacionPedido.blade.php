<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirmación de Pedido</title>
    <style>
        body, p, h1, .footer, .highlight, .price {
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
        h1 {
            font-size: 26px;
            font-weight: 600;
            margin-bottom: 24px;
            color: #4caf50; /* Verde para el encabezado de confirmación */
        }
        p {
            font-size: 18px;
            margin-bottom: 16px;
        }
        h3 {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 16px;
            color: #34d399; /* Verde para la sección de detalles */
        }
        .highlight {
            color: #34d399; /* Verde para resaltar el nombre y detalles */
        }
        .price {
            font-weight: bold;
        }
        ul {
            list-style: none;
            padding: 0;
            margin-bottom: 16px;
        }
        ul li {
            font-size: 18px;
            margin-bottom: 8px;
        }
        .footer {
            font-size: 14px;
            text-align: center;
            margin-top: 24px;
        }
        .button {
            display: inline-block;
            padding: 12px 24px;
            background-color: #ff9800; /* Naranja para el botón */
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
        <h1>¡Confirmación de Pedido!</h1>
        <p>Estimado Cliente.</p>
        <p>Su pedido con ID <strong class="highlight">{{ $idPedido }}</strong> ha sido creado con éxito en ECOMMERCE.</p>
        
        <h3>Detalles del Pedido:</h3>
        <ul>
            @foreach ($productos as $producto)
            <li>
                <span style="color: #34d399;">Cantidad:</span> <span class="price">{{ $producto->cantidad }}</span> - 
                <span style="color: #34d399;">Precio:</span> <span class="price">S/ {{ $producto->precioUnitario }}</span> - 
                <span style="color: #34d399;">Subtotal:</span> <span class="price">S/ {{ $producto->subtotal }}</span>
            </li>
            @endforeach
        </ul>

        <p><strong style="color: #34d399;">Total del Pedido:</strong> S/ {{ $total }}</p>

        <p>Por favor, realice el pago para procesar su pedido.</p>

        <p class="footer">Saludos,<br>El equipo de ECOMMERCE</p>
    </div>

</body>
</html>
