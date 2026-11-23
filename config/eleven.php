<?php

return [
    /*
    | Administrador inicial que crea el seeder. Si no se define ADMIN_PASSWORD
    | en el .env, el seeder genera una contraseña aleatoria y la muestra una
    | sola vez en la consola. En ambos casos debe cambiarla al primer ingreso.
    */
    'admin' => [
        'email' => env('ADMIN_EMAIL', 'admin@eleven.test'),
        'password' => env('ADMIN_PASSWORD'),
        'nombre' => 'Administrador',
        'apellido' => 'Eleven',
    ],
];
