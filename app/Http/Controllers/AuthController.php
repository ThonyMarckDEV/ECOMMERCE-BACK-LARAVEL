<?php

namespace App\Http\Controllers;

use App\Models\Usuario;
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
* @OA\Server(url="http://localhost:8000")
*/
class AuthController extends Controller
{
    /**
     * Login
     * @OA\Post(
     *     path="/api/login",
     *     tags={"AUTH CONTROLLER"},
     *     summary="Login de usuario",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="correo", type="string", example="usuario@dominio.com"),
     *             @OA\Property(property="password", type="string", example="contraseña123")
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
     *         response=401,
     *         description="Credenciales inválidas"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Usuario no encontrado"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error al generar el token"
     *     )
     * )
     */
    public function login(Request $request)
    {
        // Validar que el correo y la contraseña están presentes
        $request->validate([
            'correo' => 'required|email',
            'password' => 'required|string|min:6',
        ]);

        // Obtener las credenciales de correo y contraseña
        $credentials = [
            'correo' => $request->input('correo'),
            'password' => $request->input('password')
        ];

        try {
            // Buscar el usuario por correo
            $usuario = Usuario::where('correo', $credentials['correo'])->first();

            // Verificar si el usuario existe
            if (!$usuario) {
                return response()->json(['error' => 'Usuario no encontrado'], 404);
            }

            // Verificar si el usuario ya está logueado
            if ($usuario->status === 'loggedOn') {
                return response()->json(['message' => 'Usuario ya logueado'], 409);
            }

            // Intentar autenticar y generar el token JWT usando el campo 'correo'
            if (!$token = JWTAuth::attempt(['correo' => $credentials['correo'], 'password' => $credentials['password']])) {
                return response()->json(['error' => 'Credenciales inválidas'], 401);
            }

            // Actualizar el estado del usuario a "loggedOn"
            $usuario->update(['status' => 'loggedOn']);

            return response()->json(compact('token'));
        } catch (JWTException $e) {
            return response()->json(['error' => 'No se pudo crear el token'], 500);
        }
    }

