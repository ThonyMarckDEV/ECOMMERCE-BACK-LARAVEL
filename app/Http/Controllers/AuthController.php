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
               return response()->json([
                   'success' => false,
                   'errors' => $validator->errors(),
               ], 400);
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
               ]);
       
               // Crear el carrito asociado al usuario
               $carrito = new Carrito();
               $carrito->idUsuario = $user->idUsuario; // Asignar el idUsuario al carrito
               $carrito->save(); // Guardar el carrito
       
               // Devolver respuesta con éxito
               return response()->json([
                   'success' => true,
                   'message' => 'Usuario registrado y carrito creado exitosamente',
               ], 201);
       
           } catch (\Exception $e) {
               return response()->json([
                   'success' => false,
                   'message' => 'Error al registrar el usuario y crear el carrito',
                   'error' => $e->getMessage(),
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
    
        // Obtener el token del encabezado Authorization
        $authHeader = $request->header('Authorization');
    
        // Extraer el token de la cadena 'Bearer [token]'
        if ($authHeader && preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            $token = $matches[1];
        } else {
            // Si no se encuentra el token, responde con error
            return response()->json(['status' => 'invalidToken'], 401);
        }
    
        if (!$idUsuario || !$token) {
            // Sin idUsuario o token, responde como inválido
            return response()->json(['status' => 'invalidToken'], 401);
        }
    
        // Busca el usuario por id
        $user = Usuario::find($idUsuario);
    
        // Verifica si el token es válido
        $isTokenValid = $this->validateToken($token, $idUsuario);
    
        // Responde según el estado y validez del token
        if (!$user) {
            // Usuario no encontrado en la BD
            return response()->json(['status' => 'loggedOff'], 401);
        }
    
        if ($user && !$isTokenValid) {
            // Usuario existe pero el token es inválido
            return response()->json(['status' => 'loggedOnInvalidToken'], 401);
        }
    
        if ($user->status === 'loggedOff') {
            // Usuario existe pero está marcado como `loggedOff` en la BD
            return response()->json(['status' => 'loggedOff'], 401);
        }
    
        // Usuario está activo y el token es válido
        return response()->json(['status' => 'loggedOn', 'isTokenValid' => true], 200);
    }
    
    // Valida el token JWT y su expiración
    private function validateToken($token, $idUsuario)
    {
        try {
            // Decodificar y verificar el token JWT
            $payload = JWT::decode($token, new Key(env('JWT_SECRET'), 'HS256'));
    
            $expiration = $payload->exp;
            $tokenUserId = $payload->idUsuario ?? null;
    
            // Verifica que el token no esté expirado y que el idUsuario coincida
            return $expiration > time() && $tokenUserId == $idUsuario;
        } catch (\Exception $e) {
            return false;
        }
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

}
