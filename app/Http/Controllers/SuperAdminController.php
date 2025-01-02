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
            'imagen' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:5000', // Validación de imagen
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
