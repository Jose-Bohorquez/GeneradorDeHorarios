<?php
require_once 'config.php';
require_once 'functions.php';

$schedules = loadSchedules();

echo "=== VALIDACIÓN RÁPIDA DE HORARIOS ===\n";
echo "Total de horarios almacenados: " . count($schedules) . "\n\n";

foreach ($schedules as $index => $schedule) {
    echo "Horario #" . ($index + 1) . ":\n";
    echo "- Mes: " . $schedule['month'] . "\n";
    echo "- Generado: " . $schedule['generated_date'] . "\n";
    echo "- Total entradas: " . count($schedule['schedule']) . "\n";
    
    $employees = array_unique(array_column($schedule['schedule'], 'employee'));
    echo "- Empleados: " . implode(', ', $employees) . "\n";
    echo "---\n";
}
?>