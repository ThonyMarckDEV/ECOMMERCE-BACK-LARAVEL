<!-- Boleta Template -->
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Luxury Store - Confirmación de Compra</title>
    <style>
        /* Common Styles */
        body {
            font-family: 'Helvetica Neue', Arial, sans-serif;
            background-color: #f8f8f8;
            color: #333;
            line-height: 1.6;
            margin: 0;
            padding: 40px 20px;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }
        .logo {
            text-align: center;
            margin-bottom: 40px;
            font-size: 28px;
            font-weight: 700;
            color: #1a1a1a;
            letter-spacing: 2px;
        }
        .header {
            text-align: center;
            border-bottom: 1px solid #eaeaea;
            padding-bottom: 30px;
            margin-bottom: 30px;
        }
        h1 {
            font-size: 24px;
            font-weight: 300;
            color: #1a1a1a;
            margin: 0;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .greeting {
            font-size: 16px;
            color: #666;
            margin-bottom: 30px;
        }
        .details {
            margin: 30px 0;
        }
        table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin: 20px 0;
        }
        th {
            background-color: #fafafa;
            font-weight: 500;
            text-transform: uppercase;
            font-size: 12px;
            letter-spacing: 1px;
            color: #666;
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #eaeaea;
        }
        td {
            padding: 15px;
            border-bottom: 1px solid #eaeaea;
            color: #333;
            font-size: 14px;
        }
        .total-section {
            margin-top: 30px;
            text-align: right;
            padding: 20px;
            background-color: #fafafa;
            border-radius: 8px;
        }
        .total {
            font-size: 20px;
            color: #1a1a1a;
            font-weight: 500;
        }
        .footer {
            text-align: center;
            margin-top: 40px;
            padding-top: 30px;
            border-top: 1px solid #eaeaea;
            color: #999;
            font-size: 14px;
        }
        .button {
            display: inline-block;
            background-color: #1a1a1a;
            color: #ffffff;
            padding: 12px 30px;
            text-decoration: none;
            border-radius: 25px;
            font-size: 14px;
            margin-top: 20px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .order-info {
            background-color: #fafafa;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
        }
        .highlight {
            color: #1a1a1a;
            font-weight: 500;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">ECOMMERCE STORE</div>
        
        <div class="header">
            <h1>Confirmación de Compra</h1>
            <p>Boleta Electrónica</p>
        </div>

        <div class="greeting">
            Estimado(a) {{ $nombreCompleto }},
        </div>

        <p>Gracias por su compra. A continuación, encontrará los detalles de su pedido:</p>

        <div class="order-info">
            <span class="highlight">Nº de Pedido:</span> #{{ rand(10000, 99999) }}<br>
            <span class="highlight">Fecha:</span> {{ date('d/m/Y') }}
        </div>

        <div class="details">
            <table>
                <thead>
                    <tr>
                        <th>Producto</th>
                        <th>Cantidad</th>
                        <th>Subtotal (IGV.18%)</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($detallesPedido as $detalle)
                    <tr>
                        <td>{{ $detalle['producto'] }}</td>
                        <td>{{ $detalle['cantidad'] }}</td>
                        <td>S/ {{ number_format($detalle['subtotal'], 2) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="total-section">
            <div class="total">
                Total: S/ {{ number_format($total, 2) }}
            </div>
        </div>

        <div class="footer">
            <p>¿Necesita ayuda? Contáctenos a través de nuestro centro de atención al cliente.</p>
            <p>© {{ date('Y') }} ECOMMERCE Store. Todos los derechos reservados.</p>
        </div>
    </div>
</body>
</html>