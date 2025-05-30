<?php

namespace App\Http\Controllers;

use App\Models\Usuario;
use App\Models\Log as LogUser;
use Illuminate\Http\Request;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use App\Models\Carrito;
use App\Mail\VerificarCorreo;
use Illuminate\Support\Str;
use Exception;
use App\Mail\CuentaVerificada;
use App\Models\ActividadUsuario;
use Illuminate\Support\Facades\DB;
use Google_Client;
use Laravel\Socialite\Facades\Socialite;

/**
* @OA\Info(
*    title="ECOMMERCE API DOCUMENTATION", 
*    version="1.0",
*    description="API DOCUMENTATION"
* )
*
* @OA\Server(url="https://ecommerceback.thonymarckdev.online")
*/
class AuthController extends Controller
{
   /**
     * Login de usuario
     * 
     * Este endpoint permite a los usuarios autenticarse en el sistema utilizando su correo electrónico y contraseña.
     * Si las credenciales son válidas, se genera un token JWT que el usuario puede utilizar para acceder a otros endpoints protegidos.
     * Además, se registra la actividad del usuario y se actualiza su estado a "loggedOn".
     *
     * @OA\Post(
     *     path="/api/login",
     *     tags={"AUTH CONTROLLER"},
     *     summary="Login de usuario",
     *     description="Permite a los usuarios autenticarse en el sistema y obtener un token JWT.",
     *     operationId="login",
     *     @OA\RequestBody(
     *         required=true,
     *         description="Credenciales del usuario",
     *         @OA\JsonContent(
     *             required={"correo","password"},
     *             @OA\Property(property="correo", type="string", format="email", example="usuario@dominio.com"),
     *             @OA\Property(property="password", type="string", format="password", example="contraseña123")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Token generado con éxito",
     *         @OA\JsonContent(
     *             @OA\Property(property="token", type="string", example="eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Datos de entrada inválidos",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="El campo correo es requerido.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Credenciales inválidas",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Credenciales inválidas")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Usuario inactivo",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Usuario inactivo. Por favor, contacte al administrador.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Usuario no encontrado",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Usuario no encontrado")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error al generar el token",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="No se pudo crear el token")
     *         )
     *     )
     * )
     */
    public function login(Request $request)
    {
        $request->validate([
            'correo' => 'required|email',
            'password' => 'required|string|min:6',
        ]);

        $credentials = [
            'correo' => $request->input('correo'),
            'password' => $request->input('password')
        ];

        try {
            $usuario = Usuario::where('correo', $credentials['correo'])->first();

            if (!$usuario) {
                return response()->json(['error' => 'Usuario no encontrado'], 404);
            }

            if ($usuario->estado === 'eliminado') {
                return response()->json(['error' => 'Usuario eliminado'], 403);
            }

            // Generar nuevo token
            if (!$token = JWTAuth::attempt(['correo' => $credentials['correo'], 'password' => $credentials['password']])) {
                return response()->json(['error' => 'Credenciales inválidas'], 401);
            }

            // IMPORTANTE: Primero invalidamos todas las sesiones existentes
            ActividadUsuario::where('idUsuario', $usuario->idUsuario)->delete();

            $dispositivo = $this->obtenerDispositivo();

            // Crear una nueva sesión con un id único
            $actividad = ActividadUsuario::create([
                'idUsuario' => $usuario->idUsuario,
                'last_activity' => now(),
                'dispositivo' => $dispositivo,
                'jwt' => $token,
                'session_active' => true
            ]);

            // Actualizar estado del usuario
            $usuario->update(['status' => 'loggedOn']);

            return response()->json([
                'token' => $token,
                'sessionId' => $actividad->id, // Enviar el id de la sesión al frontend
                'message' => 'Login exitoso, sesiones anteriores cerradas'
            ]);

        } catch (JWTException $e) {
            Log::error('Error en login: ' . $e->getMessage());
            return response()->json(['error' => 'Error al crear token'], 500);
        }
    }
    
