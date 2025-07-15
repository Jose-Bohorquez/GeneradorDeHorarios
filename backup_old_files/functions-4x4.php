<?php
require_once 'config.php';

// Cargar empleados
function loadEmployees() {
    $data = json_decode(file_get_contents(EMPLOYEES_FILE), true);
    return $data['employees'];
}

// Guardar empleados
function saveEmployees($employees) {
    $data = ['employees' => $employees];
    file_put_contents(EMPLOYEES_FILE, json_encode($data, JSON_PRETTY_PRINT));
}

// Cargar horarios
function loadSchedules() {
    $data = json_decode(file_get_contents(SCHEDULES_FILE), true);
    return $data['schedules'];
}

// Guardar horarios
function saveSchedules($schedules) {
    $data = ['schedules' => $schedules];
    file_put_contents(SCHEDULES_FILE, json_encode($data, JSON_PRETTY_PRINT));
}

// Función principal para rotación 4x4x4x4
function generateRotativeSchedule_4x4($employees, $startDate, $month = null) {
    // Filtrar empleados rotativos (sin horario fijo)
    $rotativeEmployees = array_filter($employees, function($emp) {
        return empty($emp['fixed_schedule']) && $emp['active'];
    });

    $rotativeEmployees = array_values($rotativeEmployees); // Reindexar
    $count = count($rotativeEmployees);

    if ($count < 4) {
        return ['error' => 'Se necesitan al menos 4 empleados activos para la rotación 4x4'];
    }

    // Definir turnos
    $shifts = [
        ['name' => 'Mañana', 'start' => '06:00', 'end' => '14:00'],
        ['name' => 'Tarde', 'start' => '14:00', 'end' => '22:00'],
        ['name' => 'Noche', 'start' => '22:00', 'end' => '06:00'],
        ['name' => 'Descanso', 'start' => null, 'end' => null]
    ];

    $schedule = [];
    $currentDate = new DateTime($startDate);

    // Determinar rango de fechas (todo el mes)
    if ($month) {
        $monthDate = new DateTime($month);
        $monthDate->modify('first day of this month');
        $startDate = $monthDate->format('Y-m-d');
        $endDate = clone $monthDate;
        $endDate->modify('last day of this month');
    } else {
        $endDate = clone $currentDate;
        $endDate->add(new DateInterval('P4W'));
    }

    $currentDate = new DateTime($startDate);

    // Posición inicial basada en último horario guardado
    $lastSchedule = loadSchedules();
    $lastPosition = 0;

    if (!empty($lastSchedule)) {
        $lastEntry = end($lastSchedule);
        $lastPosition = $lastEntry['rotation_position'] ?? 0;
    }

    // Ciclo principal
    while ($currentDate <= $endDate) {
        // Grupo actual de rotación (cada 4 días)
        $daysSinceStart = $currentDate->diff(new DateTime($startDate))->days;
        $blockNumber = floor($daysSinceStart / 4); // Cada 4 días hay un bloque

        for ($i = 0; $i < 4; $i++) {
            $employeeIndex = ($lastPosition + $blockNumber + $i) % 4;
            $shiftIndex = $i % 4;

            $dateEntry = clone $currentDate;

            if ($dateEntry > $endDate) break;

            $shift = $shifts[$shiftIndex];

            $schedule[] = [
                'employee' => $rotativeEmployees[$employeeIndex]['name'],
                'date' => $dateEntry->format('Y-m-d'),
                'shift' => $shift['name'],
                'start' => $shift['start'],
                'end' => $shift['end'],
                'rotation_position' => $employeeIndex
            ];
        }

        // Saltar al siguiente bloque de 4 días
        $currentDate->add(new DateInterval('P1D'));
    }

    // Agregar empleados con horario fijo
    $fixedEmployees = array_filter($employees, function($emp) {
        return !empty($emp['fixed_schedule']) && $emp['active'];
    });

    $currentDate = new DateTime($startDate);
    while ($currentDate <= $endDate) {
        foreach ($fixedEmployees as $employee) {
            $schedule[] = [
                'employee' => $employee['name'],
                'date' => $currentDate->format('Y-m-d'),
                'shift' => 'Oficina',
                'start' => $employee['fixed_schedule'][0],
                'end' => $employee['fixed_schedule'][1],
                'rotation_position' => null
            ];
        }
        $currentDate->add(new DateInterval('P1D'));
    }

    // Ordenar por fecha
    usort($schedule, function($a, $b) {
        return strcmp($a['date'], $b['date']);
    });

    return $schedule;
}



// Funciones de traducción y formato
function translateDayToSpanish($englishDay) {
    $days = [
        'Monday' => 'Lunes', 'Tuesday' => 'Martes', 'Wednesday' => 'Miércoles',
        'Thursday' => 'Jueves', 'Friday' => 'Viernes', 'Saturday' => 'Sábado',
        'Sunday' => 'Domingo'
    ];
    return $days[$englishDay] ?? $englishDay;
}

function formatDateSpanish($dateStr) {
    $date = new DateTime($dateStr);
    $months = [
        'January' => 'Enero', 'February' => 'Febrero', 'March' => 'Marzo',
        'April' => 'Abril', 'May' => 'Mayo', 'June' => 'Junio',
        'July' => 'Julio', 'August' => 'Agosto', 'September' => 'Septiembre',
        'October' => 'Octubre', 'November' => 'Noviembre', 'December' => 'Diciembre'
    ];
    
    $englishMonth = $date->format('F');
    $spanishMonth = $months[$englishMonth] ?? $englishMonth;
    
    return $date->format('d') . ' de ' . $spanishMonth . ' de ' . $date->format('Y');
}

function organizeScheduleByWeek($schedule) {
    $weeks = [];
    $currentWeek = [];
    $currentWeekNumber = null;
    
    foreach ($schedule as $entry) {
        $date = new DateTime($entry['date']);
        $weekNumber = $date->format('W-Y');
        $dayOfWeek = $date->format('N');
        
        if ($weekNumber !== $currentWeekNumber) {
            if (!empty($currentWeek)) {
                $weeks[] = $currentWeek;
            }
            $currentWeek = array_fill(1, 7, []);
            $currentWeekNumber = $weekNumber;
        }
        
        $currentWeek[$dayOfWeek][] = $entry;
    }
    
    if (!empty($currentWeek)) {
        $weeks[] = $currentWeek;
    }
    
    return $weeks;
}

function translateMonthToSpanish($englishMonth) {
    $months = [
        'January' => 'Enero', 'February' => 'Febrero', 'March' => 'Marzo',
        'April' => 'Abril', 'May' => 'Mayo', 'June' => 'Junio',
        'July' => 'Julio', 'August' => 'Agosto', 'September' => 'Septiembre',
        'October' => 'Octubre', 'November' => 'Noviembre', 'December' => 'Diciembre'
    ];
    
    foreach ($months as $en => $es) {
        $englishMonth = str_replace($en, $es, $englishMonth);
    }
    
    return $englishMonth;
}

function getLastDayOfMonth($date) {
    $d = new DateTime($date);
    $d->modify('last day of this month');
    return $d;
}
?>