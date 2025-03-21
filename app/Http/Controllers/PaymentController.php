<?php

namespace App\Http\Controllers;


use App\Mail\NotificacionPagoCompletadoBoleta;
use App\Mail\NotificacionPagoCompletadoFactura;
use App\Models\Facturacion;
use Illuminate\Http\Request;
use App\Models\Pedido;
use App\Models\Pago;
use App\Models\Usuario;
use App\Models\Empresa;
use App\Models\Producto;
use App\Mail\NotificacionPagoCompletado;
use App\Models\Comprobante;
use App\Models\Correlativo;
use App\Models\DetalleComprobante;
use App\Models\Modelo;
use App\Models\Stock;
use App\Models\Talla;
use App\Models\Numeracion;
use App\Models\PedidoDetalle;
use App\Models\Serie;
use App\Models\TipoComprobante;
use App\Models\TipoDocumento;
use Exception;
use Illuminate\Support\Facades\Mail;
use FPDF;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
//Mercado pago
use MercadoPago\MercadoPagoConfig;
use MercadoPago\Client\Preference\PreferenceClient;
use MercadoPago\Exceptions\MPApiException;

class PaymentController extends Controller
{
    protected function authenticate()
    {
        $mpAccessToken = config('services.mercadopago.access_token');

        if (!$mpAccessToken) {
            throw new \Exception("El token de acceso de MercadoPago no está configurado.");
        }

        MercadoPagoConfig::setAccessToken($mpAccessToken);
       // MercadoPagoConfig::setRuntimeEnviroment(MercadoPagoConfig::LOCAL);
    }


    public function createPreference(Request $request)
    {

        // Initialize MercadoPago
        $this->authenticate();

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
        
        // Crear una instancia del cliente de preferencias de MercadoPago
        $client = new PreferenceClient();
        $currentUrlBase = 'https://ecommerce-thonymarckdev.vercel.app'; // DOMINIO DEL FRONT
        
        // URLs de retorno
        $backUrls = [
            "success" => "{$currentUrlBase}/pedidos?status=approved&external_reference={$idPedido}&payment_type=online",
            "failure" => "{$currentUrlBase}/pedidos?status=failure&external_reference={$idPedido}",
            "pending" => "{$currentUrlBase}/pedidos?status=pending&external_reference={$idPedido}"
        ];

        $empresaigv = Empresa::value('igv');
        
        // Crear los ítems a partir de los detalles del pedido
        $items = [];
        foreach ($detalles as $detalle) {
            // Obtener los detalles del pedido usando el idDetallePedido
            $detallePedido = PedidoDetalle::find($detalle['idDetallePedido']);
        
            if ($detallePedido) {
                // Obtener idModelo, idTalla, cantidad y precioUnitario desde el detalle
                $idModelo = $detallePedido->idModelo;
                $idTalla = $detallePedido->idTalla;
                $cantidad = $detallePedido->cantidad;
                $precioUnitario = $detallePedido->precioUnitario;
        
                // Obtener el nombre del producto
                $producto = Producto::find($detallePedido->idProducto);
                $nombreProducto = $producto ? $producto->nombreProducto : 'Producto no encontrado';
        
                // Obtener el nombre del modelo
                $modelo = Modelo::find($idModelo);
                $nombreModelo = $modelo ? $modelo->nombreModelo : 'Modelo no encontrado';
        
                // Obtener el nombre de la talla
                $talla = Talla::find($idTalla);
                $nombreTalla = $talla ? $talla->nombreTalla : 'Talla no encontrada';
        
                // Verificar el stock para el modelo y talla
                $stock = Stock::where('idModelo', $idModelo)
                            ->where('idTalla', $idTalla)
                            ->first();
        
                if ($stock) {
                    // Verificar si hay suficiente stock para descontar
                    if ($stock->cantidad >= $cantidad) {
                        // Log para confirmar que hay suficiente stock
                        Log::info('Stock disponible para el producto', [
                            'idModelo' => $idModelo,
                            'idTalla' => $idTalla,
                            'stockDisponible' => $stock->cantidad,
                            'cantidadSolicitada' => $cantidad
                        ]);
        
                        // Calcular el precio con IGV
                        $unitPriceWithIGV = (float)$precioUnitario * (1 + $empresaigv / 100); // Incluye IGV en el precio
                        $items[] = [
                            "id" => $detallePedido->idProducto,
                            "title" => $nombreProducto,
                            "quantity" => (int)$cantidad,
                            "unit_price" => $unitPriceWithIGV, // Precio con IGV
                            "currency_id" => "PEN" // Ajusta según tu moneda
                        ];
                    } else {
                        // Log para stock insuficiente
                        Log::warning('Stock insuficiente para el producto', [
                            'idProducto' => $detallePedido->idProducto,
                            'nombreProducto' => $nombreProducto,
                            'idModelo' => $idModelo,
                            'nombreModelo' => $nombreModelo,
                            'idTalla' => $idTalla,
                            'nombreTalla' => $nombreTalla,
                            'stockDisponible' => $stock->cantidad,
                            'cantidadSolicitada' => $cantidad
                        ]);
        
                        // Stock insuficiente para este producto
                        return response()->json([
                            'success' => false,
                            'message' => 'Stock insuficiente para el producto: ' . $nombreProducto . ' (Modelo: ' . $nombreModelo . ', Talla: ' . $nombreTalla . ')'
                        ], 400);
                    }
                } else {
                    // Log para no encontrar stock
                    Log::error('No se encontró stock para el producto', [
                        'idProducto' => $detallePedido->idProducto,
                        'nombreProducto' => $nombreProducto,
                        'idModelo' => $idModelo,
                        'nombreModelo' => $nombreModelo,
                        'idTalla' => $idTalla,
                        'nombreTalla' => $nombreTalla
                    ]);
        
                    // No se encontró stock para el producto
                    return response()->json([
                        'success' => false,
                        'message' => 'No se encontró stock para el producto: ' . $nombreProducto . ' (Modelo: ' . $nombreModelo . ', Talla: ' . $nombreTalla . ')'
                    ], 404);
                }
            } else {
                // Log si no se encuentra el detalle del pedido
                Log::error('No se encontró detalle del pedido', [
                    'idDetallePedido' => $detalle['idDetallePedido']
                ]);
        
                // No se encontró el detalle del pedido
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontró detalle del pedido con idDetallePedido: ' . $detalle['idDetallePedido']
                ], 404);
            }
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


