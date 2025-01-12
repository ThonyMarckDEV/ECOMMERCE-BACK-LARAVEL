<?php

namespace App\Http\Controllers;

use App\Models\Talla;
use App\Models\Usuario;
use App\Models\Categoria;
use App\Models\Producto;
use App\Models\Log as LogUser;
use App\Models\Pedido;
use App\Models\Pago;
use App\Models\DetalleDireccionPedido;
use Illuminate\Http\Request;
use App\Mail\NotificacionPagoCompletado;
use App\Mail\NotificacionPedidoEliminado;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use FPDF;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Response;
use App\Models\Facturacion;
use App\Models\ImagenModelo;
use App\Models\Modelo;
use App\Models\Stock;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class SuperAdminController extends Controller
{
    // FUNCION PARA REGISTRAR UN ADMIN
    public function agregarUsuario(Request $request)
    {
        // Validar los datos de entrada
        $validator = Validator::make($request->all(), [
            'nombres' => 'required|string|max:255',
            'apellidos' => 'required|string|max:255',
            'correo' => 'required|email|unique:usuarios,correo',
            'password' => 'required|string|min:6',
        ]);
    
        // Si la validación falla, retornar errores
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $validator->errors(),
            ], 422);
        }
    
        // Obtener nombres y apellidos
        $nombres = $request->nombres;
        $apellidos = $request->apellidos;
    
        // Separar apellidos en paterno y materno
        $apellidosArray = explode(' ', $apellidos); // Divide los apellidos por espacios
        $apellidoPaterno = $apellidosArray[0]; // Primer apellido
        $apellidoMaterno = count($apellidosArray) > 1 ? $apellidosArray[1] : ''; // Segundo apellido (si existe)
    
        // Generar el username
        $username = 
            strtolower(substr($nombres, 0, 2)) . // Dos primeras letras del nombre
            strtolower($apellidoPaterno) .       // Todo el apellido paterno
            strtolower(substr($apellidoMaterno, 0, 1)); // Primera letra del apellido materno
    
        // Crear el usuario con valores predeterminados
        $user = Usuario::create([
            'rol' => 'admin', // Valor predeterminado
            'nombres' => $nombres,
            'apellidos' => $apellidos,
            'correo' => $request->correo,
            'password' => bcrypt($request->password), // Encriptar la contraseña
            'fecha_creado' => now(), // Fecha actual
            'status' => 'loggedOff', // Valor predeterminado
            'username' => $username, // Username generado
            'emailVerified'=>1 // Email verificado
        ]);
    
        // Retornar una respuesta exitosa
        return response()->json([
            'success' => true,
            'message' => 'Usuario agregado exitosamente',
            'user' => $user,
        ], 201);
    }
    

     // Editar usuario
     public function editarUsuario(Request $request, $id)
     {
         // Validar los datos de entrada
         $validator = Validator::make($request->all(), [
             'nombres' => 'sometimes|string|max:255',
             'apellidos' => 'sometimes|string|max:255',
             'correo' => 'sometimes|email|unique:usuarios,correo,' . $id,
             'password' => 'sometimes|string|min:6',
             'rol' => 'sometimes|string|max:255',
         ]);
 
         // Si la validación falla, retornar errores
         if ($validator->fails()) {
             return response()->json([
                 'success' => false,
                 'message' => 'Error de validación',
                 'errors' => $validator->errors(),
             ], 422);
         }
 
         // Buscar el usuario por ID
         $user = Usuario::find($id);
         if (!$user) {
             return response()->json([
                 'success' => false,
                 'message' => 'Usuario no encontrado',
             ], 404);
         }
 
         // Actualizar los campos proporcionados
         if ($request->has('nombres')) {
             $user->nombres = $request->nombres;
         }
         if ($request->has('apellidos')) {
             $user->apellidos = $request->apellidos;
         }
         if ($request->has('correo')) {
             $user->correo = $request->correo;
         }
         if ($request->has('password')) {
             $user->password = bcrypt($request->password);
         }
         if ($request->has('rol')) {
             $user->rol = $request->rol;
         }
 
         $user->save();
 
         // Retornar una respuesta exitosa
         return response()->json([
             'success' => true,
             'message' => 'Usuario actualizado exitosamente',
             'user' => $user,
         ]);
     }

     public function listarUsuarios(Request $request)
     {
         try {
             // Obtener los parámetros de la solicitud
             $perPage = $request->input('per_page', 10); // Número de elementos por página (por defecto 10)
             $page = $request->input('page', 1); // Página actual (por defecto 1)
             $search = $request->input('search', ''); // Término de búsqueda general
             $filters = $request->only(['nombres', 'apellidos', 'correo', 'rol', 'estado']); // Filtros específicos
     
             // Construir la consulta
             $query = Usuario::where('rol', 'admin');
     
             // Aplicar filtros dinámicos
             foreach ($filters as $field => $value) {
                 if ($value) {
                     $query->where($field, 'like', "%{$value}%");
                 }
             }
     
             // Aplicar búsqueda general
             if ($search) {
                 $query->where(function ($q) use ($search) {
                     $q->where('nombres', 'like', "%{$search}%")
                       ->orWhere('apellidos', 'like', "%{$search}%")
                       ->orWhere('correo', 'like', "%{$search}%")
                       ->orWhere('rol', 'like', "%{$search}%")
                       ->orWhere('estado', 'like', "%{$search}%");
                 });
             }
     
             // Paginar los resultados
             $usuarios = $query->select('idUsuario', 'nombres', 'apellidos', 'correo', 'rol', 'estado')
                               ->paginate($perPage, ['*'], 'page', $page);
     
             return response()->json([
                 'success' => true,
                 'usuarios' => $usuarios->items(), // Lista de usuarios
                 'pagination' => [
                     'total' => $usuarios->total(), // Total de usuarios
                     'per_page' => $usuarios->perPage(), // Elementos por página
                     'current_page' => $usuarios->currentPage(), // Página actual
                     'last_page' => $usuarios->lastPage(), // Última página
                     'from' => $usuarios->firstItem(), // Primer elemento de la página
                     'to' => $usuarios->lastItem(), // Último elemento de la página
                 ]
             ]);
         } catch (\Exception $e) {
             return response()->json([
                 'success' => false,
                 'message' => 'Error al obtener los usuarios',
                 'error' => $e->getMessage()
             ], 500);
         }
     }
 
     public function cambiarEstado($id)
     {
         // Buscar el usuario por ID
         $user = Usuario::find($id);
         if (!$user) {
             return response()->json([
                 'success' => false,
                 'message' => 'Usuario no encontrado',
             ], 404);
         }
     
         // Cambiar el estado
         $user->estado = ($user->estado === 'activo') ? 'inactivo' : 'activo';
         $user->save();
     
         // Retornar una respuesta exitosa con el estado actualizado
         return response()->json([
             'success' => true,
             'message' => 'Estado actualizado exitosamente',
             'user' => [
                 'idUsuario' => $user->idUsuario,
                 'nombres' => $user->nombres,
                 'apellidos' => $user->apellidos,
                 'correo' => $user->correo,
                 'rol' => $user->rol,
                 'estado' => $user->estado, // Asegúrate de devolver el estado actualizado
             ],
         ]);
     }

    public function obtenerTallas(Request $request)
    {
        // Obtener los parámetros de paginación
        $page = $request->query('page', 1); // Página actual, por defecto 1
        $limit = $request->query('limit', 10); // Límite de elementos por página, por defecto 10
    
        // Obtener los parámetros de filtro y búsqueda
        $idTalla = $request->query('idTalla', '');
        $nombreTalla = $request->query('nombreTalla', '');
        $searchTerm = $request->query('searchTerm', '');
    
        // Construir la consulta
        $query = Talla::query();
    
        // Aplicar filtros
        if ($idTalla) {
            $query->where('idTalla', 'like', "%{$idTalla}%");
        }
        if ($nombreTalla) {
            $query->where('nombreTalla', 'like', "%{$nombreTalla}%");
        }
        if ($searchTerm) {
            $query->where(function ($q) use ($searchTerm) {
                $q->where('idTalla', 'like', "%{$searchTerm}%")
                  ->orWhere('nombreTalla', 'like', "%{$searchTerm}%");
            });
        }
    
        // Paginar los resultados
        $tallas = $query->paginate($limit, ['*'], 'page', $page);
    
        return response()->json([
            'data' => $tallas->items(), // Datos de la página actual
            'total' => $tallas->total(), // Total de registros
            'page' => $tallas->currentPage(), // Página actual
            'totalPages' => $tallas->lastPage(), // Total de páginas
        ]);
    }
 
     // Agregar una nueva talla
     public function agregarTalla(Request $request)
     {
         $request->validate([
             'nombreTalla' => 'required|string|unique:tallas',
         ]);
 
         $talla = Talla::create([
             'nombreTalla' => $request->nombreTalla,
         ]);
 
         return response()->json(['message' => 'Talla agregada exitosamente', 'data' => $talla], 201);
     }

    // Editar una talla existente
    public function editarTalla(Request $request, $id)
    {
        $request->validate([
            'nombreTalla' => [
                'required',
                'string',
                Rule::unique('tallas')->ignore($id, 'idTalla'), // Especifica la columna correcta
            ],
        ]);

        $talla = Talla::find($id);
        if (!$talla) {
            return response()->json(['message' => 'Talla no encontrada'], 404);
        }

        $talla->nombreTalla = $request->nombreTalla;
        $talla->save();

        return response()->json(['message' => 'Talla actualizada exitosamente', 'data' => $talla]);
    }


   
    public function listarProductos(Request $request)
    {
        // Obtener los parámetros de la solicitud
        $categoriaId = $request->input('categoria');
        $texto = $request->input('texto');
        $idProducto = $request->input('idProducto');
        $perPage = $request->input('perPage', 6); // Número de elementos por página
        $filters = json_decode($request->input('filters', '{}'), true); // Filtros adicionales

        // Construir la consulta para obtener los productos con relaciones
        $query = Producto::with([
            'categoria:idCategoria,nombreCategoria,estado', // Incluir el campo 'estado' de la categoría
            'modelos' => function($query) {
                $query->with([
                    'imagenes:idImagen,urlImagen,idModelo'
                ]);
            }
        ]);

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

        // Aplicar filtros adicionales
        if (!empty($filters)) {
            if (isset($filters['nombreProducto']) && $filters['nombreProducto'] !== '') {
                $query->where('nombreProducto', 'like', '%' . $filters['nombreProducto'] . '%');
            }
            if (isset($filters['descripcion']) && $filters['descripcion'] !== '') {
                $query->where('descripcion', 'like', '%' . $filters['descripcion'] . '%');
            }
            if (isset($filters['estado']) && $filters['estado'] !== '') {
                $query->where('estado', $filters['estado']);
            }
        }

        // Paginar los resultados
        $productos = $query->paginate($perPage);

        // Si se pasó un 'idProducto', se devuelve un solo producto
        if ($idProducto) {
            $producto = $productos->first();

            if ($producto) {
                $productoData = [
                    'idProducto' => $producto->idProducto,
                    'nombreProducto' => $producto->nombreProducto,
                    'descripcion' => $producto->descripcion,
                    'estado' => $producto->estado,
                    'nombreCategoria' => $producto->categoria ? $producto->categoria->nombreCategoria : 'Sin Categoría',
                    'modelos' => $producto->modelos->map(function($modelo) {
                        return [
                            'idModelo' => $modelo->idModelo,
                            'nombreModelo' => $modelo->nombreModelo,
                            'imagenes' => $modelo->imagenes->map(function($imagen) {
                                return [
                                    'idImagen' => $imagen->idImagen,
                                    'urlImagen' => $imagen->urlImagen
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

        // Si no se pasó un 'idProducto', devolver todos los productos paginados
        $productosData = $productos->map(function($producto) {
            return [
                'idProducto' => $producto->idProducto,
                'nombreProducto' => $producto->nombreProducto,
                'descripcion' => $producto->descripcion ? : 'N/A',
                'estado' => $producto->estado,
                'nombreCategoria' => $producto->categoria ? $producto->categoria->nombreCategoria : 'Sin Categoría',
                'modelos' => $producto->modelos->map(function($modelo) {
                    return [
                        'idModelo' => $modelo->idModelo,
                        'nombreModelo' => $modelo->nombreModelo,
                        'imagenes' => $modelo->imagenes->map(function($imagen) {
                            return [
                                'idImagen' => $imagen->idImagen,
                                'urlImagen' => $imagen->urlImagen
                            ];
                        })
                    ];
                })
            ];
        });

        return response()->json([
            'data' => $productosData,
            'current_page' => $productos->currentPage(),
            'last_page' => $productos->lastPage(),
            'per_page' => $productos->perPage(),
            'total' => $productos->total(),
        ], 200);
    }



       /**
     * @OA\Post(
     *     path="/api/agregarProducto",
     *     tags={"SUPERADMIN CONTROLLER"},
     *     summary="Agregar un nuevo producto con modelos e imágenes",
     *     description="Permite a un superadministrador agregar un nuevo producto, incluyendo sus modelos e imágenes asociadas. Se validan los datos de entrada y se registra la acción en el log del sistema.",
     *     operationId="agregarProducto",
     *     security={{"bearerAuth": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         description="Datos del producto y sus modelos",
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"nombreProducto", "estado", "idCategoria", "modelos"},
     *                 @OA\Property(property="nombreProducto", type="string", example="Producto Ejemplo"),
     *                 @OA\Property(property="descripcion", type="string", nullable=true, example="Descripción del producto"),
     *                 @OA\Property(property="estado", type="string", example="activo"),
     *                 @OA\Property(property="idCategoria", type="integer", example=1),
     *                 @OA\Property(
     *                     property="modelos",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         required={"nombreModelo", "imagen"},
     *                         @OA\Property(property="nombreModelo", type="string", example="Modelo Ejemplo"),
     *                         @OA\Property(property="imagen", type="string", format="binary", description="Imagen del modelo (formato: jpg, png, etc.)")
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Producto agregado exitosamente",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Producto agregado correctamente"),
     *             @OA\Property(
     *                 property="producto",
     *                 type="object",
     *                 @OA\Property(property="idProducto", type="integer", example=1),
     *                 @OA\Property(property="nombreProducto", type="string", example="Producto Ejemplo"),
     *                 @OA\Property(property="descripcion", type="string", example="Descripción del producto"),
     *                 @OA\Property(property="estado", type="string", example="activo"),
     *                 @OA\Property(property="idCategoria", type="integer", example=1)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error de validación",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Error de validación"),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 example={
     *                     "nombreProducto": {"El campo nombreProducto es obligatorio."},
     *                     "modelos.0.nombreModelo": {"El campo nombreModelo es obligatorio."}
     *                 }
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="No autorizado",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="No autorizado")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error interno del servidor",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Error al agregar el producto"),
     *             @OA\Property(property="error", type="string", example="Mensaje de error detallado")
     *         )
     *     )
     * )
     */
    public function agregarProducto(Request $request)
    {
        Log::info('Iniciando proceso de agregar producto'); // Log de inicio

        try {
            // Verificar si ya existe un producto con el mismo nombre
            Log::info('Verificando si el producto ya existe');
            $productoExistente = Producto::where('nombreProducto', $request->nombreProducto)->first();

            if ($productoExistente) {
                Log::warning('Producto con el mismo nombre ya existe:', ['id' => $productoExistente->idProducto]);
                return response()->json([
                    'message' => 'Error: Ya existe un producto con el mismo nombre.',
                    'productoExistente' => $productoExistente,
                ], 409); // 409 Conflict es un código HTTP adecuado para este caso
            }

            // Validar el request
            Log::info('Validando request');
            $request->validate([
                'nombreProducto' => 'required',
                'descripcion' => 'nullable',
                'estado' => 'required',
                'precio' => 'required',
                'idCategoria' => 'required|exists:categorias,idCategoria',
                'modelos' => 'required|array',
                'modelos.*.nombreModelo' => 'required',
                'modelos.*.imagenes' => 'required|array',
                'modelos.*.imagenes.*' => [
                    'required',
                    'file',
                    'mimes:jpeg,jpg,png,avif,webp', // Formatos permitidos
                    'max:5120', // Tamaño máximo de 5 MB
                ],
                'modelos.*.tallas' => 'array',
            ]);

            Log::info('Request validado correctamente');

            DB::beginTransaction();
            Log::info('Iniciando transacción de base de datos');

            // Crear el producto
            Log::info('Creando producto');
            $producto = Producto::create([
                'nombreProducto' => $request->nombreProducto,
                'descripcion' => $request->descripcion,
                'precio' => $request->precio,
                'estado' => $request->estado,
                'idCategoria' => $request->idCategoria,
            ]);
            Log::info('Producto creado:', ['id' => $producto->idProducto]);

            // Crear modelos y manejar su stock
            foreach ($request->modelos as $modeloData) {
                Log::info('Creando modelo');
                $modelo = Modelo::create([
                    'idProducto' => $producto->idProducto,
                    'nombreModelo' => $modeloData['nombreModelo'],
                    'urlModelo' => null,
                ]);
                Log::info('Modelo creado:', ['id' => $modelo->idModelo]);

                // Procesar las imágenes del modelo
                if (isset($modeloData['imagenes'])) {
                    Log::info('Procesando imágenes del modelo');
                    foreach ($modeloData['imagenes'] as $imagen) {
                        $nombreProducto = $producto->nombreProducto;
                        $nombreModelo = $modelo->nombreModelo;

                        $rutaImagen = 'imagenes/productos/' . $nombreProducto . '/modelos/' . $nombreModelo . '/' . $imagen->getClientOriginalName();

                        // Crear directorio si no existe
                        if (!Storage::disk('public')->exists('imagenes/productos/' . $nombreProducto . '/modelos/' . $nombreModelo)) {
                            Log::info('Creando directorio para la imagen');
                            Storage::disk('public')->makeDirectory('imagenes/productos/' . $nombreProducto . '/modelos/' . $nombreModelo);
                        }

                        Storage::disk('public')->putFileAs(
                            'imagenes/productos/' . $nombreProducto . '/modelos/' . $nombreModelo,
                            $imagen,
                            $imagen->getClientOriginalName()
                        );

                        $rutaImagenBD = str_replace('public/', '', $rutaImagen);

                        ImagenModelo::create([
                            'idModelo' => $modelo->idModelo,
                            'urlImagen' => $rutaImagenBD,
                            'descripcion' => 'Imagen del modelo ' . $nombreModelo,
                        ]);
                        Log::info('Imagen procesada y guardada');
                    }
                }

                // Manejar el stock para cada talla del modelo
                if (isset($modeloData['tallas']) && is_array($modeloData['tallas'])) {
                    Log::info('Procesando tallas del modelo');
                    foreach ($modeloData['tallas'] as $idTalla => $cantidad) {
                        if ($cantidad > 0) {
                            Stock::create([
                                'idModelo' => $modelo->idModelo,
                                'idTalla' => $idTalla,
                                'cantidad' => $cantidad,
                            ]);
                            Log::info('Stock creado para talla:', ['idTalla' => $idTalla, 'cantidad' => $cantidad]);
                        }
                    }
                }
            }

            DB::commit();
            Log::info('Transacción completada correctamente');

            // Registrar la acción en el log
            $usuarioId = auth()->id();
            $usuario = Usuario::find($usuarioId);
            $nombreUsuario = $usuario->nombres . ' ' . $usuario->apellidos;
            $accion = "$nombreUsuario agregó el producto: $producto->nombreProducto";
            $this->agregarLog($usuarioId, $accion);

            Log::info('Producto agregado correctamente');

            return response()->json([
                'message' => 'Producto agregado correctamente',
                'producto' => $producto,
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            Log::error('Error de validación:', ['errors' => $e->errors()]);

            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al agregar el producto: ' . $e->getMessage());
            Log::error('Trace del error:', ['trace' => $e->getTraceAsString()]);

            return response()->json([
                'message' => 'Error al agregar el producto',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/actualizarProducto/{idProducto}",
     *     tags={"SUPERADMIN CONTROLLER"},
     *     summary="Actualizar un producto existente",
     *     description="Permite a un superadministrador actualizar los datos de un producto existente, incluyendo su nombre y descripción. Además, registra la acción en el log del sistema.",
     *     operationId="actualizarProducto",
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="idProducto",
     *         in="path",
     *         required=true,
     *         description="ID del producto que se desea actualizar",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="Datos del producto que se desean actualizar",
     *         @OA\JsonContent(
     *             required={"nombreProducto"},
     *             @OA\Property(property="nombreProducto", type="string", example="Producto Actualizado"),
     *             @OA\Property(property="descripcion", type="string", nullable=true, example="Descripción actualizada")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Producto actualizado correctamente",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Producto actualizado correctamente")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Producto no encontrado",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Producto no encontrado")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="No autorizado",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="No autorizado")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error interno del servidor",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Error al actualizar el producto"),
     *             @OA\Property(property="error", type="string", example="Mensaje de error detallado")
     *         )
     *     )
     * )
     */
    public function actualizarProducto(Request $request, $idProducto)
    {
        $producto = Producto::find($idProducto);

        if (!$producto) {
            return response()->json(['error' => 'Producto no encontrado'], 404);
        }

        // Obtener el nombre del producto antes de la actualización
        $nombreProductoAntiguo = $producto->nombreProducto;

        // Actualizar los datos del producto
        $producto->nombreProducto = $request->nombreProducto;
        $producto->descripcion = $request->descripcion;
        $producto->save();

        // Registrar la acción de actualización del producto en el log
        $usuarioId = auth()->id(); // Obtener el ID del usuario autenticado
        $usuario = Usuario::find($usuarioId);
        $nombreUsuario = $usuario->nombres . ' ' . $usuario->apellidos;
        $accion = "$nombreUsuario actualizó el producto: $nombreProductoAntiguo a {$producto->nombreProducto}";
        $this->agregarLog($usuarioId, $accion);

        return response()->json(['message' => 'Producto actualizado correctamente']);
    }


    /**
     * @OA\Post(
     *     path="/api/editarModeloyImagen/{idModelo}",
     *     tags={"SUPERADMIN CONTROLLER"},
     *     summary="Editar un modelo y sus imágenes",
     *     description="Permite a un superadministrador editar un modelo existente, incluyendo su nombre, descripción y la gestión de imágenes (añadir nuevas o reemplazar existentes).",
     *     operationId="editarModeloYImagen",
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="idModelo",
     *         in="path",
     *         required=true,
     *         description="ID del modelo que se desea editar",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="Datos del modelo y sus imágenes",
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"nombreModelo"},
     *                 @OA\Property(property="nombreModelo", type="string", example="Modelo Actualizado"),
     *                 @OA\Property(property="descripcion", type="string", nullable=true, example="Descripción actualizada"),
     *                 @OA\Property(
     *                     property="nuevasImagenes",
     *                     type="array",
     *                     @OA\Items(type="string", format="binary", description="Nuevas imágenes para el modelo")
     *                 ),
     *                 @OA\Property(
     *                     property="idImagenesReemplazadas",
     *                     type="array",
     *                     @OA\Items(type="integer", example=1),
     *                     description="IDs de las imágenes existentes que se desean reemplazar"
     *                 ),
     *                 @OA\Property(
     *                     property="imagenesReemplazadas",
     *                     type="array",
     *                     @OA\Items(type="string", format="binary", description="Nuevas imágenes para reemplazar las existentes")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Modelo e imágenes actualizados correctamente",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Modelo e imágenes actualizados correctamente")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Modelo no encontrado",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Modelo no encontrado")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error de validación",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Error de validación"),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 example={
     *                     "nombreModelo": {"El campo nombreModelo es obligatorio."},
     *                     "nuevasImagenes.0": {"El archivo debe ser una imagen válida."}
     *                 }
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="No autorizado",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="No autorizado")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error interno del servidor",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Error al actualizar el modelo e imágenes"),
     *             @OA\Property(property="error", type="string", example="Mensaje de error detallado")
     *         )
     *     )
     * )
     */
    public function editarModeloYImagen(Request $request, $idModelo)
    {
        $modelo = Modelo::findOrFail($idModelo);
        
        // Obtener el nombre del modelo antes de la actualización
        $nombreModeloAntiguo = $modelo->nombreModelo;
        
        // Actualizar los datos del modelo
        $modelo->update([
            'nombreModelo' => $request->nombreModelo,
            'descripcion' => $request->descripcion,
        ]);

        // Registrar la acción de edición del modelo en el log
        $usuarioId = auth()->id(); // Obtener el ID del usuario autenticado
        $usuario = Usuario::find($usuarioId);
        $nombreUsuario = $usuario->nombres . ' ' . $usuario->apellidos;
        $accion = "$nombreUsuario editó el modelo: $nombreModeloAntiguo a $modelo->nombreModelo";
        $this->agregarLog($usuarioId, $accion);

        // Procesar nuevas imágenes
        if ($request->hasFile('nuevasImagenes')) {
            foreach ($request->file('nuevasImagenes') as $imagen) {
                $nombreProducto = $modelo->producto->nombreProducto;
                $nombreModelo = $modelo->nombreModelo;

                $nombreArchivo = time() . '_' . $imagen->getClientOriginalName();
                $ruta = "imagenes/productos/{$nombreProducto}/modelos/{$nombreModelo}/{$nombreArchivo}";
                
                // Verificar si existe una imagen con el mismo nombre en la base de datos
                $imagenExistente = ImagenModelo::where('urlImagen', $ruta)->first();

                if ($imagenExistente) {
                    Log::info("Imagen existente encontrada: {$imagenExistente->urlImagen}");

                    // Intentar eliminar el archivo del almacenamiento
                    if (Storage::disk('public')->exists($imagenExistente->urlImagen)) {
                        Storage::disk('public')->delete($imagenExistente->urlImagen);
                        Log::info("Imagen eliminada: {$imagenExistente->urlImagen}");
                    } else {
                        Log::warning("La imagen no existe en el almacenamiento: {$imagenExistente->urlImagen}");
                    }
                    
                    // Eliminar el registro de la base de datos
                    $imagenExistente->delete();
                    Log::info("Registro eliminado de la base de datos: {$imagenExistente->idImagen}");
                }

                // Guardar la nueva imagen
                $imagen->storeAs("imagenes/productos/{$nombreProducto}/modelos/{$nombreModelo}", $nombreArchivo, 'public');
                Log::info("Nueva imagen guardada en: {$ruta}");

                // Crear el registro en la base de datos
                ImagenModelo::create([
                    'urlImagen' => $ruta,
                    'idModelo' => $modelo->idModelo,
                    'descripcion' => 'Nueva imagen añadida',
                ]);
            }
        }

        // Reemplazo de imágenes existentes
        if ($request->has('idImagenesReemplazadas')) {
            foreach ($request->idImagenesReemplazadas as $index => $idImagen) {
                $imagenModelo = ImagenModelo::findOrFail($idImagen);
                $rutaAntigua = $imagenModelo->urlImagen;

                Log::info("Iniciando reemplazo de imagen: {$rutaAntigua}");

                if ($request->hasFile("imagenesReemplazadas.{$index}")) {
                    $imagenReemplazada = $request->file("imagenesReemplazadas.{$index}");

                    // Eliminar la imagen anterior si existe
                    if (Storage::disk('public')->exists($rutaAntigua)) {
                        Storage::disk('public')->delete($rutaAntigua);
                        Log::info("Imagen reemplazada eliminada: {$rutaAntigua}");
                    } else {
                        Log::warning("No se encontró la imagen a reemplazar: {$rutaAntigua}");
                    }

                    $nombreProducto = $modelo->producto->nombreProducto;
                    $nombreModelo = $modelo->nombreModelo;

                    $nombreArchivoNuevo = time() . '_' . $imagenReemplazada->getClientOriginalName();
                    $rutaNueva = "imagenes/productos/{$nombreProducto}/modelos/{$nombreModelo}/{$nombreArchivoNuevo}";

                    // Guardar la nueva imagen
                    $imagenReemplazada->storeAs("imagenes/productos/{$nombreProducto}/modelos/{$nombreModelo}", $nombreArchivoNuevo, 'public');
                    Log::info("Nueva imagen guardada en: {$rutaNueva}");

                    // Actualizar la nueva ruta en la base de datos
                    $imagenModelo->update([
                        'urlImagen' => $rutaNueva,
                    ]);
                    Log::info("Ruta actualizada en la base de datos: {$rutaNueva}");
                }
            }
        }

        return response()->json(['message' => 'Modelo e imágenes actualizados correctamente']);
    }

        /**
     * @OA\Delete(
     *     path="/api/eliminarImagenModelo/{idImagen}",
     *     tags={"SUPERADMIN CONTROLLER"},
     *     summary="Eliminar una imagen de un modelo",
     *     description="Permite a un superadministrador eliminar una imagen asociada a un modelo, tanto físicamente del almacenamiento como de la base de datos. Además, registra la acción en el log del sistema.",
     *     operationId="eliminarImagenModelo",
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="idImagen",
     *         in="path",
     *         required=true,
     *         description="ID de la imagen que se desea eliminar",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Imagen eliminada correctamente",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Imagen eliminada correctamente")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Imagen no encontrada",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Imagen no encontrada")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="No autorizado",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="No autorizado")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error interno del servidor",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Error al eliminar la imagen"),
     *             @OA\Property(property="error", type="string", example="Mensaje de error detallado")
     *         )
     *     )
     * )
     */
    public function eliminarImagenModelo($idImagen)
    {
        // Buscar la imagen en la base de datos
        $imagenModelo = ImagenModelo::findOrFail($idImagen);
        $rutaImagen = $imagenModelo->urlImagen;
    
        // Obtener el nombre del modelo y producto relacionados con la imagen
        $modelo = $imagenModelo->modelo;
        $producto = $modelo->producto;
        $nombreModelo = $modelo->nombreModelo;
        $nombreProducto = $producto->nombreProducto;
    
        // Eliminar archivo físico si existe (especificando el disco 'public')
        if (Storage::disk('public')->exists($rutaImagen)) {
            Storage::disk('public')->delete($rutaImagen);
            Log::info("Imagen eliminada correctamente de la ruta: {$rutaImagen}");
            
            // Registrar la acción en el log
            $usuarioId = auth()->id(); // Obtener el ID del usuario autenticado
            $usuario = Usuario::find($usuarioId);
            $nombreUsuario = $usuario->nombres . ' ' . $usuario->apellidos;
            $accion = "$nombreUsuario eliminó la imagen del modelo $nombreModelo del producto $nombreProducto";
            $this->agregarLog($usuarioId, $accion);
        } else {
            Log::warning("La imagen no existe en el almacenamiento: {$rutaImagen}");
        }
    
        // Eliminar el registro de la imagen de la base de datos
        $imagenModelo->delete();
        Log::info("Registro eliminado de la base de datos: {$imagenModelo->idImagen}");
    
        return response()->json(['message' => 'Imagen eliminada correctamente']);
    }


    
        /**
     * @OA\Put(
     *     path="/api/cambiarEstadoProducto/{id}",
     *     tags={"SUPERADMIN CONTROLLER"},
     *     summary="Cambiar el estado de un producto",
     *     description="Permite a un superadministrador cambiar el estado de un producto. Además, registra la acción en el log del sistema.",
     *     operationId="cambiarEstadoProducto",
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID del producto cuyo estado se desea cambiar",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="Nuevo estado del producto",
     *         @OA\JsonContent(
     *             required={"estado"},
     *             @OA\Property(property="estado", type="string", example="activo")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Estado actualizado correctamente",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Estado actualizado")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Producto no encontrado",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Producto no encontrado")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="No autorizado",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="No autorizado")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error de validación",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Error de validación"),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 example={
     *                     "estado": {"El campo estado es obligatorio."}
     *                 }
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error interno del servidor",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Error al cambiar el estado del producto"),
     *             @OA\Property(property="error", type="string", example="Mensaje de error detallado")
     *         )
     *     )
     * )
     */
    public function cambiarEstadoProducto(Request $request, $id)
    {
        $producto = Producto::findOrFail($id);
    
        // Guardar el estado anterior para el log
        $estadoAnterior = $producto->estado;
    
        // Actualizar el estado del producto
        $producto->estado = $request->estado;
        $producto->save();
    
        // Registrar la acción de cambio de estado en el log
        $usuarioId = auth()->id(); // Obtener el ID del usuario autenticado
        $usuario = Usuario::find($usuarioId);
        $nombreUsuario = $usuario->nombres . ' ' . $usuario->apellidos;
        $accion = "$nombreUsuario cambió el estado del producto {$producto->nombreProducto} de '$estadoAnterior' a '{$producto->estado}'";
        $this->agregarLog($usuarioId, $accion);
    
        return response()->json(['message' => 'Estado actualizado']);
    }


            /**
     * @OA\Post(
     *     path="/api/agregarModelo",
     *     tags={"SUPERADMIN CONTROLLER"},
     *     summary="Agregar un nuevo modelo a un producto",
     *     description="Permite a un superadministrador agregar un nuevo modelo a un producto existente. Se valida que el producto exista y se genera una ruta para el modelo basada en el nombre del producto.",
     *     operationId="agregarModelo",
     *     security={{"bearerAuth": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         description="Datos del modelo a agregar",
     *         @OA\JsonContent(
     *             required={"idProducto", "nombreModelo"},
     *             @OA\Property(property="idProducto", type="integer", example=1),
     *             @OA\Property(property="nombreModelo", type="string", example="Modelo Ejemplo")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Modelo agregado correctamente",
     *         @OA\JsonContent(
     *             @OA\Property(property="idModelo", type="integer", example=1),
     *             @OA\Property(property="idProducto", type="integer", example=1),
     *             @OA\Property(property="nombreModelo", type="string", example="Modelo Ejemplo"),
     *             @OA\Property(property="urlModelo", type="string", example="imagenes/productos/Producto Ejemplo/modelos/Modelo Ejemplo")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error de validación",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Error de validación"),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 example={
     *                     "idProducto": {"El campo idProducto es obligatorio."},
     *                     "nombreModelo": {"El campo nombreModelo es obligatorio."}
     *                 }
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Producto no encontrado",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Producto no encontrado")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="No autorizado",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="No autorizado")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error interno del servidor",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Error al agregar el modelo"),
     *             @OA\Property(property="error", type="string", example="Mensaje de error detallado")
     *         )
     *     )
     * )
     */
    public function agregarModelo(Request $request)
    {
        $request->validate([
            'idProducto' => 'required|exists:productos,idProducto',
            'nombreModelo' => 'required|string|max:255',
        ]);
        
        // Obtener el producto para usar su nombre
        $producto = Producto::findOrFail($request->idProducto);
        
        // Construir la ruta del modelo usando el nombre del producto
        $urlModelo = 'imagenes/productos/' . $producto->nombreProducto . '/modelos/' . $request->nombreModelo;
        
        $modelo = Modelo::create([
            'idProducto' => $request->idProducto,
            'nombreModelo' => $request->nombreModelo,
            'urlModelo' => $urlModelo
        ]);

        return response()->json($modelo, 201);
    }


    

    /**
     * @OA\Delete(
     *     path="/api/EliminarModelo/{idModelo}",
     *     tags={"SUPERADMIN CONTROLLER"},
     *     summary="Eliminar un modelo y sus imágenes asociadas",
     *     description="Permite a un superadministrador eliminar un modelo y todas sus imágenes asociadas, tanto de la base de datos como del almacenamiento. Además, elimina el directorio donde se almacenaban las imágenes.",
     *     operationId="EliminarModelo",
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="idModelo",
     *         in="path",
     *         required=true,
     *         description="ID del modelo que se desea eliminar",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Modelo y sus imágenes eliminados correctamente",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Modelo y sus imágenes eliminados correctamente"),
     *             @OA\Property(property="directory_deleted", type="string", example="imagenes/productos/Producto Ejemplo/modelos/Modelo Ejemplo")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Modelo no encontrado",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Modelo no encontrado")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="No autorizado",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="No autorizado")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error interno del servidor",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Error al eliminar el modelo"),
     *             @OA\Property(property="error", type="string", example="Mensaje de error detallado")
     *         )
     *     )
     * )
     */
    // public function EliminarModelo($idModelo)
    // {
    //     try {
    //         // Begin transaction
    //         DB::beginTransaction();
            
    //         // Obtener el modelo
    //         $modelo = Modelo::findOrFail($idModelo);
            
    //         // Variable para almacenar la ruta del directorio
    //         $directorioABorrar = null;
            
    //         // Primer intento: Verificar si el modelo tiene urlModelo
    //         if (!empty($modelo->urlModelo)) {
    //             $directorioABorrar = $modelo->urlModelo;
    //         } 
    //         // Segundo intento: Buscar la ruta en las imágenes relacionadas
    //         else {
    //             $primeraImagen = ImagenModelo::where('idModelo', $idModelo)
    //                                         ->whereNotNull('urlImagen')
    //                                         ->first();
                                            
    //             if ($primeraImagen) {
    //                 // Remover 'public/' si existe y obtener el directorio padre
    //                 $path = str_replace('public/', '', $primeraImagen->urlImagen);
    //                 $directorioABorrar = dirname($path);
    //             }
    //         }
            
    //         // Eliminar todas las imágenes asociadas de la BD
    //         ImagenModelo::where('idModelo', $idModelo)->delete();
            
    //         // Eliminar el directorio si se encontró una ruta válida
    //         if ($directorioABorrar && Storage::disk('public')->exists($directorioABorrar)) {
    //             Storage::disk('public')->deleteDirectory($directorioABorrar);
    //         }
            
    //         // Eliminar el modelo
    //         $modelo->delete();
            
    //         // Commit transaction
    //         DB::commit();
            
    //         return response()->json([
    //             'message' => 'Modelo y sus imágenes eliminados correctamente',
    //             'directory_deleted' => $directorioABorrar ?? 'No se encontró directorio para borrar'
    //         ]);
            
    //     } catch (\Exception $e) {
    //         // Rollback in case of error
    //         DB::rollBack();
            
    //         return response()->json([
    //             'message' => 'Error al eliminar el modelo',
    //             'error' => $e->getMessage()
    //         ], 500);
    //     }
    // }

    public function EliminarModelo($idModelo)
{
    try {
        // Begin transaction
        DB::beginTransaction();
        
        // Obtener el modelo
        $modelo = Modelo::findOrFail($idModelo);
        
        // Variable para almacenar la ruta del directorio
        $directorioABorrar = null;
        
        // Primer intento: Verificar si el modelo tiene urlModelo
        if (!empty($modelo->urlModelo)) {
            $directorioABorrar = $modelo->urlModelo;
        } 
        // Segundo intento: Buscar la ruta en las imágenes relacionadas
        else {
            $primeraImagen = ImagenModelo::where('idModelo', $idModelo)
                                        ->whereNotNull('urlImagen')
                                        ->first();
                                        
            if ($primeraImagen) {
                // Remover 'public/' si existe y obtener el directorio padre
                $path = str_replace('public/', '', $primeraImagen->urlImagen);
                $directorioABorrar = dirname($path);
            }
        }
        
        // Eliminar todas las imágenes asociadas de la BD
        ImagenModelo::where('idModelo', $idModelo)->delete();
        
        // Eliminar todos los registros de stock asociados al modelo
        Stock::where('idModelo', $idModelo)->delete();
        
        // Eliminar el directorio si se encontró una ruta válida
        if ($directorioABorrar && Storage::disk('public')->exists($directorioABorrar)) {
            Storage::disk('public')->deleteDirectory($directorioABorrar);
        }
        
        // Eliminar el modelo
        $modelo->delete();
        
        // Commit transaction
        DB::commit();
        
        return response()->json([
            'message' => 'Modelo, imágenes y stock relacionados eliminados correctamente',
            'directory_deleted' => $directorioABorrar ?? 'No se encontró directorio para borrar'
        ]);
        
    } catch (\Exception $e) {
        // Rollback in case of error
        DB::rollBack();
        
        return response()->json([
            'message' => 'Error al eliminar el modelo',
            'error' => $e->getMessage()
        ], 500);
    }
}

    public function listarTallasAdmin()
    {
        try {
            $tallas = Talla::all(['idTalla', 'nombreTalla']);
            return response()->json($tallas);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al obtener las tallas'], 500);
        }
    }

    public function listarStockPorModelo($idModelo)
    {
        try {
            $stock = Stock::join('tallas', 'stock.idTalla', '=', 'tallas.idTalla')
                         ->where('stock.idModelo', $idModelo)
                         ->select('stock.idStock', 'stock.idTalla', 'tallas.nombreTalla', 'stock.cantidad')
                         ->get();
            
            return response()->json($stock);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al obtener el stock'], 500);
        }
    }

    public function agregarStock(Request $request)
    {
        try {
            $request->validate([
                'idModelo' => 'required|exists:modelos,idModelo',
                'idTalla' => 'required|exists:tallas,idTalla',
                'cantidad' => 'required|integer|min:0'
            ]);

            // Verificar si ya existe un stock para esta talla y modelo
            $stockExistente = Stock::where('idModelo', $request->idModelo)
                                 ->where('idTalla', $request->idTalla)
                                 ->first();

            if ($stockExistente) {
                return response()->json(['message' => 'Ya existe stock para esta talla'], 400);
            }

            $stock = Stock::create([
                'idModelo' => $request->idModelo,
                'idTalla' => $request->idTalla,
                'cantidad' => $request->cantidad
            ]);

            return response()->json($stock, 201);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al agregar el stock'], 500);
        }
    }

    public function actualizarStock(Request $request, $idStock)
    {
        try {
            $request->validate([
                'cantidad' => 'required|integer|min:0'
            ]);

            $stock = Stock::findOrFail($idStock);
            $stock->cantidad = $request->cantidad;
            $stock->save();

            return response()->json($stock);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al actualizar el stock'], 500);
        }
    }

    public function eliminarStock($idStock)
    {
        try {
            $stock = Stock::findOrFail($idStock);
            $stock->delete();

            return response()->json(['message' => 'Stock eliminado correctamente']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al eliminar el stock'], 500);
        }
    }


    public function agregarCategorias(Request $request)
    {
        // Validar los datos de entrada
        $request->validate([
            'nombreCategoria' => 'required|string|max:255',
            'descripcion' => 'nullable|string|max:60',
            'imagen' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:5000', // Validación de imagen
        ]);
    
        // Obtener el nombre de la categoría
        $nombreCategoria = $request->input('nombreCategoria');
    
        // Verificar si ya existe una categoría con el mismo nombre
        $categoriaExistente = Categoria::where('nombreCategoria', $nombreCategoria)->first();
        if ($categoriaExistente) {
            return response()->json([
                'message' => 'Error: Ya existe una categoría con el mismo nombre.',
                'categoriaExistente' => $categoriaExistente,
            ], 409); // 409 Conflict
        }
    
        $descripcion = $request->input('descripcion', null);
    
        // Guardar la imagen en el directorio correspondiente
        $imagen = $request->file('imagen');
        $rutaImagen = 'imagenes/categorias/' . $nombreCategoria . '/' . $imagen->getClientOriginalName();
        Storage::disk('public')->putFileAs('imagenes/categorias/' . $nombreCategoria, $imagen, $imagen->getClientOriginalName());
    
        // Crear la categoría en la base de datos
        $categoria = Categoria::create([
            'nombreCategoria' => $nombreCategoria,
            'descripcion' => $descripcion,
            'imagen' => $rutaImagen, // Guardar la ruta de la imagen
            'estado' => 'activo'
        ]);
    
        return response()->json([
            'message' => 'Categoría agregada exitosamente',
            'categoria' => $categoria
        ], 200);
    }


    public function obtenerCategorias(Request $request)
    {
        // Obtener los parámetros de paginación
        $page = $request->input('page', 1); // Página actual, por defecto 1
        $limit = $request->input('limit', 5); // Límite de elementos por página, por defecto 5
    
        // Obtener los parámetros de filtro y búsqueda
        $idCategoria = $request->input('idCategoria', '');
        $nombreCategoria = $request->input('nombreCategoria', '');
        $descripcion = $request->input('descripcion', '');
        $estado = $request->input('estado', '');
        $searchTerm = $request->input('searchTerm', '');
    
        // Construir la consulta
        $query = Categoria::query();
    
        // Aplicar filtros
        if ($idCategoria) {
            $query->where('idCategoria', 'like', "%{$idCategoria}%");
        }
        if ($nombreCategoria) {
            $query->where('nombreCategoria', 'like', "%{$nombreCategoria}%");
        }
        if ($descripcion) {
            $query->where('descripcion', 'like', "%{$descripcion}%");
        }
        if ($estado) {
            $query->where('estado', 'like', "%{$estado}%");
        }
        if ($searchTerm) {
            $query->where(function ($q) use ($searchTerm) {
                $q->where('idCategoria', 'like', "%{$searchTerm}%")
                  ->orWhere('nombreCategoria', 'like', "%{$searchTerm}%")
                  ->orWhere('descripcion', 'like', "%{$searchTerm}%")
                  ->orWhere('estado', 'like', "%{$searchTerm}%");
            });
        }
    
        // Paginar los resultados
        $categorias = $query->paginate($limit, ['*'], 'page', $page);
    
        return response()->json([
            'data' => $categorias->items(), // Datos de la página actual
            'total' => $categorias->total(), // Total de registros
            'page' => $categorias->currentPage(), // Página actual
            'totalPages' => $categorias->lastPage(), // Total de páginas
        ]);
    }

     public function cambiarEstadoCategoria($id, Request $request)
    {
        // Validar el estado recibido
        $request->validate([
            'estado' => 'required|in:activo,inactivo',
        ]);

        // Buscar la categoría por ID
        $categoria = Categoria::findOrFail($id);

        // Actualizar el estado
        $categoria->estado = $request->estado;
        $categoria->save();

        // Devolver una respuesta exitosa
        return response()->json(['message' => 'Estado actualizado correctamente']);
    }
    public function actualizarCategoria(Request $request, $id)
    {
        Log::info('Iniciando actualización de categoría', [
            'id' => $id,
            'request_all' => $request->all(),
            'files' => $request->hasFile('imagen') ? 'Tiene imagen' : 'No tiene imagen'
        ]);
    
        try {
            $categoria = Categoria::where('idCategoria', $id)->first();
            
            if (!$categoria) {
                Log::error('Categoría no encontrada', ['id' => $id]);
                return response()->json(['error' => 'Categoría no encontrada'], 404);
            }
    
            Log::info('Categoría encontrada', ['categoria' => $categoria]);
    
            $nombreCategoriaAntiguo = $categoria->nombreCategoria;
    
            if ($request->has('nombreCategoria')) {
                $nombreCategoria = trim($request->nombreCategoria);
                $nombreCategoria = preg_replace('/\s+/', ' ', $nombreCategoria);
                $nombreCategoria = preg_replace('/([a-zA-Z])\1{2,}/', '$1$1', $nombreCategoria);
                $nombreCategoria = preg_replace('/s{2,}$/', 's', $nombreCategoria);
    
                $categoria->nombreCategoria = $nombreCategoria;
                Log::info('Actualizando nombre', ['nuevo_nombre' => $nombreCategoria]);
            }
    
            if ($request->has('descripcion')) {
                $categoria->descripcion = $request->descripcion;
                Log::info('Actualizando descripción', ['nueva_descripcion' => $request->descripcion]);
            }
    
            if ($request->hasFile('imagen')) {
                Log::info('Procesando nueva imagen');
                
                $oldFolder = str_replace(' ', '_', $nombreCategoriaAntiguo);
                $newFolder = str_replace(' ', '_', $categoria->nombreCategoria);
                
                $oldBasePath = "imagenes/categorias/{$oldFolder}";
                $newBasePath = "imagenes/categorias/{$newFolder}";
    
                // Eliminar cualquier imagen existente antes de guardar la nueva
                if ($categoria->imagen && Storage::disk('public')->exists($categoria->imagen)) {
                    Storage::disk('public')->delete($categoria->imagen);
                    Log::info('Imagen anterior eliminada', ['path' => $categoria->imagen]);
                }
    
                if ($nombreCategoriaAntiguo !== $categoria->nombreCategoria) {
                    if (Storage::disk('public')->exists($oldBasePath)) {
                        $files = Storage::disk('public')->files($oldBasePath);
                        foreach ($files as $file) {
                            Storage::disk('public')->delete($file);
                            Log::info('Archivo eliminado de carpeta antigua', ['file' => $file]);
                        }
                        Storage::disk('public')->deleteDirectory($oldBasePath);
                        Log::info('Carpeta antigua eliminada', ['path' => $oldBasePath]);
                    }
                    
                    if (!Storage::disk('public')->exists($newBasePath)) {
                        Storage::disk('public')->makeDirectory($newBasePath);
                        Log::info('Nueva carpeta creada', ['path' => $newBasePath]);
                    }
                }
    
                $imagePath = $request->file('imagen')->store("imagenes/categorias/{$newFolder}", 'public');
                $categoria->imagen = $imagePath;
                
                Log::info('Nueva imagen guardada', ['path' => $categoria->imagen]);
            }
            
            $categoria->save();
    
            Log::info('Categoría actualizada exitosamente', [
                'id' => $categoria->idCategoria,
                'nombreCategoria' => $categoria->nombreCategoria,
                'descripcion' => $categoria->descripcion,
                'imagen' => $categoria->imagen
            ]);
    
            return response()->json([
                'message' => 'Categoría actualizada exitosamente',
                'data' => $categoria
            ]);
    
        } catch (\Exception $e) {
            Log::error('Error al actualizar categoría', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
    
            return response()->json([
                'error' => 'Error al actualizar la categoría: ' . $e->getMessage()
            ], 500);
        }
    }
    
       /**
     * @OA\Get(
     *     path="/api/categoriasproductos",
     *     tags={"SUPERADMIN CONTROLLER"},
     *     summary="Listar categorías de productos activas",
     *     description="Permite a un superadministrador obtener una lista de todas las categorías de productos con estado 'activo'.",
     *     operationId="listarCategoriasProductos",
     *     security={{"bearerAuth": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Lista de categorías obtenida exitosamente",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="idCategoria", type="integer", example=1),
     *                 @OA\Property(property="nombreCategoria", type="string", example="Electrónica"),
     *                 @OA\Property(property="estado", type="string", example="activo")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="No autorizado",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="No autorizado")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error interno del servidor",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Error interno del servidor")
     *         )
     *     )
     * )
     */
    public function listarCategoriasProductos()
    {
        // Filtrar categorías con estado "activo"
        $categorias = Categoria::where('estado', 'activo')->get();
        return response()->json($categorias);
    }


      public function getAllOrders()
    {
        $orders = Pedido::with(['usuario', 'pagos', 'detalles.producto'])
            ->where('estado', '<>', 'completado')
            ->get();

        return response()->json(['success' => true, 'orders' => $orders]);
    }

    

    public function updateOrderStatus(Request $request, $idPedido)
    {
        $estado = $request->input('estado');

        $pedido = Pedido::find($idPedido);

        if (!$pedido) {
            return response()->json(['success' => false, 'message' => 'Pedido no encontrado'], 404);
        }

        $pedido->estado = $estado;
        $pedido->save();

        return response()->json(['success' => true, 'message' => 'Estado del pedido actualizado']);
    }

    public function verComprobante($userId, $pagoId, $filename)
    {
        $path = storage_path("pagos/comprobante/{$userId}/{$pagoId}/{$filename}");

        if (!File::exists($path)) {
            abort(404);
        }

        $file = File::get($path);
        $type = File::mimeType($path);

        return Response::make($file, 200)->header("Content-Type", $type);
    }


        public function deleteOrder($idPedido)
        {
            $pedido = Pedido::find($idPedido);

            if (!$pedido) {
                return response()->json(['success' => false, 'message' => 'Pedido no encontrado'], 404);
            }

            // Obtener el usuario asociado al pedido
            $usuario = Usuario::find($pedido->idUsuario);
            if ($usuario) {
                $nombreCompleto = $usuario->nombres . ' ' . $usuario->apellidos;
            }

            // Eliminar los detalles del pedido
            $pedido->detalles()->delete();

            // Eliminar los pagos asociados
            $pedido->pagos()->delete();

            // Eliminar el pedido
            $pedido->delete();

            // Enviar el correo de notificación al usuario
            if ($usuario) {
                Mail::to($usuario->correo)->send(new NotificacionPedidoEliminado(
                    $nombreCompleto,
                    $idPedido
                ));
            }

            return response()->json(['success' => true, 'message' => 'Pedido eliminado correctamente']);
        }

         // Obtener el estado de la facturación electrónica
    public function getEstadoFacturacion()
    {
        $facturacion = Facturacion::first(); // Obtener el primer registro
        if (!$facturacion) {
            // Si no existe, crear un registro por defecto
            $facturacion = Facturacion::create(['status' => 0]);
        }
        return response()->json([
            'success' => true,
            'activa' => $facturacion->status == 1,
        ]);
    }

    // Cambiar el estado de la facturación electrónica
    public function toggleFacturacionElectronica(Request $request)
    {
        $request->validate([
            'activa' => 'required|boolean',
        ]);

        $facturacion = Facturacion::first();
        if (!$facturacion) {
            // Si no existe, crear un registro por defecto
            $facturacion = Facturacion::create(['status' => 0]);
        }

        $facturacion->status = $request->activa ? 1 : 0;
        $facturacion->save();

        return response()->json([
            'success' => true,
            'activa' => $facturacion->status == 1,
        ]);
    }

    public function obtenerMetodoPago()
    {
        try {
            // Obtener el método de pago activo
            $metodoPago = DB::table('tipo_pago')
                ->where('status', 1)
                ->first();

            if (!$metodoPago) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontró un método de pago activo.',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'metodo' => $metodoPago->nombre,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener el método de pago.',
            ], 500);
        }
    }

    public function actualizarMetodoPago(Request $request)
    {
        $request->validate([
            'metodo' => 'required|in:mercadopago,comprobante',
            'status' => 'required|boolean',
        ]);

        DB::beginTransaction();
        try {
            // Desactivar todos los métodos de pago
            DB::table('tipo_pago')->update(['status' => 0]);

            // Activar el método de pago seleccionado
            DB::table('tipo_pago')
                ->where('nombre', $request->metodo)
                ->update(['status' => $request->status]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Método de pago actualizado correctamente.',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el método de pago.',
            ], 500);
        }
    }


    //FUNCIONES PARA REPORTES

    public function totalIngresos()
    {
        $totalVentas = DB::table('pagos')
            ->where('estado_pago', 'completado')
            ->sum('monto');

        return response()->json(['totalVentas' => $totalVentas], 200);
    }


    public function totalPedidosCompletados()
    {
        $totalPedidos = DB::table('pedidos')
            ->where('estado', 'completado')
            ->count();

        return response()->json(['totalPedidos' => $totalPedidos], 200);
    }


    public function totalClientes()
    {
        $totalClientes = DB::table('usuarios')
            ->where('rol', 'cliente')
            ->count();

        return response()->json(['totalClientes' => $totalClientes], 200);
    }

    public function totalProductos()
    {
        $totalProductos = DB::table('productos')->count();

        return response()->json(['totalProductos' => $totalProductos], 200);
    }

    public function productosBajoStock()
    {
        $productos = DB::table('productos')
            ->where('stock', '<', 10)
            ->select('nombreProducto', 'stock')
            ->get();

        return response()->json(['productosBajoStock' => $productos], 200);
    }

    public function obtenerPagosCompletados()
    {
        $cantidadPagosCompletados = Pago::where('estado_pago', 'completado')->count();
        
        return response()->json([
            'cantidadPagosCompletados' => $cantidadPagosCompletados,
        ]);
    }


    public function obtenerCantidadPedidosAdmin(Request $request)
    {
        // Filtra los pedidos que no estén en estado 'completado'
        $cantidadPedidos = DB::table('pedidos')
            ->whereIn('estado', ['pendiente', 'aprobando', 'en preparacion', 'enviado'])
            ->count();

        return response()->json(['success' => true, 'cantidad' => $cantidadPedidos]);
    }


    public function pedidosPorMes()
    {
        $pedidos = Pedido::selectRaw('MONTH(fecha_pedido) as mes, COUNT(*) as cantidad')
            ->groupBy('mes')
            ->orderBy('mes')
            ->get();

        return response()->json($pedidos);
    }

    public function ingresosPorMes()
    {
        $ingresos = Pago::where('estado_pago', 'completado')
            ->selectRaw('MONTH(fecha_pago) as mes, SUM(monto) as total_ingresos')
            ->groupBy('mes')
            ->orderBy('mes')
            ->get();

        return response()->json($ingresos);
    }
    

   
    // Función para agregar un log directamente desde el backend
    public function agregarLog($usuarioId, $accion)
    {
        // Obtener el usuario por id
        $usuario = Usuario::find($usuarioId);

        if ($usuario) {
            // Crear el log
            $log = LogUser::create([
                'idUsuario' => $usuario->idUsuario,
                'nombreUsuario' => $usuario->nombres . ' ' . $usuario->apellidos,
                'rol' => $usuario->rol,
                'accion' => $accion,
                'fecha' => now(),
            ]);

            return response()->json(['message' => 'Log agregado correctamente', 'log' => $log], 200);
        }

        return response()->json(['message' => 'Usuario no encontrado'], 404);
    }

}
