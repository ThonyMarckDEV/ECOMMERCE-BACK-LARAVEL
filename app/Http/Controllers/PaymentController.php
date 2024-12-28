<?php

namespace App\Http\Controllers;


use App\Models\Facturacion;
use Illuminate\Http\Request;
use MercadoPago\MercadoPagoConfig;
use MercadoPago\Client\Preference\PreferenceClient;
use App\Models\Pedido;
use App\Models\Pago;
use App\Models\Usuario;
use App\Models\Empresa;
use App\Models\Producto;
use App\Mail\NotificacionPagoCompletado;
use App\Models\Modelo;
use App\Models\Stock;
use App\Models\Talla;
use Illuminate\Support\Facades\Mail;
use FPDF;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

class PaymentController extends Controller
{


    public function __construct()
    {
        // Agrega las credenciales de MercadoPago
        MercadoPagoConfig::setAccessToken(env('MERCADOPAGO_ACCESS_TOKEN'));
    }

    public function createPreference(Request $request)
    {
        // Validar los datos recibidos
        $request->validate([
            'idPedido' => 'required|integer',
            'detalles' => 'required|array',
            'total' => 'required|numeric',
            'correo' => 'required|email'
        ]);
        
        // Obtener los datos del request
        $idPedido = $request->input('idPedido');
        $detalles = $request->input('detalles');
        $total = $request->input('total');
       // $correo = $request->input('correo');
        
        // Crear una instancia del cliente de preferencias de MercadoPago
        $client = new PreferenceClient();
    
        $currentUrlBase = 'https://ecommerce-front-react.vercel.app'; // DOMINIO DEL FRONT
    
        // URLs de retorno
        $backUrls = [
            "success" => "{$currentUrlBase}/pedidos?status=approved&external_reference={$idPedido}&payment_type=online",
            "failure" => "{$currentUrlBase}/pedidos?status=failure&external_reference={$idPedido}",
            "pending" => "{$currentUrlBase}/pedidos?status=pending&external_reference={$idPedido}"
        ];
    
        // Crear los ítems a partir de los detalles del pedido
        $items = [];
        foreach ($detalles as $detalle) {
            $items[] = [
                "id" => $detalle['idProducto'],
                "title" => $detalle['nombreProducto'],
                "quantity" => (int)$detalle['cantidad'],
                "unit_price" => (float)$detalle['precioUnitario'],
                "currency_id" => "PEN" // Ajusta según tu moneda
            ];
        }
    
        // Configurar la preferencia con los datos necesarios
        $preferenceData = [
            "items" => $items,
            "payer" => [
               // "email" => $correo
            ],
            "back_urls" => $backUrls,
            "auto_return" => "approved", // Automáticamente vuelve al front-end cuando el pago es aprobado
            "binary_mode" => true, // Usar modo binario para más seguridad
            "external_reference" => $idPedido
        ];
    
        try {
            // Crear la preferencia en MercadoPago
            $preference = $client->create($preferenceData);
    
            // Verificar si se creó la preferencia correctamente
            if (isset($preference->id)) {
                // Responder con el punto de inicio del pago
                return response()->json([
                    'success' => true,
                    'init_point' => $preference->init_point,
                    'preference_id' => $preference->id // Para el modal
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Error al crear la preferencia en MercadoPago'
                ]);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al crear la preferencia: ' . $e->getMessage()
            ]);
        }
    }

    public function actualizarComprobante(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'idPedido' => 'required|integer|exists:pedidos,idPedido',
                'tipo_comprobante' => 'required|in:boleta,factura',
                'ruc' => 'nullable|required_if:tipo_comprobante,factura|string|size:11',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error de validación',
                    'errors' => $validator->errors()
                ], 422);
            }

            $pedido = Pedido::findOrFail($request->idPedido);


            // Verificar que el pedido está en estado pendiente
            if ($pedido->estado !== 'pendiente') {
                return response()->json([
                    'success' => false,
                    'message' => 'Solo se pueden modificar pedidos pendientes'
                ], 400);
            }

            // Actualizar datos del comprobante
            $pedido->tipo_comprobante = $request->tipo_comprobante;
            
            if ($request->tipo_comprobante === 'factura') {
                $pedido->ruc = $request->ruc;
            } else {
                $pedido->ruc = null;
            }

            $pedido->save();

            return response()->json([
                'success' => true,
                'message' => 'Comprobante actualizado correctamente',
                'data' => $pedido
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el comprobante: ' . $e->getMessage()
            ], 500);
        }
    }


    public function recibirPago(Request $request)
    {
        try {
            $id = $request->input('data')['id'] ?? null;
            $type = $request->input('type') ?? null;

            if (!$id || $type !== 'payment') {
                Log::warning('ID del pago o tipo no válido.');
                return response()->json(['error' => 'ID del pago o tipo no válido'], 400);
            }

            // Consultar a la API de Mercado Pago
            $url = "https://api.mercadopago.com/v1/payments/{$id}";
            $client = new Client();
            $response = $client->request('GET', $url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . env('MERCADOPAGO_ACCESS_TOKEN'),
                ],
            ]);

            $pago = json_decode($response->getBody(), true);

            $estado_pago = trim(strtolower($pago['status'])); // Aseguramos formato uniforme
            $metodo_pago = $pago['payment_method_id'] ?? null;
            $externalReference = $pago['external_reference'];

            $pagoModel = Pago::where('idPedido', $externalReference)->first();

            if (!$pagoModel) {
                return response()->json(['success' => false, 'message' => 'Pago no encontrado para este pedido'], 200);
            }

            if ($pagoModel->estado_pago === 'completado') {
                return response()->json(['success' => false, 'message' => 'Este pago ya ha sido completado previamente'], 200);
            }

            $pagoModel->estado_pago = 'completado';
            if ($metodo_pago) {
                $pagoModel->metodo_pago = $metodo_pago;
            }
            $pagoModel->save();

            $pedido = Pedido::with('detalles')->find($externalReference);

            if (!$pedido) {
                return response()->json(['success' => false, 'message' => 'Pedido no encontrado'], 404);
            }

            if ($estado_pago === 'approved') {
                
                if (in_array($pedido->estado, ['aprobando', 'completado'])) {
                    return response()->json(['success' => false, 'message' => 'El pedido ya fue procesado previamente'], 200);
                }

                $pedido->estado = 'aprobando';
                $pedido->save();

                // Asignar el ID del pedido
                $idPedido = $pedido->idPedido;

                //ACA DESCUEBTA EL STOCK VERIRFICAR SI ESTA BIEN
                foreach ($pedido->detalles as $detalle) {
                    // Obtener el registro de stock según idModelo y idTalla
                    $stock = Stock::where('idModelo', $detalle->idModelo)
                                  ->where('idTalla', $detalle->idTalla)
                                  ->first();
                
                    if ($stock) {
                        // Verificar si hay suficiente stock para descontar
                        if ($stock->cantidad >= $detalle->cantidad) {
                            // Descontar la cantidad del stock
                            $stock->cantidad -= $detalle->cantidad;
                            $stock->save();
                        } else {
                            // Manejar el caso de stock insuficiente
                            return response()->json([
                                'success' => false,
                                'message' => 'Stock insuficiente para el producto con idModelo: ' . $detalle->idModelo . ', idTalla: ' . $detalle->idTalla
                            ], 400);
                        }
                    } else {
                        // Manejar el caso en que no se encuentra un registro de stock
                        return response()->json([
                            'success' => false,
                            'message' => 'No se encontró stock para el producto con idModelo: ' . $detalle->idModelo . ', idTalla: ' . $detalle->idTalla
                        ], 404);
                    }
                }

                
               // MANEJO DE FACTURACIÓN
                if (Facturacion::where('status', 1)->exists()) {
                    // Obtener el pedido por su id
                    $pedido = Pedido::find($idPedido);

                    if (!$pedido) {
                        return response()->json([
                            'success' => false,
                            'message' => 'No se encontró el pedido con ID: ' . $idPedido
                        ], 404);
                    }

                    // Determinar el tipo de comprobante
                    if ($pedido->tipo_comprobante === 'boleta') {
                        $this->FacturacionActivaBoleta($idPedido);
                    } elseif ($pedido->tipo_comprobante === 'factura') {
                        // Extraer el RUC y enviarlo a la función
                        if (!$pedido->ruc) {
                            return response()->json([
                                'success' => false,
                                'message' => 'El pedido con ID: ' . $idPedido . ' no tiene un RUC válido'
                            ], 400);
                        }
                        $this->FacturacionActivaFactura($idPedido, $pedido->ruc);
                    } else {
                        return response()->json([
                            'success' => false,
                            'message' => 'Tipo de comprobante no válido para el pedido con ID: ' . $idPedido
                        ], 400);
                    }
                } else {
                    $usuario = Usuario::find($pedido->idUsuario);
                    if ($usuario) {
                        $nombreCompleto = "{$usuario->nombres} {$usuario->apellidos}";

                        $detallesPedido = [];
                        $total = 0;

                        foreach ($pedido->detalles as $detalle) {
                            $producto = Producto::find($detalle->idProducto);
                            $detallesPedido[] = [
                                'producto' => $producto ? $producto->nombreProducto : 'Producto no encontrado',
                                'cantidad' => $detalle->cantidad,
                                'subtotal' => $detalle->subtotal,
                            ];
                            $total += $detalle->subtotal;
                        }

                        $pdfDirectory = "boletas/{$usuario->idUsuario}/{$externalReference}";
                        $pdfFileName = "boleta_pedido_{$externalReference}.pdf";
                        $pdfPath = public_path("{$pdfDirectory}/{$pdfFileName}");

                        if (!file_exists(public_path($pdfDirectory))) {
                            mkdir(public_path($pdfDirectory), 0755, true);
                        }

                        $this->generateBoletaPDF($pdfPath, $nombreCompleto, $detallesPedido, $total);

                        Mail::to($usuario->correo)->send(new NotificacionPagoCompletado(
                            $nombreCompleto,
                            $detallesPedido,
                            $total,
                            $pdfPath
                        ));
                    }
                }
            } else {
                Log::warning("El estado del pago no es 'approved'. Estado recibido: {$estado_pago}");
                return response()->json(['success' => false, 'message' => 'El pago no está aprobado'], 200);
            }

            return response()->json(['success' => true, 'message' => 'Estado de pago y pedido actualizados correctamente'], 200);
        } catch (\Exception $e) {
            Log::error('Error al procesar el webhook: ' . $e->getMessage());
            return response()->json(['error' => 'Error interno: ' . $e->getMessage()], 500);
        }
    }


    private function generateBoletaPDF($pdfPath, $nombreCompleto, $detallesPedido, $total)
    {
        $pdf = new FPDF();
        $pdf->AddPage();
        $pdf->SetFont('Arial', 'B', 16);
    
        // Título
        $pdf->Cell(0, 10, "Boleta de Pago", 0, 1, 'C');
        $pdf->Ln(10);
    
        // Información del cliente
        $pdf->SetFont('Arial', '', 12);
        $pdf->Cell(0, 10, "Cliente: {$nombreCompleto}", 0, 1);
        $pdf->Ln(5);
    
        // Detalles del pedido
        $pdf->Cell(0, 10, "Detalles del Pedido:", 0, 1);
        $pdf->SetFont('Arial', '', 10);
        foreach ($detallesPedido as $detalle) {
            $pdf->Cell(0, 10, "Producto: {$detalle['producto']}, Cantidad: {$detalle['cantidad']}, Subtotal: S/{$detalle['subtotal']}", 0, 1);
        }
    
        // Total
        $pdf->Ln(5);
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(0, 10, "Total: S/{$total}", 0, 1);
    
        // Guardar el PDF en la ruta especificada
        $pdf->Output('F', $pdfPath);
    }


    //ESTE ES PARA FACTURA
    public function FacturacionActivaFactura($idPedido,$ruc)
    {
        try {
                    Log::info("La facturación está activada, procesando la API Factura para el pedido: {$idPedido}");
            
                    // Obtener el pedido
                    $pedido = Pedido::with('detalles', 'usuario') // Relación con los detalles y usuario
                        ->where('idPedido', $idPedido)
                        ->first();
            
                    if (!$pedido) {
                        return response()->json(['success' => false, 'message' => 'Pedido no encontrado'], 404);
                    }
            
                    // Obtener los datos del cliente
                    $cliente = $pedido->usuario; // Relación con el modelo Usuario
                    if (!$cliente) {
                        return response()->json(['success' => false, 'message' => 'Usuario no encontrado'], 404);
                    }
            
                    $empresa = Empresa::first();

                    // Crear estructura de company
                    $companyData = [
                        "ruc" => $empresa->ruc,
                    ];

                    // Crear estructura de cliente
                    $clientData = [
                        "tipo_doc" => "6", // 6(RUC) - 1(DNI)
                        "num_doc" => $ruc, // Usando el RUC recibido
                        "razon_social" => $cliente->nombres . ' ' . $cliente->apellidos,
                        "correo" => $cliente->correo, // Agregando el correo del cliente
                        "direccion"=>$pedido->direccion,
                    ];
            
                   // Inicializamos el arreglo de detalles
                    $details = [];

                    // Obtenemos los detalles de la base de datos (suponiendo que tienes un modelo de Producto y pedido_detalle)
                    foreach ($pedido->detalles as $detalle) {
                        // Buscar el producto, talla y modelo correspondiente
                        $producto = Producto::find($detalle->idProducto);
                        $talla = Talla::find($detalle->idTalla);
                        $modelo = Modelo::find($detalle->idModelo);

                        // Comprobamos que los objetos existan antes de continuar
                        if ($producto && $talla && $modelo) {
                            // Construir la descripción concatenada
                            $descripcion = $producto->nombreProducto . ' Talla: ' . $talla->nombreTalla . ' Modelo: ' . $modelo->nombreModelo;

                            // Añadir los detalles de la factura al arreglo
                            $details[] = [
                                "cod_producto" => $producto->idProducto ?? 'SIN-CODIGO',
                                "unidad" => "NIU",  // Unidad de medida
                                "cantidad" => $detalle->cantidad,
                                "mto_valor_unitario" => $detalle->precioUnitario,
                                "descripcion" => $descripcion,
                                "mto_base_igv" => $detalle->precioUnitario * $detalle->cantidad,
                                "porcentaje_igv" =>$empresa->igv,
                                "igv" => ($detalle->precioUnitario * $detalle->cantidad) * ($empresa->igv/100), //0.18
                                "tip_afe_igv" => "10",  // Código de afectación IGV (gravado estándar)
                                "total_impuestos" => ($detalle->precioUnitario * $detalle->cantidad) * ($empresa->igv/100),//0.18
                                "mto_valor_venta" => $detalle->precioUnitario * $detalle->cantidad,
                                "mto_precio_unitario" => $detalle->precioUnitario *( ($empresa->igv/100)+1),//1.18
                            ];
                        }
                    }
            
                    // Calcular totales
                    $totalGravadas = array_sum(array_column($details, 'mto_valor_venta'));
                    $totalIGV = array_sum(array_column($details, 'igv'));
                    $totalImpuestos = array_sum(array_column($details, 'total_impuestos'));
                    $totalVenta = $totalGravadas + $totalImpuestos;
            
                    // Crear la estructura de la factura
                    $invoiceData = [
                        "ubl_version" => "2.1",
                        "tipo_operacion" => "0101",
                        "tipo_doc" => "01", //01(FACTURA) (03)BOLETA
                        "serie" => "F001", //VER COMO HACER LO DE LA SERIE
                        "correlativo" => "1", // Ajustar según tu lógica
                        "fecha_emision" => now()->toISOString(),
                        "tipo_moneda" => "PEN",
                        "mto_oper_gravadas" => $totalGravadas,
                        "mto_igv" => $totalIGV,
                        "total_impuestos" => $totalImpuestos,
                        "valor_venta" => $totalGravadas,
                        "sub_total" => $totalVenta,
                        "mto_imp_venta" => $totalVenta,
                        "legend" => "SON " . strtoupper($this->numerodosletras($totalVenta)) . " SOLES",
                    ];
            
                    // Construir el cuerpo final de la solicitud
                    $data = [
                        "company"=>$companyData,
                        "client" => $clientData,
                        "invoice" => $invoiceData,
                        "details" => $details,
                    ];
            
                    //PONER EN ENV
                    // Enviar los datos a la API
                    $apiResponse = Http::post('http://localhost:8001/api/API_FACTURA_PDF', $data);

                    // Log::error("ERROR",$data);
                    $body = $apiResponse->json();

                    if ($apiResponse->successful()) {
                        // Manejar la respuesta exitosa de la API
                        Log::info("Pago procesado correctamente para el pedido ");
                    } else {
                       Log::error("Error ",  $body);
                    }
                
            //Log::info("Estado de pago y pedido actualizados correctamente para el ID {$id}.");
            return response()->json(['success' => true, 'message' => 'Estado de pago y pedido actualizados correctamente'],200);
        } catch (\Exception $e) {
           // Log::error('Error al procesar el webhook: ' . $e->getMessage());
            return response()->json(['error' => 'Error interno: ' . $e->getMessage()], 500);
        }
    }

    //ESTE ES PARA BOLETA
    public function FacturacionActivaBoleta($idPedido)
    {
        try {
            Log::info("La facturación está activada, procesando la API Boleta para el pedido: {$idPedido}");

            // Obtener el pedido
            $pedido = Pedido::with('detalles', 'usuario') // Relación con los detalles y usuario
            ->where('idPedido', $idPedido)
            ->first();

            if (!$pedido) {
                return response()->json(['success' => false, 'message' => 'Pedido no encontrado'], 404);
            }

            // Obtener los datos del cliente
            $cliente = $pedido->usuario; // Relación con el modelo Usuario
            if (!$cliente) {
                return response()->json(['success' => false, 'message' => 'Usuario no encontrado'], 404);
            }

            $empresa = Empresa::first();

            // Crear estructura de company
            $companyData = [
                "ruc" => $empresa->ruc,
            ];

            // Crear estructura de cliente
            $clientData = [
                "tipo_doc" => "1",
                "num_doc" => $cliente->dni, // Número de documento
                "razon_social" => $cliente->nombres . ' ' . $cliente->apellidos,
                "correo" => $cliente->correo,
                "direccion"=>$pedido->direccion,
            ];

            // Inicializamos el arreglo de detalles
            $details = [];

            // Obtenemos los detalles de la base de datos
            foreach ($pedido->detalles as $detalle) {
                $producto = Producto::find($detalle->idProducto);
                $talla = Talla::find($detalle->idTalla);
                $modelo = Modelo::find($detalle->idModelo);

                if ($producto && $talla && $modelo) {
                    $descripcion = $producto->nombreProducto . ' Talla: ' . $talla->nombreTalla . ' Modelo: ' . $modelo->nombreModelo;

               // Añadir los detalles de la factura al arreglo
                $details[] = [
                    "cod_producto" => $producto->idProducto ?? 'SIN-CODIGO',
                    "unidad" => "NIU",  // Unidad de medida
                    "cantidad" => $detalle->cantidad,
                    "mto_valor_unitario" => $detalle->precioUnitario,
                    "descripcion" => $descripcion,
                    "mto_base_igv" => $detalle->precioUnitario * $detalle->cantidad,
                    "porcentaje_igv" =>$empresa->igv,
                    "igv" => ($detalle->precioUnitario * $detalle->cantidad) * ($empresa->igv/100), //0.18
                    "tip_afe_igv" => "10",  // Código de afectación IGV (gravado estándar)
                    "total_impuestos" => ($detalle->precioUnitario * $detalle->cantidad) * ($empresa->igv/100),//0.18
                    "mto_valor_venta" => $detalle->precioUnitario * $detalle->cantidad,
                    "mto_precio_unitario" => $detalle->precioUnitario *( ($empresa->igv/100)+1),//1.18
                ];
                }
            }

            // Calcular totales
            $totalGravadas = array_sum(array_column($details, 'mto_valor_venta'));
            $totalIGV = array_sum(array_column($details, 'igv'));
            $totalImpuestos = array_sum(array_column($details, 'total_impuestos'));
            $totalVenta = $totalGravadas + $totalImpuestos;

            // Crear la estructura de la factura
            $invoiceData = [
                "ubl_version" => "2.1",
                "tipo_operacion" => "0101",
                "tipo_doc" => "03", // 01(FACTURA) o 03(BOLETA)
                "serie" => "B001",
                "correlativo" => "1",
                "fecha_emision" => now()->toISOString(),
                "tipo_moneda" => "PEN",
                "mto_oper_gravadas" => $totalGravadas,
                "mto_igv" => $totalIGV,
                "total_impuestos" => $totalImpuestos,
                "valor_venta" => $totalGravadas,
                "sub_total" => $totalVenta,
                "mto_imp_venta" => $totalVenta,
                "legends" => [
                    [
                        "code" => "1000",
                        "value" => "SON " . strtoupper($this->numerodosletras($totalVenta)) . " SOLES"
                    ]
                ]
            ];

            // Construir el cuerpo final de la solicitud
            $data = [
                "company" => $companyData,
                "client" => $clientData,
                "invoice" => $invoiceData,
                "details" => $details,
            ];

            // Enviar los datos a la API
            $apiResponse = Http::post('http://localhost:8001/api/API_BOLETA_PDF', $data);

            if ($apiResponse->successful()) {
                Log::info("Pago procesado correctamente para el pedido {$idPedido}");
            } else {
                Log::error("Error al procesar el pago para el pedido {$idPedido}");
            }

            return response()->json(['success' => true, 'message' => 'Estado de pago y pedido actualizados correctamente'], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error interno: ' . $e->getMessage()], 500);
        }
    }


    public function numerodosletras($number)
    {
        $units = [
            '', 'uno', 'dos', 'tres', 'cuatro', 'cinco', 'seis', 'siete', 'ocho', 'nueve'
        ];
        $tens = [
            '', 'diez', 'veinte', 'treinta', 'cuarenta', 'cincuenta', 'sesenta', 'setenta', 'ochenta', 'noventa'
        ];
        $teens = [
            'once', 'doce', 'trece', 'catorce', 'quince', 'dieciséis', 'diecisiete', 'dieciocho', 'diecinueve'
        ];
    
        $hundreds = [
            '', 'cien', 'doscientos', 'trescientos', 'cuatrocientos', 'quinientos', 'seiscientos', 'setecientos', 'ochocientos', 'novecientos'
        ];
    
        if ($number == 0) {
            return 'cero';
        }
    
        $parts = explode('.', number_format($number, 2, '.', ''));
        $integerPart = (int)$parts[0];
        $decimalPart = isset($parts[1]) ? (int)$parts[1] : 0;
    
        $result = $this->convertNumberToWords($integerPart, $units, $tens, $teens, $hundreds);
        if ($decimalPart > 0) {
            $result .= " con " . $this->convertNumberToWords($decimalPart, $units, $tens, $teens, $hundreds) . " centavos";
        }
    
        return $result;
    }
    
    private function convertNumberToWords($number, $units, $tens, $teens, $hundreds)
    {
        if ($number < 10) {
            return $units[$number];
        } elseif ($number < 20) {
            return $number == 10 ? 'diez' : $teens[$number - 11];
        } elseif ($number < 100) {
            $tensPart = $tens[intval($number / 10)];
            $unitsPart = $number % 10;
            return $tensPart . ($unitsPart ? ' y ' . $units[$unitsPart] : '');
        } elseif ($number < 1000) {
            $hundredsPart = $hundreds[intval($number / 100)];
            $remainder = $number % 100;
            return $hundredsPart . ($remainder ? ' ' . $this->convertNumberToWords($remainder, $units, $tens, $teens, $hundreds) : '');
        } else {
            // Manejar números mayores (miles, millones, etc.)
            $thousands = intval($number / 1000);
            $remainder = $number % 1000;
            return $this->convertNumberToWords($thousands, $units, $tens, $teens, $hundreds) . " mil" . ($remainder ? ' ' . $this->convertNumberToWords($remainder, $units, $tens, $teens, $hundreds) : '');
        }
    }

}







