<?php

namespace App\Http\Controllers;

use App\Models\Usuario;
use App\Models\Carrito;
use App\Models\CarritoDetalle;
use Illuminate\Http\Request;
use App\Models\Producto;
use App\Models\Stock;
use App\Models\Modelo;
use App\Models\Pedido;
use App\Models\ImagenModelo;
use App\Models\DetalleDireccion;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Mail\NotificacionCrearCuenta;
use App\Mail\NotificacionActualizarCorreo;
use App\Mail\NotificacionPedido;
use App\Mail\NotificacionPedidoCancelado;
use App\Mail\NotificacionPagoProcesado;
use App\Mail\NotificacionDireccionAgregada;
use App\Mail\NotificacionDireccionEliminada;
use App\Mail\NotificacionDireccionPredeterminada;
use App\Mail\CodigoVerificacion;
use App\Models\Categoria;
use App\Models\DetalleDireccionPedido;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use GuzzleHttp\Client;

class ClienteController extends Controller
{
  
     /**
 * Obtener perfil de usuario
 * @OA\Get(
 *     path="/api/perfilCliente",
 *     tags={"CLIENTE CONTROLLER"},
 *     summary="Obtener perfil de usuario",
 *     security={{"bearerAuth": {}}},
 *     @OA\Response(
 *         response=200,
 *         description="Perfil de usuario obtenido con éxito",
 *         @OA\JsonContent(
 *             @OA\Property(property="success", type="boolean", example=true),
 *             @OA\Property(property="data", type="object", 
 *                 @OA\Property(property="idUsuario", type="integer", example=1),
 *                 @OA\Property(property="username", type="string", example="usuario123"),
 *                 @OA\Property(property="nombres", type="string", example="Juan"),
 *                 @OA\Property(property="apellidos", type="string", example="Pérez"),
 *                 @OA\Property(property="dni", type="string", example="12345678"),
 *                 @OA\Property(property="correo", type="string", example="usuario@dominio.com"),
 *                 @OA\Property(property="edad", type="integer", example=25),
 *                 @OA\Property(property="nacimiento", type="string", format="date", example="1999-01-01"),
 *                 @OA\Property(property="sexo", type="string", example="M"),
 *                 @OA\Property(property="direccion", type="string", example="Calle 123"),
 *                 @OA\Property(property="telefono", type="string", example="987654321"),
 *                 @OA\Property(property="departamento", type="string", example="Lima"),
 *                 @OA\Property(property="perfil", type="string", example="http://localhost/storage/perfil.jpg")
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=401,
 *         description="Token no proporcionado o inválido"
 *     ),
 *     @OA\Response(
 *         response=500,
 *         description="Error al obtener el perfil"
 *     )
 * )
 * 
 * @OA\SecurityScheme(
 *     securityScheme="bearerAuth",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT",
 *     description="Usar un token JWT en el encabezado Authorization como Bearer <token>"
 * )
 */
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


    /**
     * @OA\Post(
     *     path="/api/uploadProfileImageCliente/{idUsuario}",
     *     summary="Subir imagen de perfil de cliente",
     *     description="Este endpoint permite a un cliente subir o actualizar su imagen de perfil.",
     *     operationId="uploadProfileImageCliente",
     *     tags={"CLIENTE CONTROLLER"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="idUsuario",
     *         in="path",
     *         required=true,
     *         description="ID del usuario cuya imagen de perfil se va a actualizar",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="Imagen de perfil que el cliente quiere subir",
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"perfil"},
     *                 @OA\Property(
     *                     property="perfil",
     *                     type="string",
     *                     format="binary",
     *                     description="Imagen de perfil del cliente"
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Imagen de perfil subida con éxito.",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="filename", type="string", example="1/imagen_perfil.jpg")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="No se cargó la imagen o no se encontró el usuario.",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="No se cargó la imagen")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Usuario no encontrado.",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Usuario no encontrado")
     *         )
     *     )
     * )
     */
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


    /**
     * @OA\Put(
     *     path="/api/updateCliente/{idUsuario}",
     *     summary="Actualizar datos del cliente",
     *     description="Este endpoint permite actualizar los datos del cliente, como su nombre, correo, y otros detalles.",
     *     operationId="updateCliente",
     *     tags={"CLIENTE CONTROLLER"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="idUsuario",
     *         in="path",
     *         required=true,
     *         description="ID del usuario (cliente) que se va a actualizar",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="Datos que se desean actualizar del cliente",
     *         @OA\JsonContent(
     *             required={"nombres", "apellidos", "correo"},
     *             @OA\Property(property="nombres", type="string", example="Juan"),
     *             @OA\Property(property="apellidos", type="string", example="Pérez"),
     *             @OA\Property(property="dni", type="string", example="12345678"),
     *             @OA\Property(property="correo", type="string", example="juan.perez@example.com"),
     *             @OA\Property(property="edad", type="integer", example=30),
     *             @OA\Property(property="nacimiento", type="string", format="date", example="1994-01-01"),
     *             @OA\Property(property="sexo", type="string", example="Masculino"),
     *             @OA\Property(property="direccion", type="string", example="Calle Ficticia 123"),
     *             @OA\Property(property="telefono", type="string", example="987654321"),
     *             @OA\Property(property="departamento", type="string", example="Lima")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Datos del cliente actualizados correctamente.",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Datos actualizados correctamente")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="El correo electrónico ya está en uso.",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="El correo ya está en uso")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Cliente no encontrado.",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Cliente no encontrado")
     *         )
     *     )
     * )
     */
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

    public function validateDNI($numero)
    {
        // Verificar que el DNI no sea vacío
        if (empty($numero)) {
            return response()->json(['success' => false, 'message' => 'El DNI no puede estar vacío'], 400);
        }
    
        // Verificar que el DNI tenga exactamente 8 caracteres
        if (strlen($numero) !== 8) {
            return response()->json(['success' => false, 'message' => 'El DNI debe tener exactamente 8 caracteres'], 400);
        }
    
        // Verificar que no todos los caracteres sean iguales (ejemplo: "11111111" o "00000000")
        if (preg_match('/^(.)\1{7}$/', $numero)) {
            return response()->json(['success' => false, 'message' => 'El DNI no puede tener todos los caracteres iguales'], 400);
        }
    
        // Verificar si el DNI ya está registrado en la tabla usuarios
        $dniExistente = Usuario::where('dni', $numero)->exists();
        if ($dniExistente) {
            return response()->json(['success' => false, 'message' => 'El DNI ya está registrado en la base de datos'], 400);
        }
    
        // Si pasa todas las validaciones
        return response()->json(['success' => true, 'message' => 'El DNI es válido'], 200);
    }

    // public function listarProductos(Request $request)
    // {
    //     // Obtener los parámetros de la solicitud
    //     $categoriaId = $request->input('categoria');
    //     $texto = $request->input('texto');
    //     $idProducto = $request->input('idProducto');
    //     $precioInicial = $request->input('precioInicial');
    //     $precioFinal = $request->input('precioFinal');
    
    //     // Construir la consulta para obtener los productos con relaciones
    //     $query = Producto::with([
    //         'categoria:idCategoria,nombreCategoria,estado', // Incluir el campo 'estado' de la categoría
    //         'modelos' => function($query) {
    //             $query->with([
    //                 'imagenes:idImagen,urlImagen,idModelo',
    //                 'stock' => function($query) {
    //                     $query->with('talla:idTalla,nombreTalla');
    //                 }
    //             ]);
    //         }
    //     ]);
    
    //     // Filtrar por estado 'activo' en la tabla 'productos'
    //     $query->where('estado', 'activo');
    
    //     // Filtrar por estado 'activo' en la tabla 'categorias'
    //     $query->whereHas('categoria', function($q) {
    //         $q->where('estado', 'activo');
    //     });
    
    //     // Filtrar por idProducto si el parámetro 'idProducto' existe
    //     if ($idProducto) {
    //         $query->where('idProducto', $idProducto);
    //     }
    
    //     // Filtrar por categoría si el parámetro 'categoria' existe
    //     if ($categoriaId) {
    //         $query->where('idCategoria', $categoriaId);
    //     }
    
    //     // Filtrar por texto en el nombre del producto si el parámetro 'texto' existe
    //     if ($texto) {
    //         $query->where('nombreProducto', 'like', '%' . $texto . '%');
    //     }
    
    //     // Filtrar por rango de precios si se proporcionan
    //     if ($precioInicial !== null && $precioFinal !== null) {
    //         $query->whereBetween('precio', [$precioInicial, $precioFinal]);
    //     }
    
    //     // Obtener los productos
    //     $productos = $query->get();
    
    //     // Si se pasó un 'idProducto', se devuelve un solo producto
    //     if ($idProducto) {
    //         $producto = $productos->first();
    
