<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Pago;
use App\Models\Pedido;
use App\Models\Producto;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;
use App\Models\Usuario;
use Illuminate\Support\Facades\Mail;
use App\Mail\NotificacionPagoCompletado;
use FPDF;

// Mercado Pago
use MercadoPago\MercadoPagoConfig;
use MercadoPago\Client\Payment\PaymentClient;
use MercadoPago\Client\Preference\PreferenceClient;
use MercadoPago\Resources\MerchantOrder\Item;

class MercadoPagoController extends Controller
{
    public function __construct()
    {
        Log::info('Inicializando configuración de MercadoPago');
        MercadoPagoConfig::setAccessToken(env('MP_ACCESS_TOKEN'));
    }

    public function createPreference(Request $request)
    {
        Log::info('Iniciando la creación de preferencia con MercadoPago', ['data' => $request->all()]);
        $request->validate([
            'idPedido' => 'required|integer',
            'detalles' => 'required|array',
            'total' => 'required|numeric'
        ]);

        $idPedido = $request->input('idPedido');
        $detalles = $request->input('detalles');
        $total = $request->input('total');

        Log::info('Datos validados correctamente', ['idPedido' => $idPedido, 'total' => $total]);

        $currentUrlBase = 'https://ecommerce-front-react.vercel.app';
        $backUrls = [
            "success" => "{$currentUrlBase}/pedidos?status=approved&external_reference={$idPedido}&payment_type=online",
            "failure" => "{$currentUrlBase}/pedidos?status=failure&external_reference={$idPedido}",
            "pending" => "{$currentUrlBase}/pedidos?status=pending&external_reference={$idPedido}"
        ];

        $items = [];
        foreach ($detalles as $detalle) {
            Log::info('Procesando detalle del pedido', ['detalle' => $detalle]);
            $item = new Item();
            $item->id = $detalle['idProducto'];
            $item->title = $detalle['nombreProducto'];
            $item->quantity = (int)$detalle['cantidad'];
            $item->unit_price = (float)$detalle['precioUnitario'];
            $item->currency_id = "PEN";
            $items[] = $item;
        }

        $preferenceClient = new PreferenceClient();
        $preference = [
            "items" => $items,
            "back_urls" => $backUrls,
            "auto_return" => "approved",
            "external_reference" => $idPedido
        ];

        try {
            Log::info('Iniciando proceso para crear preferencia en MercadoPago', [
                'request_data' => [
                    'idPedido' => $preference['external_reference'] ?? null,
                    'items' => $preference['items'] ?? [],
                    'back_urls' => $preference['back_urls'] ?? []
                ]
            ]);
        
            Log::info('Enviando preferencia a la API de MercadoPago', ['preference' => $preference]);
            $response = $preferenceClient->create($preference);
        
            // Log para capturar la respuesta completa
            Log::info('Respuesta de la API de MercadoPago', ['response' => $response]);
        
            if (isset($response->id)) {
                Log::info('Preferencia creada exitosamente', [
                    'preference_id' => $response->id,
                    'init_point' => $response->init_point
                ]);
                return response()->json([
                    'success' => true,
                    'init_point' => $response->init_point,
                    'preference_id' => $response->id
                ]);
            } else {
                Log::warning('No se recibió un ID de preferencia en la respuesta de MercadoPago', [
                    'response' => $response,
                    'status' => $response->status ?? null
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Error al crear la preferencia en MercadoPago'
                ]);
            }
        } catch (\MercadoPago\Exceptions\MPApiException $e) {
            Log::error('Error en la API de MercadoPago', [
                'error_message' => $e->getMessage(),
                'error_response' => $e->getApiResponse()->getContent()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Error al crear la preferencia: ' . $e->getApiResponse()->getContent()
            ]);
        } catch (\Exception $e) {
            Log::error('Error inesperado al crear la preferencia', [
                'error_message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Error al crear la preferencia: ' . $e->getMessage()
            ]);
        }
    }

   
    public function recibirPago(Request $request)
    {
        Log::info('Recibiendo webhook de MercadoPago', ['request_data' => $request->all()]);

        try {
            $id = $request->input('data')['id'] ?? null;
            $type = $request->input('type') ?? null;

            if (!$id || $type !== 'payment') {
                Log::warning('Datos inválidos en el webhook', ['id' => $id, 'type' => $type]);
                return response()->json(['error' => 'Datos inválidos'], 400);
            }

            $url = "https://api.mercadopago.com/v1/payments/{$id}";
            Log::info("Consultando pago en MercadoPago", ['url' => $url]);

            $client = new Client();
            $response = $client->request('GET', $url, [
                'headers' => ['Authorization' => 'Bearer ' . env('MP_ACCESS_TOKEN')],
            ]);
            $pago = json_decode($response->getBody(), true);

            Log::info('Respuesta de MercadoPago', ['pago' => $pago]);

            // Verificación de estado del pago
            $estado_pago = $pago['status'];
            $metodo_pago = $pago['payment_method_id'] ?? null;
            $externalReference = $pago['external_reference'];

            Log::info("Estado del pago recibido", [
                'estado_pago' => $estado_pago,
                'metodo_pago' => $metodo_pago,
                'external_reference' => $externalReference
            ]);

            // Consultar el modelo Pago
            $pagoModel = Pago::where('idPedido', $externalReference)->first();
            if (!$pagoModel) {
                Log::warning("Pago no encontrado para el pedido", ['external_reference' => $externalReference]);
                return response()->json(['success' => false, 'message' => 'Pago no encontrado para este pedido'], 200);
            }

            if ($pagoModel->estado_pago === 'completado') {
                Log::warning("El pago ya ha sido completado previamente", ['id' => $id]);
                return response()->json(['success' => false, 'message' => 'Este pago ya ha sido completado previamente'], 200);
            }

            // Actualización de estado de pago
            $pagoModel->estado_pago = 'completado';
            if ($metodo_pago) {
                $pagoModel->metodo_pago = $metodo_pago;
            }
            $pagoModel->save();

            // Consultar el pedido
            $pedido = Pedido::with('detalles')->find($externalReference);
            if (!$pedido) {
                Log::warning("Pedido no encontrado", ['external_reference' => $externalReference]);
                return response()->json(['success' => false, 'message' => 'Pedido no encontrado'], 404);
            }

            // Actualizar estado del pedido
            if ($estado_pago === 'approved') {
                if (in_array($pedido->estado, ['aprobando', 'completado'])) {
                    Log::warning("El pedido ya fue procesado previamente", ['external_reference' => $externalReference]);
                    return response()->json(['success' => false, 'message' => 'El pedido ya fue procesado previamente'], 200);
                }

                $pedido->estado = 'aprobando';
                $pedido->save();

                // Descontar stock de productos
                foreach ($pedido->detalles as $detalle) {
                    $producto = Producto::find($detalle->idProducto);
                    if ($producto) {
                        $producto->stock -= $detalle->cantidad;
                        $producto->save();
                    }
                }

                // Generar boleta y enviar correo
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

                    // Generación de boleta
                    $pdfDirectory = "boletas/{$usuario->idUsuario}/{$externalReference}";
                    $pdfFileName = "boleta_pedido_{$externalReference}.pdf";
                    $pdfPath = public_path("{$pdfDirectory}/{$pdfFileName}");

                    if (!file_exists(public_path($pdfDirectory))) {
                        mkdir(public_path($pdfDirectory), 0755, true);
                    }

                    // Generar PDF de la boleta
                    $this->generateBoletaPDF($pdfPath, $nombreCompleto, $detallesPedido, $total);

                    // Enviar correo
                    Mail::to($usuario->correo)->send(new NotificacionPagoCompletado(
                        $nombreCompleto,
                        $detallesPedido,
                        $total,
                        $pdfPath
                    ));

                    Log::info("Boleta generada y correo enviado", ['usuario' => $usuario->correo, 'pdf_path' => $pdfPath]);
                }
            }

            Log::info("Estado de pago y pedido actualizado correctamente", ['id' => $id]);
            return response()->json(['success' => true, 'message' => 'Estado de pago y pedido actualizados correctamente'], 200);
        } catch (\Exception $e) {
            Log::error('Error al procesar el webhook de pago', ['error' => $e->getMessage()]);
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
}
