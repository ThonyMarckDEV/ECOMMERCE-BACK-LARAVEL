<?php

use App\Http\Controllers\AdminController;

use App\Http\Controllers\ClienteController;
use App\Http\Controllers\PaymentController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\SuperAdminController;

//RUTAS
//================================================================================================
        //RUTAS  AUTH

        //RUTA PARA QUE LOS USUAIOS SE LOGEEN POR EL CONTROLLADOR AUTHCONTROLLER
        Route::post('login', [AuthController::class, 'login']);

        // Ruta para el login con Google
        Route::post('/login-google', [AuthController::class, 'loginWithGoogle']);
        
        Route::post('/registerUser', [AuthController::class, 'registerUser']);

        Route::post('/registerUserGoogle', [AuthController::class, 'registerUserGoogle']);

        Route::post('/send-message', [AuthController::class, 'sendContactEmail']);

        Route::post('/send-verification-codeUser', [AuthController::class, 'sendVerificationCodeUser']);

        Route::post('/verify-codeUser', [AuthController::class, 'verifyCodeUser']);
        
        Route::post('/change-passwordUser', [AuthController::class, 'changePasswordUser']);

        //PARA HOME

        Route::get('productos', [ClienteController::class, 'listarProductos']);

        Route::get('/listarCategorias', [ClienteController::class, 'listarCategorias']);

        // Ruta para verificar el token de correo
        Route::post('verificar-token', [AuthController::class, 'verificarCorreo']);
        
        Route::get('/status-mantenimiento', [AuthController::class, 'getStatus']);

        Route::post('/webhook/mercadopago', [PaymentController::class, 'recibirPago']);

        Route::post('actualizar-comprobante', [PaymentController::class, 'actualizarComprobante']);

//================================================================================================
    //RUTAS  AUTH PROTEGIDAS par todos los roles

    Route::middleware(['auth.jwt', 'checkRolesMW'])->group(function () {

        Route::post('refresh-token', [AuthController::class, 'refreshToken']);

        Route::post('logout', [AuthController::class, 'logout']);

        Route::post('update-activity', [AuthController::class, 'updateLastActivity']);

        Route::post('/check-status', [AuthController::class, 'checkStatus']);

    });