    //         if ($producto) {
    //             $productoData = [
    //                 'idProducto' => $producto->idProducto,
    //                 'nombreProducto' => $producto->nombreProducto,
    //                 'descripcion' => $producto->descripcion,
    //                 'nombreCategoria' => $producto->categoria ? $producto->categoria->nombreCategoria : 'Sin Categoría',
    //                 'precio' => $producto->precio,
    //                 'modelos' => $producto->modelos->map(function($modelo) {
    //                     return [
    //                         'idModelo' => $modelo->idModelo, // Agregar idModelo
    //                         'nombreModelo' => $modelo->nombreModelo,
    //                         'imagenes' => $modelo->imagenes->map(function($imagen) {
    //                             return [
    //                                 'urlImagen' => $imagen->urlImagen
    //                             ];
    //                         }),
    //                         'tallas' => $modelo->stock->map(function($stock) {
    //                             return [
    //                                 'idTalla' => $stock->talla->idTalla, // Agregar idTalla
    //                                 'nombreTalla' => $stock->talla->nombreTalla,
    //                                 'cantidad' => $stock->cantidad
    //                             ];
    //                         })
    //                     ];
    //                 })
    //             ];
    
    //             return response()->json(['data' => $productoData], 200);
    //         } else {
    //             return response()->json(['message' => 'Producto no encontrado'], 404);
    //         }
    //     }
    
    //     // Si no se pasó un 'idProducto', se devuelve la lista de productos filtrados
    //     $productosData = $productos->map(function($producto) {
    //         return [
    //             'idProducto' => $producto->idProducto,
    //             'nombreProducto' => $producto->nombreProducto,
    //             'descripcion' => $producto->descripcion,
    //             'nombreCategoria' => $producto->categoria ? $producto->categoria->nombreCategoria : 'Sin Categoría',
    //             'precio' => $producto->precio,
    //             'modelos' => $producto->modelos->map(function($modelo) {
    //                 return [
    //                     'idModelo' => $modelo->idModelo, // Agregar idModelo
    //                     'nombreModelo' => $modelo->nombreModelo,
    //                     'imagenes' => $modelo->imagenes->map(function($imagen) {
    //                         return [
    //                             'urlImagen' => $imagen->urlImagen
    //                         ];
    //                     }),
    //                     'tallas' => $modelo->stock->map(function($stock) {
    //                         return [
    //                             'idTalla' => $stock->talla->idTalla, // Agregar idTalla
    //                             'nombreTalla' => $stock->talla->nombreTalla,
    //                             'cantidad' => $stock->cantidad
    //                         ];
    //                     })
    //                 ];
    //             })
    //         ];
    //     });
    
    //     return response()->json(['data' => $productosData], 200);
    // }


