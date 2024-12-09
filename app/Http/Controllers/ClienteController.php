<?php

namespace App\Http\Controllers;

use App\Models\Usuario;
use App\Models\Carrito;
use App\Models\CarritoDetalle;
use Illuminate\Http\Request;
use App\Models\Producto;
use App\Models\Pedido;
use App\Models\DetalleDireccion;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Mail\NotificacionCrearCuenta;
use App\Mail\NotificacionActualizarCorreo;
use App\Mail\NotificacionPedido;
use App\Mail\NotificacionPagoProcesado;
use App\Mail\NotificacionDireccionAgregada;
use App\Mail\NotificacionDireccionEliminada;
use App\Mail\NotificacionDireccionPredeterminada;
use App\Mail\CodigoVerificacion;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;

class ClienteController extends Controller
{
  
    // En EstudianteController.php
    public function perfilCliente()
    {
        $usuario = Auth::user();
        $profileUrl = $usuario->perfil ? url("storage/{$usuario->perfil}") : null;

        return response()->json([
            'success' => true,
            'data' => [
                'idUsuario' => $usuario->idUsuario,
                'username' => $usuario->username,
                'nombres' => $usuario->nombres,
                'apellidos' => $usuario->apellidos,
                'dni' => $usuario->dni,
                'correo' => $usuario->correo,
                'edad' => $usuario->edad,
                'nacimiento' => $usuario->nacimiento,
                'sexo' => $usuario->sexo,
                'direccion' => $usuario->direccion,
                'telefono' => $usuario->telefono,
                'departamento' => $usuario->departamento,
                'perfil' => $profileUrl,  // URL completa de la imagen de perfil
            ]
        ]);
    }

    public function uploadProfileImageCliente(Request $request, $idUsuario)
    {
        $docente = Usuario::find($idUsuario);
        if (!$docente) {
            return response()->json(['success' => false, 'message' => 'Usuario no encontrado'], 404);
        }

        // Verifica si hay un archivo en la solicitud
        if ($request->hasFile('perfil')) {
            $path = "profiles/$idUsuario";

            // Si hay una imagen de perfil existente, elimínala antes de guardar la nueva
            if ($docente->perfil && Storage::disk('public')->exists($docente->perfil)) {
                Storage::disk('public')->delete($docente->perfil);
            }

            // Guarda la nueva imagen de perfil en el disco 'public'
            $filename = $request->file('perfil')->store($path, 'public');
            $docente->perfil = $filename; // Actualiza la ruta en el campo `perfil` del usuario
            $docente->save();

            return response()->json(['success' => true, 'filename' => basename($filename)]);
        }

        return response()->json(['success' => false, 'message' => 'No se cargó la imagen'], 400);
    }


    public function updateCliente(Request $request, $idUsuario)
    {
        $docente = Usuario::find($idUsuario);
        if (!$docente || $docente->rol !== 'cliente') {
            return response()->json(['success' => false, 'message' => 'Cliente no encontrado'], 404);
        }
    
        // Verificar si el nuevo correo ya está en uso por otro usuario
        $nuevoCorreo = $request->input('correo');
        if ($nuevoCorreo && $nuevoCorreo !== $docente->correo) {
            $correoExistente = Usuario::where('correo', $nuevoCorreo)->where('idUsuario', '!=', $idUsuario)->exists();
            if ($correoExistente) {
                return response()->json(['success' => false, 'message' => 'El correo ya está en uso'], 400);
            }
        }
    
        // Actualizar los datos del usuario
        $docente->update($request->only([
            'nombres', 'apellidos', 'dni', 'correo', 'edad', 'nacimiento',
            'sexo', 'direccion', 'telefono', 'departamento'
        ]));
    
        // Enviar correo al nuevo correo si el correo ha cambiado
        if ($nuevoCorreo && $nuevoCorreo !== $docente->correo) {
            $mensaje = 'Tu dirección de correo electrónico ha sido actualizada correctamente en Cpura.';
            Mail::to($nuevoCorreo)->send(new NotificacionActualizarCorreo($mensaje));
        }
    
        return response()->json(['success' => true, 'message' => 'Datos actualizados correctamente']);
    }

