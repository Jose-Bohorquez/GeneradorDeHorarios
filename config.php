<?php
/**
// Configuración básica
define('DATA_DIR', __DIR__ . '/../data/');
define('EMPLOYEES_FILE', DATA_DIR . 'employees.json');
define('SCHEDULES_FILE', DATA_DIR . 'schedules.json');

// Crear directorio data si no existe
if (!file_exists(DATA_DIR)) {
    mkdir(DATA_DIR, 0755, true);
}

// Inicializar archivos JSON si no existen
if (!file_exists(EMPLOYEES_FILE)) {
    file_put_contents(EMPLOYEES_FILE, json_encode([
        'employees' => [
            ['name' => 'Dilan', 'active' => true],
            ['name' => 'Diego', 'active' => true],
            ['name' => 'Tania', 'active' => true],
            ['name' => 'Carlos', 'active' => true],
            ['name' => 'Brayan', 'active' => true, 'fixed_schedule' => ['07:00', '17:30']],
            ['name' => 'Pablo', 'active' => true, 'fixed_schedule' => ['07:00', '17:30'], 'supervisor' => true]
        ]
    ], JSON_PRETTY_PRINT));
}

if (!file_exists(SCHEDULES_FILE)) {
    file_put_contents(SCHEDULES_FILE, json_encode(['schedules' => []], JSON_PRETTY_PRINT));
} **/
?>



<?php
// Configuración básica
define('DATA_DIR', __DIR__ . '/data/');
define('EMPLOYEES_FILE', DATA_DIR . 'employees.json');
define('SCHEDULES_FILE', DATA_DIR . 'schedules.json');

// Crear directorio data si no existe
if (!file_exists(DATA_DIR)) {
    mkdir(DATA_DIR, 0755, true);
}

// Inicializar archivos JSON si no existen
if (!file_exists(EMPLOYEES_FILE)) {
    file_put_contents(EMPLOYEES_FILE, json_encode([
        'employees' => [
            ['name' => 'Dilan', 'active' => true],
            ['name' => 'Diego', 'active' => true],
            ['name' => 'Tania', 'active' => true],
            ['name' => 'Carlos', 'active' => true],
            ['name' => 'Brayan', 'active' => true, 'fixed_schedule' => ['07:00', '17:30']],
            ['name' => 'Pablo', 'active' => true, 'fixed_schedule' => ['07:00', '17:30'], 'supervisor' => true]
        ]
    ], JSON_PRETTY_PRINT));
}

if (!file_exists(SCHEDULES_FILE)) {
    file_put_contents(SCHEDULES_FILE, json_encode(['schedules' => []], JSON_PRETTY_PRINT));
}