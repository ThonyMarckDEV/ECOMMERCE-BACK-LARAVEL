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


class AuthController extends Controller
{

        /**
     * Login de usuario y generación de token JWT.
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

     // FUNCION PARA REGISTRAR UN USUARIO
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
               // return response()->json(['errors' => $validator->errors()], 422);
            }

            // Verificar si el nombre de usuario, correo o DNI ya están en uso
            $existingUser = Usuario::where('username', $request->username)
                                ->orWhere('correo', $request->correo)
                                ->orWhere('dni', $request->dni)
                                ->first();

            if ($existingUser) {
                return response()->json([
                    'errors' => [
                        'correo' => 'El correo ya está registrado.',
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
                       'dni' => '00000000', // Establecer un valor por defecto o generar uno
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

    public function verificarToken(Request $request)
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
     * Logout del usuario y revocación del token JWT.
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

    /**
     * Refrescar el token JWT.
     */
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
