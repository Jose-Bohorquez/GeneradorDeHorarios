	<?php
require_once 'config.php';

// Cargar empleados (igual que antes)
function loadEmployees() {
    $data = json_decode(file_get_contents(EMPLOYEES_FILE), true);
    return $data['employees'];
}

// Guardar empleados (igual que antes)
function saveEmployees($employees) {
    $data = ['employees' => $employees];
    file_put_contents(EMPLOYEES_FILE, json_encode($data, JSON_PRETTY_PRINT));
}

// Cargar horarios (igual que antes)
function loadSchedules() {
    $data = json_decode(file_get_contents(SCHEDULES_FILE), true);
    return $data['schedules'];
}

// Guardar horarios (igual que antes)
function saveSchedules($schedules) {
    $data = ['schedules' => $schedules];
    file_put_contents(SCHEDULES_FILE, json_encode($data, JSON_PRETTY_PRINT));
}

// Función principal modificada para 4x4x4x4
function generateRotativeSchedule4x4($employees, $startDate, $month = null) {
    // Filtrar empleados rotativos
    $rotativeEmployees = array_filter($employees, function($emp) {
        return empty($emp['fixed_schedule']) && $emp['active'];
    });
    
    $rotativeEmployees = array_values($rotativeEmployees);
    $count = count($rotativeEmployees);
    
    if ($count < 4) {
        return ['error' => 'Se necesitan al menos 4 empleados activos para la rotación 4x4x4x4'];
    }
    
    // Definir turnos
    $shifts = [
        ['name' => 'Mañana', 'start' => '06:00', 'end' => '14:00'],
        ['name' => 'Tarde', 'start' => '14:00', 'end' => '22:00'],
        ['name' => 'Noche', 'start' => '22:00', 'end' => '06:00'],
        ['name' => 'Descanso', 'start' => null, 'end' => null]
    ];
    
    $schedule = [];
    
    // Determinar rango de fechas (todo el mes)
    if ($month) {
        $monthDate = new DateTime($month);
        $monthDate->modify('first day of this month');
        $startDate = $monthDate->format('Y-m-d');
        $endDate = clone $monthDate;
        $endDate->modify('last day of this month');
    } else {
        $endDate = (new DateTime($startDate))->add(new DateInterval('P1M'));
    }
    
    $currentDate = new DateTime($startDate);
    
    // Determinar posición inicial
    $lastSchedule = loadSchedules();
    $lastPosition = 0;
    
    if (!empty($lastSchedule)) {
        $lastEntry = end($lastSchedule);
        $lastPosition = $lastEntry['rotation_position'] ?? 0;
    }
    
    // Generar horario con rotación 4x4x4x4
    $rotationCycle = 0; // Contador de ciclos de 4 días
    $employeeIndex = $lastPosition % $count;
    
    while ($currentDate <= $endDate) {
        // Determinar el turno basado en el ciclo actual (cada 4 días cambia)
        $shiftIndex = floor($rotationCycle / $count) % 4;
        
        // Asignar turno a cada empleado en secuencia
        for ($i = 0; $i < 4 && $currentDate <= $endDate; $i++) {
            $currentEmployeeIndex = ($employeeIndex + $i) % $count;
            
            $schedule[] = [
                'employee' => $rotativeEmployees[$currentEmployeeIndex]['name'],
                'date' => $currentDate->format('Y-m-d'),
                'shift' => $shifts[$shiftIndex]['name'],
                'start' => $shifts[$shiftIndex]['start'],
                'end' => $shifts[$shiftIndex]['end'],
                'rotation_position' => $currentEmployeeIndex
            ];
            
            $currentDate->add(new DateInterval('P1D'));
        }
        
        $rotationCycle++;
        $employeeIndex = ($employeeIndex + 4) % $count;
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

// Funciones de traducción (igual que antes)
function translateDayToSpanish($englishDay) {
    $days = [
        'Monday' => 'Lunes', 'Tuesday' => 'Martes', 'Wednesday' => 'Miércoles',
        'Thursday' => 'Jueves', 'Friday' => 'Viernes', 'Saturday' => 'Sábado',
        'Sunday' => 'Domingo'
    ];
    return $days[$englishDay] ?? $englishDay;
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

// Organizar horarios por semana para vista de calendario (igual que antes)
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