    public function listarProductos(Request $request)
    {
        // Obtener los parámetros de la solicitud
        $categoriaId = $request->input('categoria');
        $texto = $request->input('texto');
        $idProducto = $request->input('idProducto');
        $precioInicial = $request->input('precioInicial');
        $precioFinal = $request->input('precioFinal');
    
        // Construir la consulta para obtener los productos
        $query = Producto::with('categoria:idCategoria,nombreCategoria');
    
        // Filtrar por idProducto si el parámetro 'idProducto' existe
        if ($idProducto) {
            $query->where('idProducto', $idProducto);
        }
    
        // Filtrar por categoría si el parámetro 'categoria' existe
        if ($categoriaId) {
            $query->where('idCategoria', $categoriaId);
        }
    
        // Filtrar por texto en el nombre del producto si el parámetro 'texto' existe
        if ($texto) {
            $query->where('nombreProducto', 'like', '%' . $texto . '%');
        }
    
        // Filtrar por rango de precios si se proporcionan
        if ($precioInicial !== null && $precioFinal !== null) {
            $query->whereBetween('precio', [$precioInicial, $precioFinal]);
        }
    
        // Obtener los productos
        $productos = $query->get();
    
        // Si se pasó un 'idProducto', se devuelve un solo producto
        if ($idProducto) {
            $producto = $productos->first();
    
            if ($producto) {
                $producto = [
                    'idProducto' => $producto->idProducto,
                    'nombreProducto' => $producto->nombreProducto,
                    'descripcion' => $producto->descripcion,
                    'nombreCategoria' => $producto->categoria ? $producto->categoria->nombreCategoria : 'Sin Categoría',
                    'precio' => $producto->precio,
                    'stock' => $producto->stock,
                    'imagen' => $producto->imagen,
                ];
    
                return response()->json(['data' => $producto], 200);
            } else {
                return response()->json(['message' => 'Producto no encontrado'], 404);
            }
        }
    
        // Si no se pasa un 'idProducto', se devuelve la lista de productos filtrados
        $productos = $productos->map(function($producto) {
            return [
                'idProducto' => $producto->idProducto,
                'nombreProducto' => $producto->nombreProducto,
                'descripcion' => $producto->descripcion,
                'nombreCategoria' => $producto->categoria ? $producto->categoria->nombreCategoria : 'Sin Categoría',
                'precio' => $producto->precio,
                'stock' => $producto->stock,
                'imagen' => $producto->imagen,
            ];
        });
    
        return response()->json(['data' => $productos], 200);
    }