    // Función para obtener el dispositivo
    private function obtenerDispositivo()
    {
        return request()->header('User-Agent');  // Obtiene el User-Agent del encabezado de la solicitud
    }



    public function checkSessionActive(Request $request)
    {
        try {
            $idUsuario = $request->input('idUsuario');
            $sessionId = $request->input('sessionId'); // sessionId enviado desde el frontend
    
            // Obtener la sesión activa del usuario
            $actividad = ActividadUsuario::where('idUsuario', $idUsuario)
                ->where('session_active', true)
                ->first();
    
            // Verificar si la sesión activa coincide con el sessionId enviado
            $validSession = $actividad && $actividad->id == $sessionId;
    
            return response()->json([
                'validSession' => $validSession
            ], 200);
    
        } catch (\Exception $e) {
            Log::error('Error en checkActiveSession: ' . $e->getMessage());
            return response()->json(['error' => 'Error al verificar la sesión activa'], 500);
        }
    }
    
    
  
    public function refreshToken(Request $request)
    {
        try {
            $oldToken = JWTAuth::getToken();  // Obtener el token actual
            
            Log::info('Refrescando token: Token recibido', ['token' => (string) $oldToken]);
            
            // Decodificar el token para obtener el payload
            $decodedToken = JWTAuth::getPayload($oldToken);  // Utilizamos getPayload para obtener el payload
            $userId = $decodedToken->get('idUsuario');  // Usamos get() para acceder a 'idUsuario'
            
            // Refrescar el token
            $newToken = JWTAuth::refresh($oldToken);
            
            // Actualizar el campo jwt en la tabla actividad_usuario
            $actividadUsuario = ActividadUsuario::updateOrCreate(
                ['idUsuario' => $userId],  // Si ya existe, se actualizará por el idUsuario
                ['jwt' => $newToken]  // Actualizar el campo jwt con el nuevo token
            );
            
           Log::info('JWT actualizado en la actividad del usuario', ['userId' => $userId, 'jwt' => $newToken]);
            
            return response()->json(['accessToken' => $newToken], 200);
        } catch (JWTException $e) {
            Log::error('Error al refrescar el token', ['error' => $e->getMessage()]);
            
            return response()->json(['error' => 'No se pudo refrescar el token'], 500);
        }
    }



    public function updateLastActivity(Request $request)
    {
        $request->validate([
            'idUsuario' => 'required|integer',
        ]);

        $user = Usuario::find($request->idUsuario);
        
        if (!$user) {
            return response()->json(['error' => 'Usuario no encontrado'], 404);
        }
        
        $user->activity()->updateOrCreate(
            ['idUsuario' => $user->idUsuario],
            ['last_activity' => now()]
        );
        
        return response()->json(['message' => 'Last activity updated'], 200);
    }

    public function loginWithGoogle(Request $request)
    {
        $request->validate([
            'googleToken' => 'required|string',
        ]);

        try {
            // Verificar el token de Google y obtener los datos del usuario
            $googleToken = $request->input('googleToken');
            $googleUser = $this->getGoogleUser($googleToken);

            if (!$googleUser) {
                return response()->json(['error' => 'Token de Google inválido o expirado'], 401);
            }

            // Verificar si el usuario ya existe en la base de datos
            $usuario = Usuario::where('correo', $googleUser['email'])->first();

            if (!$usuario) {
                // Si el usuario no existe, crear uno nuevo
                $usuario = Usuario::create([
                    'correo' => $googleUser['email'],
                    'username' => $googleUser['given_name'] . $googleUser['family_name'],
                    'rol' => 'cliente',
                    'nombres' => $googleUser['given_name'],
                    'apellidos' => $googleUser['family_name'],
                    'dni' => null, // Establecer un valor por defecto o generar uno
                    'password' => bcrypt(Str::random(16)), // Genera una contraseña aleatoria
                    'status' => 'loggedOn',
                    'emailVerified' => 1, // Establecer email_verified como 1 para usuarios de Google
                ]);
            }

            if ($usuario->estado === 'inactivo') {
                return response()->json(['error' => 'Usuario inactivo'], 403);
            }

            // Generar el token JWT para el usuario
            $token = JWTAuth::fromUser($usuario);

            // IMPORTANTE: Primero invalidamos todas las sesiones existentes
            ActividadUsuario::where('idUsuario', $usuario->idUsuario)->delete();

            $dispositivo = $this->obtenerDispositivo();

            $actividad = ActividadUsuario::create([
                'idUsuario' => $usuario->idUsuario,
                'last_activity' => now(),
                'dispositivo' => $dispositivo,
                'jwt' => $token,
                'session_active' => true
            ]);

            // Actualizar estado del usuario
            $usuario->update(['status' => 'loggedOn']);

            // Log de la acción
            $nombreUsuario = $usuario->nombre . ' ' . $usuario->apellidos;
            $this->agregarLog($usuario->idUsuario, "$nombreUsuario inició sesión con Google desde: $dispositivo");

            return response()->json([
                'token' => $token,
                'sessionId' => $actividad->id, // Enviar el id de la sesión al frontend
                'message' => 'Login con Google exitoso, sesiones anteriores cerradas'
            ]);

        } catch (\Exception $e) {
            Log::error('Error en loginWithGoogle: ' . $e->getMessage());
            return response()->json(['error' => 'Error en el inicio de sesión con Google'], 500);
        }
    }


