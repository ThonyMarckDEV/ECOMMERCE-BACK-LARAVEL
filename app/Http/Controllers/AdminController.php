<?php

namespace App\Http\Controllers;

use App\Models\Talla;
use App\Models\Usuario;
use App\Models\Categoria;
use App\Models\Producto;
use App\Models\Carrito;
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
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    // // FUNCION PARA REGISTRAR UN USUARIO
    // public function register(Request $request)
    // {
    //     $messages = [
    //         'username.required' => 'El nombre de usuario es obligatorio.',
    //         'username.unique' => 'El nombre de usuario ya está en uso.',
    //         'rol.required' => 'El rol es obligatorio.',
    //         'nombres.required' => 'El nombre es obligatorio.',
    //         'apellidos.required' => 'Los apellidos son obligatorios.',
    //         'apellidos.regex' => 'Debe ingresar al menos dos apellidos separados por un espacio.',
    //         'dni.required' => 'El DNI es obligatorio.',
    //         'dni.size' => 'El DNI debe tener exactamente 8 caracteres.',
    //         'dni.unique' => 'El DNI ya está registrado.',
    //         'correo.required' => 'El correo es obligatorio.',
    //         'correo.email' => 'El correo debe tener un formato válido.',
    //         'correo.unique' => 'El correo ya está registrado.',
    //         'edad.integer' => 'La edad debe ser un número entero.',
    //         'edad.between' => 'La edad debe ser mayor a 18.',
    //         'nacimiento.date' => 'La fecha de nacimiento debe ser una fecha válida.',
    //         'nacimiento.before' => 'La fecha de nacimiento debe ser anterior a hoy.',
    //         'telefono.size' => 'El numero e telefono debe de ser de 9 digitos.',
    //         'password.required' => 'La contraseña es obligatoria.',
    //         'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
    //         'password.regex' => 'La contraseña debe incluir al menos una mayúscula y un símbolo.',
    //         'password.confirmed' => 'Las contraseñas no coinciden.',
    //     ];

    //     $validator = Validator::make($request->all(), [
    //         'username' => 'required|string|max:255|unique:usuarios',
    //         'rol' => 'required|string|max:255',
    //         'nombres' => 'required|string|max:255',
    //         'apellidos' => [
    //             'required',
    //             'regex:/^[a-zA-ZÀ-ÿ]+(\s[a-zA-ZÀ-ÿ]+)+$/'
    //         ],
    //         'dni' => 'required|string|size:8|unique:usuarios',
    //         'correo' => 'required|string|email|max:255|unique:usuarios',
    //         'edad' => 'nullable|integer|between:18,100',
    //         'nacimiento' => 'nullable|date|before:today',
    //         'telefono' => 'nullable|string|size:9|regex:/^\d{9}$/',
    //         'departamento' => 'nullable|string|max:255',
    //         'password' => [
    //             'required',
    //             'string',
    //             'min:8',
    //             'max:255',
    //             'regex:/^(?=.*[A-Z])(?=.*[!@#$%^&*(),.?":{}|<>])[A-Za-z\d!@#$%^&*(),.?":{}|<>]{8,}$/',
    //         ]
    //     ], $messages);

    //     if ($validator->fails()) {
    //         return response()->json([
    //             'success' => false,
    //             'errors' => $validator->errors(),
    //         ], 400);
    //     }

    //     try {
    //         // Registrar el usuario
    //         $user = Usuario::create([
    //             'username' => $request->username,
    //             'rol' => $request->rol,
    //             'nombres' => $request->nombres,
    //             'apellidos' => $request->apellidos,
    //             'dni' => $request->dni,
    //             'correo' => $request->correo,
    //             'edad' => $request->edad ?? null,
    //             'nacimiento' => $request->nacimiento ?? null,
    //             'telefono' => $request->telefono ?? null,
    //             'departamento' => $request->departamento ?? null,
    //             'password' => bcrypt($request->password),
    //             'status' => 'loggedOff',
    //         ]);

    //         // Crear el carrito asociado al usuario recién registrado
    //         $carrito = new Carrito();
    //         $carrito->idUsuario = $user->idUsuario; // Asignar el id del usuario al carrito
    //         $carrito->save(); // Guardar el carrito

    //         // Devolver la respuesta con éxito
    //         return response()->json([
    //             'success' => true,
    //             'message' => 'Usuario registrado y carrito creado exitosamente',
    //         ], 201);

    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Error al registrar el usuario y crear el carrito',
    //             'error' => $e->getMessage(),
    //         ], 500);
    //     }
    // }

    
    // // Listar usuarios
    // // public function listarUsuarios()
    // // {
    // //     $usuarios = Usuario::select('idUsuario', 'username', 'rol', 'correo')
    // //                 ->where('rol', '!=', 'admin') // Excluir usuarios con rol "admin"
    // //                 ->get();
    // //     return response()->json(['success' => true, 'data' => $usuarios]);
    // // }

    public function listarUsuarios(Request $request)
    {
        // Decodificar el token JWT para obtener el usuario autenticado
        $usuarioAutenticado = auth()->user();
    
        // Verificar si el usuario autenticado existe
        if (!$usuarioAutenticado) {
            return response()->json(['success' => false, 'message' => 'Usuario no autenticado.'], 401);
        }
    
        // Obtener el username y el rol del usuario autenticado
        $username = $usuarioAutenticado->username;
        $rolAutenticado = $usuarioAutenticado->rol;
    
        // Verificar el rol y filtrar usuarios
        if ($rolAutenticado === 'admin') {
            if ($username === 'admin') {
                // Si el username es 'admin', listar usuarios con rol 'admin' y 'cliente' pero omitiendo el 'admin' en la lista
                $usuarios = Usuario::select('idUsuario', 'username', 'rol', 'correo')
                            ->whereIn('rol', ['admin', 'cliente'])
                            ->where('username', '!=', 'admin') // Excluir el usuario con username 'admin'
                            ->get();
            } else {
                // Si el username no es 'admin', listar solo usuarios con rol 'cliente', omitiendo 'admin'
                $usuarios = Usuario::select('idUsuario', 'username', 'rol', 'correo')
                            ->where('rol', 'cliente')
                            ->where('username', '!=', 'admin') // Excluir el usuario con username 'admin'
                            ->get();
            }
        } else {
            // No tiene permiso para listar usuarios
            return response()->json(['success' => false, 'message' => 'No tiene permiso para realizar esta acción.'], 403);
        }
    
        // Retornar la lista de usuarios
        return response()->json(['success' => true, 'data' => $usuarios]);
    }

    // Eliminar usuario
    public function eliminarUsuario($id)
    {
        $usuario = Usuario::find($id);
        if ($usuario) {
            $usuario->delete();
            return response()->json(['success' => true, 'message' => 'Usuario eliminado correctamente']);
        }
        return response()->json(['success' => false, 'message' => 'Usuario no encontrado'], 404);
    }

    // Actualizar usuario
    public function actualizarUsuario(Request $request, $id)
    {
        $usuario = Usuario::find($id);
        if ($usuario) {
            $usuario->update($request->only('username', 'rol', 'correo'));
            return response()->json(['success' => true, 'message' => 'Usuario actualizado correctamente']);
        }
        return response()->json(['success' => false, 'message' => 'Usuario no encontrado'], 404);
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


    // Listar todos los productos con el nombre de la categoría y URL completa de la imagen
    public function listarProductos()
    {
        $productos = Producto::with('categoria:idCategoria,nombreCategoria')->get();

        // Mapeo para agregar el nombre de la categoría y la URL completa de la imagen
        $productos = $productos->map(function ($producto) {
            return [
                'idProducto' => $producto->idProducto,
                'nombreProducto' => $producto->nombreProducto,
                'descripcion' => $producto->descripcion,
                'precio' => $producto->precio,
                'stock' => $producto->stock,
                'imagen' => $producto->imagen ? url("storage/{$producto->imagen}") : null, // URL completa de la imagen
                'idCategoria' => $producto->idCategoria,
                'nombreCategoria' => $producto->categoria ? $producto->categoria->nombreCategoria : null,
            ];
        });

        return response()->json(['success' => true, 'data' => $productos], 200);
    }

    // Crear un nuevo producto
    public function agregarProducto(Request $request)
    {
        // Validar los datos de entrada, incluyendo el tipo de archivo de imagen
        $request->validate([
            'nombreProducto' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'precio' => 'required|numeric',
            'stock' => 'required|integer',
            'imagen' => 'nullable|mimes:jpeg,jpg,png,gif|max:2048', // Solo formatos de imagen permitidos
            'idCategoria' => 'required|exists:categorias,idCategoria',
        ]);

        // Crear un nuevo producto sin la imagen
        $productoData = $request->except('imagen');

        // Guardar la imagen si se proporciona
        if ($request->hasFile('imagen')) {
            $path = $request->file('imagen')->store('imagenes', 'public');
            $productoData['imagen'] = $path;
        }

        // Crear el producto con los datos obtenidos
        $producto = Producto::create($productoData);

        return response()->json([
            'success' => true, 
            'message' => 'Producto creado exitosamente', 
            'data' => $producto
        ], 201);
    }

        // Actualizar un producto
        public function actualizarProducto(Request $request, $id)
        {
            // Validación de los datos entrantes, incluyendo los tipos de archivo de imagen
            $request->validate([
                'nombreProducto' => 'required|string|max:255',
                'descripcion' => 'nullable|string',
                'precio' => 'required|numeric',
                'stock' => 'required|integer',
                'imagen' => 'nullable|mimes:jpeg,jpg,png,gif|max:2048', // Solo formatos de imagen permitidos
                'idCategoria' => 'required|exists:categorias,idCategoria',
            ]);

            // Buscar el producto por ID
            $producto = Producto::findOrFail($id);

            // Procesar la nueva imagen si se proporciona
            if ($request->hasFile('imagen')) {
                // Eliminar la imagen anterior si existe
                if ($producto->imagen && Storage::disk('public')->exists($producto->imagen)) {
                    Storage::disk('public')->delete($producto->imagen);
                }

                // Guardar la nueva imagen y actualizar la ruta en el producto
                $path = $request->file('imagen')->store('imagenes', 'public');
                $producto->imagen = $path;
            }

            // Actualizar otros campos del producto
            $producto->nombreProducto = $request->nombreProducto;
            $producto->descripcion = $request->descripcion;
            $producto->precio = $request->precio;
            $producto->stock = $request->stock;
            $producto->idCategoria = $request->idCategoria;
            
            // Guardar los cambios
            $producto->save();

            return response()->json([
                'success' => true, 
                'message' => 'Producto actualizado exitosamente', 
                'data' => $producto
            ], 200);
        }


    // Eliminar un producto
    public function eliminarProducto($id)
    {
        $producto = Producto::findOrFail($id);
        $producto->delete();
        return response()->json(['success' => true, 'message' => 'Producto eliminado exitosamente'], 200);
    }


    public function agregarCategorias(Request $request)
    {
        // Validar los datos de entrada
        $request->validate([
            'nombreCategoria' => 'required|string|max:255',
            'descripcion' => 'nullable|string|max:60',
            'imagen' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048', // Validación de imagen
        ]);

        // Obtener el nombre de la categoría
        $nombreCategoria = $request->input('nombreCategoria');
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
        ]);

        return response()->json([
            'message' => 'Categoría agregada exitosamente',
            'categoria' => $categoria
        ], 200);
    }

  
    
    // Obtener las 8 primeras categorías con estado "activo" para el home principal
    public function listarCategorias()
    {
        // Filtrar las categorías por estado "activo" y obtener las primeras 8
        $categorias = Categoria::where('estado', 'activo')
                            ->take(8)
                            ->get();

        // Devolver las categorías como JSON con un mensaje de éxito
        return response()->json(['success' => true, 'data' => $categorias], 200);
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
    

   

}
