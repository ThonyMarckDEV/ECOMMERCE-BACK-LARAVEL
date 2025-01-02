<!DOCTYPE html>
<html>
<head>
    <title>Notificación de Pago Completado - Factura</title>
</head>
<body>
    <h1>Hola, {{ $nombreCompleto }}</h1>
    <p>Su pago ha sido completado exitosamente. Aquí están los detalles de su pedido:</p>
    <ul>
        @foreach ($detallesPedido as $detalle)
            <li>{{ $detalle['producto'] }} - Cantidad: {{ $detalle['cantidad'] }} - Subtotal: S/ {{ $detalle['subtotal'] }}</li>
        @endforeach
    </ul>
    <p><strong>Total: S/ {{ $total }}</strong></p>
    <p>Gracias por su compra.</p>
</body>
</html>