    public function agregarAlCarrito(Request $request)
    {
        $validatedData = $request->validate([
            'idProducto' => 'required|exists:productos,idProducto',
            'cantidad' => 'required|integer|min:1',
            'idUsuario' => 'required|exists:usuarios,idUsuario'
        ]);
    
        try {
            // Obtener el producto y verificar el stock
            $producto = Producto::find($validatedData['idProducto']);
            $cantidadAgregar = $validatedData['cantidad'];
    
            // Obtener el carrito del usuario
            $carrito = Carrito::firstOrCreate(['idUsuario' => $validatedData['idUsuario']]);
    
            // Verificar si el producto ya está en el carrito
            $carritoDetalle = CarritoDetalle::where('idCarrito', $carrito->idCarrito)
                                            ->where('idProducto', $validatedData['idProducto'])
                                            ->first();
    
            // Si el producto ya está en el carrito
            if ($carritoDetalle) {
                // Calcular la nueva cantidad total sumando la cantidad actual con la nueva
                $nuevaCantidad = $carritoDetalle->cantidad + $cantidadAgregar;
    
                // Verificar si la cantidad total excede el stock disponible
                if ($nuevaCantidad > $producto->stock) {
                    return response()->json([
                        'success' => false,
                        'message' => 'La cantidad total en el carrito supera el stock disponible',
                    ], 400);
                }
    
                // Actualizar la cantidad y recalcular el precio total
                $nuevoPrecio = $producto->precio * $nuevaCantidad;
    
                // Actualizar el detalle del carrito
                $carritoDetalle->update([
                    'cantidad' => $nuevaCantidad,
                    'precio' => $nuevoPrecio
                ]);
            } else {
                // Si el producto no está en el carrito, lo agregamos
                // Verificar si la cantidad no supera el stock disponible
                if ($cantidadAgregar > $producto->stock) {
                    return response()->json([
                        'success' => false,
                        'message' => 'La cantidad solicitada excede el stock disponible',
                    ], 400);
                }
    
                // Crear un nuevo detalle en el carrito
                $nuevoPrecio = $producto->precio * $cantidadAgregar;
                CarritoDetalle::create([
                    'idCarrito' => $carrito->idCarrito,
                    'idProducto' => $validatedData['idProducto'],
                    'cantidad' => $cantidadAgregar,
                    'precio' => $nuevoPrecio
                ]);
            }
    
            return response()->json([
                'success' => true,
                'message' => 'Producto agregado al carrito con éxito',
            ], 201);
    
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al agregar al carrito',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function listarCarrito()
    {
        try {
            $userId = Auth::id();
    
            // Obtener los productos en el carrito del usuario autenticado
            $carritoDetalles = CarritoDetalle::with('producto')
                ->whereHas('carrito', function($query) use ($userId) {
                    $query->where('idUsuario', $userId);
                })
                ->get();
    
            $productos = $carritoDetalles->map(function($detalle) {
                return [
                    'idProducto' => $detalle->producto->idProducto,
                    'nombreProducto' => $detalle->producto->nombreProducto,
                    'descripcion' => $detalle->producto->descripcion,
                    'cantidad' => $detalle->cantidad,
                    'precio' => (float) $detalle->producto->precio, // Cambié esto para que obtenga el precio correcto del producto
                    'subtotal' => (float) ($detalle->producto->precio * $detalle->cantidad), // Se calcula correctamente
                    'stock' => (int) $detalle->producto->stock,
                    'imagen' => $detalle->producto->imagen,  // Asegúrate de que el campo 'imagen' exista
                    'idCategoria' => $detalle->producto->idCategoria,
                ];
            });
    
            return response()->json(['success' => true, 'data' => $productos], 200);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error al obtener el carrito'], 500);
        }
    }
    public function actualizarCantidad(Request $request, $idProducto)
    {
        $userId = Auth::id();
        $cantidad = $request->input('cantidad');
        
        // Buscar el detalle del carrito que corresponde al producto y usuario autenticado
        $detalle = CarritoDetalle::whereHas('carrito', function($query) use ($userId) {
                $query->where('carrito.idUsuario', $userId);
            })
            ->where('idProducto', $idProducto)
            ->first();
    
        if (!$detalle) {
            return response()->json(['success' => false, 'message' => 'Producto no encontrado en el carrito'], 404);
        }
    
        // Obtener el producto
        $producto = Producto::find($idProducto);
        if (!$producto) {
            return response()->json(['success' => false, 'message' => 'Producto no encontrado en la base de datos'], 404);
        }
    
        // Verificar si la cantidad solicitada excede el stock disponible
        if ($cantidad > $producto->stock) {
            return response()->json([
                'success' => false,
                'message' => 'La cantidad solicitada supera el stock disponible'
            ], 400);
        }
    
        // Actualizar la cantidad en el carrito
        $detalle->cantidad = $cantidad;
    
        // Actualizar el precio total (subtotal) en el carritoDetalle
        $detalle->precio = $producto->precio * $cantidad;
    
        // Guardar los cambios
        $detalle->save();
    
        return response()->json(['success' => true, 'message' => 'Cantidad y precio actualizados correctamente'], 200);
    }
 
     // Eliminar un producto del carrito
     public function eliminarProducto($idProducto)
     {
         $userId = Auth::id();
 
         // Buscar el detalle del carrito que corresponde al producto y usuario autenticado
         $detalle = CarritoDetalle::whereHas('carrito', function($query) use ($userId) {
                 $query->where('carrito.idUsuario', $userId); // Cambiar `carrito.id` por `carrito.idUsuario`
             })
             ->where('idProducto', $idProducto)
             ->first();
 
         if (!$detalle) {
             return response()->json(['success' => false, 'message' => 'Producto no encontrado en el carrito'], 404);
         }
 
         // Eliminar el detalle del carrito
         $detalle->delete();
 
         return response()->json(['success' => true, 'message' => 'Producto eliminado del carrito'], 200);
     }


     public function crearPedido(Request $request)
    {
        DB::beginTransaction();

        try {
            $request->validate([
                'idUsuario' => 'required|integer',
                'idCarrito' => 'required|integer',
                'total' => 'required|numeric',
                'idDireccion' => 'required|integer|exists:detalle_direcciones,idDireccion',
            ]);

            $idUsuario = $request->input('idUsuario');
            $idCarrito = $request->input('idCarrito');
            $total = $request->input('total');
            $idDireccion = $request->input('idDireccion');
            $estadoPedido = 'pendiente';

            // Crear el pedido
            $pedidoId = DB::table('pedidos')->insertGetId([
                'idUsuario' => $idUsuario,
                'total' => $total,
                'estado' => $estadoPedido,
            ]);

            DB::table('detalle_direccion_pedido')->insert([
                'idPedido' => $pedidoId,
                'idDireccion' => $idDireccion,
            ]);

            $detallesCarrito = DB::table('carrito_detalle')
                ->where('idCarrito', $idCarrito)
                ->get();

            if ($detallesCarrito->isEmpty()) {
                throw new \Exception('El carrito está vacío.');
            }

            $productos = [];
            foreach ($detallesCarrito as $detalle) {
                $producto = DB::table('productos')->where('idProducto', $detalle->idProducto)->first();
                if (!$producto || $producto->stock < $detalle->cantidad) {
                    throw new \Exception("Stock insuficiente para el producto: {$producto->nombreProducto}.");
                }

                $subtotal = $detalle->cantidad * $detalle->precio;
                DB::table('pedido_detalle')->insert([
                    'idPedido' => $pedidoId,
                    'idProducto' => $detalle->idProducto,
                    'cantidad' => $detalle->cantidad,
                    'precioUnitario' => $detalle->precio,
                    'subtotal' => $subtotal,
                ]);

                $productos[] = (object) [
                    'nombreProducto' => $producto->nombreProducto,
                    'cantidad' => $detalle->cantidad,
                    'precioUnitario' => $detalle->precio,
                    'subtotal' => $subtotal,
                ];
            }

            // Registrar el pago (sin metodo_pago ni comprobante si no se proporcionan)
            DB::table('pagos')->insert([
                'idPedido' => $pedidoId,
                'monto' => $total,
                'estado_pago' => 'pendiente', // El pago aún no está completado
            ]);

            DB::table('carrito_detalle')->where('idCarrito', $idCarrito)->delete();

            DB::commit();

            // Enviar el correo de confirmación al usuario
            $correoUsuario = DB::table('usuarios')->where('idUsuario', $idUsuario)->value('correo');
            Mail::to($correoUsuario)->send(new NotificacionPedido($pedidoId, $productos, $total));

            return response()->json([
                'success' => true,
                'message' => 'Pedido creado exitosamente.',
                'idPedido' => $pedidoId,
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al crear pedido y pago: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al crear el pedido.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
        

    //  public function listarPedidos($idUsuario)
    //  {
    //      try {
    //          // Verificar que el idUsuario existe en la tabla 'usuarios'
    //          $usuarioExiste = DB::table('usuarios')->where('idUsuario', $idUsuario)->exists();
    //          if (!$usuarioExiste) {
    //              return response()->json([
    //                  'success' => false,
    //                  'message' => 'Usuario no encontrado.',
    //              ], 404);
    //          }
 
    //          // Obtener los pedidos del usuario, ordenados por 'idPedido' descendente
    //          $pedidos = DB::table('pedidos')
    //              ->where('idUsuario', $idUsuario)
    //              ->orderBy('idPedido', 'desc') // Ordenar por idPedido descendente
    //              ->get();
 
    //          // Para cada pedido, obtener los detalles (productos)
    //          $pedidosConDetalles = [];
 
    //          foreach ($pedidos as $pedido) {
    //              // Obtener los detalles del pedido desde 'pedido_detalle' y 'productos'
    //              $detalles = DB::table('pedido_detalle')
    //                  ->where('idPedido', $pedido->idPedido)
    //                  ->join('productos', 'pedido_detalle.idProducto', '=', 'productos.idProducto')
    //                  ->select(
    //                      'pedido_detalle.idDetallePedido',
    //                      'productos.idProducto',
    //                      'productos.nombreProducto',
    //                      'pedido_detalle.cantidad',
    //                      'pedido_detalle.precioUnitario',
    //                      'pedido_detalle.subtotal'
    //                  )
    //                  ->get();
 
    //              // Agregar los detalles al pedido
    //              $pedidosConDetalles[] = [
    //                  'idPedido' => $pedido->idPedido,
    //                  'idUsuario' => $pedido->idUsuario,
    //                  'total' => $pedido->total,
    //                  'estado' => $pedido->estado,
    //                  'detalles' => $detalles,
    //              ];
    //          }
 
    //          return response()->json([
    //              'success' => true,
    //              'pedidos' => $pedidosConDetalles,
    //          ], 200);
 
    //      } catch (\Exception $e) {
    //          Log::error('Error al listar pedidos: ' . $e->getMessage());
 
    //          return response()->json([
    //              'success' => false,
    //              'message' => 'Error al obtener los pedidos.',
    //              'error' => $e->getMessage(),
    //          ], 500);
    //      }
    //  }

    public function listarPedidos($idUsuario)
    {
        try {
            // Verificar que el idUsuario existe en la tabla 'usuarios'
            $usuarioExiste = DB::table('usuarios')->where('idUsuario', $idUsuario)->exists();
            if (!$usuarioExiste) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no encontrado.',
                ], 404);
            }
    
            // Obtener los pedidos del usuario, ordenados por 'idPedido' descendente
            $pedidos = DB::table('pedidos')
                ->where('idUsuario', $idUsuario)
                ->orderBy('idPedido', 'desc') // Ordenar por idPedido descendente
                ->get();
    
            // Para cada pedido, obtener los detalles (productos) y la dirección
            $pedidosConDetallesYDireccion = [];
    
            foreach ($pedidos as $pedido) {
                // Obtener los detalles del pedido desde 'pedido_detalle' y 'productos'
                $detalles = DB::table('pedido_detalle')
                    ->where('idPedido', $pedido->idPedido)
                    ->join('productos', 'pedido_detalle.idProducto', '=', 'productos.idProducto')
                    ->select(
                        'pedido_detalle.idDetallePedido',
                        'productos.idProducto',
                        'productos.nombreProducto',
                        'pedido_detalle.cantidad',
                        'pedido_detalle.precioUnitario',
                        'pedido_detalle.subtotal'
                    )
                    ->get();
    
                // Obtener la dirección del pedido (corregido el join)
                $direccion = DB::table('detalle_direccion_pedido')
                    ->where('idPedido', $pedido->idPedido)
                    ->join('detalle_direcciones', 'detalle_direccion_pedido.idDireccion', '=', 'detalle_direcciones.idDireccion') // Relación corregida aquí
                    ->select('detalle_direcciones.region', 'detalle_direcciones.provincia', 'detalle_direcciones.direccion')
                    ->first();
    
                // Agregar los detalles y la dirección al pedido
                $pedidosConDetallesYDireccion[] = [
                    'idPedido' => $pedido->idPedido,
                    'idUsuario' => $pedido->idUsuario,
                    'total' => $pedido->total,
                    'estado' => $pedido->estado,
                    'detalles' => $detalles,
                    'direccion' => $direccion, // Dirección agregada
                ];
            }
    
            return response()->json([
                'success' => true,
                'pedidos' => $pedidosConDetallesYDireccion,
            ], 200);
    
        } catch (\Exception $e) {
            Log::error('Error al listar pedidos: ' . $e->getMessage());
    
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener los pedidos.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


     public function procesarPago(Request $request, $idPedido)
     {
         DB::beginTransaction();
     
         try {
             // Obtener el pedido y verificar su existencia y estado
             $pedido = DB::table('pedidos')->where('idPedido', $idPedido)->first();
             if (!$pedido || $pedido->estado === 'pagado') {
                 return response()->json(['success' => false, 'message' => 'Error: Pedido no encontrado o ya pagado.'], 400);
             }
     
             $metodoPago = $request->input('metodo_pago');
             $rutaComprobante = null;
     
             // Verifica si hay un archivo de comprobante y si el método de pago es Yape o Plin
             if (in_array($metodoPago, ['yape', 'plin']) && $request->hasFile('comprobante')) {
                 $path = "pagos/comprobante/{$pedido->idUsuario}/{$idPedido}";
                 $rutaComprobante = $request->file('comprobante')->store($path, 'public');
             }
     
             // Inserta el pago en la tabla 'pagos'
             DB::table('pagos')->insert([
                 'idPedido' => $idPedido,
                 'monto' => $pedido->total,
                 'metodo_pago' => $metodoPago,
                 'estado_pago' => 'pendiente',
                 'ruta_comprobante' => $rutaComprobante,
             ]);
     
             // Cambiar el estado del pedido a 'aprobando'
             DB::table('pedidos')
                 ->where('idPedido', $idPedido)
                 ->update(['estado' => 'aprobando']);
          
             // Confirmar la transacción
             DB::commit();
     
             // Enviar correo de confirmación al usuario
             $correoUsuario = DB::table('usuarios')->where('idUsuario', $pedido->idUsuario)->value('correo');
             Mail::to($correoUsuario)->send(new NotificacionPagoProcesado($idPedido));
     
             return response()->json(['success' => true, 'message' => 'Pago procesado exitosamente.', 'ruta_comprobante' => $rutaComprobante], 200);
     
         } catch (\Exception $e) {
             DB::rollBack();
             Log::error('Error al procesar el pago: ' . $e->getMessage());
             return response()->json(['success' => false, 'message' => 'Error al procesar el pago.', 'error' => $e->getMessage()], 500);
         }
     }

     public function obtenerCantidadCarrito(Request $request)
     {
         // Obtén el idUsuario de los parámetros de la URL
         $idUsuario = $request->query('idUsuario');
 
         if (!$idUsuario) {
             return response()->json(['success' => false, 'message' => 'idUsuario no proporcionado'], 400);
         }
 
         // Consulta la cantidad total de productos en el carrito del usuario
         $cantidadProductos = DB::table('carrito_detalle')
             ->join('carrito', 'carrito_detalle.idCarrito', '=', 'carrito.idCarrito')
             ->where('carrito.idUsuario', $idUsuario)
             ->sum('carrito_detalle.cantidad');
 
         return response()->json(['cantidad' => $cantidadProductos]);
     }


     public function obtenerCantidadPedidos(Request $request)
    {
        // Obtener el idUsuario desde el token JWT en el frontend
        $idUsuario = $request->input('idUsuario');

        if (!$idUsuario) {
            return response()->json(['success' => false, 'message' => 'idUsuario no proporcionado'], 400);
        }

        // Consulta la cantidad de pedidos del usuario, excluyendo los completados
        $cantidadPedidos = DB::table('pedidos')
            ->where('idUsuario', $idUsuario)
            ->where('estado', '!=', 'completado') // Excluir pedidos con estado 'completado'
            ->count();

        return response()->json(['success' => true, 'cantidad' => $cantidadPedidos]);
    }

    public function listarDireccion($idUsuario)
    {
        try {
            // Verifica si el usuario existe
            if (!Usuario::find($idUsuario)) {
                return response()->json(['error' => 'Usuario no encontrado'], 404);
            }
    
            // Obtén las direcciones del usuario
            $direcciones = DetalleDireccion::where('idUsuario', $idUsuario)
                ->select('idDireccion', 'idUsuario', 'region', 'provincia', 'direccion', 'estado', 'latitud', 'longitud')
                ->get();
    
            // Verifica si hay direcciones
            if ($direcciones->isEmpty()) {
                return response()->json(['message' => 'No se encontraron direcciones'], 404);
            }
    
            return response()->json($direcciones, 200);
        } catch (\Exception $e) {
            Log::error('Error al listar direcciones: ' . $e->getMessage());
            return response()->json(['error' => 'Error interno al listar direcciones'], 500);
        }
    }
    public function agregarDireccion(Request $request)
    {
        try {
            // Log para verificar el contenido de la solicitud
            Log::info('Datos recibidos:', $request->all());  // Esto te ayudará a ver si el idUsuario está presente
    
            // Validar los datos recibidos
            $request->validate([
                'idUsuario' => 'required|integer|exists:usuarios,idUsuario',
                'region' => 'required|string|max:255',
                'provincia' => 'required|string|max:255',
                'direccion' => 'required|string|max:255',
                'latitud' => 'required|numeric',
                'longitud' => 'required|numeric',
            ]);
    
            // Crear la dirección
            $direccion = DetalleDireccion::create($request->all());
    
            // Enviar correo de confirmación
            $correoUsuario = DB::table('usuarios')->where('idUsuario', $request->idUsuario)->value('correo');
            Mail::to($correoUsuario)->send(new NotificacionDireccionAgregada($direccion));
    
            return response()->json($direccion, 201);
        } catch (\Exception $e) {
            Log::error('Error al agregar dirección: ' . $e->getMessage());
            return response()->json(['error' => 'Error interno al agregar dirección'], 500);
        }
    }

    public function eliminarDireccion($idDireccion)
    {
        try {
            $estadoRestringido = DB::table('detalle_direccion_pedido')
                ->join('pedidos', 'detalle_direccion_pedido.idPedido', '=', 'pedidos.idPedido')
                ->where('detalle_direccion_pedido.idDireccion', $idDireccion)
                ->whereIn('pedidos.estado', ['pendiente', 'aprobando', 'en preparacion', 'enviado'])
                ->exists();

            if ($estadoRestringido) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede eliminar la dirección: existen pedidos en proceso con esta dirección asignada.',
                ], 400);
            }

            // Obtener los datos de la dirección antes de eliminarla
            $direccion = DB::table('detalle_direcciones')->where('idDireccion', $idDireccion)->first();
            DB::table('detalle_direcciones')->where('idDireccion', $idDireccion)->delete();

            // Enviar correo de confirmación
            $correoUsuario = DB::table('usuarios')->where('idUsuario', $direccion->idUsuario)->value('correo');
            Mail::to($correoUsuario)->send(new NotificacionDireccionEliminada($direccion));

            return response()->json([
                'success' => true,
                'message' => 'Dirección eliminada exitosamente.',
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error al eliminar la dirección: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar la dirección.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function setDireccionUsando($idDireccion)
    {
        $direccion = DetalleDireccion::findOrFail($idDireccion);
        $idUsuario = $direccion->idUsuario;

        DetalleDireccion::where('idUsuario', $idUsuario)->update(['estado' => 'no usando']);
        $direccion->update(['estado' => 'usando']);

        // Enviar correo de confirmación
        $correoUsuario = DB::table('usuarios')->where('idUsuario', $idUsuario)->value('correo');
        Mail::to($correoUsuario)->send(new NotificacionDireccionPredeterminada($direccion));

        return response()->json(['message' => 'Dirección actualizada a usando.']);
    }



    public function enviarCodigo($idUsuario)
    {
        $usuario = Usuario::findOrFail($idUsuario);
        $codigo = rand(100000, 999999);
        Cache::put("verificacion_codigo_{$idUsuario}", $codigo, 300); // Expira en 5 minutos

        // Envía el correo electrónico con el código
        Mail::to($usuario->correo)->send(new CodigoVerificacion($codigo));

        return response()->json(['success' => true]);
    }

    public function verificarCodigo(Request $request, $idUsuario)
    {
        $codigoIngresado = $request->input('code');
        $codigoAlmacenado = Cache::get("verificacion_codigo_{$idUsuario}");

        if ($codigoAlmacenado && $codigoAlmacenado == $codigoIngresado) {
            Cache::forget("verificacion_codigo_{$idUsuario}"); // Borra el código después de validarlo
            return response()->json(['success' => true, 'message' => 'Código verificado correctamente']);
        }

        return response()->json(['success' => false, 'message' => 'Código incorrecto. Inténtalo nuevamente.']);
    }

    public function cambiarContrasena(Request $request)
    {
        $usuario = $request->user();
        $usuario->update(['password' => bcrypt($request->input('newPassword'))]);

        Cache::forget("verificacion_{$usuario->id}");

        return response()->json(['success' => true]);
    }

    public function cancelarPedido(Request $request)
    {
        $idPedido = $request->input('idPedido');

        // Validar que el ID esté presente
        if (!$idPedido) {
            return response()->json(['error' => 'ID de pedido no proporcionado'], 400);
        }

        // Buscar el pedido en la base de datos
        $pedido = Pedido::find($idPedido);

        if (!$pedido) {
            return response()->json(['error' => 'Pedido no encontrado'], 404);
        }

        // Verificar si el estado es "pendiente"
        if ($pedido->estado !== 'pendiente') {
            return response()->json(['error' => 'Solo se pueden cancelar pedidos pendientes'], 400);
        }

        // Eliminar el pedido
        $pedido->delete();

        return response()->json(['message' => 'Pedido cancelado exitosamente'], 200);
    }

    //APIS ESTADISTICA

    public function getPedidosCompletos($idUsuario)
    {
        try {
            // Consulta SQL mejorada con imágenes
            $query = "
                SELECT 
                        p.idPedido,
                        COALESCE(GROUP_CONCAT(CONCAT(prod.nombreProducto, ' (x', pd.cantidad, ')') SEPARATOR ', '), '') AS productos,
                        COALESCE(GROUP_CONCAT(prod.imagen SEPARATOR ', '), '') AS imagenes,
                        COALESCE(SUM(pd.cantidad), 0) AS cantidadTotal,
                        p.fecha_pedido,
                        pg.metodo_pago,
                        COALESCE(-pg.monto, 0) AS montoPagoNegativo
                    FROM pedidos p
                    LEFT JOIN pagos pg ON p.idPedido = pg.idPedido
                    LEFT JOIN pedido_detalle pd ON p.idPedido = pd.idPedido
                    LEFT JOIN productos prod ON pd.idProducto = prod.idProducto
                    WHERE p.idUsuario = ?
                    GROUP BY p.idPedido, p.fecha_pedido, pg.metodo_pago, pg.monto
                    ORDER BY p.fecha_pedido DESC;
            ";
    
            // Ejecutar la consulta con el parámetro del usuario
            $result = DB::select($query, [$idUsuario]);
    
            // Asegurar que el resultado sea un array válido
            if (empty($result)) {
                return response()->json([], 200); // Si no hay datos, devolver un array vacío
            }
    
            return response()->json($result, 200);
        } catch (\Exception $e) {
            // Registrar el error en los logs para depuración
            Log::error("Error al obtener pedidos completos: {$e->getMessage()}");
            return response()->json(['error' => 'Error interno del servidor.'], 500);
        }
    }


    public function getProductosMasComprados()
    {
        try {
            $query = "
                SELECT 
                    prod.idProducto,
                    prod.nombreProducto,
                    prod.imagen,
                    SUM(pd.cantidad) AS cantidadVendida
                FROM pedido_detalle pd
                LEFT JOIN productos prod ON pd.idProducto = prod.idProducto
                GROUP BY prod.idProducto, prod.nombreProducto, prod.imagen
                HAVING cantidadVendida >= 2
                ORDER BY cantidadVendida DESC
                LIMIT 10
            ";
    
            $result = DB::select($query);
    
            return response()->json($result, 200);
        } catch (\Exception $e) {
            Log::error("Error al obtener productos más comprados: {$e->getMessage()}");
            return response()->json(['error' => 'Error interno del servidor.'], 500);
        }
    }

}