        /**
     * Login de usuario con Google y generación de token JWT.
     */
    public function loginWithGoogle(Request $request)
    {
        // Validar que el token de Google esté presente
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
                    'nombre' => $googleUser['given_name'],
                    'apellidos' => $googleUser['family_name'],
                    'status' => 'loggedOn',
                ]);
            }

            // Si el usuario está registrado, pero no está logueado, actualizar su estado
            if ($usuario->status !== 'loggedOn') {
                $usuario->update(['status' => 'loggedOn']);
            }

            // Generar el token JWT para el usuario
            $token = JWTAuth::fromUser($usuario);

            return response()->json(compact('token'));

        } catch (\Exception $e) {
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
                'dni.required' => 'El DNI es obligatorio.',
                'dni.size' => 'El DNI debe tener exactamente 8 caracteres.',
                'dni.unique' => 'El DNI ya está registrado.',
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
                'dni' => 'required|string|size:8|unique:usuarios',
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
                   'dni' => $request->dni, 
                   'correo' => $request->correo,
                   'edad' => $request->edad ?? null,
                   'nacimiento' => $request->nacimiento ?? null,
                   'telefono' => $request->telefono ?? null,
                   'departamento' => $request->departamento ?? null,
                   'password' => bcrypt($request->password),
                   'status' => 'loggedOff',
                   'verification_token' => Str::random(60), // Genera un token único
               ]);
              // http://localhost:3000
              //https://ecommerce-front-react.vercel.app
                // URL para verificar el correo
                $verificationUrl = "https://ecommerce-front-react.vercel.app/verificar-correo-token?token_veririficador={$user->verification_token}";

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
                       'dni' => "", // Establecer un valor por defecto o generar uno
                       'password' => bcrypt(Str::random(16)), // Genera una contraseña aleatoria
                       'status' => 'loggedOff',
                       'emailVerified' => 1, // Establecer email_verified como 1 para usuarios de Google
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
     * @OA\Post(
     *     path="/api/logout",
     *     summary="Cerrar sesión del usuario",
     *     description="Este endpoint se utiliza para cerrar sesión de un usuario y revocar su token JWT.",
     *     operationId="logout",
     *     tags={"AUTH CONTROLLER"},
     *     security={{"bearerAuth": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"idUsuario"},
     *             @OA\Property(property="idUsuario", type="integer", description="ID del usuario que desea cerrar sesión.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Usuario deslogueado correctamente.",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Usuario deslogueado correctamente.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="No se encontró el usuario.",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="No se pudo encontrar el usuario.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error al desloguear al usuario.",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="No se pudo desloguear al usuario.")
     *         )
     *     ),
     * )
     */
    public function logout(Request $request)
    {
        $request->validate([
            'idUsuario' => 'required|integer',
        ]);

        $user = Usuario::where('idUsuario', $request->idUsuario)->first();

        if ($user) {
            try {
                $user->status = 'loggedOff';
                $user->save();

                return response()->json(['success' => true, 'message' => 'Usuario deslogueado correctamente'], 200);
            } catch (JWTException $e) {
                return response()->json(['error' => 'No se pudo desloguear al usuario'], 500);
            }
        }

        return response()->json(['success' => false, 'message' => 'No se pudo encontrar el usuario'], 404);
    }


    public function refreshToken(Request $request)
    {
        try {
            $oldToken = JWTAuth::getToken();

            Log::info('Refrescando token: Token recibido', ['token' => (string) $oldToken]);

            $newToken = JWTAuth::refresh($oldToken);

            Log::info('Token refrescado: Nuevo token', ['newToken' => $newToken]);

            return response()->json(['accessToken' => $newToken], 200);
        } catch (JWTException $e) {
            Log::error('Error al refrescar el token', ['error' => $e->getMessage()]);

            return response()->json(['error' => 'No se pudo refrescar el token'], 500);
        }
    }


    /**
     * @OA\Post(
     *     path="/api/update-activity",
     *     summary="Actualizar la última actividad del usuario",
     *     description="Este endpoint actualiza la fecha de la última actividad del usuario especificado.",
     *     operationId="updateLastActivity",
     *     tags={"AUTH CONTROLLER"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="idUsuario",
     *         in="query",
     *         description="ID del usuario cuya última actividad se actualizará.",
     *         required=true,
     *         @OA\Schema(
     *             type="integer",
     *             example=1
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Actividad actualizada correctamente.",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Last activity updated")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Usuario no encontrado.",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Usuario no encontrado")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Datos de entrada inválidos.",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="ID de usuario requerido")
     *         )
     *     )
     * )
     */
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


    /**
     * @OA\Post(
     *     path="/api/check-status",
     *     summary="Verificar el estado del usuario",
     *     description="Este endpoint verifica el estado del usuario, si está conectado o desconectado.",
     *     operationId="checkStatus",
     *     tags={"AUTH CONTROLLER"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="idUsuario",
     *         in="query",
     *         description="ID del usuario cuyo estado se desea verificar.",
     *         required=true,
     *         @OA\Schema(
     *             type="integer",
     *             example=1
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="El usuario está activo.",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="active")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="ID de usuario no proporcionado.",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="message", type="string", example="ID de usuario no proporcionado")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Usuario no encontrado.",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="message", type="string", example="Usuario no encontrado")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Usuario desconectado.",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="message", type="string", example="Usuario desconectado")
     *         )
     *     )
     * )
     */
    public function checkStatus(Request $request)
    {
        $idUsuario = $request->input('idUsuario');
        
        if (!$idUsuario) {
            // Sin idUsuario, responde con Bad Request (400)
            return response()->json(['status' => 'error', 'message' => 'ID de usuario no proporcionado'], 400);
        }

        // Busca el usuario por id
        $user = Usuario::find($idUsuario);

        // Si el usuario no se encuentra en la BD, responde con Not Found (404)
        if (!$user) {
            return response()->json(['status' => 'error', 'message' => 'Usuario no encontrado'], 404);
        }

        // Si el usuario está marcado como 'loggedOff'
        if ($user->status === 'loggedOff') {
            return response()->json(['status' => 'error', 'message' => 'Usuario desconectado'], 403);
        }

        // Si el usuario está activo y encontrado
        return response()->json(['status' => 'active'], 200);
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

}
