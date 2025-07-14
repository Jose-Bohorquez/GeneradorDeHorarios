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

// Generar horarios rotativos 2x2 con configuración de turnos
function generateRotativeSchedule($employees, $startDate, $month = null, $shiftType = '8hours') {
    // Separar empleados por tipo
    $rotativeEmployees = array_filter($employees, function($emp) {
        return empty($emp['fixed_schedule']) && empty($emp['supervisor']) && $emp['active'];
    });
    
    $supervisorEmployees = array_filter($employees, function($emp) {
        return !empty($emp['supervisor']) && $emp['active'];
    });
    
    $fixedEmployees = array_filter($employees, function($emp) {
        return !empty($emp['fixed_schedule']) && empty($emp['supervisor']) && $emp['active'];
    });
    
    $rotativeEmployees = array_values($rotativeEmployees); // Reindexar array
    $count = count($rotativeEmployees);
    
    // Definir turnos según modalidad
    if ($shiftType === '6hours') {
        $shifts = [
            'Madrugada' => ['start' => '00:00', 'end' => '06:00'],
            'Mañana' => ['start' => '06:00', 'end' => '12:00'],
            'Tarde' => ['start' => '12:00', 'end' => '18:00'],
            'Noche' => ['start' => '18:00', 'end' => '00:00']
        ];
        $maxRotativeEmployees = 5;
        $shiftNames = ['Madrugada', 'Mañana', 'Tarde', 'Noche'];
    } else {
        $shifts = [
            'Mañana' => ['start' => '06:00', 'end' => '14:00'],
            'Tarde' => ['start' => '14:00', 'end' => '22:00'],
            'Noche' => ['start' => '22:00', 'end' => '06:00']
        ];
        $maxRotativeEmployees = 4;
        $shiftNames = ['Mañana', 'Tarde', 'Noche'];
    }
    
    if ($count < count($shifts)) {
        return ['error' => "Se necesitan al menos " . count($shifts) . " empleados rotativos"];
    }
    
    // Seleccionar empleados para rotación
    $activeRotativeEmployees = array_slice($rotativeEmployees, 0, $maxRotativeEmployees);
    $officeEmployees = array_slice($rotativeEmployees, $maxRotativeEmployees);
    
    $schedule = [];
    
    // Determinar rango de fechas
    if ($month) {
        $monthDate = new DateTime($month);
        $monthDate->modify('first day of this month');
        $startDate = $monthDate->format('Y-m-d');
        $endDate = clone $monthDate;
        $endDate->modify('last day of this month');
    } else {
        $currentDate = new DateTime($startDate);
        $endDate = clone $currentDate;
        $endDate->add(new DateInterval('P4W'));
    }
    
    // IMPLEMENTACIÓN CORRECTA DEL PATRÓN 2x2
    // Patrón de rotación de 8 días para cada empleado:
    // - Días 1-2: Turno A
    // - Días 3-4: Turno B  
    // - Días 5-6: Turno C
    // - Días 7-8: Descanso
    
    // Definir el patrón de rotación para 4 empleados en turnos de 8 horas
    $rotationPattern = [];
    $numShifts = count($shiftNames);
    $cycleLength = 8;
    
    // Crear patrón para cada empleado
    for ($empIndex = 0; $empIndex < $maxRotativeEmployees; $empIndex++) {
        $pattern = [];
        
        // Cada empleado tiene un offset de 2 días
        $offset = $empIndex * 2;
        
        for ($day = 0; $day < $cycleLength; $day++) {
            $adjustedDay = ($day + $offset) % $cycleLength;
            
            if ($adjustedDay < 2) {
                // Días 0-1: Primer turno
                $shiftIndex = 0;
            } elseif ($adjustedDay < 4) {
                // Días 2-3: Segundo turno
                $shiftIndex = 1;
            } elseif ($adjustedDay < 6) {
                // Días 4-5: Tercer turno
                $shiftIndex = 2;
            } else {
                // Días 6-7: Descanso
                $shiftIndex = -1;
            }
            
            $pattern[$day] = $shiftIndex;
        }
        
        $rotationPattern[$empIndex] = $pattern;
    }
    
    // Generar horario día por día
    $currentDate = new DateTime($startDate);
    
    while ($currentDate <= $endDate) {
        $daysSinceStart = $currentDate->diff(new DateTime($startDate))->days;
        $cycleDay = $daysSinceStart % $cycleLength;
        
        $dailyAssignments = [];
        
        // Asignar cada turno
        for ($shiftIndex = 0; $shiftIndex < $numShifts; $shiftIndex++) {
            $shiftName = $shiftNames[$shiftIndex];
            $assignedEmployee = null;
            
            // Buscar qué empleado debe trabajar este turno hoy
            for ($empIndex = 0; $empIndex < count($activeRotativeEmployees); $empIndex++) {
                if ($rotationPattern[$empIndex][$cycleDay] === $shiftIndex) {
                    $assignedEmployee = $activeRotativeEmployees[$empIndex];
                    break;
                }
            }
            
            // Asignar el turno
            if ($assignedEmployee) {
                $schedule[] = [
                    'employee' => $assignedEmployee['name'],
                    'date' => $currentDate->format('Y-m-d'),
                    'shift' => $shiftName,
                    'start' => $shifts[$shiftName]['start'],
                    'end' => $shifts[$shiftName]['end'],
                    'rotation_position' => $cycleDay
                ];
                
                $dailyAssignments[] = $assignedEmployee['name'];
            }
        }
        
        // Asignar empleados en descanso
        for ($empIndex = 0; $empIndex < count($activeRotativeEmployees); $empIndex++) {
            $employee = $activeRotativeEmployees[$empIndex];
            
            if (!in_array($employee['name'], $dailyAssignments)) {
                $schedule[] = [
                    'employee' => $employee['name'],
                    'date' => $currentDate->format('Y-m-d'),
                    'shift' => 'Descanso',
                    'start' => null,
                    'end' => null,
                    'rotation_position' => $cycleDay
                ];
            }
        }
        
        $currentDate->add(new DateInterval('P1D'));
    }
    
    // Agregar empleados de oficina
    $currentDate = new DateTime($startDate);
    while ($currentDate <= $endDate) {
        foreach ($officeEmployees as $employee) {
            $schedule[] = [
                'employee' => $employee['name'],
                'date' => $currentDate->format('Y-m-d'),
                'shift' => 'Oficina',
                'start' => '07:00',
                'end' => '17:00',
                'rotation_position' => null
            ];
        }
        $currentDate->add(new DateInterval('P1D'));
    }
    
    // Agregar supervisores
    $currentDate = new DateTime($startDate);
    while ($currentDate <= $endDate) {
        foreach ($supervisorEmployees as $employee) {
            $schedule[] = [
                'employee' => $employee['name'],
                'date' => $currentDate->format('Y-m-d'),
                'shift' => 'Supervisor',
                'start' => '07:00',
                'end' => '17:00',
                'rotation_position' => null
            ];
        }
        $currentDate->add(new DateInterval('P1D'));
    }
    
    // Agregar empleados con horario fijo
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
    
    // Ordenar por fecha y prioridad
    usort($schedule, function($a, $b) {
        $dateCompare = strcmp($a['date'], $b['date']);
        if ($dateCompare === 0) {
            $shiftPriority = [
                'Madrugada' => 1, 'Mañana' => 2, 'Tarde' => 3, 'Noche' => 4, 
                'Supervisor' => 5, 'Oficina' => 6, 'Descanso' => 7
            ];
            $priorityA = $shiftPriority[$a['shift']] ?? 8;
            $priorityB = $shiftPriority[$b['shift']] ?? 8;
            return $priorityA - $priorityB;
        }
        return $dateCompare;
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