//================================================================================================
    //RUTAS PROTEGIDAS A
    // RUTAS PARA ADMINISTRADOR VALIDADA POR MIDDLEWARE AUTH (PARA TOKEN JWT) Y CHECKROLE (PARA VALIDAR ROL DEL TOKEN)
    Route::middleware(['auth.jwt', 'checkRoleMW:superadmin'])->group(function () { 

        // Listar usuarios
        Route::get('/listarUsuariosAdmin', [SuperAdminController::class, 'listarUsuarios']);
        // Editar usuario
        Route::put('/listarUsuariosAdmin/{id}', [SuperAdminController::class, 'editarUsuario']);
        // Cambiar estado de usuario (activo/inactivo)
        Route::patch('/listarUsuariosAdmin/{id}/cambiar-estado', [SuperAdminController::class, 'cambiarEstado']);

         // Obtener el estado de la facturación electrónica
        Route::get('/configuracion/facturacion-electronica', [SuperAdminController::class, 'getEstadoFacturacion']);
        // Cambiar el estado de la facturación electrónica
        Route::put('/configuracion/facturacion-electronica', [SuperAdminController::class, 'toggleFacturacionElectronica']);


        Route::post('register', [SuperAdminController::class, 'register']);
        Route::post('adminAgregar', [SuperAdminController::class, 'agregarUsuario']);
    
        Route::put('/actualizarUsuario/{id}', [SuperAdminController::class, 'actualizarUsuario']);
        Route::delete('/eliminarUsuario/{id}', [SuperAdminController::class, 'eliminarUsuario']);

        Route::post('/agregarProducto', [SuperAdminController::class, 'agregarProducto']);
        Route::post('/actualizarProducto/{id}', [SuperAdminController::class, 'actualizarProducto']);
        Route::delete('/eliminarProducto/{id}', [SuperAdminController::class, 'eliminarProducto']);

        Route::post('/categorias', [SuperAdminController::class, 'agregarCategorias']);
        Route::post('/actualizarCategoria/{id}', [SuperAdminController::class, 'actualizarCategoria']);
        Route::delete('/eliminarCategoria/{id}', [SuperAdminController::class, 'eliminarCategoria']);
        Route::get('/obtenerCategorias', [SuperAdminController::class, 'obtenerCategorias']);
        Route::put('/cambiarEstadoCategoria/{id}', [SuperAdminController::class, 'cambiarEstadoCategoria']);

        Route::get('/obtenerTallas', [SuperAdminController::class, 'obtenerTallas']);
        Route::post('/agregarTalla', [SuperAdminController::class, 'agregarTalla']);
        Route::put('/editarTalla/{id}', [SuperAdminController::class, 'editarTalla']);
   
        Route::get('/admin/pedidos', [SuperAdminController::class, 'getAllOrders']);

        Route::put('/admin/pedidos/{idPedido}', [SuperAdminController::class, 'updateOrderStatus']);
        Route::delete('/admin/pedidos/{idPedido}', [SuperAdminController::class, 'deleteOrder']);
        Route::get('/pagos/comprobante/{userId}/{pagoId}/{filename}', [SuperAdminController::class, 'verComprobante']);

        Route::get('/obtenerDireccionPedido/{idPedido}', [SuperAdminController::class, 'obtenerDireccionPedido']);


        Route::get('/reportes/total-ingresos', [SuperAdminController::class, 'totalIngresos']);
        Route::get('/reportes/total-pedidos-completados', [SuperAdminController::class, 'totalPedidosCompletados']);
        Route::get('/reportes/total-clientes', [SuperAdminController::class, 'totalClientes']);
        Route::get('/reportes/total-productos', [SuperAdminController::class, 'totalProductos']);
        Route::get('/reportes/productos-bajo-stock', [SuperAdminController::class, 'productosBajoStock']);
        Route::get('/reportes/pagos-completados', [SuperAdminController::class, 'obtenerPagosCompletados']);
        Route::get('/reportes/pedidos-por-mes', [SuperAdminController::class, 'pedidosPorMes']);
        Route::get('/reportes/ingresos-por-mes', [SuperAdminController::class, 'ingresosPorMes']);


        Route::post('/admin/pedidos/cantidad', [SuperAdminController::class, 'obtenerCantidadPedidosAdmin']);

        

        Route::get('listarUsuarios', [SuperAdminController::class, 'listarUsuarios']);

        Route::get('/listarProductos', [SuperAdminController::class, 'listarProductos']);
    });


    // RUTAS PARA CLIENTE VALIDADA POR MIDDLEWARE AUTH (PARA TOKEN JWT) Y CHECKROLE (PARA VALIDAR ROL DEL TOKEN)
    Route::middleware(['auth.jwt', 'checkRoleMW:cliente'])->group(function () {
        Route::get('validate-dni/{numero}', [ClienteController::class, 'validateDNI']);

        Route::get('perfilCliente', [ClienteController::class, 'perfilCliente']);
        Route::post('uploadProfileImageCliente/{idUsuario}', [ClienteController::class, 'uploadProfileImageCliente']);
        Route::put('updateCliente/{idUsuario}', [ClienteController::class, 'updateCliente']);
        

        // Ruta para agregar un producto al carrito
        Route::post('agregarCarrito', [ClienteController::class, 'agregarAlCarrito']);
        Route::post('carrito', [ClienteController::class, 'listarCarrito']); // Listar productos en el carrito
        Route::put('carrito_detalle/{idDetalle}', [ClienteController::class, 'actualizarCantidadCarrito']); // Actualizar cantidad de producto
        Route::delete('carrito_detalle/{idDetalle}', [ClienteController::class, 'eliminarProductoCarrito']); // Eliminar producto del carrito
        Route::get('/carrito/cantidad', [ClienteController::class, 'obtenerCantidadCarrito']);
      
      
        // Ruta para procesar el pago de un pedido
        Route::post('/procesar-pago/{idPedido}', [ClienteController::class, 'procesarPago']);
    
    
        // Ruta para listar pedidos de un usuario
        Route::get('/obtenerDireccionPedidoUser/{idPedido}', [ClienteController::class, 'obtenerDireccionPedido']);
        Route::get('/listarDireccion/{idUsuario}', [ClienteController::class, 'listarDireccion']);
        Route::get('/listarDireccionPedido/{idUsuario}', [ClienteController::class, 'listarDireccionPedido']);

        Route::post('/agregarDireccion', [ClienteController::class, 'agregarDireccion']);
        Route::delete('/eliminarDireccion/{id}', [ClienteController::class, 'eliminarDireccion']);
        Route::put('/setDireccionUsando/{idDireccion}', [ClienteController::class, 'setDireccionUsando']);


        Route::post('/pedido', [ClienteController::class, 'crearPedido']);
        Route::post('/pedidos/cantidad', [ClienteController::class, 'obtenerCantidadPedidos']);
        Route::delete('/cancelarPedido', [ClienteController::class, 'cancelarPedido']);
        Route::get('/pedidos/{idUsuario}', [ClienteController::class, 'listarPedidos']);

        Route::post('/enviarCodigo/{idUsuario}', [ClienteController::class, 'enviarCodigo']);
        Route::post('/verificarCodigo/{idUsuario}', [ClienteController::class, 'verificarCodigo']);
        Route::post('/cambiarContrasena', [ClienteController::class, 'cambiarContrasena']);

        

        Route::post('/payment/preference', [PaymentController::class, 'createPreference']);

        Route::get('/pedidos-completos/{idUsuario}', [ClienteController::class, 'getPedidosCompletos']);

        Route::get('/productos-mas-comprados', [ClienteController::class, 'getProductosMasComprados']);
 
    });



//================================================================================================

