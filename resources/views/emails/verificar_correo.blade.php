<div style="font-family: Arial, sans-serif; text-align: center;">
    <h1>¡Hola, {{ $user->nombres }}!</h1>
    <p>Gracias por registrarte en nuestra plataforma. Por favor, haz clic en el botón de abajo para verificar tu correo electrónico:</p>
    <a href="{{ $url }}" style="display: inline-block; padding: 10px 20px; background-color: #4CAF50; color: white; text-decoration: none; border-radius: 5px;">
        Verificar correo
    </a>
    <p>Si no solicitaste esta verificación, ignora este correo.</p>
</div>