            // Fetch payment details from MercadoPago API
            $url = "https://api.mercadopago.com/v1/payments/{$id}";
            $client = new Client();
            $response = $client->request('GET', $url, [
                'verify' => storage_path('certs/cacert.pem'), // Path to the CA bundle
                'headers' => [
                    'Authorization' => 'Bearer ' . env('MERCADOPAGO_ACCESS_TOKEN'),
                ],
            ]);

            // // Consultar a la API de Mercado Pago
            // $url = "https://api.mercadopago.com/v1/payments/{$id}";
            // $client = new Client();
            // $response = $client->request('GET', $url, [
            //     'headers' => [
            //         'Authorization' => 'Bearer ' . env('MERCADOPAGO_ACCESS_TOKEN'),
            //     ],
            // ]);

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
                            // Log de stock insuficiente
                            Log::warning('Stock insuficiente para el producto con idModelo: ' . $detalle->idModelo . ', idTalla: ' . $detalle->idTalla . '. Stock disponible: ' . $stock->cantidad . ', cantidad solicitada: ' . $detalle->cantidad);
                            
                            // Manejar el caso de stock insuficiente
                            return response()->json([
                                'success' => false,
                                'message' => 'Stock insuficiente para el producto con idModelo: ' . $detalle->idModelo . ', idTalla: ' . $detalle->idTalla
                            ], 400);
                        }
                    } else {
                        // Log si no se encuentra stock para el producto
                        Log::warning('No se encontró stock para el producto con idModelo: ' . $detalle->idModelo . ', idTalla: ' . $detalle->idTalla);
                        
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
                    // Obtener el pedido por su id
                    $pedido = Pedido::find($idPedido);
                
                    if (!$pedido) {
                        return response()->json([
                            'success' => false,
                            'message' => 'No se encontró el pedido con ID: ' . $idPedido
                        ], 404);
                    }
                
                    // Obtener el usuario asociado al pedido
                    $usuario = Usuario::find($pedido->idUsuario);
                
                    if (!$usuario) {
                        return response()->json([
                            'success' => false,
                            'message' => 'No se encontró el usuario asociado al pedido con ID: ' . $idPedido
                        ], 404);
                    }
                
                    $nombreCompleto = "{$usuario->nombres} {$usuario->apellidos}";
                
                    // Obtener los detalles del pedido
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
                
                    // Determinar el tipo de comprobante
                    if ($pedido->tipo_comprobante === 'boleta') {
                        $pdfDirectory = "storage/comprobantesEcommerce/Boletas/";
                        $pdfFileName = "boleta_" . date('Ymd_His') . "_" . $idPedido . ".pdf"; // Agregar la hora
                        $pdfPath = $pdfDirectory . $pdfFileName;
                
                        if (!file_exists($pdfDirectory)) {
                            mkdir($pdfDirectory, 0755, true);
                        }
                
                        $this->generateBoletaPDF($pdfPath, $nombreCompleto, $detallesPedido, $total);
                
                        // Enviar notificación con archivo adjunto
                        Mail::to($usuario->correo)->send(new NotificacionPagoCompletadoBoleta(
                            $nombreCompleto,
                            $detallesPedido,
                            $total,
                            $pdfPath,
                            $idPedido
                        ));
                    } elseif ($pedido->tipo_comprobante === 'factura') {
                        $pdfDirectory = "storage/comprobantesEcommerce/Facturas/";
                        $pdfFileName = "factura_" . date('Ymd_His') . "_" . $idPedido . ".pdf"; // Agregar la hora
                        $pdfPath = $pdfDirectory . $pdfFileName;
                
                        if (!file_exists($pdfDirectory)) {
                            mkdir($pdfDirectory, 0755, true);
                        }
                
                        $this->generateFacturaPDF($pdfPath, $nombreCompleto, $detallesPedido, $total, $pedido->ruc);
                        Mail::to($usuario->correo)->send(new NotificacionPagoCompletadoFactura(
                            $nombreCompleto,
                            $detallesPedido,
                            $total,
                            $pdfPath,
                            $pedido->ruc,
                            $idPedido
                        ));
                    } else {
                        return response()->json([
                            'success' => false,
                            'message' => 'Tipo de comprobante no válido para el pedido con ID: ' . $idPedido
                        ], 400);
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

    // public function recibirPagoComprobante(Request $request)
    // {
    //     try {
    //         $idPedido = $request->input('idPedido');
    //         $comprobante = $request->file('comprobante');

    //         if (!$idPedido || !$comprobante) {
    //             Log::warning('ID del pedido o comprobante no proporcionado.');
    //             return response()->json(['error' => 'ID del pedido y comprobante son requeridos'], 400);
    //         }

    //         // Validar el archivo
    //         $validator = Validator::make($request->all(), [
    //             'comprobante' => 'required|image|mimes:jpeg,png,jpg|max:2048'
    //         ]);

    //         if ($validator->fails()) {
    //             return response()->json(['error' => 'El archivo debe ser una imagen (jpeg, png, jpg) y no exceder 2MB'], 400);
    //         }

    //          // Guardar el comprobante
    //         $nombreArchivo = time() . '_' . $comprobante->getClientOriginalName();
    //         $rutaComprobante = $comprobante->storeAs("comprobantes/pedidos/{$idPedido}", $nombreArchivo, 'public');

    //         $pagoModel = Pago::where('idPedido', $idPedido)->first();

    //         if (!$pagoModel) {
    //             return response()->json(['success' => false, 'message' => 'Pago no encontrado para este pedido'], 404);
    //         }

    //         if ($pagoModel->estado_pago === 'completado') {
    //             return response()->json(['success' => false, 'message' => 'Este pago ya ha sido completado previamente'], 200);
    //         }

    //         // Actualizar el modelo de pago
    //         $pagoModel->estado_pago = 'completado';
    //         $pagoModel->comprobante_url = $rutaComprobante;
    //         $pagoModel->metodo_pago = $request->input('metodo_pago', 'transferencia'); // o yape, plin, etc.
    //         $pagoModel->fecha_pago = now();
    //         $pagoModel->save();

    //         $pedido = Pedido::with('detalles')->find($idPedido);

    //         if (!$pedido) {
    //             return response()->json(['success' => false, 'message' => 'Pedido no encontrado'], 404);
    //         }

    //         if (in_array($pedido->estado, ['aprobando', 'completado'])) {
    //             return response()->json(['success' => false, 'message' => 'El pedido ya fue procesado previamente'], 200);
    //         }

    //         $pedido->estado = 'aprobando';
    //         $pedido->save();

    //         // Verificar y actualizar stock
    //         foreach ($pedido->detalles as $detalle) {
    //             $stock = Stock::where('idModelo', $detalle->idModelo)
    //                         ->where('idTalla', $detalle->idTalla)
    //                         ->first();
            
    //             if ($stock) {
    //                 if ($stock->cantidad >= $detalle->cantidad) {
    //                     $stock->cantidad -= $detalle->cantidad;
    //                     $stock->save();
    //                 } else {
    //                     Log::warning('Stock insuficiente para el producto con idModelo: ' . $detalle->idModelo . ', idTalla: ' . $detalle->idTalla);
    //                     return response()->json([
    //                         'success' => false,
    //                         'message' => 'Stock insuficiente para el producto'
    //                     ], 400);
    //                 }
    //             } else {
    //                 Log::warning('No se encontró stock para el producto con idModelo: ' . $detalle->idModelo . ', idTalla: ' . $detalle->idTalla);
    //                 return response()->json([
    //                     'success' => false,
    //                     'message' => 'No se encontró stock para el producto'
    //                 ], 404);
    //             }
    //         }

    //         // MANEJO DE FACTURACIÓN
    //         if (Facturacion::where('status', 1)->exists()) {
    //             if ($pedido->tipo_comprobante === 'boleta') {
    //                 $this->FacturacionActivaBoleta($idPedido);
    //             } elseif ($pedido->tipo_comprobante === 'factura') {
    //                 if (!$pedido->ruc) {
    //                     return response()->json([
    //                         'success' => false,
    //                         'message' => 'RUC no válido'
    //                     ], 400);
    //                 }
    //                 $this->FacturacionActivaFactura($idPedido, $pedido->ruc);
    //             }
    //         } else {
    //             // Generar comprobante local
    //             $usuario = Usuario::find($pedido->idUsuario);
                
    //             if (!$usuario) {
    //                 return response()->json([
    //                     'success' => false,
    //                     'message' => 'Usuario no encontrado'
    //                 ], 404);
    //             }
                
    //             $nombreCompleto = "{$usuario->nombres} {$usuario->apellidos}";
    //             $detallesPedido = [];
    //             $total = 0;
                
    //             foreach ($pedido->detalles as $detalle) {
    //                 $producto = Producto::find($detalle->idProducto);
    //                 $detallesPedido[] = [
    //                     'producto' => $producto ? $producto->nombreProducto : 'Producto no encontrado',
    //                     'cantidad' => $detalle->cantidad,
    //                     'subtotal' => $detalle->subtotal,
    //                 ];
    //                 $total += $detalle->subtotal;
    //             }
                
    //             if ($pedido->tipo_comprobante === 'boleta') {
    //                 $pdfDirectory = "storage/comprobantesEcommerce/Boletas/";
    //                 $pdfFileName = "boleta_" . date('Ymd_His') . "_" . $idPedido . ".pdf";
    //                 $pdfPath = $pdfDirectory . $pdfFileName;
                    
    //                 if (!file_exists($pdfDirectory)) {
    //                     mkdir($pdfDirectory, 0755, true);
    //                 }
                    
    //                 $this->generateBoletaPDF($pdfPath, $nombreCompleto, $detallesPedido, $total);
    //                 // Modified section from the main code where the email is sent
    //                 Mail::to($usuario->correo)->send(new NotificacionPagoCompletadoBoleta(
    //                     $nombreCompleto,
    //                     $detallesPedido,
    //                     $total,
    //                     $pdfPath,
    //                     $idPedido  // Pass the idPedido
    //                 ));
    //             } elseif ($pedido->tipo_comprobante === 'factura') {
    //                 $pdfDirectory = "storage/comprobantesEcommerce/Facturas/";
    //                 $pdfFileName = "factura_" . date('Ymd_His') . "_" . $idPedido . ".pdf";
    //                 $pdfPath = $pdfDirectory . $pdfFileName;
                    
    //                 if (!file_exists($pdfDirectory)) {
    //                     mkdir($pdfDirectory, 0755, true);
    //                 }
                    
    //                 $this->generateFacturaPDF($pdfPath, $nombreCompleto, $detallesPedido, $total, $pedido->ruc);
    //                 Mail::to($usuario->correo)->send(new NotificacionPagoCompletadoFactura(
    //                     $nombreCompleto,
    //                     $detallesPedido,
    //                     $total,
    //                     $pdfPath,
    //                     $pedido->ruc,
    //                     $idPedido  // Pass the idPedido
    //                 ));
    //             }
    //         }

    //         return response()->json([
    //             'success' => true, 
    //             'message' => 'Comprobante recibido y procesado correctamente'
    //         ], 200);

    //     } catch (\Exception $e) {
    //         Log::error('Error al procesar el comprobante: ' . $e->getMessage());
    //         return response()->json(['error' => 'Error interno: ' . $e->getMessage()], 500);
    //     }
    // }

    public function recibirPagoComprobante(Request $request)
    {
        try {
            $idPedido = $request->input('idPedido');
            $comprobante = $request->file('comprobante');

            if (!$idPedido || !$comprobante) {
                Log::warning('ID del pedido o comprobante no proporcionado.');
                return response()->json(['error' => 'ID del pedido y comprobante son requeridos'], 400);
            }

            // Validar el archivo
            $validator = Validator::make($request->all(), [
                'comprobante' => 'required|image|mimes:jpeg,png,jpg|max:2048'
            ]);

            if ($validator->fails()) {
                return response()->json(['error' => 'El archivo debe ser una imagen (jpeg, png, jpg) y no exceder 2MB'], 400);
            }

            // Obtener el pedido
            $pedido = Pedido::with('detalles')->find($idPedido);

            if (!$pedido) {
                return response()->json(['success' => false, 'message' => 'Pedido no encontrado'], 404);
            }

             // Validar stock antes de proceder
            $productosSinStock = []; // Array para almacenar productos sin stock

            foreach ($pedido->detalles as $detalle) {
                $stock = Stock::where('idModelo', $detalle->idModelo)
                            ->where('idTalla', $detalle->idTalla)
                            ->first();

                if (!$stock) {
                    // Obtener el nombre del producto
                    $producto = Producto::find($detalle->idProducto);
                    $nombreProducto = $producto ? $producto->nombreProducto : 'Producto no encontrado';
                    
                    // Agregar a la lista de productos sin stock
                    $productosSinStock[] = $nombreProducto;
                } elseif ($stock->cantidad < $detalle->cantidad) {
                    // Obtener el nombre del producto
                    $producto = Producto::find($detalle->idProducto);
                    $nombreProducto = $producto ? $producto->nombreProducto : 'Producto no encontrado';
                    
                    // Agregar a la lista de productos sin stock
                    $productosSinStock[] = $nombreProducto;
                }
            }

            // Si hay productos sin stock, devolver un error con la lista
            if (!empty($productosSinStock)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Los siguientes productos no tienen stock suficiente: ' . implode(', ', $productosSinStock),
                    'productosSinStock' => $productosSinStock // Enviar la lista de productos sin stock
                ], 400);
            }

            // Guardar el comprobante
            $nombreArchivo = time() . '_' . $comprobante->getClientOriginalName();
            $rutaComprobante = $comprobante->storeAs("comprobantes/pedidos/{$idPedido}", $nombreArchivo, 'public');

            $pagoModel = Pago::where('idPedido', $idPedido)->first();

            if (!$pagoModel) {
                return response()->json(['success' => false, 'message' => 'Pago no encontrado para este pedido'], 404);
            }

            if ($pagoModel->estado_pago === 'completado') {
                return response()->json(['success' => false, 'message' => 'Este pago ya ha sido completado previamente'], 200);
            }

            // Actualizar el modelo de pago
            $pagoModel->estado_pago = 'completado';
            $pagoModel->comprobante_url = $rutaComprobante;
            $pagoModel->metodo_pago = $request->input('metodo_pago', 'transferencia'); // o yape, plin, etc.
            $pagoModel->fecha_pago = now();
            $pagoModel->save();

            // Actualizar estado del pedido
            $pedido->estado = 'aprobando';
            $pedido->save();

            // Descontar stock
            foreach ($pedido->detalles as $detalle) {
                $stock = Stock::where('idModelo', $detalle->idModelo)
                            ->where('idTalla', $detalle->idTalla)
                            ->first();

                $stock->cantidad -= $detalle->cantidad;
                $stock->save();
            }

            // MANEJO DE FACTURACIÓN
            if (Facturacion::where('status', 1)->exists()) {
                if ($pedido->tipo_comprobante === 'boleta') {
                    $this->FacturacionActivaBoleta($idPedido);
                } elseif ($pedido->tipo_comprobante === 'factura') {
                    if (!$pedido->ruc) {
                        return response()->json([
                            'success' => false,
                            'message' => 'RUC no válido'
                        ], 400);
                    }
                    $this->FacturacionActivaFactura($idPedido, $pedido->ruc);
                }
            } else {
                // Generar comprobante local
                $usuario = Usuario::find($pedido->idUsuario);

                if (!$usuario) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Usuario no encontrado'
                    ], 404);
                }

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

                if ($pedido->tipo_comprobante === 'boleta') {
                    $pdfDirectory = "storage/comprobantesEcommerce/Boletas/";
                    $pdfFileName = "boleta_" . date('Ymd_His') . "_" . $idPedido . ".pdf";
                    $pdfPath = $pdfDirectory . $pdfFileName;

                    if (!file_exists($pdfDirectory)) {
                        mkdir($pdfDirectory, 0755, true);
                    }

                    $this->generateBoletaPDF($pdfPath, $nombreCompleto, $detallesPedido, $total);
                    Mail::to($usuario->correo)->send(new NotificacionPagoCompletadoBoleta(
                        $nombreCompleto,
                        $detallesPedido,
                        $total,
                        $pdfPath,
                        $idPedido
                    ));
                } elseif ($pedido->tipo_comprobante === 'factura') {
                    $pdfDirectory = "storage/comprobantesEcommerce/Facturas/";
                    $pdfFileName = "factura_" . date('Ymd_His') . "_" . $idPedido . ".pdf";
                    $pdfPath = $pdfDirectory . $pdfFileName;

                    if (!file_exists($pdfDirectory)) {
                        mkdir($pdfDirectory, 0755, true);
                    }

                    $this->generateFacturaPDF($pdfPath, $nombreCompleto, $detallesPedido, $total, $pedido->ruc);
                    Mail::to($usuario->correo)->send(new NotificacionPagoCompletadoFactura(
                        $nombreCompleto,
                        $detallesPedido,
                        $total,
                        $pdfPath,
                        $pedido->ruc,
                        $idPedido
                    ));
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Comprobante recibido y procesado correctamente'
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error al procesar el comprobante: ' . $e->getMessage());
            return response()->json(['error' => 'Error interno: ' . $e->getMessage()], 500);
        }
    }

    private function generateBoletaPDF($pdfPath, $nombreCompleto, $detallesPedido, $total) {
        $pdf = new FPDF();
        $pdf->AddPage();
        
        // Configuración de márgenes
        $pdf->SetMargins(20, 20, 20);
        
        // Encabezado con logo y título
        $pdf->SetFont('Arial', 'B', 24);
        $pdf->Cell(0, 20, "ECOMMERCE STORE", 0, 1, 'C');
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(0, 5, "Av. Elegancia 123 - Lima", 0, 1, 'C');
        $pdf->Cell(0, 5, "Tel: +51 999 999 999", 0, 1, 'C');
        
        // Línea decorativa
        $pdf->Ln(5);
        $pdf->SetDrawColor(200, 200, 200);
        $pdf->Line(20, $pdf->GetY(), 190, $pdf->GetY());
        $pdf->Ln(10);
        
        // Título del documento
        $pdf->SetFont('Arial', 'B', 16);
        $pdf->SetTextColor(50, 50, 50);
        $pdf->Cell(0, 10, "BOLETA DE VENTA", 0, 1, 'C');
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(0, 5, "Fecha: " . date('d/m/Y'), 0, 1, 'C');
        $pdf->Ln(10);
        
        // Información del cliente en un marco elegante
        $pdf->SetFillColor(248, 248, 248);
        $pdf->SetDrawColor(200, 200, 200);
        $pdf->Rect(20, $pdf->GetY(), 170, 20, 3, 'DF');
        $pdf->SetXY(25, $pdf->GetY() + 5);
        $pdf->SetFont('Arial', 'B', 11);
        $pdf->Cell(30, 5, "Cliente:", 0, 0);
        $pdf->SetFont('Arial', '', 11);
        $pdf->Cell(0, 5, $nombreCompleto, 0, 1);
        $pdf->Ln(20);
        
        // Cabecera de la tabla de productos
        $pdf->SetFillColor(240, 240, 240);
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(80, 10, "Producto", 1, 0, 'C', true);
        $pdf->Cell(30, 10, "Cantidad", 1, 0, 'C', true);
        $pdf->Cell(40, 10, "Precio Unit. (IGV. 18%)", 1, 0, 'C', true);
        $pdf->Cell(20, 10, "Total", 1, 1, 'C', true);
        
        // Detalles de productos
        $pdf->SetFont('Arial', '', 10);
        $pdf->SetFillColor(255, 255, 255);
        foreach ($detallesPedido as $detalle) {
            $pdf->Cell(80, 8, $detalle['producto'], 1, 0, 'L', true);
            $pdf->Cell(30, 8, $detalle['cantidad'], 1, 0, 'C', true);
            $precioUnit = $detalle['subtotal'] / $detalle['cantidad'];
            $pdf->Cell(40, 8, "S/ " . number_format($precioUnit, 2), 1, 0, 'R', true);
            $pdf->Cell(20, 8, "S/ " . number_format($detalle['subtotal'], 2), 1, 1, 'R', true);
        }
        
        // Total
        $pdf->Ln(5);
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(130);
        $pdf->Cell(20, 10, "Total:", 0, 0, 'R');
        $pdf->Cell(20, 10, "S/ " . number_format($total, 2), 0, 1, 'R');
        
        // Pie de página con agradecimiento
        $pdf->Ln(15);
        $pdf->SetFont('Arial', 'I', 10);
        $pdf->SetTextColor(100, 100, 100);
        $pdf->Cell(0, 10, "Gracias por su preferencia", 0, 1, 'C');
        
        // QR Code placeholder en la esquina inferior derecha
        $pdf->SetDrawColor(200, 200, 200);
        $pdf->Rect(150, 240, 30, 30);
        
        // Guardar el PDF
        $pdf->Output('F', $pdfPath);
    }


    private function generateFacturaPDF($pdfPath, $nombreCompleto, $detallesPedido, $total, $ruc) {
        $pdf = new FPDF();
        $pdf->AddPage();
        
        // Configuración de márgenes
        $pdf->SetMargins(20, 20, 20);
        
        // Encabezado con logo y título
        $pdf->SetFont('Arial', 'B', 24);
        $pdf->Cell(0, 20, "ECOMMERCE STORE", 0, 1, 'C');
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(0, 5, "Av. Elegancia 123 - Lima", 0, 1, 'C');
        $pdf->Cell(0, 5, "Tel: +51 999 999 999", 0, 1, 'C');
        $pdf->Cell(0, 5, "RUC: 20XXXXXXXXX", 0, 1, 'C');
        
        // Línea decorativa
        $pdf->Ln(5);
        $pdf->SetDrawColor(200, 200, 200);
        $pdf->Line(20, $pdf->GetY(), 190, $pdf->GetY());
        $pdf->Ln(10);
        
        // Título del documento
        $pdf->SetFont('Arial', 'B', 16);
        $pdf->SetTextColor(50, 50, 50);
        $pdf->Cell(0, 10, "FACTURA ELECTRÓNICA", 0, 1, 'C');
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(0, 5, "Fecha: " . date('d/m/Y'), 0, 1, 'C');
        $pdf->Ln(10);
        
        // Información del cliente en un marco elegante
        $pdf->SetFillColor(248, 248, 248);
        $pdf->SetDrawColor(200, 200, 200);
        $pdf->Rect(20, $pdf->GetY(), 170, 30, 3, 'DF');
        $pdf->SetXY(25, $pdf->GetY() + 5);
        $pdf->SetFont('Arial', 'B', 11);
        $pdf->Cell(30, 5, "Cliente:", 0, 0);
        $pdf->SetFont('Arial', '', 11);
        $pdf->Cell(0, 5, $nombreCompleto, 0, 1);
        $pdf->SetXY(25, $pdf->GetY() + 5);
        $pdf->SetFont('Arial', 'B', 11);
        $pdf->Cell(30, 5, "RUC:", 0, 0);
        $pdf->SetFont('Arial', '', 11);
        $pdf->Cell(0, 5, $ruc, 0, 1);
        $pdf->Ln(20);
        
        // Cabecera de la tabla de productos
        $pdf->SetFillColor(240, 240, 240);
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(80, 10, "Producto", 1, 0, 'C', true);
        $pdf->Cell(30, 10, "Cantidad", 1, 0, 'C', true);
        $pdf->Cell(40, 10, "Precio Unit.", 1, 0, 'C', true);
        $pdf->Cell(20, 10, "Total", 1, 1, 'C', true);
        
        // Detalles de productos
        $pdf->SetFont('Arial', '', 10);
        $pdf->SetFillColor(255, 255, 255);
        foreach ($detallesPedido as $detalle) {
            $pdf->Cell(80, 8, $detalle['producto'], 1, 0, 'L', true);
            $pdf->Cell(30, 8, $detalle['cantidad'], 1, 0, 'C', true);
            $precioUnit = $detalle['subtotal'] / $detalle['cantidad'];
            $pdf->Cell(40, 8, "S/ " . number_format($precioUnit, 2), 1, 0, 'R', true);
            $pdf->Cell(20, 8, "S/ " . number_format($detalle['subtotal'], 2), 1, 1, 'R', true);
        }
        
        // Subtotal, IGV y Total
        $pdf->Ln(5);
        $pdf->SetFont('Arial', '', 11);
        $pdf->Cell(130);
        $pdf->Cell(20, 7, "Subtotal:", 0, 0, 'R');
        $pdf->Cell(20, 7, "S/ " . number_format($total / 1.18, 2), 0, 1, 'R');
        
        $pdf->Cell(130);
        $pdf->Cell(20, 7, "IGV (18%):", 0, 0, 'R');
        $pdf->Cell(20, 7, "S/ " . number_format($total - ($total / 1.18), 2), 0, 1, 'R');
        
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(130);
        $pdf->Cell(20, 10, "Total:", 0, 0, 'R');
        $pdf->Cell(20, 10, "S/ " . number_format($total, 2), 0, 1, 'R');
        
        // Pie de página con información adicional
        $pdf->Ln(15);
        $pdf->SetFont('Arial', 'I', 10);
        $pdf->SetTextColor(100, 100, 100);
        $pdf->Cell(0, 10, "Gracias por su preferencia", 0, 1, 'C');
        $pdf->Cell(0, 5, "Este documento es una representación impresa de un Comprobante Electrónico", 0, 1, 'C');
        
        // QR Code placeholder en la esquina inferior derecha
        $pdf->SetDrawColor(200, 200, 200);
        $pdf->Rect(150, 240, 30, 30);
        
        // Guardar el PDF
        $pdf->Output('F', $pdfPath);
    }

    public function FacturacionActivaFactura($idPedido)
    {
        try {
            Log::info("Iniciando facturación de factura para el pedido: {$idPedido}");
    
            $pedido = Pedido::with(['detalles', 'usuario'])
                ->where('idPedido', $idPedido)
                ->firstOrFail();
    
            $empresa = Empresa::firstOrFail();
            
            // Estructura de company
            $companyData = [
                "ruc" => $empresa->ruc,
            ];
    
            // Estructura de cliente
            $clientData = [
                "tipo_doc" => "6", //RUC
                "num_doc" => $pedido->ruc,
                "razon_social" => $pedido->usuario->nombres . ' ' . $pedido->usuario->apellidos,
                "correo" => $pedido->usuario->correo,
                "direccion" => $pedido->direccion,
            ];
    
            // Procesamiento de detalles
            $details = [];
            foreach ($pedido->detalles as $detalle) {
                $producto = Producto::findOrFail($detalle->idProducto);
                $talla = Talla::findOrFail($detalle->idTalla);
                $modelo = Modelo::findOrFail($detalle->idModelo);
    
                $precioBase = $detalle->precioUnitario;
                $cantidad = $detalle->cantidad;
                $subtotal = $precioBase * $cantidad;
                $igv = $subtotal * ($empresa->igv/100);
    
                $details[] = [
                    "cod_producto" => $producto->idProducto,
                    "unidad" => "NIU",
                    "cantidad" => $cantidad,
                    "mto_valor_unitario" => $precioBase,
                    "descripcion" => "{$producto->nombreProducto} Talla: {$talla->nombreTalla} Modelo: {$modelo->nombreModelo}",
                    "mto_base_igv" => $subtotal,
                    "porcentaje_igv" => $empresa->igv,
                    "igv" => $igv,
                    "tip_afe_igv" => "10",
                    "total_impuestos" => $igv,
                    "mto_valor_venta" => $subtotal,
                    "mto_precio_unitario" => $precioBase * (1 + ($empresa->igv/100)),
                ];
            }
    
            // Cálculo de totales
            $totalGravadas = array_sum(array_column($details, 'mto_valor_venta'));
            $totalIGV = array_sum(array_column($details, 'igv'));
            $totalImpuestos = array_sum(array_column($details, 'total_impuestos'));
            $totalVenta = $totalGravadas + $totalImpuestos;
    
             // Primero, asegurarnos que existe el tipo de documento
            $tipoDocumento = TipoDocumento::firstOrCreate(
                ['codigo' => '01'],
                [
                    'descripcion' => 'Factura',
                    'abreviatura' => 'F'
                ]
            );

            // Validar si ya existe un correlativo para este tipo de documento
            $correlativo = Correlativo::where('idTipoDocumento', $tipoDocumento->idTipoDocumento)
                ->where('codigo', '01')
                ->first();

            // Si no existe, crear el correlativo con valores iniciales
            if (!$correlativo) {
                $correlativo = Correlativo::create([
                    'idTipoDocumento' => $tipoDocumento->idTipoDocumento,
                    'numero_serie' => '001',
                    'numero_actual' => 1,
                    'codigo' => '01'
                ]);
            }
                
            $numeroSerie = $tipoDocumento->abreviatura . str_pad($correlativo->numero_serie, 3, '0', STR_PAD_LEFT);
            $numeroCorrelativo = $correlativo->numero_actual;
    
    
            // Estructura de la factura
            $invoiceData = [
                "ubl_version" => "2.1",
                "tipo_operacion" => "0101",
                "tipo_doc" => "01",
                "serie" => $numeroSerie,
                "correlativo" => $numeroCorrelativo,
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
    
            // Datos completos para la API
            $data = [
                "company" => $companyData,
                "client" => $clientData,
                "invoice" => $invoiceData,
                "details" => $details,
            ];
    
            // Comenzar transacción
            DB::beginTransaction();
            try {
                // Guardar comprobante
                $comprobante = new Comprobante([
                    'idTipoDocumento' => $tipoDocumento->idTipoDocumento,
                    'idPedido' => $pedido->idPedido,
                    'idUsuario' => $pedido->idUsuario,
                    'serie' => $numeroSerie,
                    'correlativo' => $numeroCorrelativo,
                    'fecha_emision' => now(),
                    'sub_total' => $totalGravadas,
                    'mto_total' => $totalVenta
                ]);
                $comprobante->save();
    
                // Guardar detalles del comprobante
                foreach ($pedido->detalles as $detalle) {
                    DetalleComprobante::create([
                        'idComprobante' => $comprobante->idComprobante,
                        'idProducto' => $detalle->idProducto,
                        'idTalla' => $detalle->idTalla,
                        'idModelo' => $detalle->idModelo,
                        'cantidad' => $detalle->cantidad,
                        'precio_unitario' => $detalle->precioUnitario,
                        'subtotal' => $detalle->cantidad * $detalle->precioUnitario
                    ]);
                }

                // Buscar el correlativo donde el código sea '01'
                $correlativo = Correlativo::where('codigo', '01')
                ->where('idTipoDocumento', $tipoDocumento->idTipoDocumento)
                ->first();

                // Verificar si el correlativo existe
                if ($correlativo) {
                // Incrementar correlativo
                $correlativo->numero_actual++;
                $correlativo->save();
                } else {
                // Lanzar una excepción o manejar el caso en que no exista el correlativo
                throw new \Exception("No se encontró un correlativo con código '03'.");
                }
    
                // Enviar a la API
                $apiResponse = Http::post('https://facturacion.thonymarckdev.online/api/API_FACTURA_PDF', $data);
    
                if (!$apiResponse->successful()) {
                    throw new Exception('Error en la respuesta de la API de facturación');
                }
    
                DB::commit();
                Log::info("Facturación completada exitosamente para el pedido {$idPedido}");
                
                return response()->json([
                    'success' => true, 
                    'message' => 'Factura generada correctamente'
                ]);
    
            } catch (Exception $e) {
                DB::rollBack();
                Log::error("Error en la transacción: " . $e->getMessage());
                throw $e;
            }
    
        } catch (Exception $e) {
            Log::error("Error al generar la Factura: " . $e->getMessage());
            return response()->json([
                'success' => false, 
                'message' => 'Error al generar la Factura: ' . $e->getMessage()
            ], 500);
        }
    }
   

    public function FacturacionActivaBoleta($idPedido)
    {
        try {
            Log::info("Iniciando facturación de boleta para el pedido: {$idPedido}");
    
            $pedido = Pedido::with(['detalles', 'usuario'])
                ->where('idPedido', $idPedido)
                ->firstOrFail();
    
            $empresa = Empresa::firstOrFail();
            
            // Estructura de company
            $companyData = [
                "ruc" => $empresa->ruc,
            ];
    
            // Estructura de cliente
            $clientData = [
                "tipo_doc" => "1",
                "num_doc" => $pedido->usuario->dni,
                "razon_social" => $pedido->usuario->nombres . ' ' . $pedido->usuario->apellidos,
                "correo" => $pedido->usuario->correo,
                "direccion" => $pedido->direccion,
            ];
    
            // Procesamiento de detalles
            $details = [];
            foreach ($pedido->detalles as $detalle) {
                $producto = Producto::findOrFail($detalle->idProducto);
                $talla = Talla::findOrFail($detalle->idTalla);
                $modelo = Modelo::findOrFail($detalle->idModelo);
    
                $precioBase = $detalle->precioUnitario;
                $cantidad = $detalle->cantidad;
                $subtotal = $precioBase * $cantidad;
                $igv = $subtotal * ($empresa->igv/100);
    
                $details[] = [
                    "cod_producto" => $producto->idProducto,
                    "unidad" => "NIU",
                    "cantidad" => $cantidad,
                    "mto_valor_unitario" => $precioBase,
                    "descripcion" => "{$producto->nombreProducto} Talla: {$talla->nombreTalla} Modelo: {$modelo->nombreModelo}",
                    "mto_base_igv" => $subtotal,
                    "porcentaje_igv" => $empresa->igv,
                    "igv" => $igv,
                    "tip_afe_igv" => "10",
                    "total_impuestos" => $igv,
                    "mto_valor_venta" => $subtotal,
                    "mto_precio_unitario" => $precioBase * (1 + ($empresa->igv/100)),
                ];
            }
    
            // Cálculo de totales
            $totalGravadas = array_sum(array_column($details, 'mto_valor_venta'));
            $totalIGV = array_sum(array_column($details, 'igv'));
            $totalImpuestos = array_sum(array_column($details, 'total_impuestos'));
            $totalVenta = $totalGravadas + $totalImpuestos;
    

             // Primero, asegurarnos que existe el tipo de documento
             $tipoDocumento = TipoDocumento::firstOrCreate(
                ['codigo' => '03'],
                [
                    'descripcion' => 'Boleta',
                    'abreviatura' => 'B'
                ]
            );

            // Validar si ya existe un correlativo para este tipo de documento
            $correlativo = Correlativo::where('idTipoDocumento', $tipoDocumento->idTipoDocumento)
                ->where('codigo', '03')
                ->first();

            // Si no existe, crear el correlativo con valores iniciales
            if (!$correlativo) {
                $correlativo = Correlativo::create([
                    'idTipoDocumento' => $tipoDocumento->idTipoDocumento,
                    'numero_serie' => '001',
                    'numero_actual' => 1,
                    'codigo' => '03'
                ]);
            }
    
            $numeroSerie = $tipoDocumento->abreviatura . str_pad($correlativo->numero_serie, 3, '0', STR_PAD_LEFT);
            $numeroCorrelativo = $correlativo->numero_actual;
    
    
            // Estructura de la factura
            $invoiceData = [
                "ubl_version" => "2.1",
                "tipo_operacion" => "0101",
                "tipo_doc" => "03",
                "serie" => $numeroSerie,
                "correlativo" => $numeroCorrelativo,
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
    
            // Datos completos para la API
            $data = [
                "company" => $companyData,
                "client" => $clientData,
                "invoice" => $invoiceData,
                "details" => $details,
            ];
    
            // Comenzar transacción
            DB::beginTransaction();
            try {
                // Guardar comprobante
                $comprobante = new Comprobante([
                    'idTipoDocumento' => $tipoDocumento->idTipoDocumento,
                    'idPedido' => $pedido->idPedido,
                    'idUsuario' => $pedido->idUsuario,
                    'serie' => $numeroSerie,
                    'correlativo' => $numeroCorrelativo,
                    'fecha_emision' => now(),
                    'sub_total' => $totalGravadas,
                    'mto_total' => $totalVenta
                ]);
                $comprobante->save();
    
                // Guardar detalles del comprobante
                foreach ($pedido->detalles as $detalle) {
                    DetalleComprobante::create([
                        'idComprobante' => $comprobante->idComprobante,
                        'idProducto' => $detalle->idProducto,
                        'idTalla' => $detalle->idTalla,
                        'idModelo' => $detalle->idModelo,
                        'cantidad' => $detalle->cantidad,
                        'precio_unitario' => $detalle->precioUnitario,
                        'subtotal' => $detalle->cantidad * $detalle->precioUnitario
                    ]);
                }
    
                // Buscar el correlativo donde el código sea '03'
                $correlativo = Correlativo::where('codigo', '03')
                ->where('idTipoDocumento', $tipoDocumento->idTipoDocumento)
                ->first();

                // Verificar si el correlativo existe
                if ($correlativo) {
                // Incrementar correlativo
                $correlativo->numero_actual++;
                $correlativo->save();
                } else {
                // Lanzar una excepción o manejar el caso en que no exista el correlativo
                throw new \Exception("No se encontró un correlativo con código '03'.");
                }
    
                // Enviar a la API
                $apiResponse = Http::post('https://facturacion.thonymarckdev.online/api/API_BOLETA_PDF', $data);
    
                if (!$apiResponse->successful()) {
                    throw new Exception('Error en la respuesta de la API de facturación');
                }
    
                DB::commit();
                Log::info("Facturación completada exitosamente para el pedido {$idPedido}");
                
                return response()->json([
                    'success' => true, 
                    'message' => 'Boleta generada correctamente'
                ]);
    
            } catch (Exception $e) {
                DB::rollBack();
                Log::error("Error en la transacción: " . $e->getMessage());
                throw $e;
            }
    
        } catch (Exception $e) {
            Log::error("Error al generar boleta: " . $e->getMessage());
            return response()->json([
                'success' => false, 
                'message' => 'Error al generar la boleta: ' . $e->getMessage()
            ], 500);
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







