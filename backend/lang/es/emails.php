<?php

return [
    'verification' => [
        'subject' => 'Verifica tu correo electrónico',
        'title' => 'Verifica tu correo electrónico',
        'greeting' => 'Hola :name,',
        'body' => 'Gracias por registrarte en :platform. Por favor verifica tu dirección de correo electrónico para completar tu registro.',
        'button' => 'Verificar correo electrónico',
        'ignore' => 'Si no creaste una cuenta, puedes ignorar este correo de forma segura.',
        'expiration' => 'Este enlace expirará en :minutes minutos.',
        'rights' => 'Todos los derechos reservados.',
    ],
    'account_locked_out' => [
        'subject' => 'Alerta de seguridad: Cuenta bloqueada temporalmente',
        'title' => 'Cuenta bloqueada temporalmente',
        'greeting' => 'Hola :name,',
        'body' => 'Detectamos demasiados intentos fallidos de inicio de sesión en tu cuenta de :platform. Por tu seguridad, tu cuenta ha sido bloqueada temporalmente. Puedes intentar iniciar sesión nuevamente después de que expire el período de bloqueo.',
        'security_note' => 'Si no fuiste tú, te recomendamos cambiar tu contraseña una vez que expire el bloqueo para proteger tu cuenta.',
        'rights' => 'Todos los derechos reservados.',
    ],
];
