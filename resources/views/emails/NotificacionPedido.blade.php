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
        table {
            width: 100%;
            margin-bottom: 16px;
            border-collapse: collapse;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #4caf50;
            color: #ffffff;
        }
        tr:nth-child(even) {
            background-color: #333;
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
        <table>
            <thead>
                <tr>
                    <th>Producto</th>
                    <th>Cantidad</th>
                    <th>Precio Unitario</th>
                    <th>Subtotal</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($productos as $producto)
                <tr>
                    <td class="price">{{ $producto->nombreProducto }} - Talla: {{ $producto->talla }} - Modelo: {{ $producto->modelo }}</td>
                    <td class="price">{{ $producto->cantidad }}</td>
                    <td class="price">S/ {{ $producto->precioUnitario }}</td>
                    <td class="price">S/ {{ $producto->subtotal }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <p><strong style="color: #34d399;">Total del Pedido:</strong> S/ {{ $total }}</p>

        <p>Por favor, realice el pago para procesar su pedido.</p>

        <p class="footer">Saludos,<br>El equipo de ECOMMERCE</p>
    </div>

</body>
</html>
