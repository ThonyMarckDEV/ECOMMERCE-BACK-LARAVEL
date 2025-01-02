<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dirección Predeterminada Seleccionada</title>
    <!DOCTYPE html>
<html lang="es">
<h>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cuenta Verificada</title>
    <style>
        /* Reset and Base Styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Helvetica Neue', Arial, sans-serif;
            background-color: #f8f8f8;
            color: #333;
            line-height: 1.6;
            margin: 0;
            padding: 40px 20px;
        }

        /* Container and Layout */
        .container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }

        /* Typography */
        h1 {
            font-size: 24px;
            font-weight: 300;
            color: #1a1a1a;
            margin: 0 0 20px 0;
            text-transform: uppercase;
            letter-spacing: 1px;
            text-align: center;
        }

        h3 {
            font-size: 18px;
            color: #1a1a1a;
            margin: 25px 0 15px 0;
            font-weight: 500;
        }

        p {
            margin-bottom: 15px;
            font-size: 16px;
            color: #4a4a4a;
        }

        /* Table Styles */
        table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin: 20px 0;
            background-color: #ffffff;
            border-radius: 8px;
            overflow: hidden;
        }

        th {
            background-color: #f3f4f6;
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

        td.price {
            font-family: 'Monaco', monospace;
            color: #1a1a1a;
        }

        /* Buttons and Links */
        .button, a {
            display: inline-block;
            background-color: #1a1a1a;
            color: #ffffff !important;
            padding: 12px 30px;
            text-decoration: none;
            border-radius: 25px;
            font-size: 14px;
            margin: 20px 0;
            text-transform: uppercase;
            letter-spacing: 1px;
            text-align: center;
            transition: all 0.3s ease;
        }

        .button:hover, a:hover {
            background-color: #333;
            transform: translateY(-1px);
        }

        /* Utility Classes */
        .highlight {
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

        /* Logo and Branding */
        .logo {
            text-align: center;
            margin-bottom: 40px;
            font-size: 28px;
            font-weight: 700;
            color: #1a1a1a;
            letter-spacing: 2px;
        }

        /* Responsive Design */
        @media screen and (max-width: 600px) {
            .container {
                padding: 20px;
            }

            table {
                font-size: 12px;
            }

            td, th {
                padding: 10px;
            }
        }
    </style>
</head>
<body>
    <h1>Dirección Predeterminada Seleccionada</h1>
    <p>Has seleccionado la siguiente dirección como predeterminada:</p>
    <p>Dirección completa: {{ $direccion->direccion }}</p>
</body>
</html>
