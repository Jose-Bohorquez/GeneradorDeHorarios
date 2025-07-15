<?php
require_once 'config.php';

// Función para cargar empleados
function loadEmployees() {
    $file = DATA_DIR . '/employees.json';
    if (file_exists($file)) {
        $data = json_decode(file_get_contents($file), true);
        return $data['employees'] ?? [];
    }
    return [];
}

// Función para guardar empleados
function saveEmployees($employees) {
    $file = DATA_DIR . '/employees.json';
    $data = ['employees' => $employees];
    return file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
}

// Función para cargar horarios
function loadSchedules() {
    $file = DATA_DIR . '/schedules.json';
    if (file_exists($file)) {
        $data = json_decode(file_get_contents($file), true);
        return $data['schedules'] ?? [];
    }
    return [];
}

// Función para guardar horarios
function saveSchedules($schedules) {
    $file = DATA_DIR . '/schedules.json';
    $data = ['schedules' => $schedules];
    return file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
}

// Función principal para generar horario rotativo
function generateRotativeSchedule($employees, $shiftType = '8hours', $startDate = null, $month = null) {
    // Separar empleados por tipo
    $rotativeEmployees = array_filter($employees, function($emp) {
        return $emp['active'] && empty($emp['fixed_schedule']);
    });
    
    $supervisorEmployees = array_filter($employees, function($emp) {
        return $emp['active'] && !empty($emp['supervisor']);
    });
    
    $fixedEmployees = array_filter($employees, function($emp) {
        return $emp['active'] && !empty($emp['fixed_schedule']);
    });
    
    $count = count($rotativeEmployees);
    
    // Configurar turnos según el tipo
    if ($shiftType === '6hours') {
        // NUEVO SISTEMA DE 6 HORAS - LEY 2101 DE 2021
        // Máximo 44 horas semanales = 7 días × 6 horas = 42 horas (cumple la ley)
        $shifts = [
            'Mañana' => ['start' => '06:00', 'end' => '12:00'],      // 6 horas
            'Tarde' => ['start' => '12:00', 'end' => '18:00'],       // 6 horas  
            'Noche' => ['start' => '18:00', 'end' => '00:00'],       // 6 horas
            'Madrugada' => ['start' => '00:00', 'end' => '06:00']    // 6 horas
        ];
        $maxRotativeEmployees = 5; // 5 empleados rotativos para mejor cobertura
        $shiftNames = ['Mañana', 'Tarde', 'Noche', 'Madrugada'];
        $cycleLength = 10; // Ciclo de 10 días para 5 empleados
    } else {
        // SISTEMA DE 8 HORAS (se mantiene igual)
        $shifts = [
            'Mañana' => ['start' => '06:00', 'end' => '14:00'],
            'Tarde' => ['start' => '14:00', 'end' => '22:00'],
            'Noche' => ['start' => '22:00', 'end' => '06:00']
        ];
        $maxRotativeEmployees = 4;
        $shiftNames = ['Mañana', 'Tarde', 'Noche'];
        $cycleLength = 8; // Ciclo de 8 días: 6 trabajando + 2 descanso
    }
    
    if ($count < $maxRotativeEmployees) {
        return ['error' => "Se necesitan al menos " . $maxRotativeEmployees . " empleados rotativos para el sistema de " . ($shiftType === '6hours' ? '6' : '8') . " horas"];
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
    
    // PATRÓN DE ROTACIÓN MEJORADO PARA LEY 2101
    if ($shiftType === '6hours') {
        // PATRÓN PARA 5 EMPLEADOS - COBERTURA 24/7 GARANTIZADA
        // Cada empleado trabaja 8 días consecutivos, luego 2 días de descanso
        // Los descansos están escalonados para mantener cobertura completa
        
        $rotationPattern = [];
        
        // Empleado 0 (Dilan): M,M,T,T,N,N,Mad,Mad,D,D
        $rotationPattern[0] = [0, 0, 1, 1, 2, 2, 3, 3, -1, -1];
        
        // Empleado 1 (Diego): T,T,N,N,Mad,Mad,D,D,M,M
        $rotationPattern[1] = [1, 1, 2, 2, 3, 3, -1, -1, 0, 0];
        
        // Empleado 2 (Tania): N,N,Mad,Mad,D,D,M,M,T,T
        $rotationPattern[2] = [2, 2, 3, 3, -1, -1, 0, 0, 1, 1];
        
        // Empleado 3 (Carlos): Mad,Mad,D,D,M,M,T,T,N,N
        $rotationPattern[3] = [3, 3, -1, -1, 0, 0, 1, 1, 2, 2];
        
        // Empleado 4 (Brayan): D,D,M,M,T,T,N,N,Mad,Mad
        $rotationPattern[4] = [-1, -1, 0, 0, 1, 1, 2, 2, 3, 3];
        
    } else {
        // Patrón para 3 turnos de 8 horas (se mantiene igual)
        $rotationPattern = [];
        
        // Empleado 0: Mañana(2) -> Tarde(2) -> Noche(2) -> Descanso(2)
        $rotationPattern[0] = [0, 0, 1, 1, 2, 2, -1, -1];
        
        // Empleado 1: Tarde(2) -> Noche(2) -> Descanso(2) -> Mañana(2)
        $rotationPattern[1] = [1, 1, 2, 2, -1, -1, 0, 0];
        
        // Empleado 2: Noche(2) -> Descanso(2) -> Mañana(2) -> Tarde(2)
        $rotationPattern[2] = [2, 2, -1, -1, 0, 0, 1, 1];
        
        // Empleado 3: Descanso(2) -> Mañana(2) -> Tarde(2) -> Noche(2)
        $rotationPattern[3] = [-1, -1, 0, 0, 1, 1, 2, 2];
    }
    
    // Generar horario día por día
    $currentDate = new DateTime($startDate);
    
    while ($currentDate <= $endDate) {
        $daysSinceStart = $currentDate->diff(new DateTime($startDate))->days;
        $cycleDay = $daysSinceStart % $cycleLength;
        
        $dailyAssignments = [];
        
        // Asignar cada turno
        for ($shiftIndex = 0; $shiftIndex < count($shiftNames); $shiftIndex++) {
            $shiftName = $shiftNames[$shiftIndex];
            $assignedEmployee = null;
            
            // Buscar qué empleado debe trabajar este turno hoy
            for ($empIndex = 0; $empIndex < count($activeRotativeEmployees); $empIndex++) {
                if (isset($rotationPattern[$empIndex][$cycleDay]) && $rotationPattern[$empIndex][$cycleDay] === $shiftIndex) {
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
                'Mañana' => 1, 'Tarde' => 2, 'Noche' => 3, 'Madrugada' => 4,
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

// Función para formatear fecha en español
function formatDateSpanish($date) {
    $months = [
        'January' => 'Enero', 'February' => 'Febrero', 'March' => 'Marzo',
        'April' => 'Abril', 'May' => 'Mayo', 'June' => 'Junio',
        'July' => 'Julio', 'August' => 'Agosto', 'September' => 'Septiembre',
        'October' => 'Octubre', 'November' => 'Noviembre', 'December' => 'Diciembre'
    ];
    
    $days = [
        'Monday' => 'Lunes', 'Tuesday' => 'Martes', 'Wednesday' => 'Miércoles',
        'Thursday' => 'Jueves', 'Friday' => 'Viernes', 'Saturday' => 'Sábado', 'Sunday' => 'Domingo'
    ];
    
    $dateObj = new DateTime($date);
    $dayName = $days[$dateObj->format('l')];
    $monthName = $months[$dateObj->format('F')];
    
    return $dayName . ', ' . $dateObj->format('j') . ' de ' . $monthName;
}

// Función para traducir mes a español
function translateMonthToSpanish($monthYear) {
    $months = [
        'January' => 'Enero', 'February' => 'Febrero', 'March' => 'Marzo',
        'April' => 'Abril', 'May' => 'Mayo', 'June' => 'Junio',
        'July' => 'Julio', 'August' => 'Agosto', 'September' => 'Septiembre',
        'October' => 'Octubre', 'November' => 'Noviembre', 'December' => 'Diciembre'
    ];
    
    return str_replace(array_keys($months), array_values($months), $monthYear);
}

// Función para organizar horario por fechas
function organizeScheduleByDate($schedule) {
    $organized = [];
    foreach ($schedule as $entry) {
        $date = $entry['date'];
        if (!isset($organized[$date])) {
            $organized[$date] = [];
        }
        $organized[$date][] = $entry;
    }
    return $organized;
}

// Obtener último día del mes
function getLastDayOfMonth($date) {
    $d = new DateTime($date);
    $d->modify('last day of this month');
    return $d;
}

// Calcular estadísticas de horas trabajadas por empleado
function calculateEmployeeHoursStats($schedule) {
    $stats = [];
    
    foreach ($schedule as $entry) {
        $employee = $entry['employee'];
        $shift = $entry['shift'];
        
        // Inicializar empleado si no existe
        if (!isset($stats[$employee])) {
            $stats[$employee] = [
                'Mañana' => 0,
                'Tarde' => 0,
                'Noche' => 0,
                'Madrugada' => 0,
                'Oficina' => 0,
                'Supervisor' => 0,
                'Descanso' => 0,
                'total_hours' => 0,        // Cambiado de total_horas
                'work_days' => 0,          // Cambiado de dias_trabajados
                'rest_days' => 0,          // Cambiado de dias_descanso
                'avg_hours_per_day' => 0   // Agregado
            ];
        }
        
        // Calcular horas según el turno
        $horas = 0;
        switch ($shift) {
            case 'Mañana':
            case 'Tarde':
            case 'Noche':
                // Detectar si es turno de 6 u 8 horas basado en horarios
                if ($entry['start'] && $entry['end']) {
                    $start = new DateTime($entry['start']);
                    $end = new DateTime($entry['end']);
                    if ($end < $start) {
                        $end->add(new DateInterval('P1D'));
                    }
                    $horas = $start->diff($end)->h;
                } else {
                    $horas = 8; // Por defecto 8 horas si no hay horarios específicos
                }
                $stats[$employee]['work_days']++;
                break;
            case 'Madrugada':
                $horas = 6; // Turno de madrugada siempre es de 6 horas
                $stats[$employee]['work_days']++;
                break;
            case 'Oficina':
            case 'Supervisor':
                $horas = 10; // Horario de oficina
                $stats[$employee]['work_days']++;
                break;
            case 'Descanso':
                $horas = 0;
                $stats[$employee]['rest_days']++;
                break;
        }
        
        $stats[$employee][$shift] += $horas;
        $stats[$employee]['total_hours'] += $horas;
    }
    
    // Calcular promedio de horas por día
    foreach ($stats as $employee => &$stat) {
        if ($stat['work_days'] > 0) {
            $stat['avg_hours_per_day'] = $stat['total_hours'] / $stat['work_days'];
        } else {
            $stat['avg_hours_per_day'] = 0;
        }
    }
    
    return $stats;
}

// Obtener estadísticas generales del equipo
function getTeamStats($schedule) {
    $stats = [
        'total_empleados' => 0,
        'total_horas_mes' => 0,
        'promedio_horas_empleado' => 0,
        'turnos_cubiertos' => 0,
        'dias_mes' => 0
    ];
    
    $employeeStats = calculateEmployeeHoursStats($schedule);
    $stats['total_empleados'] = count($employeeStats);
    
    foreach ($employeeStats as $empStats) {
        $stats['total_horas_mes'] += $empStats['total_hours']; // Cambiado de total_horas
    }
    
    if ($stats['total_empleados'] > 0) {
        $stats['promedio_horas_empleado'] = round($stats['total_horas_mes'] / $stats['total_empleados'], 1);
    }
    
    // Contar días únicos en el horario
    $uniqueDates = array_unique(array_column($schedule, 'date'));
    $stats['dias_mes'] = count($uniqueDates);
    
    // Contar turnos cubiertos (excluyendo descansos)
    $workShifts = array_filter($schedule, function($entry) {
        return !in_array($entry['shift'], ['Descanso']);
    });
    $stats['turnos_cubiertos'] = count($workShifts);
    
    return $stats;
}

// Obtener datos para gráficos
function getChartData($schedule) {
    $employeeStats = calculateEmployeeHoursStats($schedule);
    
    // Contar distribución de turnos
    $shiftDistribution = [];
    foreach ($schedule as $entry) {
        $shift = $entry['shift'];
        if ($shift !== 'Descanso') { // No contar descansos
            if (!isset($shiftDistribution[$shift])) {
                $shiftDistribution[$shift] = 0;
            }
            $shiftDistribution[$shift]++;
        }
    }
    
    $chartData = [
        'employees' => [],
        'shifts' => ['Mañana', 'Tarde', 'Noche', 'Madrugada', 'Oficina', 'Supervisor'],
        'colors' => [
            'Mañana' => '#FFD700',
            'Tarde' => '#FF6B35', 
            'Noche' => '#4A90E2',
            'Madrugada' => '#8E44AD',
            'Oficina' => '#50C878',
            'Supervisor' => '#9B59B6'
        ],
        'hoursData' => [],
        'totalHours' => [],
        'shift_distribution' => $shiftDistribution
    ];
    
    foreach ($employeeStats as $employee => $stats) {
        $chartData['employees'][] = $employee;
        $chartData['totalHours'][] = $stats['total_hours']; // Cambiado de total_horas
        
        $employeeHours = [];
        foreach ($chartData['shifts'] as $shift) {
            $employeeHours[] = $stats[$shift] ?? 0;
        }
        $chartData['hoursData'][] = $employeeHours;
    }
    
    return $chartData;
}

// Organizar horario por semanas
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

// Función para traducir día a español
function translateDayToSpanish($englishDay) {
    $days = [
        'Monday' => 'Lunes', 'Tuesday' => 'Martes', 'Wednesday' => 'Miércoles',
        'Thursday' => 'Jueves', 'Friday' => 'Viernes', 'Saturday' => 'Sábado',
        'Sunday' => 'Domingo'
    ];
    return $days[$englishDay] ?? $englishDay;
}
?>