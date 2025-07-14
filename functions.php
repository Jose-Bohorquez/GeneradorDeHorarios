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
    $result = file_put_contents(EMPLOYEES_FILE, json_encode($data, JSON_PRETTY_PRINT));
    return $result !== false;
}

// Cargar horarios
function loadSchedules() {
    $data = json_decode(file_get_contents(SCHEDULES_FILE), true);
    return $data['schedules'];
}

// Guardar horarios
function saveSchedules($schedules) {
    $data = ['schedules' => $schedules];
    $result = file_put_contents(SCHEDULES_FILE, json_encode($data, JSON_PRETTY_PRINT));
    return $result !== false;
}

// Generar horarios rotativos 2x2
// Modifica la función generateRotativeSchedule
function generateRotativeSchedule($employees, $startDate, $month = null) {
    // Filtrar empleados rotativos (sin horario fijo)
    $rotativeEmployees = array_filter($employees, function($emp) {
        return empty($emp['fixed_schedule']) && $emp['active'];
    });
    
    $rotativeEmployees = array_values($rotativeEmployees); // Reindexar array
    $count = count($rotativeEmployees);
    
    if ($count < 4) {
        return ['error' => 'Se necesitan al menos 4 empleados activos para la rotación 2x2'];
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
        // Si no se especifica mes, usar 4 semanas desde startDate
        $endDate = clone $currentDate;
        $endDate->add(new DateInterval('P4W'));
    }
    
    $currentDate = new DateTime($startDate);
    
    // Determinar posición inicial basada en el último horario guardado
    $lastSchedule = loadSchedules();
    $lastPosition = 0;
    
    if (!empty($lastSchedule)) {
        $lastEntry = end($lastSchedule);
        $lastPosition = $lastEntry['rotation_position'] ?? 0;
    }
    
    // Generar horario
    while ($currentDate <= $endDate) {
        // Rotación cada 2 días
        $rotationGroup = floor(($currentDate->diff(new DateTime($startDate))->days) / 2) % $count;
        $rotationPosition = ($lastPosition + $rotationGroup) % $count;
        
        for ($i = 0; $i < $count; $i++) {
            $employeeIndex = ($rotationPosition + $i) % $count;
            $shiftIndex = $i % 4;
            
            $day1 = clone $currentDate;
            $day2 = (clone $currentDate)->add(new DateInterval('P1D'));
            
            // Solo agregar si estamos dentro del rango de fechas
            if ($day1 <= $endDate) {
                $schedule[] = [
                    'employee' => $rotativeEmployees[$employeeIndex]['name'],
                    'date' => $day1->format('Y-m-d'),
                    'shift' => $shifts[$shiftIndex]['name'],
                    'start' => $shifts[$shiftIndex]['start'],
                    'end' => $shifts[$shiftIndex]['end'],
                    'rotation_position' => $rotationPosition
                ];
            }
            
            if ($day2 <= $endDate) {
                $schedule[] = [
                    'employee' => $rotativeEmployees[$employeeIndex]['name'],
                    'date' => $day2->format('Y-m-d'),
                    'shift' => $shifts[$shiftIndex]['name'],
                    'start' => $shifts[$shiftIndex]['start'],
                    'end' => $shifts[$shiftIndex]['end'],
                    'rotation_position' => $rotationPosition
                ];
            }
        }
        
        $currentDate->add(new DateInterval('P2D'));
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

// Traducir día de la semana a español
function translateDayToSpanish($englishDay) {
    $days = [
        'Monday' => 'Lunes',
        'Tuesday' => 'Martes',
        'Wednesday' => 'Miércoles',
        'Thursday' => 'Jueves',
        'Friday' => 'Viernes',
        'Saturday' => 'Sábado',
        'Sunday' => 'Domingo'
    ];
    return $days[$englishDay] ?? $englishDay;
}

// Formatear fecha en español
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

// Organizar horarios por semana para vista de calendario
function organizeScheduleByWeek($schedule) {
    $weeks = [];
    $currentWeek = [];
    $currentWeekNumber = null;
    
    foreach ($schedule as $entry) {
        $date = new DateTime($entry['date']);
        $weekNumber = $date->format('W-Y');
        $dayOfWeek = $date->format('N'); // 1 (Lunes) a 7 (Domingo)
        
        if ($weekNumber !== $currentWeekNumber) {
            if (!empty($currentWeek)) {
                $weeks[] = $currentWeek;
            }
            $currentWeek = array_fill(1, 7, []); // Inicializar semana
            $currentWeekNumber = $weekNumber;
        }
        
        $currentWeek[$dayOfWeek][] = $entry;
    }
    
    if (!empty($currentWeek)) {
        $weeks[] = $currentWeek;
    }
    
    return $weeks;
}

// Traducir mes a español
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

// Obtener último día del mes
function getLastDayOfMonth($date) {
    $d = new DateTime($date);
    $d->modify('last day of this month');
    return $d;
}