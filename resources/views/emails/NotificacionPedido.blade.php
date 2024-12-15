<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirmación de Pedido</title>
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
        h3 {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 8px;
        }
        ul {
            list-style-type: none;
            padding-left: 0;
        }
        li {
            font-size: 14px;
            margin-bottom: 8px;
        }
        .highlight {
            color: #34d399; /* green-400 */
        }
        .price {
            color: #fbbf24; /* yellow-400 */
        }
        .total {
            font-weight: 600;
            font-size: 18px;
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
        <h1>Confirmación de Pedido</h1>

        <p>Hola,</p>
        <p>Has creado exitosamente el pedido <strong class="highlight">#{{ $idPedido }}</strong> en ECOMMERCE.</p>

        <h3>Detalles del Pedido:</h3>
        <ul>
            @foreach ($productos as $producto)
                <li>
                    <span class="font-medium">{{ $producto->nombreProducto }}</span> - 
                    Cantidad: {{ $producto->cantidad }} - 
                    Precio: <span class="price">S/ {{ $producto->precioUnitario }}</span> - 
                    Subtotal: <span class="price">S/ {{ $producto->subtotal }}</span>
                </li>
            @endforeach
        </ul>

        <p class="total">
            <span class="highlight">Total del Pedido:</span> S/ {{ $total }}
        </p>

        <p>Por favor, realiza el pago lo antes posible para que podamos procesar tu pedido.</p>

        <p class="footer">Saludos,<br>El equipo de ECOMMERCE</p>
    </div>

</body>
</html>
