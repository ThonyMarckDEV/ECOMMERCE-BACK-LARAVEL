<!DOCTYPE html>
<html>
<head>
    <title>Notificación de Pago Completado - Factura</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            color: #333;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        h1 {
            color: #4CAF50;
            text-align: center;
        }
        .details {
            margin-top: 20px;
        }
        .details table {
            width: 100%;
            border-collapse: collapse;
        }
        .details th, .details td {
            padding: 10px;
            border: 1px solid #ddd;
            text-align: left;
        }
        .total {
            font-size: 18px;
            font-weight: bold;
            text-align: right;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Notificación de Pago Completado - Factura</h1>
        <p>Hola, {{ $nombreCompleto }},</p>
        <p>Su pago ha sido completado exitosamente. Aquí están los detalles de su pedido:</p>
        <div class="details">
            <table>
                <thead>
                    <tr>
                        <th>Producto</th>
                        <th>Cantidad</th>
                        <th>Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($detallesPedido as $detalle)
                        <tr>
                            <td>{{ $detalle['producto'] }}</td>
                            <td>{{ $detalle['cantidad'] }}</td>
                            <td>S/ {{ $detalle['subtotal'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="total">
            <p>Total: S/ {{ $total }}</p>
        </div>
        <p>Gracias por su compra.</p>
    </div>
</body>
</html>