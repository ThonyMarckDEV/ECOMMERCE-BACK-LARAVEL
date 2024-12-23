<?php

namespace App\Http\Controllers;


use App\Models\Facturacion;
use Illuminate\Http\Request;
use MercadoPago\MercadoPagoConfig;
use MercadoPago\Client\Preference\PreferenceClient;
use App\Models\Pedido;
use App\Models\Pago;
use App\Models\Usuario;
use App\Models\Producto;
use App\Mail\NotificacionPagoCompletado;
use App\Models\Stock;
use Illuminate\Support\Facades\Mail;
use FPDF;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

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


    // public function recibirPago(Request $request)
    // {
    //     try {

    //         // Obtener el ID del pago desde el request
    //         $id = $request->input('data')['id'] ?? null;
    //         $type = $request->input('type') ?? null;
    
    //        // Log::info("ID del pago recibido: {$id}, Tipo: {$type}");
    
    //         // Validar que el ID y el tipo estén presentes
    //         if (!$id || $type !== 'payment') {
    //             Log::warning('ID del pago o tipo no válido.');
    //             return response()->json(['error' => 'ID del pago o tipo no válido'], 400);
    //         }
    
    //         // URL de la API de Mercado Pago
    //         $url = "https://api.mercadopago.com/v1/payments/{$id}";
    //        // Log::info("URL para consultar el pago: {$url}");
    
    //         // Solicitar el pago a la API de Mercado Pago
    //         $client = new Client();
    //         $response = $client->request('GET', $url, [
    //             'headers' => [
    //                 'Authorization' => 'Bearer ' . env('MERCADOPAGO_ACCESS_TOKEN'),
    //             ],
    //         ]);
    //         $pago = json_decode($response->getBody(), true);
    
    //        // Log::info('Respuesta obtenida de Mercado Pago:', $pago);
    
    //         // Verificar estado del pago
    //         $estado_pago = $pago['status'];
    //         $metodo_pago = $pago['payment_method_id'] ?? null;
    //         $externalReference = $pago['external_reference'];
    
    
    //         // Buscar el pago asociado al pedido
    //         $pagoModel = Pago::where('idPedido', $externalReference)->first();
    
    //         if (!$pagoModel) {
    //           //  Log::warning("Pago no encontrado para el pedido con referencia {$externalReference}.");
    //             return response()->json(['success' => false, 'message' => 'Pago no encontrado para este pedido'],200);
    //         }
    
    //         if ($pagoModel->estado_pago === 'completado') {
    //            // Log::warning("El pago con ID {$id} ya ha sido completado previamente.");
    //             return response()->json(['success' => false, 'message' => 'Este pago ya ha sido completado previamente'],200);
    //         }
    
    //         // Actualizar el estado del pago a "completado"
    //         $pagoModel->estado_pago = 'completado';
    //         if ($metodo_pago) {
    //             $pagoModel->metodo_pago = $metodo_pago;
    //         }
    //         $pagoModel->save();
    
    //         // Buscar el pedido asociado
    //         $pedido = Pedido::with('detalles')->find($externalReference);
    
    //         if (!$pedido) {
    //            // Log::warning("Pedido con referencia {$externalReference} no encontrado.");
    //             return response()->json(['success' => false, 'message' => 'Pedido no encontrado'], 404);
    //         }

    //         // Actualizar estado del pedido
    //         if ($estado_pago === 'approved') {
                
    //             if (in_array($pedido->estado, ['aprobando', 'completado'])) {
    //                 Log::warning("El pedido ya fue procesado previamente", ['external_reference' => $externalReference]);
    //                 return response()->json(['success' => false, 'message' => 'El pedido ya fue procesado previamente'], 200);
    //             }

    //             $pedido->estado = 'aprobando';
    //             $pedido->save();

    //             // Descontar stock de productos
    //             foreach ($pedido->detalles as $detalle) {
    //                 $producto = Producto::find($detalle->idProducto);
    //                 if ($producto) {
    //                     $producto->stock -= $detalle->cantidad;
    //                     $producto->save();
    //                 }
    //             }


    //                 // Comprobar si la tabla facturación tiene el estado 1
    //                 if (Facturacion::where('status', 1)->exists()) {
    //                     // Llamar a la función FacturacionActiva si la facturación está activada
    //                     $this->FacturacionActiva();
    //                 }else{
    //                     // Generar boleta y enviar correo
    //                     $usuario = Usuario::find($pedido->idUsuario);
    //                     if ($usuario) {
    //                         $nombreCompleto = "{$usuario->nombres} {$usuario->apellidos}";

    //                         $detallesPedido = [];
    //                         $total = 0;

    //                         foreach ($pedido->detalles as $detalle) {
    //                             $producto = Producto::find($detalle->idProducto);
    //                             $detallesPedido[] = [
    //                                 'producto' => $producto ? $producto->nombreProducto : 'Producto no encontrado',
    //                                 'cantidad' => $detalle->cantidad,
    //                                 'subtotal' => $detalle->subtotal,
    //                             ];
    //                             $total += $detalle->subtotal;
    //                         }

    //                         // Ruta para guardar la boleta
    //                         $pdfDirectory = "boletas/{$usuario->idUsuario}/{$externalReference}";
    //                         $pdfFileName = "boleta_pedido_{$externalReference}.pdf";
    //                         $pdfPath = public_path("{$pdfDirectory}/{$pdfFileName}");

    //                         // Crear el directorio si no existe
    //                         if (!file_exists(public_path($pdfDirectory))) {
    //                             mkdir(public_path($pdfDirectory), 0755, true);
    //                         }
                            
    //                         // Generar el PDF
    //                         $this->generateBoletaPDF($pdfPath, $nombreCompleto, $detallesPedido, $total);

    //                         // Enviar el correo con la boleta adjunta
    //                         Mail::to($usuario->correo)->send(new NotificacionPagoCompletado(
    //                             $nombreCompleto,
    //                             $detallesPedido,
    //                             $total,
    //                             $pdfPath
    //                         ));
    //                     }
    //                 }  
    //         }
    
    //         //Log::info("Estado de pago y pedido actualizados correctamente para el ID {$id}.");
    //         return response()->json(['success' => true, 'message' => 'Estado de pago y pedido actualizados correctamente'],200);
    //     } catch (\Exception $e) {
    //        // Log::error('Error al procesar el webhook: ' . $e->getMessage());
    //         return response()->json(['error' => 'Error interno: ' . $e->getMessage()], 500);
    //     }
    // }


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

                
                if (Facturacion::where('status', 1)->exists()) {
                    $this->FacturacionActiva();
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


    public function FacturacionActiva()
    {
        try{
                    Log::info("La facturación está activada, procesando la API.");
                    // Construir el cuerpo de la solicitud
                    $data = [
                        "client" => [
                            "tipo_doc" => "6",
                            "num_doc" => "20000000001",
                            "razon_social" => "ANTHONY MARCK MENDOZA SANCHEZ",
                        ],
                        "invoice" => [
                            "ubl_version" => "2.1",
                            "tipo_operacion" => "0101",
                            "tipo_doc" => "01",
                            "serie" => "F001",
                            "correlativo" => "1",
                            "fecha_emision" => now()->toISOString(),
                            "tipo_moneda" => "PEN",
                            "mto_oper_gravadas" => 1000.20,
                            "mto_igv" => 180.04,
                            "total_impuestos" => 180.84,
                            "valor_venta" => 1000.20,
                            "sub_total" => 1181.04,
                            "mto_imp_venta" => 1181.04,
                            "legend" => "SON MIL CIENTO OCHENTA Y UNO CON 04/100 SOLES",
                        ],
                        "details" => [
                            [
                                "cod_producto" => "001",
                                "unidad" => "NIU",
                                "cantidad" => 1,
                                "mto_valor_unitario" => 1000.00,
                                "descripcion" => "Producto A",
                                "mto_base_igv" => 1000.00,
                                "porcentaje_igv" => 18,
                                "igv" => 180.00,
                                "tip_afe_igv" => "10",
                                "total_impuestos" => 180.00,
                                "mto_valor_venta" => 1000.00,
                                "mto_precio_unitario" => 1180.00,
                            ],
                            [
                                "cod_producto" => "P002",
                                "unidad" => "NIU",
                                "cantidad" => 4,
                                "mto_valor_unitario" => 0.05,
                                "descripcion" => "BOLSA PLASTICA",
                                "mto_base_igv" => 0.20,
                                "porcentaje_igv" => 18,
                                "igv" => 0.04,
                                "tip_afe_igv" => "10",
                                "factorIcbper" => 0.20,
                                "icbper" => 0.80,
                                "total_impuestos" => 0.84,
                                "mto_valor_venta" => 0.20,
                                "mto_precio_unitario" => 0.059,
                            ],
                        ],
                    ];

                    // Enviar los datos a la API
                    $apiResponse = Http::post('http://localhost:8001/api/API_PDF', $data);

                    if ($apiResponse->successful()) {
                        // Manejar la respuesta exitosa de la API
                        Log::info("Pago procesado correctamente para el pedido ");
                    } else {
                        // Manejar errores de la API
                        Log::error("Error al procesar el pago para el pedido ");
                    }
                
            //Log::info("Estado de pago y pedido actualizados correctamente para el ID {$id}.");
            return response()->json(['success' => true, 'message' => 'Estado de pago y pedido actualizados correctamente'],200);
        } catch (\Exception $e) {
           // Log::error('Error al procesar el webhook: ' . $e->getMessage());
            return response()->json(['error' => 'Error interno: ' . $e->getMessage()], 500);
        }
    }

}







