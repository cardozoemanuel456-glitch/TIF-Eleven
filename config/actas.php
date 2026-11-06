<?php

/*
|--------------------------------------------------------------------------
| Configuración del módulo 1 · Actas
|--------------------------------------------------------------------------
| Si la preceptora cambia el diseño de la planilla, se ajusta acá y no en el código.
| Las posiciones salen de la planilla real de seguimiento:
|   - fila 4: nombre de cada materia (fila 3: cuatrimestre; en la pestaña 2023 también PLAN 2003)
|   - columna D: número de legajo, desde la fila 5
|   - pestañas por cohorte, de la más reciente a la más antigua (2025, 2024, 2023)
*/

return [

    // Disco privado de Laravel (storage/app/private). Nunca público.
    'disco' => 'local',
    'directorio' => 'actas',

    // M1-01: tamaño máximo de cada archivo, en kilobytes.
    'max_kb' => 10240,

    // M1-07: dónde se busca la materia y el legajo en cada pestaña.
    'filas_materia' => [3, 4],
    'columna_legajo' => 4,   // D
    'fila_inicial' => 5,

    // Formato con que se escribe la nota en la celda: "9 (nueve)".
    'formato_nota' => '{nota} ({letras})',

    // M1-12: horas que se conserva una planilla subida que no se confirmó.
    'horas_limpieza' => 24,

    /*
    | M1-08: abreviaturas aplicadas al comparar materias, después de pasar a minúsculas
    | y quitar tildes y puntos. Se reemplaza la clave más larga primero.
    | Ejemplo: "Arquitectura y Sistemas Operativos" (acta) = "ARQUIT. Y SIST OPERATIVOS" (planilla).
    */
    'abreviaturas' => [
        'programacion' => 'prog',
        'programac' => 'prog',
        'matematica' => 'matem',
        'arquitectura' => 'arquit',
        'sistemas' => 'sist',
        'organizacion' => 'organiz',
        'procesamiento' => 'proces',
    ],
];