    public function listarProductos(Request $request)
    {
        // Obtener los parámetros de la solicitud
        $categoriaId = $request->input('categoria');
        $texto = $request->input('texto');
        $idProducto = $request->input('idProducto');
        $precioInicial = $request->input('precioInicial');
        $precioFinal = $request->input('precioFinal');

        // Construir la consulta para obtener los productos con relaciones
        $query = Producto::with([
            'categoria:idCategoria,nombreCategoria,estado', // Incluir el campo 'estado' de la categoría
            'modelos' => function($query) {
                $query->with([
                    'imagenes:idImagen,urlImagen,idModelo',
                    'stock' => function($query) {
                        $query->with('talla:idTalla,nombreTalla');
                    }
                ]);
            },
            'ofertas' => function($query) {
                $query->where('estado', 1) // Ofertas activas
                    ->where('fechaInicio', '<=', now())
                    ->where('fechaFin', '>=', now());
            }
        ]);

        // Filtrar por estado 'activo' en la tabla 'productos'
        $query->where('estado', 'activo');

        // Filtrar por estado 'activo' en la tabla 'categorias'
        $query->whereHas('categoria', function($q) {
            $q->where('estado', 'activo');
        });

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
                // Verificar si el producto tiene una oferta activa
                $precioOriginal = $producto->precio;
                $precioDescuento = $precioOriginal;
                $ofertaActiva = $producto->ofertas->first();

                if ($ofertaActiva) {
                    $descuento = $ofertaActiva->porcentajeDescuento;
                    $precioDescuento = $precioOriginal * (1 - ($descuento / 100));
                }

                $productoData = [
                    'idProducto' => $producto->idProducto,
                    'nombreProducto' => $producto->nombreProducto,
                    'descripcion' => $producto->descripcion,
                    'nombreCategoria' => $producto->categoria ? $producto->categoria->nombreCategoria : 'Sin Categoría',
                    'precioOriginal' => $precioOriginal,
                    'precioDescuento' => $precioDescuento,
                    'tieneOferta' => !!$ofertaActiva,
                    'modelos' => $producto->modelos->map(function($modelo) {
                        return [
                            'idModelo' => $modelo->idModelo,
                            'nombreModelo' => $modelo->nombreModelo,
                            'imagenes' => $modelo->imagenes->map(function($imagen) {
                                return [
                                    'urlImagen' => $imagen->urlImagen
                                ];
                            }),
                            'tallas' => $modelo->stock->map(function($stock) {
                                return [
                                    'idTalla' => $stock->talla->idTalla,
                                    'nombreTalla' => $stock->talla->nombreTalla,
                                    'cantidad' => $stock->cantidad
                                ];
                            })
                        ];
                    })
                ];

                return response()->json(['data' => $productoData], 200);
            } else {
                return response()->json(['message' => 'Producto no encontrado'], 404);
            }
        }

    // Si no se pasó un 'idProducto', devolver todos los productos
    $productosData = $productos->map(function($producto) {
        $precioOriginal = $producto->precio;
        $precioDescuento = $precioOriginal;
        $ofertaActiva = $producto->ofertas->first();

        if ($ofertaActiva) {
            $descuento = $ofertaActiva->porcentajeDescuento;
            $precioDescuento = $precioOriginal * (1 - ($descuento / 100));
        }

        return [
            'idProducto' => $producto->idProducto,
            'nombreProducto' => $producto->nombreProducto,
            'descripcion' => $producto->descripcion,
            'nombreCategoria' => $producto->categoria ? $producto->categoria->nombreCategoria : 'Sin Categoría',
            'precioOriginal' => $precioOriginal,
            'precioDescuento' => $precioDescuento,
            'tieneOferta' => !!$ofertaActiva,
            'modelos' => $producto->modelos->map(function($modelo) {
                return [
                    'idModelo' => $modelo->idModelo,
                    'nombreModelo' => $modelo->nombreModelo,
                    'imagenes' => $modelo->imagenes->map(function($imagen) {
                        return [
                            'urlImagen' => $imagen->urlImagen
                        ];
                    }),
                    'tallas' => $modelo->stock->map(function($stock) {
                        return [
                            'idTalla' => $stock->talla->idTalla,
                            'nombreTalla' => $stock->talla->nombreTalla,
                            'cantidad' => $stock->cantidad
                        ];
                    })
                ];
            })
        ];
    });

    return response()->json(['data' => $productosData], 200);
}


    /**
     * @OA\Post(
     *     path="/api/agregarCarrito",
     *     summary="Agregar producto al carrito de compras",
     *     description="Este endpoint permite a un usuario agregar un producto a su carrito de compras.",
     *     operationId="agregarAlCarrito",
     *     tags={"CLIENTE CONTROLLER"},
     *     security={{"bearerAuth": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         description="Datos del producto a agregar al carrito",
     *         @OA\JsonContent(
     *             required={"idProducto", "cantidad", "idUsuario", "idModelo", "idTalla"},
     *             @OA\Property(property="idProducto", type="integer", example=1, description="ID del producto que se va a agregar al carrito"),
     *             @OA\Property(property="cantidad", type="integer", example=2, description="Cantidad de producto a agregar"),
     *             @OA\Property(property="idUsuario", type="integer", example=123, description="ID del usuario que está agregando el producto"),
     *             @OA\Property(property="idModelo", type="integer", example=5, description="ID del modelo del producto"),
     *             @OA\Property(property="idTalla", type="integer", example=3, description="ID de la talla seleccionada del producto")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Producto agregado al carrito con éxito.",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Producto agregado al carrito con éxito")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Error de validación o stock insuficiente.",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="La cantidad solicitada excede el stock disponible")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Producto, modelo, talla o usuario no encontrado.",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Producto no encontrado")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error en la base de datos o inesperado.",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Error al agregar al carrito"),
     *             @OA\Property(property="error", type="string", example="Detalle del error")
     *         )
     *     )
     * )
     */
    // public function agregarAlCarrito(Request $request)
    // {
    //     // Validación de los datos recibidos
    //     $validatedData = $request->validate([
    //         'idProducto' => 'required|exists:productos,idProducto',
    //         'cantidad' => 'required|integer|min:1',
    //         'idUsuario' => 'required|exists:usuarios,idUsuario',
    //         'idModelo' => 'required|exists:modelos,idModelo',
    //         'idTalla' => 'required|exists:tallas,idTalla',
    //     ]);
        
    //     // Registro en el log para ver los datos recibidos
    //     Log::info('Datos recibidos:', $request->all());
        
    //     try {
    //         // Obtener el producto desde la relación en la tabla modelo
    //         $modelo = Modelo::find($validatedData['idModelo']);
            
    //         // Verificar si el modelo existe
    //         if (!$modelo) {
    //             return response()->json([
    //                 'success' => false,
    //                 'message' => 'Modelo no encontrado',
    //             ], 404);
    //         }
            
    //         // Obtener el producto relacionado al modelo
    //         $producto = Producto::find($modelo->idProducto);
            
    //         // Verificar si el producto existe
    //         if (!$producto) {
    //             return response()->json([
    //                 'success' => false,
    //                 'message' => 'Producto no encontrado',
    //             ], 404);
    //         }
    
    //         // Obtener el stock disponible del modelo y talla seleccionados
    //         $stock = Stock::where('idModelo', $validatedData['idModelo'])
    //                         ->where('idTalla', $validatedData['idTalla'])
    //                         ->first();
            
    //        // Verificar si hay stock disponible
    //         if (!$stock || $stock->cantidad < $validatedData['cantidad']) {
    //             return response()->json([
    //                 'success' => false,
    //                 'message' => 'La cantidad solicitada excede el stock disponible',
    //             ], 400);
    //         }
            
    //         // Obtener el precio del producto
    //         $precio = $producto->precio;
            
    //         // Verificar que el precio es un número válido
    //         if (!is_numeric($precio) || $precio <= 0) {
    //             return response()->json([
    //                 'success' => false,
    //                 'message' => 'Precio inválido del producto',
    //             ], 400);
    //         }
    
    //         // Calcular el subtotal en el backend
    //         $subtotal = $precio * $validatedData['cantidad'];
    
    //         // Verificar que el subtotal sea un número válido
    //         if (!is_numeric($subtotal) || $subtotal <= 0) {
    //             return response()->json([
    //                 'success' => false,
    //                 'message' => 'El precio calculado es inválido',
    //             ], 400);
    //         }
    
    //         // Obtener el carrito del usuario (si no existe, lo crea)
    //         $carrito = Carrito::firstOrCreate(['idUsuario' => $validatedData['idUsuario']]);
            
    //         // Verificar si el producto ya está en el carrito para el modelo y talla específicos
    //         $carritoDetalle = CarritoDetalle::where('idCarrito', $carrito->idCarrito)
    //                                         ->where('idProducto', $validatedData['idProducto'])
    //                                         ->where('idModelo', $validatedData['idModelo'])
    //                                         ->where('idTalla', $validatedData['idTalla'])
    //                                         ->first();
            
    //         // Si el producto ya está en el carrito
    //         if ($carritoDetalle) {
    //             // Calcular la nueva cantidad total sumando la cantidad actual con la nueva
    //             $nuevaCantidad = $carritoDetalle->cantidad + $validatedData['cantidad'];
                
    //             // Verificar si la cantidad total excede el stock disponible
    //             if ($nuevaCantidad > $stock->cantidad) {
    //                 return response()->json([
    //                     'success' => false,
    //                     'message' => 'La cantidad total en el carrito supera el stock disponible',
    //                 ], 400);
    //             }
    
    //             // Actualizar la cantidad y recalcular el precio total
    //             $nuevoSubtotal = $precio * $nuevaCantidad;
    
    //             // Actualizar el detalle del carrito
    //             $carritoDetalle->update([
    //                 'cantidad' => $nuevaCantidad,
    //                 'subtotal' => $nuevoSubtotal
    //             ]);
    //         } else {
    //             // Si el producto no está en el carrito, lo agregamos
    //             // Verificar si la cantidad no supera el stock disponible
    //             if ($validatedData['cantidad'] > $stock->cantidad) {
    //                 return response()->json([
    //                     'success' => false,
    //                     'message' => 'La cantidad solicitada excede el stock disponible',
    //                 ], 400);
    //             }
    
    //             // Si el producto no está en el carrito, lo agregamos
    //             CarritoDetalle::create([
    //                 'idCarrito' => $carrito->idCarrito,
    //                 'idProducto' => $validatedData['idProducto'],
    //                 'idModelo' => $validatedData['idModelo'],
    //                 'idTalla' => $validatedData['idTalla'],
    //                 'cantidad' => $validatedData['cantidad'],
    //                 'subtotal' => $subtotal,  // Aseguramos de incluir el subtotal
    //             ]);
    //         }
    
    //         return response()->json([
    //             'success' => true,
    //             'message' => 'Producto agregado al carrito con éxito',
    //         ], 201);
        
    //     } catch (\Illuminate\Database\QueryException $e) {
    //         // Log de error en base de datos
    //         Log::error('Error en la base de datos: ' . $e->getMessage());
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Error en la base de datos',
    //             'error' => $e->getMessage()
    //         ], 500);
    //     } catch (\Exception $e) {
    //         // Log de error inesperado
    //         Log::error('Error inesperado al agregar al carrito: ' . $e->getMessage());
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Error al agregar al carrito',
    //             'error' => $e->getMessage()
    //         ], 500);
    //     }
    // }

    public function agregarAlCarrito(Request $request)
    {
        // Validación de los datos recibidos
        $validatedData = $request->validate([
            'idProducto' => 'required|exists:productos,idProducto',
            'cantidad' => 'required|integer|min:1',
            'idUsuario' => 'required|exists:usuarios,idUsuario',
            'idModelo' => 'required|exists:modelos,idModelo',
            'idTalla' => 'required|exists:tallas,idTalla',
        ]);
        
        // Registro en el log para ver los datos recibidos
        Log::info('Datos recibidos:', $request->all());
        
        try {
            // Obtener el producto desde la relación en la tabla modelo
            $modelo = Modelo::find($validatedData['idModelo']);
            
            // Verificar si el modelo existe
            if (!$modelo) {
                return response()->json([
                    'success' => false,
                    'message' => 'Modelo no encontrado',
                ], 404);
            }
            
            // Obtener el producto relacionado al modelo
            $producto = Producto::find($modelo->idProducto);
            
            // Verificar si el producto existe
            if (!$producto) {
                return response()->json([
                    'success' => false,
                    'message' => 'Producto no encontrado',
                ], 404);
            }

            // Obtener el stock disponible del modelo y talla seleccionados
            $stock = Stock::where('idModelo', $validatedData['idModelo'])
                            ->where('idTalla', $validatedData['idTalla'])
                            ->first();
            
            // Verificar si hay stock disponible
            if (!$stock || $stock->cantidad < $validatedData['cantidad']) {
                return response()->json([
                    'success' => false,
                    'message' => 'La cantidad solicitada excede el stock disponible',
                ], 400);
            }
            
            // Obtener el precio del producto
            $precio = $producto->precio;
            
            // Verificar que el precio es un número válido
            if (!is_numeric($precio) || $precio <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Precio inválido del producto',
                ], 400);
            }

            // Verificar si el producto tiene una oferta activa
            $ofertaActiva = $producto->ofertas()
                ->where('estado', 1) // Oferta activa
                ->where('fechaInicio', '<=', now())
                ->where('fechaFin', '>=', now())
                ->first();

            // Calcular el precio con descuento si hay una oferta activa
            $precioFinal = $precio;
            if ($ofertaActiva) {
                $descuento = $ofertaActiva->porcentajeDescuento;
                $precioFinal = $precio * (1 - ($descuento / 100));
            }

            // Calcular el subtotal en el backend
            $subtotal = $precioFinal * $validatedData['cantidad'];

            // Verificar que el subtotal sea un número válido
            if (!is_numeric($subtotal) || $subtotal <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'El precio calculado es inválido',
                ], 400);
            }

            // Obtener el carrito del usuario (si no existe, lo crea)
            $carrito = Carrito::firstOrCreate(['idUsuario' => $validatedData['idUsuario']]);
            
            // Verificar si el producto ya está en el carrito para el modelo y talla específicos
            $carritoDetalle = CarritoDetalle::where('idCarrito', $carrito->idCarrito)
                                            ->where('idProducto', $validatedData['idProducto'])
                                            ->where('idModelo', $validatedData['idModelo'])
                                            ->where('idTalla', $validatedData['idTalla'])
                                            ->first();
            
            // Si el producto ya está en el carrito
            if ($carritoDetalle) {
                // Calcular la nueva cantidad total sumando la cantidad actual con la nueva
                $nuevaCantidad = $carritoDetalle->cantidad + $validatedData['cantidad'];
                
                // Verificar si la cantidad total excede el stock disponible
                if ($nuevaCantidad > $stock->cantidad) {
                    return response()->json([
                        'success' => false,
                        'message' => 'La cantidad total en el carrito supera el stock disponible',
                    ], 400);
                }

                // Actualizar la cantidad y recalcular el precio total
                $nuevoSubtotal = $precioFinal * $nuevaCantidad;

                // Actualizar el detalle del carrito
                $carritoDetalle->update([
                    'cantidad' => $nuevaCantidad,
                    'subtotal' => $nuevoSubtotal
                ]);
            } else {
                // Si el producto no está en el carrito, lo agregamos
                // Verificar si la cantidad no supera el stock disponible
                if ($validatedData['cantidad'] > $stock->cantidad) {
                    return response()->json([
                        'success' => false,
                        'message' => 'La cantidad solicitada excede el stock disponible',
                    ], 400);
                }

                // Si el producto no está en el carrito, lo agregamos
                CarritoDetalle::create([
                    'idCarrito' => $carrito->idCarrito,
                    'idProducto' => $validatedData['idProducto'],
                    'idModelo' => $validatedData['idModelo'],
                    'idTalla' => $validatedData['idTalla'],
                    'cantidad' => $validatedData['cantidad'],
                    'subtotal' => $subtotal,  // Aseguramos de incluir el subtotal
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Producto agregado al carrito con éxito',
            ], 201);
        
        } catch (\Illuminate\Database\QueryException $e) {
            // Log de error en base de datos
            Log::error('Error en la base de datos: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error en la base de datos',
                'error' => $e->getMessage()
            ], 500);
        } catch (\Exception $e) {
            // Log de error inesperado
            Log::error('Error inesperado al agregar al carrito: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al agregar al carrito',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/carrito",
     *     summary="Listar productos en el carrito de compras",
     *     description="Este endpoint permite a un usuario listar los productos que tiene en su carrito de compras.",
     *     operationId="listarCarrito",
     *     tags={"CLIENTE CONTROLLER"},
     *     security={{"bearerAuth": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         description="Datos necesarios para obtener el carrito de compras",
     *         @OA\JsonContent(
     *             required={"idUsuario"},
     *             @OA\Property(property="idUsuario", type="integer", example=123, description="ID del usuario cuyo carrito se quiere obtener")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Carrito listado con éxito.",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(
     *                 @OA\Property(property="idDetalle", type="integer", example=1),
     *                 @OA\Property(property="idProducto", type="integer", example=1),
     *                 @OA\Property(property="nombreProducto", type="string", example="Producto 1"),
     *                 @OA\Property(property="descripcion", type="string", example="Descripción del producto"),
     *                 @OA\Property(property="cantidad", type="integer", example=2),
     *                 @OA\Property(property="precio", type="number", format="float", example=100.00),
     *                 @OA\Property(property="stock", type="integer", example=10),
     *                 @OA\Property(property="urlImagen", type="string", example="https://example.com/image.jpg"),
     *                 @OA\Property(property="idCategoria", type="integer", example=1),
     *                 @OA\Property(property="nombreTalla", type="string", example="M"),
     *                 @OA\Property(property="nombreModelo", type="string", example="Modelo 1"),
     *                 @OA\Property(property="subtotal", type="number", format="float", example=200.00)
     *             ))
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="El idUsuario es obligatorio.",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="El idUsuario es obligatorio")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error al obtener el carrito.",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Error al obtener el carrito")
     *         )
     *     )
     * )
     */
    // public function listarCarrito(Request $request)
    // {
    //     try {
    //         // Obtener el idUsuario desde el cuerpo de la solicitud
    //         $userId = $request->input('idUsuario');

    //         if (!$userId) {
    //             return response()->json(['success' => false, 'message' => 'El idUsuario es obligatorio'], 400);
    //         }

    //        $productos = DB::table('carrito_detalle as cd')
    //         ->join('carrito as c', 'cd.idCarrito', '=', 'c.idCarrito')
    //         ->join('productos as p', 'cd.idProducto', '=', 'p.idProducto')
    //         ->join('modelos as m', 'cd.idModelo', '=', 'm.idModelo')
    //         ->leftJoin('tallas as t', 'cd.idTalla', '=', 't.idTalla')
    //         ->leftJoin('stock as s', function ($join) {
    //             $join->on('s.idModelo', '=', 'm.idModelo')
    //                 ->on('s.idTalla', '=', 'cd.idTalla');
    //         })
    //         ->leftJoin('imagenes_modelo as im', 'm.idModelo', '=', 'im.idModelo')
    //         ->select(
    //           //  DB::raw('CONCAT(p.idProducto, "-", IFNULL(t.nombreTalla, ""), "-", IFNULL(m.nombreModelo, "")) as idDetalle'),
    //             'cd.idDetalle',
    //             'p.idProducto',
    //             'p.nombreProducto',
    //             'p.descripcion',
    //             'cd.cantidad', // Usamos la cantidad directamente de la tabla carrito_detalle
    //             'p.precio', // Solo listamos el precio de carrito_detalle
    //             DB::raw('IFNULL(MAX(s.cantidad), 0) as stock'), // Obtenemos el stock disponible
    //             DB::raw('MAX(im.urlImagen) as urlImagen'), // Obtenemos la URL de la imagen
    //             'p.idCategoria',
    //             't.nombreTalla',
    //             'm.nombreModelo',
    //             'cd.subtotal' // Listamos directamente el campo subtotal desde carrito_detalle
    //         )
    //         ->where('c.idUsuario', '=', $userId)
    //         ->groupBy(
    //             'cd.idDetalle',
    //             'p.idProducto',
    //             'p.nombreProducto',
    //             'p.descripcion',
    //             'p.precio',  // Aseguramos que precio esté en el GROUP BY
    //             'cd.subtotal', // Agregamos subtotal
    //             'cd.cantidad',
    //             'p.idCategoria',
    //             't.nombreTalla',
    //             'm.nombreModelo'
    //         )
    //         ->orderBy('p.idProducto')
    //         ->get();

    //         return response()->json(['success' => true, 'data' => $productos], 200);
    //     } catch (\Exception $e) {
    //         Log::error('Error al obtener el carrito: ' . $e->getMessage());
    //         return response()->json(['success' => false, 'message' => 'Error al obtener el carrito'], 500);
    //     }
    // }

    public function listarCarrito(Request $request)
{
    try {
        // Obtener el idUsuario desde el cuerpo de la solicitud
        $userId = $request->input('idUsuario');

        if (!$userId) {
            return response()->json(['success' => false, 'message' => 'El idUsuario es obligatorio'], 400);
        }

        $productos = DB::table('carrito_detalle as cd')
            ->join('carrito as c', 'cd.idCarrito', '=', 'c.idCarrito')
            ->join('productos as p', 'cd.idProducto', '=', 'p.idProducto')
            ->join('modelos as m', 'cd.idModelo', '=', 'm.idModelo')
            ->leftJoin('tallas as t', 'cd.idTalla', '=', 't.idTalla')
            ->leftJoin('stock as s', function ($join) {
                $join->on('s.idModelo', '=', 'm.idModelo')
                    ->on('s.idTalla', '=', 'cd.idTalla');
            })
            ->leftJoin('imagenes_modelo as im', 'm.idModelo', '=', 'im.idModelo')
            ->leftJoin('productosofertas as po', 'p.idProducto', '=', 'po.idProducto')
            ->leftJoin('ofertas as o', function ($join) {
                $join->on('o.idOferta', '=', 'po.idOferta')
                    ->where('o.estado', 1) // Oferta activa
                    ->where('o.fechaInicio', '<=', now())
                    ->where('o.fechaFin', '>=', now());
            })
            ->select(
                'cd.idDetalle',
                'p.idProducto',
                'p.nombreProducto',
                'p.descripcion',
                'cd.cantidad',
                'p.precio as precioOriginal', // Precio original del producto
                DB::raw('IFNULL(o.porcentajeDescuento, 0) as descuento'), // Descuento de la oferta
                DB::raw('IFNULL(p.precio * (1 - o.porcentajeDescuento / 100), p.precio) as precioFinal'), // Precio con descuento
                DB::raw('IFNULL(MAX(s.cantidad), 0) as stock'),
                DB::raw('MAX(im.urlImagen) as urlImagen'),
                'p.idCategoria',
                't.nombreTalla',
                'm.nombreModelo',
                'cd.subtotal'
            )
            ->where('c.idUsuario', '=', $userId)
            ->groupBy(
                'cd.idDetalle',
                'p.idProducto',
                'p.nombreProducto',
                'p.descripcion',
                'p.precio',
                'cd.subtotal',
                'cd.cantidad',
                'p.idCategoria',
                't.nombreTalla',
                'm.nombreModelo',
                'o.porcentajeDescuento'
            )
            ->orderBy('p.idProducto')
            ->get();

        return response()->json(['success' => true, 'data' => $productos], 200);
    } catch (\Exception $e) {
        Log::error('Error al obtener el carrito: ' . $e->getMessage());
        return response()->json(['success' => false, 'message' => 'Error al obtener el carrito'], 500);
    }
}

    /**
     * @OA\Put(
     *     path="/api/actualizarCantidadCarrito/{idDetalle}",
     *     summary="Actualizar cantidad de un producto en el carrito",
     *     description="Este endpoint permite a un usuario actualizar la cantidad de un producto en su carrito de compras.",
     *     operationId="actualizarCantidadCarrito",
     *     tags={"CLIENTE CONTROLLER"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="idDetalle",
     *         in="path",
     *         required=true,
     *         description="ID del detalle del carrito cuyo producto se quiere actualizar",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="Datos necesarios para actualizar la cantidad en el carrito",
     *         @OA\JsonContent(
     *             required={"cantidad", "idUsuario"},
     *             @OA\Property(property="cantidad", type="integer", example=2, description="Nueva cantidad del producto en el carrito"),
     *             @OA\Property(property="idUsuario", type="integer", example=123, description="ID del usuario dueño del carrito")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Cantidad y precio actualizados correctamente.",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Cantidad y precio actualizados correctamente")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="La cantidad solicitada supera el stock disponible o la cantidad no es válida.",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="La cantidad solicitada supera el stock disponible")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Detalle no encontrado en el carrito, no se encontró stock, o producto no encontrado.",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Detalle no encontrado en el carrito")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error inesperado al actualizar la cantidad.",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Error inesperado al actualizar la cantidad")
     *         )
     *     )
     * )
     */
    // public function actualizarCantidadCarrito(Request $request, $idDetalle)
    // {
    //     // Obtener la cantidad y el idUsuario desde el cuerpo de la solicitud
    //     $cantidad = $request->input('cantidad');
    //     $idUsuario = $request->input('idUsuario'); // Obtener el idUsuario
        
    //     // Verificar que la cantidad es válida
    //     if (!is_numeric($cantidad) || $cantidad <= 0) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'La cantidad debe ser un número mayor que 0.',
    //         ], 400);
    //     }

    //     // Buscar el detalle del carrito con el idDetalle y el idUsuario recibido
    //     $detalle = CarritoDetalle::whereHas('carrito', function($query) use ($idUsuario) {
    //             $query->where('carrito.idUsuario', $idUsuario);
    //         })
    //         ->where('idDetalle', $idDetalle)
    //         ->first();

    //     if (!$detalle) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Detalle no encontrado en el carrito',
    //         ], 404);
    //     }

    //     // Obtener los datos del stock según el idModelo y idTalla del detalle
    //     $stock = DB::table('stock')
    //         ->where('idModelo', $detalle->idModelo)
    //         ->where('idTalla', $detalle->idTalla)
    //         ->first();

    //     if (!$stock) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'No se encontró stock para el modelo y talla especificados',
    //         ], 404);
    //     }

    //     // Verificar si la cantidad solicitada excede el stock disponible
    //     if ($cantidad > $stock->cantidad) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'La cantidad solicitada supera el stock disponible',
    //         ], 400);
    //     }

    //     // Obtener el producto asociado al detalle
    //     $producto = Producto::find($detalle->idProducto);
    //     if (!$producto) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Producto no encontrado en la base de datos',
    //         ], 404);
    //     }

    //     // Actualizar la cantidad y el subtotal en el carritoDetalle
    //     $detalle->cantidad = $cantidad;
    //     $detalle->subtotal = $producto->precio * $cantidad;

    //     // Guardar los cambios
    //     $detalle->save();

    //     return response()->json([
    //         'success' => true,
    //         'message' => 'Cantidad y precio actualizados correctamente',
    //     ], 200);
    // }
    public function actualizarCantidadCarrito(Request $request, $idDetalle)
    {
        // Obtener la cantidad y el idUsuario desde el cuerpo de la solicitud
        $cantidad = $request->input('cantidad');
        $idUsuario = $request->input('idUsuario');
    
        // Verificar que la cantidad es válida
        if (!is_numeric($cantidad) || $cantidad <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'La cantidad debe ser un número mayor que 0.',
            ], 400);
        }
    
        // Buscar el detalle del carrito con el idDetalle y el idUsuario recibido
        $detalle = CarritoDetalle::whereHas('carrito', function($query) use ($idUsuario) {
                $query->where('carrito.idUsuario', $idUsuario);
            })
            ->where('idDetalle', $idDetalle)
            ->first();
    
        if (!$detalle) {
            return response()->json([
                'success' => false,
                'message' => 'Detalle no encontrado en el carrito',
            ], 404);
        }
    
        // Obtener los datos del stock según el idModelo y idTalla del detalle
        $stock = DB::table('stock')
            ->where('idModelo', $detalle->idModelo)
            ->where('idTalla', $detalle->idTalla)
            ->first();
    
        if (!$stock) {
            return response()->json([
                'success' => false,
                'message' => 'No se encontró stock para el modelo y talla especificados',
            ], 404);
        }
    
        // Verificar si la cantidad solicitada excede el stock disponible
        if ($cantidad > $stock->cantidad) {
            return response()->json([
                'success' => false,
                'message' => 'La cantidad solicitada supera el stock disponible',
            ], 400);
        }
    
        // Obtener el producto asociado al detalle
        $producto = Producto::find($detalle->idProducto);
        if (!$producto) {
            return response()->json([
                'success' => false,
                'message' => 'Producto no encontrado en la base de datos',
            ], 404);
        }
    
        // Verificar si el producto tiene una oferta activa
        $ofertaActiva = $producto->ofertas()
            ->where('estado', 1)
            ->where('fechaInicio', '<=', now())
            ->where('fechaFin', '>=', now())
            ->first();
    
        // Calcular el precio con descuento si hay una oferta activa
        $precioFinal = $producto->precio;
        if ($ofertaActiva) {
            $descuento = $ofertaActiva->porcentajeDescuento;
            $precioFinal = $producto->precio * (1 - ($descuento / 100));
        }
    
        // Actualizar la cantidad y el subtotal en el carritoDetalle
        $detalle->cantidad = $cantidad;
        $detalle->subtotal = $precioFinal * $cantidad;
    
        // Guardar los cambios
        $detalle->save();
    
        return response()->json([
            'success' => true,
            'message' => 'Cantidad y precio actualizados correctamente',
            'data' => [
                'cantidad' => $cantidad,
                'subtotal' => $detalle->subtotal,
                'precioFinal' => $precioFinal
            ]
        ], 200);
    }

    
    /**
     * @OA\Delete(
     *     path="/api/carrito_detalle/{idDetalle}",
     *     summary="Eliminar un producto del carrito",
     *     description="Este endpoint permite a un usuario eliminar un producto específico de su carrito de compras.",
     *     operationId="eliminarProductoCarrito",
     *     tags={"CLIENTE CONTROLLER"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="idDetalle",
     *         in="path",
     *         required=true,
     *         description="ID del detalle del carrito que se quiere eliminar",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Producto eliminado del carrito exitosamente.",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Producto eliminado del carrito")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Detalle no encontrado en el carrito.",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Detalle no encontrado en el carrito")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error inesperado al eliminar el producto.",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Error inesperado al eliminar el producto")
     *         )
     *     )
     * )
     */
    public function eliminarProductoCarrito($idDetalle)
    {
        $userId = Auth::id();

        // Buscar el detalle del carrito por idDetalle y usuario autenticado
        $detalle = CarritoDetalle::whereHas('carrito', function($query) use ($userId) {
                $query->where('carrito.idUsuario', $userId);
            })
            ->where('idDetalle', $idDetalle) // Aquí usamos idDetalle en lugar de idProducto
            ->first();

        if (!$detalle) {
            return response()->json(['success' => false, 'message' => 'Detalle no encontrado en el carrito'], 404);
        }

        // Eliminar el detalle del carrito
        $detalle->delete();

        return response()->json(['success' => true, 'message' => 'Producto eliminado del carrito'], 200);
    }

   /**
     * @OA\Post(
     *     path="/api/pedidos",
     *     summary="Crear un nuevo pedido",
     *     description="Este endpoint permite crear un nuevo pedido, incluyendo detalles como productos, dirección de envío y pago.",
     *     operationId="crearPedido",
     *     tags={"CLIENTE CONTROLLER"},
     *     security={
     *         {"bearerAuth": {}}
     *     },
     *     requestBody={
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 type="object",
     *                 required={"idUsuario", "idCarrito", "total", "idDireccion"},
     *                 @OA\Property(property="idUsuario", type="integer", description="ID del usuario que realiza el pedido"),
     *                 @OA\Property(property="idCarrito", type="integer", description="ID del carrito de compras"),
     *                 @OA\Property(property="total", type="number", format="float", description="Monto total del pedido"),
     *                 @OA\Property(property="idDireccion", type="integer", description="ID de la dirección de envío")
     *             )
     *         )
     *     },
     *     @OA\Response(
     *         response=201,
     *         description="Pedido creado exitosamente",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Pedido creado exitosamente."),
     *             @OA\Property(property="idPedido", type="integer", example=12345)
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Datos de entrada inválidos",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Error al crear el pedido."),
     *             @OA\Property(property="error", type="string", example="La dirección proporcionada no existe o no está en uso.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error interno del servidor",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Error al crear el pedido."),
     *             @OA\Property(property="error", type="string", example="Error al crear el pedido.")
     *         )
     *     )
     * )
     */
    public function crearPedido(Request $request)
    {
        DB::beginTransaction();

        try {
            // Validación de los datos de entrada
            $request->validate([
                'idUsuario' => 'required|integer',
                'idCarrito' => 'required|integer',
                'total' => 'required|numeric',
                'idDireccion' => 'required|integer|exists:detalle_direcciones,idDireccion'
            ]);
        
            // Extraemos los datos del request
            $idUsuario = $request->input('idUsuario');
            $idCarrito = $request->input('idCarrito');
            $total = $request->input('total');
            $idDireccion = $request->input('idDireccion');
            $estadoPedido = 'pendiente';
        
            // Obtener los detalles de la dirección desde la tabla detalle_direcciones
            $direccionDetalles = DB::table('detalle_direcciones')
                ->where('idDireccion', $idDireccion)
                ->where('idUsuario', $idUsuario)
                ->where('estado', 'usando')  // Solo seleccionar si el estado es 'usando'
                ->first();
        
            if (!$direccionDetalles) {
                throw new \Exception('La dirección proporcionada no existe o no está en uso.');
            }
        
            // Extraemos los datos de la dirección
            $departamento = $direccionDetalles->departamento;
            $provincia = $direccionDetalles->provincia;
            $distrito = $direccionDetalles->distrito;
            $direccion = $direccionDetalles->direccion;
            $latitud = $direccionDetalles->latitud;
            $longitud = $direccionDetalles->longitud;
        
            // Crear el pedido con los nuevos campos obtenidos de la dirección
            $pedidoId = DB::table('pedidos')->insertGetId([
                'idUsuario' => $idUsuario,
                'total' => $total,
                'estado' => $estadoPedido,
                'departamento' => $departamento,
                'provincia' => $provincia,
                'distrito' => $distrito,
                'direccion' => $direccion,
                'latitud' => $latitud,
                'longitud' => $longitud,
                'fecha_pedido' => now(), 
                'tipo_comprobante' =>null, 
                'ruc' => null,
                'serie' => null
            ]);
        
            // Insertar la dirección en la tabla de detalle_direccion_pedido
            DB::table('detalle_direccion_pedido')->insert([
                'idPedido' => $pedidoId,
                'idDireccion' => $idDireccion,
            ]);
        
            // Obtener los detalles del carrito
            $detallesCarrito = DB::table('carrito_detalle')
                ->where('idCarrito', $idCarrito)
                ->get();
        
            if ($detallesCarrito->isEmpty()) {
                throw new \Exception('El carrito está vacío.');
            }
        
            $productos = [];
            $totalConIgv = 0;
        
            foreach ($detallesCarrito as $detalle) {
                // Obtener el producto con el precio correcto desde la tabla productos
                $producto = DB::table('productos')->where('idProducto', $detalle->idProducto)->first();
                
                if (!$producto) {
                    throw new \Exception("Producto no encontrado para el ID: {$detalle->idProducto}.");
                }
            
                // Consultar la cantidad de stock disponible para el modelo y talla
                $stock = DB::table('stock')
                    ->where('idModelo', $detalle->idModelo)
                    ->where('idTalla', $detalle->idTalla)
                    ->value('cantidad');
            
                if ($stock === null) {
                    throw new \Exception("No se encontró stock para el producto: {$producto->nombreProducto}, modelo: {$detalle->idModelo}, talla: {$detalle->idTalla}.");
                }
            
                if ($stock < $detalle->cantidad) {
                    throw new \Exception("Stock insuficiente para el producto: {$producto->nombreProducto}. Solo hay {$stock} unidades disponibles.");
                }
            
                // Consultar el modelo y la talla
                $modelo = DB::table('modelos')->where('idModelo', $detalle->idModelo)->value('nombreModelo');
                $talla = DB::table('tallas')->where('idTalla', $detalle->idTalla)->value('nombreTalla');
            
                // Obtener el precio unitario del producto y calcular el subtotal
                $precioUnitario = $producto->precio;
                $subtotal = $detalle->cantidad * $precioUnitario;
        
                // Calcular IGV (18%)
                $igv = $subtotal * 0.18;
                $subtotalConIgv = $subtotal + $igv;
        
                // Insertar en la tabla pedido_detalle con los nuevos campos (idModelo y idTalla)
                DB::table('pedido_detalle')->insert([
                    'idPedido' => $pedidoId,
                    'idProducto' => $detalle->idProducto,
                    'idModelo' => $detalle->idModelo,  // Nuevo campo
                    'idTalla' => $detalle->idTalla,    // Nuevo campo
                    'cantidad' => $detalle->cantidad,
                    'precioUnitario' => $precioUnitario,
                    'subtotal' => $subtotalConIgv,
                ]);
        
                // Agregar al array de productos
                $productos[] = (object) [
                    'nombreProducto' => $producto->nombreProducto,
                    'cantidad' => $detalle->cantidad,
                    'precioUnitario' => $precioUnitario,
                    'subtotal' => $subtotalConIgv,
                    'talla' => $talla ?? 'Sin Talla',         // Aseguramos un valor por defecto
                    'modelo' => $modelo ?? 'Sin Modelo',      // Aseguramos un valor por defecto  
                ];
        
                // Acumulamos el total con IGV
                $totalConIgv += $subtotalConIgv;
            }
        
            // Registrar el pago (sin metodo_pago ni comprobante si no se proporcionan)
            DB::table('pagos')->insert([
                'idPedido' => $pedidoId,
                'monto' => $totalConIgv,  // El total ahora incluye IGV
                'estado_pago' => 'pendiente', // El pago aún no está completado
            ]);
        
            // Limpiar el carrito de detalles (vaciar el carrito)
            DB::table('carrito_detalle')->where('idCarrito', $idCarrito)->delete();
        
            // Confirmamos la transacción
            DB::commit();
        
            // Enviar el correo de confirmación al usuario
            $correoUsuario = DB::table('usuarios')->where('idUsuario', $idUsuario)->value('correo');
            Mail::to($correoUsuario)->send(new NotificacionPedido($pedidoId, $productos, $totalConIgv));
        
            // Respuesta exitosa
            return response()->json([
                'success' => true,
                'message' => 'Pedido creado exitosamente.',
                'idPedido' => $pedidoId,
            ], 201);
        
        } catch (\Exception $e) {
            // Si hay un error, revertimos la transacción
            DB::rollBack();
            Log::error('Error al crear pedido y pago: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al crear el pedido.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    /**
     * @OA\Get(
     *     path="/api/pedidos/cantidad/{idUsuario}",
     *     summary="Listar los pedidos de un usuario",
     *     description="Este endpoint permite a un usuario obtener todos sus pedidos, incluyendo los productos en cada pedido y la dirección de envío.",
     *     operationId="listarPedidos",
     *     tags={"CLIENTE CONTROLLER"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="idUsuario",
     *         in="path",
     *         required=true,
     *         description="ID del usuario para el cual se desean listar los pedidos",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Pedidos obtenidos exitosamente.",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="pedidos", type="array", @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="idPedido", type="integer", example=1),
     *                 @OA\Property(property="idUsuario", type="integer", example=1),
     *                 @OA\Property(property="total", type="number", format="float", example=100.00),
     *                 @OA\Property(property="estado", type="string", example="pendiente"),
     *                 @OA\Property(property="departamento", type="string", example="Lima"),
     *                 @OA\Property(property="provincia", type="string", example="Lima"),
     *                 @OA\Property(property="distrito", type="string", example="Miraflores"),
     *                 @OA\Property(property="direccion", type="string", example="Av. Pardo y Aliaga 640"),
     *                 @OA\Property(property="detalles", type="array", @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="idDetallePedido", type="integer", example=1),
     *                     @OA\Property(property="idProducto", type="integer", example=1),
     *                     @OA\Property(property="nombreProducto", type="string", example="Producto 1"),
     *                     @OA\Property(property="nombreModelo", type="string", example="Modelo A"),
     *                     @OA\Property(property="nombreTalla", type="string", example="M"),
     *                     @OA\Property(property="cantidad", type="integer", example=2),
     *                     @OA\Property(property="precioUnitario", type="number", format="float", example=50.00),
     *                     @OA\Property(property="subtotal", type="number", format="float", example=100.00)
     *                 )),
     *                 @OA\Property(property="direccionEnvio", type="object",
     *                     @OA\Property(property="departamento", type="string", example="Lima"),
     *                     @OA\Property(property="provincia", type="string", example="Lima"),
     *                     @OA\Property(property="distrito", type="string", example="Miraflores"),
     *                     @OA\Property(property="direccion", type="string", example="Av. Pardo y Aliaga 640")
     *                 )
     *             ))
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Usuario no encontrado.",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Usuario no encontrado.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error interno al obtener los pedidos.",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Error al obtener los pedidos."),
     *             @OA\Property(property="error", type="string", example="Mensaje de error detallado")
     *         )
     *     )
     * )
     */
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
                // Obtener los detalles del pedido desde 'pedido_detalle', 'productos', 'modelos', y 'tallas'
                $detalles = DB::table('pedido_detalle')
                    ->where('idPedido', $pedido->idPedido)
                    ->join('productos', 'pedido_detalle.idProducto', '=', 'productos.idProducto')
                    ->join('modelos', 'pedido_detalle.idModelo', '=', 'modelos.idModelo')
                    ->join('tallas', 'pedido_detalle.idTalla', '=', 'tallas.idTalla')
                    ->select(
                        'pedido_detalle.idDetallePedido',
                        'productos.idProducto',
                        'productos.nombreProducto',
                        'modelos.nombreModelo',
                        'tallas.nombreTalla',
                        'pedido_detalle.cantidad',
                        'pedido_detalle.precioUnitario',
                        'pedido_detalle.subtotal'
                    )
                    ->get();

        
                  // Obtener la dirección del pedido (corregido el join)
                  $direccion = DB::table('pedidos')
                  ->where('idPedido', $pedido->idPedido)
                  ->select(
                      'pedidos.departamento',
                      'pedidos.provincia',
                      'pedidos.distrito',
                      'pedidos.direccion'
                  )
                  ->first();
  

                // Agregar los detalles y la dirección al pedido
                $pedidosConDetallesYDireccion[] = [
                    'idPedido' => $pedido->idPedido,
                    'idUsuario' => $pedido->idUsuario,
                    'total' => $pedido->total,
                    'estado' => $pedido->estado,
                    'departamento' => $pedido->departamento,
                    'distrito' => $pedido->distrito,
                    'provincia' => $pedido->provincia,
                    'direccion' => $pedido->direccion,
                    'detalles' => $detalles,
                    'direccionEnvio' => $direccion,
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



    /**
     * @OA\Get(
     *     path="/api/carrito/cantidad",
     *     summary="Obtener la cantidad total de productos en el carrito de un usuario",
     *     description="Este endpoint permite obtener la cantidad total de productos en el carrito de un usuario especificado mediante su idUsuario.",
     *     operationId="obtenerCantidadCarrito",
     *     tags={"CLIENTE CONTROLLER"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="idUsuario",
     *         in="query",
     *         required=true,
     *         description="ID del usuario cuyo carrito se desea consultar",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Cantidad de productos obtenida exitosamente.",
     *         @OA\JsonContent(
     *             @OA\Property(property="cantidad", type="integer", example=5)
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Error: idUsuario no proporcionado.",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="idUsuario no proporcionado")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error interno al obtener la cantidad del carrito.",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Error al obtener la cantidad del carrito."),
     *             @OA\Property(property="error", type="string", example="Mensaje de error detallado")
     *         )
     *     )
     * )
     */
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



     /**
     * @OA\Get(
     *     path="/api/pedidos/cantidad",
     *     summary="Obtener la cantidad de pedidos pendientes de un usuario",
     *     description="Este endpoint permite obtener la cantidad de pedidos que un usuario tiene en el sistema, excluyendo aquellos que ya se han completado.",
     *     operationId="obtenerCantidadPedidos",
     *     tags={"CLIENTE CONTROLLER"},
     *      security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="idUsuario",
     *         in="query",
     *         required=true,
     *         description="ID del usuario cuyo número de pedidos pendientes se desea consultar",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Cantidad de pedidos obtenida exitosamente.",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="cantidad", type="integer", example=3)
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Error: idUsuario no proporcionado.",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="idUsuario no proporcionado")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error interno al obtener la cantidad de pedidos.",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Error al obtener la cantidad de pedidos."),
     *             @OA\Property(property="error", type="string", example="Mensaje de error detallado")
     *         )
     *     )
     * )
     */
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



    /**
     * @OA\Get(
     *     path="/api/listarDireccion/{idUsuario}",
     *     summary="Obtener las direcciones de un usuario",
     *     description="Este endpoint permite obtener todas las direcciones asociadas a un usuario específico.",
     *     operationId="listarDireccion",
     *     tags={"CLIENTE CONTROLLER"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="idUsuario",
     *         in="path",
     *         required=true,
     *         description="ID del usuario para obtener sus direcciones",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Direcciones obtenidas correctamente.",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="idDireccion", type="integer", example=1),
     *                 @OA\Property(property="idUsuario", type="integer", example=1),
     *                 @OA\Property(property="departamento", type="string", example="Lima"),
     *                 @OA\Property(property="provincia", type="string", example="Lima"),
     *                 @OA\Property(property="distrito", type="string", example="Miraflores"),
     *                 @OA\Property(property="direccion", type="string", example="Av. Pardo y Aliaga 610"),
     *                 @OA\Property(property="estado", type="string", example="usando"),
     *                 @OA\Property(property="latitud", type="number", format="float", example="-12.0464"),
     *                 @OA\Property(property="longitud", type="number", format="float", example="-77.0352")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Usuario no encontrado o no tiene direcciones.",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Usuario no encontrado")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error interno al listar direcciones.",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Error interno al listar direcciones")
     *         )
     *     )
     * )
     */
    public function listarDireccion($idUsuario)
    {
        try {
            // Verifica si el usuario existe
            if (!Usuario::find($idUsuario)) {
                return response()->json(['error' => 'Usuario no encontrado'], 404);
            }
    
            // Obtén las direcciones del usuario
            $direcciones = DetalleDireccion::where('idUsuario', $idUsuario)
                ->select('idDireccion', 'idUsuario', 'departamento', 'provincia','distrito', 'direccion', 'estado', 'latitud', 'longitud')
                ->get();
    
            // Verifica si hay direcciones
            if ($direcciones->isEmpty()) {
                return response()->json(['message' => 'No tienes direcciones agregadas'], 404);
            }
            
            return response()->json($direcciones, 200);
        } catch (\Exception $e) {
            Log::error('Error al listar direcciones: ' . $e->getMessage());
            return response()->json(['error' => 'Error interno al listar direcciones'], 500);
        }
    }


    /**
     * @OA\Get(
     *     path="/api/listarDireccionPedido/{idUsuario}",
     *     summary="Obtener la dirección activa de un usuario",
     *     description="Este endpoint permite obtener la dirección activa (estado 'usando') de un usuario.",
     *     operationId="listarDireccionPedido",
     *     tags={"CLIENTE CONTROLLER"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="idUsuario",
     *         in="path",
     *         required=true,
     *         description="ID del usuario para obtener su dirección activa",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Dirección activa obtenida correctamente.",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="idDireccion", type="integer", example=1),
     *             @OA\Property(property="idUsuario", type="integer", example=1),
     *             @OA\Property(property="departamento", type="string", example="Lima"),
     *             @OA\Property(property="provincia", type="string", example="Lima"),
     *             @OA\Property(property="distrito", type="string", example="Miraflores"),
     *             @OA\Property(property="direccion", type="string", example="Av. Pardo y Aliaga 610"),
     *             @OA\Property(property="latitud", type="number", format="float", example="-12.0464"),
     *             @OA\Property(property="longitud", type="number", format="float", example="-77.0352")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Usuario no encontrado o no tiene dirección activa.",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Usuario no encontrado")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error interno al listar dirección activa.",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Error interno al listar direcciones")
     *         )
     *     )
     * )
     */
    public function listarDireccionPedido($idUsuario)
    {
        try {
            // Verifica si el usuario existe
            if (!Usuario::find($idUsuario)) {
                return response()->json(['error' => 'Usuario no encontrado'], 404);
            }

            // Obtén la dirección cuyo estado sea 'usando'
            $direccionUsando = DetalleDireccion::where('idUsuario', $idUsuario)
                ->where('estado', 'usando')  // Solo seleccionar donde el estado es 'usando'
                ->select('idDireccion', 'idUsuario', 'departamento', 'provincia', 'distrito', 'direccion', 'latitud', 'longitud')
                ->first(); // Usamos 'first' para obtener solo una dirección

            // Verifica si se encontró una dirección 'usando'
            if (!$direccionUsando) {
                return response()->json(['message' => 'No tienes ninguna dirección activa o en uso'], 404);
            }

            // Devuelve la dirección encontrada
            return response()->json($direccionUsando, 200);
        } catch (\Exception $e) {
            Log::error('Error al listar direcciones: ' . $e->getMessage());
            return response()->json(['error' => 'Error interno al listar direcciones'], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/agregarDireccion",
     *     summary="Agregar una nueva dirección para un usuario",
     *     description="Este endpoint permite agregar una nueva dirección para un usuario. Si el usuario ya tiene una dirección marcada como 'usando', esa dirección se actualizará a 'no usando'.",
     *     operationId="agregarDireccion",
     *     tags={"CLIENTE CONTROLLER"},
     *     security={{"bearerAuth": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         description="Datos necesarios para agregar una nueva dirección",
     *         @OA\JsonContent(
     *             required={"idUsuario", "departamento", "provincia", "distrito", "direccion"},
     *             @OA\Property(property="idUsuario", type="integer", example=1, description="ID del usuario"),
     *             @OA\Property(property="departamento", type="string", example="Lima", description="Departamento de la dirección"),
     *             @OA\Property(property="provincia", type="string", example="Lima", description="Provincia de la dirección"),
     *             @OA\Property(property="distrito", type="string", example="Miraflores", description="Distrito de la dirección"),
     *             @OA\Property(property="direccion", type="string", example="Av. Pardo y Aliaga 610", description="Dirección completa"),
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Dirección agregada correctamente.",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="idDireccion", type="integer", example=1),
     *             @OA\Property(property="idUsuario", type="integer", example=1),
     *             @OA\Property(property="departamento", type="string", example="Lima"),
     *             @OA\Property(property="provincia", type="string", example="Lima"),
     *             @OA\Property(property="distrito", type="string", example="Miraflores"),
     *             @OA\Property(property="direccion", type="string", example="Av. Pardo y Aliaga 610"),
     *             @OA\Property(property="estado", type="string", example="usando"),
     *             @OA\Property(property="latitud", type="number", format="float", example="-12.0464"),
     *             @OA\Property(property="longitud", type="number", format="float", example="-77.0352")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Error en los datos de entrada (validación).",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="El campo departamento es requerido")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error interno al agregar dirección.",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Error interno al agregar dirección")
     *         )
     *     )
     * )
     */
    public function agregarDireccion(Request $request)
    {
        try {
            // Log para verificar el contenido de la solicitud
            Log::info('Datos recibidos:', $request->all());
        
            // Validar los datos recibidos
            $request->validate([
                'idUsuario' => 'required|integer|exists:usuarios,idUsuario',
                'departamento' => 'required|string|max:255',
                'provincia' => 'required|string|max:255',
                'distrito' => 'required|string|max:255',
                'direccion' => 'required|string|max:255',
            ]);
        
            // Buscar si ya existe una dirección marcada como "usando"
            $direccionUsando = DetalleDireccion::where('idUsuario', $request->idUsuario)
                                               ->where('estado', 'usando')
                                               ->first();
        
            if ($direccionUsando) {
                // Si ya hay una dirección marcada como "usando", actualízala a "no usando"
                $direccionUsando->estado = 'no usando';
                $direccionUsando->save();
               // Log::info('Dirección anterior marcada como "usando" fue cambiada a "no usando"');
            }
        
            // Crear la nueva dirección, asegurándote de marcarla como "usando"
            $request->merge(['estado' => 'usando']);  // Agregamos el estado "usando" antes de crear la nueva dirección
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


    /**
     * @OA\Delete(
     *     path="/api/eliminarDireccion/{idDireccion}",
     *     summary="Eliminar una dirección del usuario",
     *     description="Este endpoint permite eliminar una dirección específica para un usuario. La dirección será eliminada si no está asociada a un pedido en proceso.",
     *     operationId="eliminarDireccion",
     *     tags={"CLIENTE CONTROLLER"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="idDireccion",
     *         in="path",
     *         required=true,
     *         description="ID de la dirección que se desea eliminar",
     *         @OA\Schema(
     *             type="integer",
     *             example=1
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Dirección eliminada exitosamente.",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Dirección eliminada exitosamente.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Error: la dirección no puede ser eliminada porque está asociada a un pedido en proceso.",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="No se puede eliminar la dirección: existen pedidos en proceso con esta dirección asignada.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Error: dirección no encontrada.",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Dirección no encontrada.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error interno al eliminar la dirección.",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Error al eliminar la dirección."),
     *             @OA\Property(property="error", type="string", example="Mensaje de error detallado.")
     *         )
     *     )
     * )
     */
    public function eliminarDireccion($idDireccion)
    {
        try {
            // $estadoRestringido = DB::table('detalle_direccion_pedido')
            //     ->join('pedidos', 'detalle_direccion_pedido.idPedido', '=', 'pedidos.idPedido')
            //     ->where('detalle_direccion_pedido.idDireccion', $idDireccion)
            //     ->whereIn('pedidos.estado', ['pendiente', 'aprobando', 'en preparacion', 'enviado'])
            //     ->exists();

            // if ($estadoRestringido) {
            //     return response()->json([
            //         'success' => false,
            //         'message' => 'No se puede eliminar la dirección: existen pedidos en proceso con esta dirección asignada.',
            //     ], 400);
            // }

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


    /**
     * @OA\Put(
     *     path="/api/setDireccionUsando/{idDireccion}",
     *     summary="Establecer una dirección como predeterminada (usando)",
     *     description="Este endpoint permite actualizar el estado de una dirección específica a 'usando' y marcar todas las demás direcciones del usuario como 'no usando'.",
     *     operationId="setDireccionUsando",
     *     tags={"CLIENTE CONTROLLER"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="idDireccion",
     *         in="path",
     *         required=true,
     *         description="ID de la dirección que se desea marcar como 'usando'",
     *         @OA\Schema(
     *             type="integer",
     *             example=1
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Dirección actualizada correctamente.",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Dirección actualizada a usando.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Dirección no encontrada.",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Dirección no encontrada.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error interno al actualizar la dirección.",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Error al actualizar la dirección."),
     *             @OA\Property(property="error", type="string", example="Mensaje de error detallado.")
     *         )
     *     )
     * )
     */
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


    /**
     * @OA\Post(
     *     path="/api/enviarCodigo/{idUsuario}",
     *     summary="Enviar un código de verificación al correo del usuario",
     *     description="Este endpoint envía un código de verificación de 6 dígitos al correo electrónico del usuario especificado. El código se almacena en caché durante 5 minutos.",
     *     operationId="enviarCodigo",
     *     tags={"CLIENTE CONTROLLER"},
     *     @OA\Parameter(
     *         name="idUsuario",
     *         in="path",
     *         required=true,
     *         description="ID del usuario al que se le enviará el código de verificación",
     *         @OA\Schema(
     *             type="integer",
     *             example=1
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Código enviado correctamente.",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Usuario no encontrado.",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Usuario no encontrado.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error interno al enviar el código.",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Error al enviar el código."),
     *             @OA\Property(property="error", type="string", example="Mensaje de error detallado.")
     *         )
     *     )
     * )
     */
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
    
        // Obtener el usuario asociado al pedido
        $usuario = $pedido->usuario; // Asumimos que 'usuario' es la relación en el modelo Pedido
    
        // Concatenar el nombre y apellido del usuario
        $nombreCompleto = $usuario->nombres . ' ' . $usuario->apellidos;
    
        // Eliminar el pedido
        $pedido->delete();
    
        // Enviar el correo electrónico con los detalles
        Mail::to($usuario->correo)->send(new NotificacionPedidoCancelado($nombreCompleto, $pedido->idPedido));
    
        return response()->json(['message' => 'Pedido cancelado exitosamente'], 200);
    }

    public function obtenerDireccionPedido($idPedido)
    {
        $direccion = DetalleDireccionPedido::where('idPedido', $idPedido)
            ->with('detalleDireccion') // Cargar datos de relación detalle_direccion
            ->first();

        if ($direccion) {
            return response()->json([
                'success' => true,
                'direccion' => [
                    'region' => $direccion->detalleDireccion->region,
                    'provincia' => $direccion->detalleDireccion->provincia,
                    'direccion' => $direccion->detalleDireccion->direccion,
                    'latitud' => $direccion->detalleDireccion->latitud,
                    'longitud' => $direccion->detalleDireccion->longitud,
                ],
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Dirección no encontrada para el pedido'
        ]);
    }

    // Obtener las 8 primeras categorías con estado "activo" para el home principal
    public function listarCategorias()
    {
        // Filtrar las categorías por estado "activo" y obtener las primeras 8
        $categorias = Categoria::where('estado', 'activo')
                            ->take(12)
                            ->get();

        // Devolver las categorías como JSON con un mensaje de éxito
        return response()->json(['success' => true, 'data' => $categorias], 200);
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