    /**
     * Función para verificar el token de Google
     */
    private function getGoogleUser($token)
    {
        try {
            // Verificar el token con la API de Google
            $client = new \Google_Client(['client_id' => env('GOOGLE_CLIENT_ID')]); // Debes configurar tu client_id
            $payload = $client->verifyIdToken($token);

            // Si el token es válido, devolver los datos del usuario
            if ($payload) {
                return [
                    'email' => $payload['email'],
                    'given_name' => $payload['given_name'],
                    'family_name' => $payload['family_name'],
                ];
            }

            return null; // Si el token no es válido
        } catch (\Exception $e) {
            return null;
        }
    }





        /**
         * Registro de usuario
         * 
         * Esta API permite registrar un nuevo usuario, validando la entrada y asegurando que los datos sean correctos, como el correo, DNI y nombre de usuario.
         * 
         * @OA\Post(
         *     path="/api/registerUser",
         *     tags={"AUTH CONTROLLER"},
         *     summary="Registrar un nuevo usuario",
         *     @OA\RequestBody(
         *         required=true,
         *         @OA\JsonContent(
         *             @OA\Property(property="username", type="string", example="usuario123"),
         *             @OA\Property(property="rol", type="string", example="cliente"),
         *             @OA\Property(property="nombres", type="string", example="Juan"),
         *             @OA\Property(property="apellidos", type="string", example="Pérez Gómez"),
         *             @OA\Property(property="dni", type="string", example="12345678"),
         *             @OA\Property(property="correo", type="string", example="usuario@dominio.com"),
         *             @OA\Property(property="edad", type="integer", example=25),
         *             @OA\Property(property="nacimiento", type="string", format="date", example="1998-05-15"),
         *             @OA\Property(property="telefono", type="string", example="987654321"),
         *             @OA\Property(property="departamento", type="string", example="Lima"),
         *             @OA\Property(property="password", type="string", example="Contraseña123!")
         *         )
         *     ),
         *     @OA\Response(
         *         response=201,
         *         description="Usuario registrado con éxito y carrito creado",
         *         @OA\JsonContent(
         *             @OA\Property(property="success", type="boolean", example=true),
         *             @OA\Property(property="message", type="string", example="Usuario registrado y carrito creado exitosamente, Verifica tu correo.")
         *         )
         *     ),
         *     @OA\Response(
         *         response=400,
         *         description="Error en la validación de los datos proporcionados",
         *         @OA\JsonContent(
         *             @OA\Property(property="errors", type="object", additionalProperties={}),
         *         )
         *     ),
         *     @OA\Response(
         *         response=409,
         *         description="El correo o DNI ya está registrado",
         *         @OA\JsonContent(
         *             @OA\Property(property="errors", type="object",
         *                 @OA\Property(property="correo", type="string", example="El correo ya está registrado."),
         *                 @OA\Property(property="dni", type="string", example="El DNI ya está registrado.")
         *             )
         *         )
         *     ),
         *     @OA\Response(
         *         response=500,
         *         description="Error interno del servidor al registrar el usuario",
         *         @OA\JsonContent(
         *             @OA\Property(property="success", type="boolean", example=false),
         *             @OA\Property(property="message", type="string", example="Error al registrar el usuario y crear el carrito"),
         *             @OA\Property(property="error", type="string", example="Error details")
         *         )
         *     )
         * )
         */
        public function registerUser(Request $request)
        {
            $messages = [
                'username.required' => 'El nombre de usuario es obligatorio.',
                'username.unique' => 'El nombre de usuario ya está en uso.',
                'rol.required' => 'El rol es obligatorio.',
                'nombres.required' => 'El nombre es obligatorio.',
                'apellidos.required' => 'Los apellidos son obligatorios.',
                'apellidos.regex' => 'Debe ingresar al menos dos apellidos separados por un espacio.',
                'correo.required' => 'El correo es obligatorio.',
                'correo.email' => 'El correo debe tener un formato válido.',
                'correo.unique' => 'El correo ya está registrado.',
                'edad.integer' => 'La edad debe ser un número entero.',
                'edad.between' => 'La edad debe ser mayor a 18.',
                'nacimiento.date' => 'La fecha de nacimiento debe ser una fecha válida.',
                'nacimiento.before' => 'La fecha de nacimiento debe ser anterior a hoy.',
                'password.required' => 'La contraseña es obligatoria.',
                'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
                'password.regex' => 'La contraseña debe incluir al menos una mayúscula y un símbolo.',
                'password.confirmed' => 'Las contraseñas no coinciden.',
            ];
            
            $validator = Validator::make($request->all(), [
                'username' => 'required|string|max:255|unique:usuarios',
                'rol' => 'required|string|max:255',
                'nombres' => 'required|string|max:255',
                'apellidos' => [
                    'required',  
                    'regex:/^[a-zA-ZÀ-ÿ]+(\s[a-zA-ZÀ-ÿ]+)+$/'
                ],
                'correo' => 'required|string|email|max:255|unique:usuarios',
                'edad' => 'nullable|integer|between:18,100',
                'nacimiento' => 'nullable|date|before:today',
                'telefono' => 'nullable|string|size:9|regex:/^\d{9}$/',
                'departamento' => 'nullable|string|max:255',
                'password' => [
                    'required',
                    'string',
                    'min:8',
                    'max:255',
                    'regex:/^(?=.*[A-Z])(?=.*[!@#$%^&*(),.?":{}|<>_])[A-Za-z\d!@#$%^&*(),.?":{}|<>_]{8,}$/',
                ]
            ], $messages);
            
            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }
            
            // Verificar si el nombre de usuario ya está en uso
            $existingUsername = Usuario::where('username', $request->username)->first();
            if ($existingUsername) {
                return response()->json([
                    'errors' => [
                        'username' => 'El nombre de usuario ya está en uso.'
                    ]
                ], 409);
            }
            
            // Verificar si el correo ya está registrado
            $existingEmail = Usuario::where('correo', $request->correo)->first();
            if ($existingEmail) {
                return response()->json([
                    'errors' => [
                        'correo' => 'El correo ya está registrado.'
                    ]
                ], 409);
            }
            
            // Verificar si el DNI ya está registrado
            $existingDni = Usuario::where('dni', $request->dni)->first();
            if ($existingDni) {
                return response()->json([
                    'errors' => [
                        'dni' => 'El DNI ya está registrado.'
                    ]
                ], 409);
            }
       
           try {
               // Registrar el usuario
               $user = Usuario::create([
                   'username' => $request->username,
                   'rol' => $request->rol,
                   'nombres' => $request->nombres,
                   'apellidos' => $request->apellidos,
                   'dni' => null, 
                   'correo' => $request->correo,
                   'edad' => $request->edad ?? null,
                   'nacimiento' => $request->nacimiento ?? null,
                   'telefono' => $request->telefono ?? null,
                   'departamento' => $request->departamento ?? null,
                   'password' => bcrypt($request->password),
                   'status' => 'loggedOff',
                   'verification_token' => Str::random(60), // Genera un token único
                   'estado'=> 'activo',
               ]);
              // http://localhost:3000
                // URL para verificar el correo
                $verificationUrl = "https://melymarckstore.vercel.app/verificar-correo-token?token_veririficador={$user->verification_token}";

                // Enviar el correo
                Mail::to($user->correo)->send(new VerificarCorreo($user, $verificationUrl));
                
               // Crear el carrito asociado al usuario
               $carrito = new Carrito();
               $carrito->idUsuario = $user->idUsuario; // Asignar el idUsuario al carrito
               $carrito->save(); // Guardar el carrito
       
               // Devolver respuesta con éxito
               return response()->json([
                   'success' => true,
                   'message' => 'Usuario registrado y carrito creado exitosamente, Verifica tu correo.',
               ], 201);
       
           } catch (\Exception $e) {
               return response()->json([
                   'success' => false,
                   'message' => 'Error al registrar el usuario y crear el carrito',
                   'error' => $e->getMessage(),
               ], 500);
           }
       }

       public function registerUserGoogle(Request $request)
       {
           $googleClient = new Google_Client();
           $googleClient->setClientId(env('GOOGLE_CLIENT_ID'));
       
           try {
               // Verificar el token de Google
               $payload = $googleClient->verifyIdToken($request->googleToken);
               
               if ($payload) {
                   // Crear o actualizar el usuario
                   $user = Usuario::firstOrCreate([
                       'correo' => $payload['email'],
                   ], [
                       'username' => $payload['given_name'] . $payload['family_name'],
                       'rol' => 'cliente',
                       'nombres' => $payload['given_name'],
                       'apellidos' => $payload['family_name'],
                       'dni' => null, // Establecer un valor por defecto o generar uno
                       'password' => bcrypt(Str::random(16)), // Genera una contraseña aleatoria
                       'status' => 'loggedOff',
                       'emailVerified' => 1, // Establecer email_verified como 1 para usuarios de Google
                       'estado'=> 'activo',
                   ]);
       
                   // Verifica si el usuario fue creado o solo actualizado
                   if ($user->wasRecentlyCreated) {
                       // Si es un nuevo usuario, crear el carrito
                       $carrito = new Carrito();
                       $carrito->idUsuario = $user->idUsuario; // Asignar el idUsuario al carrito
                       $carrito->save(); // Guardar el carrito
       
                       return response()->json([
                           'message' => 'Usuario registrado exitosamente, carrito creado.',
                       ]);
                   } else {
                       // Si el usuario ya existe, puedes retornar un mensaje o actualizar la verificación
                       $user->update(['email_verified' => 1]); // Actualiza el campo email_verified
       
                       return response()->json([
                           'message' => 'Usuario ya registrado, verificado con Google.',
                       ]);
                   }
       
               } else {
                   return response()->json([
                       'message' => 'Token inválido de Google',
                   ], 400);
               }
           } catch (Exception $e) {
               return response()->json([
                   'message' => 'Error al verificar el token de Google',
               ], 400);
           }
       }


    /**
     * @OA\Post(
     *     path="/api/verificar-token",
     *     summary="Verificar correo electrónico",
     *     description="Este endpoint se utiliza para verificar el correo electrónico de un usuario utilizando un token de verificación.",
     *     operationId="verificarCorreo",
     *     tags={"AUTH CONTROLLER"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"token_veririficador"},
     *             @OA\Property(property="token_veririficador", type="string", description="Token de verificación enviado al correo del usuario.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Correo verificado exitosamente.",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Correo verificado exitosamente."),
     *             @OA\Property(property="token", type="string", nullable=true, example="eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpZCI6MX0.sVjK...") 
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Token no válido o ya utilizado.",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Token no válido o ya utilizado.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error al verificar el correo.",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Error al verificar el correo.")
     *         )
     *     ),
     * )
     */
    public function verificarCorreo(Request $request)
    {
        try {
            // Validar la solicitud
            $request->validate([
                'token_veririficador' => 'required|string',
            ]);
    
            // Buscar usuario por el token de verificación
            $usuario = Usuario::where('verification_token', $request->token_veririficador)->first();
    
            if (!$usuario) {
                return response()->json([
                    'success' => false,
                    'message' => 'Token no válido o ya utilizado.',
                ], 400);
            }
    
            // Actualizar el estado de verificación
            $usuario->emailVerified = true;
           // $usuario->verification_token = null; // Eliminar el token después de usarlo
            $usuario->save();

            // Enviar notificación de cuenta verificada
            Mail::to($usuario->correo)->send(new CuentaVerificada($usuario));
    
            // Comprobamos si hay un token en la solicitud para determinar si está logueado
            // Si no hay token, no se genera un nuevo JWT
            if (!$request->header('Authorization')) {
                return response()->json([
                    'success' => true,
                    'message' => 'Correo verificado exitosamente.',
                    'token' => null,  // No generar token si no está autenticado
                ], 200);
            }
    
            // Si está autenticado, generar el JWT
            $carrito = $usuario->carrito()->first(); // Obtener el carrito del usuario
    
            $payload = [
                'idUsuario' => $usuario->idUsuario,
                'dni' => $usuario->dni,
                'nombres' => $usuario->nombres,
                'username' => $usuario->username,
                'correo' => $usuario->correo,
                'estado' => $usuario->status,
                'rol' => $usuario->rol,
                'perfil' => $usuario->perfil,
                'idCarrito' => $carrito ? $carrito->idCarrito : null,
                'emailVerified' => $usuario->emailVerified,
            ];
    
            // Generar el token con los datos del usuario
            $token = JWTAuth::fromUser($usuario);
    
            return response()->json([
                'success' => true,
                'message' => 'Correo verificado exitosamente.',
                'token' => $token,
            ], 200);
    
        } catch (Exception $e) {
            Log::error('Error verificando el correo', ['error' => $e->getMessage()]);
    
            return response()->json([
                'success' => false,
                'message' => 'Error al verificar el correo.',
            ], 500);
        }
    }

     /**
     * Cerrar sesión del usuario
     * 
     * Este endpoint permite a los usuarios cerrar sesión en el sistema. Revoca el token JWT actual
     * y actualiza el estado del usuario a "loggedOff". Además, registra la acción en el log de actividades.
     *
     * @OA\Post(
     *     path="/api/logout",
     *     summary="Cerrar sesión del usuario",
     *     description="Este endpoint se utiliza para cerrar sesión de un usuario y revocar su token JWT.",
     *     operationId="logout",
     *     tags={"AUTH CONTROLLER"},
     *     security={{"bearerAuth": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         description="ID del usuario que desea cerrar sesión",
     *         @OA\JsonContent(
     *             required={"idUsuario"},
     *             @OA\Property(property="idUsuario", type="integer", example=1, description="ID del usuario que desea cerrar sesión.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Usuario deslogueado correctamente",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Usuario deslogueado correctamente.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Datos de entrada inválidos",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="El campo idUsuario es requerido.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="No autorizado",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="No autorizado.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Usuario no encontrado",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="No se pudo encontrar el usuario.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error al desloguear al usuario",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="No se pudo desloguear al usuario.")
     *         )
     *     )
     * )
     */
   public function logout(Request $request)
   {
       // Validar que el ID del usuario esté presente y sea un entero
       $request->validate([
           'idUsuario' => 'required|integer',
       ]);

       // Buscar el usuario por su ID
       $user = Usuario::where('idUsuario', $request->idUsuario)->first();

       if ($user) {
           try {
               // Iniciar transacción
               DB::beginTransaction();

               // Obtener el token actual de la tabla actividad_usuario
               $actividad = ActividadUsuario::where('idUsuario', $request->idUsuario)->first();
               
               if ($actividad && $actividad->jwt) {
                   try {
                       // Configurar el token en JWTAuth
                       $token = $actividad->jwt;
                       JWTAuth::setToken($token);

                       // Verificar que el token sea válido antes de intentar invalidarlo
                       if (JWTAuth::check()) {
                           // Invalidar el token y forzar su expiración
                           JWTAuth::invalidate(true);
                       }
                   } catch (JWTException $e) {
                       Log::error('Error al invalidar token: ' . $e->getMessage());
                   } catch (\Exception $e) {
                       Log::error('Error general con el token: ' . $e->getMessage());
                   }
               }

               // Actualizar el estado del usuario a "loggedOff"
               $user->status = 'loggedOff';
               $user->save();

               // Limpiar el JWT en la tabla actividad_usuario
               if ($actividad) {
                   $actividad->jwt = null;
                   $actividad->save();
               }

               // Obtener el nombre completo del usuario
               $nombreUsuario = $user->nombres . ' ' . $user->apellidos;

               // Definir la acción y mensaje para el log
               $accion = "$nombreUsuario cerró sesión";

               // Llamada a la función agregarLog para registrar el log
               $this->agregarLog($user->idUsuario, $accion);

               // Confirmar transacción
               DB::commit();

               return response()->json([
                   'success' => true, 
                   'message' => 'Usuario deslogueado correctamente'
               ], 200);

           } catch (\Exception $e) {
               // Revertir transacción en caso de error
               DB::rollBack();

               return response()->json([
                   'success' => false, 
                   'message' => 'No se pudo desloguear al usuario',
                   'error' => $e->getMessage()
               ], 500);
           }
       }

       return response()->json([
           'success' => false, 
           'message' => 'No se pudo encontrar el usuario'
       ], 404);
   }

    /**
     * @OA\Post(
     *     path="/api/send-message",
     *     summary="Enviar mensaje de contacto",
     *     description="Este endpoint permite a los usuarios enviar un mensaje de contacto al administrador.",
     *     operationId="sendContactEmail",
     *     tags={"AUTH CONTROLLER"},
     *     @OA\RequestBody(
     *         required=true,
     *         description="Datos del mensaje de contacto",
     *         @OA\JsonContent(
     *             required={"name", "email", "message"},
     *             @OA\Property(property="name", type="string", description="Nombre del remitente", example="John Doe"),
     *             @OA\Property(property="email", type="string", format="email", description="Correo electrónico del remitente", example="johndoe@example.com"),
     *             @OA\Property(property="message", type="string", description="Mensaje de contacto", example="Hola, tengo una consulta sobre los productos.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Mensaje enviado correctamente.",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="string", example="Mensaje enviado correctamente.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Error en los datos enviados.",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="El nombre es requerido.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error al enviar el mensaje.",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Error al enviar el mensaje. Inténtalo más tarde.")
     *         )
     *     )
     * )
     */
     public function sendContactEmail(Request $request)
     {
         $request->validate([
             'name' => 'required|string|max:255',
             'email' => 'required|email',
             'message' => 'required|string',
         ]);
 
         // Configura los datos del correo
         $data = [
             'name' => $request->name,
             'email' => $request->email,
             'messageContent' => $request->message,
         ];
 
         // Envía el correo
         Mail::send('emails.contact', $data, function($message) use ($request) {
             $message->to('destinatario@example.com', 'Administrador')
                     ->subject('Nuevo mensaje de contacto');
             $message->from($request->email, $request->name);
         });
 
         return response()->json(['success' => 'Mensaje enviado correctamente.']);
     }


    /**
     * @OA\Post(
     *     path="/api/send-verification-codeUser",
     *     summary="Enviar código de verificación para restablecer contraseña",
     *     description="Este endpoint permite a los usuarios recibir un código de verificación por correo electrónico para restablecer su contraseña.",
     *     operationId="sendVerificationCodeUser",
     *     tags={"AUTH CONTROLLER"},
     *     @OA\RequestBody(
     *         required=true,
     *         description="Correo electrónico del usuario para el código de verificación",
     *         @OA\JsonContent(
     *             required={"email"},
     *             @OA\Property(property="email", type="string", format="email", description="Correo electrónico del usuario", example="usuario@ejemplo.com")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Código de verificación enviado correctamente.",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Código de verificación enviado")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Correo electrónico no válido o no existe.",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="El correo electrónico no existe.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error al enviar el código de verificación.",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Error al enviar el código de verificación. Intenta nuevamente.")
     *         )
     *     )
     * )
     */
     public function sendVerificationCodeUser(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:usuarios,correo',
        ]);

        $user = Usuario::where('correo', $request->email)->first();
        
        $verificationCode = rand(100000, 999999);
        Cache::put("verification_code_{$user->id}", $verificationCode, now()->addMinutes(10)); // Expira en 10 minutos

        Mail::raw("Tu código de verificación es: {$verificationCode}", function($message) use ($user) {
            $message->to($user->correo)
                    ->subject('Código de Verificación para Restablecer Contraseña');
        });

        return response()->json(['message' => 'Código de verificación enviado'], 200);
    }

    
    /**
     * @OA\Post(
     *     path="/api/verify-codeUser",
     *     summary="Verificar código de verificación para restablecer contraseña",
     *     description="Este endpoint permite verificar el código de verificación recibido por correo electrónico para proceder con el restablecimiento de la contraseña.",
     *     operationId="verifyCodeUser",
     *     tags={"AUTH CONTROLLER"},
     *     @OA\RequestBody(
     *         required=true,
     *         description="Correo electrónico y código de verificación del usuario",
     *         @OA\JsonContent(
     *             required={"email", "code"},
     *             @OA\Property(property="email", type="string", format="email", description="Correo electrónico del usuario", example="usuario@ejemplo.com"),
     *             @OA\Property(property="code", type="integer", description="Código de verificación enviado al correo electrónico", example=123456)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Código verificado correctamente.",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Código verificado")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Código incorrecto o expirado.",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Código incorrecto o expirado")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error al verificar el código de verificación.",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Error al verificar el código. Intenta nuevamente.")
     *         )
     *     )
     * )
     */
    public function verifyCodeUser(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:usuarios,correo',
            'code' => 'required|numeric',
        ]);

        $user = Usuario::where('correo', $request->email)->first();
        $storedCode = Cache::get("verification_code_{$user->id}");

        if ($storedCode && $storedCode == $request->code) {
            Cache::forget("verification_code_{$user->id}");
            Cache::put("password_reset_allowed_{$user->id}", true, now()->addMinutes(10));

            return response()->json(['message' => 'Código verificado'], 200);
        }

        return response()->json(['message' => 'Código incorrecto o expirado'], 400);
    }
    

    public function changePasswordUser(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:usuarios,correo',
            'newPassword' => 'required|min:8',
        ]);

        $user = Usuario::where('correo', $request->email)->first();
        $resetAllowed = Cache::get("password_reset_allowed_{$user->id}");

        if ($resetAllowed) {
            $user->password = bcrypt($request->newPassword);
            $user->save();

            Cache::forget("password_reset_allowed_{$user->id}");

            Mail::raw('Tu contraseña ha sido cambiada correctamente.', function($message) use ($user) {
                $message->to($user->correo)
                        ->subject('Confirmación de Cambio de Contraseña');
            });

            return response()->json(['message' => 'Contraseña cambiada exitosamente'], 200);
        }

        return response()->json(['message' => 'No autorizado para cambiar la contraseña'], 403);
    }

    public function getStatus()
    {
        // Consulta directa a la tabla "mantenimiento"
        $mantenimiento = DB::select('SELECT estado, mensaje FROM mantenimiento LIMIT 1');
    
        if (!empty($mantenimiento)) {
            return response()->json([
                'estado' => $mantenimiento[0]->estado,
                'mensaje' => $mantenimiento[0]->mensaje
            ], 200);
        }
    
        return response()->json([
            'estado' => 0,
            'mensaje' => 'No se pudo obtener el estado de mantenimiento'
        ], 404);
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
