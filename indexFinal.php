<?php
require_once 'config.php';
require_once 'functions.php';

// Variables para mensajes
$error = '';
$success = '';
$update = '';

// Cargar empleados y horarios
$employees = loadEmployees();
$schedules = loadSchedules();

// Procesar formulario de empleados
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_employees'])) {
        $updatedEmployees = [];
        
        foreach ($employees as $index => $employee) {
            $isActive = isset($_POST['active'][$index]);
            $employeeType = $_POST['employee_type'][$index] ?? 'rotative';
            $isSupervisor = isset($_POST['supervisor'][$index]);
            
            $updatedEmployee = [
                'name' => $employee['name'],
                'active' => $isActive
            ];
            
            if ($isSupervisor) {
                $updatedEmployee['supervisor'] = true;
            }
            
            if ($employeeType === 'fixed' && $isActive) {
                $startTime = $_POST['start_time'][$index] ?? '';
                $endTime = $_POST['end_time'][$index] ?? '';
                
                if (!empty($startTime) && !empty($endTime)) {
                    $updatedEmployee['fixed_schedule'] = [$startTime, $endTime];
                } else {
                    $error = "Debe especificar horarios de inicio y fin para empleados con horario fijo.";
                    break;
                }
            }
            
            $updatedEmployees[] = $updatedEmployee;
        }
        
        if (empty($error)) {
            if (saveEmployees($updatedEmployees)) {
                $success = "Empleados actualizados correctamente.";
                $employees = $updatedEmployees;
            } else {
                $error = "Error al guardar los empleados.";
            }
        }
    }
    
    // Procesar generación de horario
    if (isset($_POST['generate_schedule']) || isset($_POST['preview_schedule'])) {
        $selectedMonth = $_POST['month'] ?? date('Y-m');
        $shiftType = $_POST['shift_type'] ?? '8hours';
        
        if (!empty($selectedMonth)) {
            $monthDate = $selectedMonth . '-01';
            $schedule = generateRotativeSchedule($employees, $monthDate, $monthDate, $shiftType);
            
            if (isset($schedule['error'])) {
                $error = $schedule['error'];
            } else {
                if (isset($_POST['generate_schedule'])) {
                    // Guardar horario
                    $scheduleData = [
                        'month' => $selectedMonth,
                        'shift_type' => $shiftType,
                        'generated_date' => date('Y-m-d H:i:s'),
                        'schedule' => $schedule,
                        'rotation_position' => $schedule[0]['rotation_position'] ?? 0
                    ];
                    
                    $schedules[] = $scheduleData;
                    
                    if (saveSchedules($schedules)) {
                        $success = "Horario generado y guardado correctamente para " . translateMonthToSpanish(date('F Y', strtotime($monthDate))) . " con turnos de " . ($shiftType === '6hours' ? '6 horas' : '8 horas');
                    } else {
                        $error = "Error al guardar el horario.";
                    }
                } else {
                    // Solo preview
                    $previewSchedule = $schedule;
                    $update = "Vista previa del horario para " . translateMonthToSpanish(date('F Y', strtotime($monthDate))) . " con turnos de " . ($shiftType === '6hours' ? '6 horas' : '8 horas');
                }
            }
        } else {
            $error = "Debe seleccionar un mes.";
        }
    }
}

// Obtener horario generado para mostrar
$generatedSchedule = [];
if (isset($_POST['preview_schedule']) && isset($previewSchedule)) {
    $generatedSchedule = $previewSchedule;
} elseif (!empty($schedules)) {
    $latestSchedule = end($schedules);
    $generatedSchedule = $latestSchedule;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generador de Horarios NOC 2x2</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#f0f9ff',
                            100: '#e0f2fe',
                            200: '#bae6fd',
                            300: '#7dd3fc',
                            400: '#38bdf8',
                            500: '#0ea5e9',
                            600: '#0284c7',
                            700: '#0369a1',
                            800: '#075985',
                            900: '#0c4a6e',
                        },
                        secondary: {
                            50: '#f8fafc',
                            100: '#f1f5f9',
                            200: '#e2e8f0',
                            300: '#cbd5e1',
                            400: '#94a3b8',
                            500: '#64748b',
                            600: '#475569',
                            700: '#334155',
                            800: '#1e293b',
                            900: '#0f172a',
                        }
                    }
                }
            }
        }
    </script>
    
    <style>
        .employee-card {
            transition: all 0.3s ease;
        }
        
        .employee-card:hover {
            transform: translateY(-2px);
        }
        
        .employee-card.inactive {
            opacity: 0.6;
        }
        
        .time-inputs {
            display: none;
        }
        
        .time-inputs.show {
            display: block;
        }
        
        .calendar-day {
            min-height: 120px;
        }
        
        .shift-badge {
            font-size: 0.75rem;
            margin-bottom: 5px;
        }
        
        .header-gradient {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .badge-container {
            min-height: 30px;
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Header -->
    <header class="header-gradient text-white py-8 mb-6">
        <div class="container mx-auto px-4">
            <div class="text-center">
                <h1 class="text-4xl md:text-5xl font-bold mb-4">
                    <i class="fas fa-calendar-alt mr-4"></i>Generador de Horarios NOC 2x2
                </h1>
                <p class="text-xl opacity-90">Sistema de rotación de turnos para equipos de monitoreo</p>
            </div>
        </div>
        
        <!-- Mensajes de alerta -->
        <?php if ($error): ?>
            <div class="container mx-auto px-4 mt-6">
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg relative" role="alert">
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-triangle mr-2"></i>
                        <span><?= htmlspecialchars($error) ?></span>
                        <button type="button" class="absolute top-0 bottom-0 right-0 px-4 py-3" onclick="this.parentElement.parentElement.style.display='none'">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="container mx-auto px-4 mt-6">
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg relative" role="alert">
                    <div class="flex items-center">
                        <i class="fas fa-check-circle mr-2"></i>
                        <span><?= htmlspecialchars($success) ?></span>
                        <button type="button" class="absolute top-0 bottom-0 right-0 px-4 py-3" onclick="this.parentElement.parentElement.style.display='none'">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if ($update): ?>
            <div class="container mx-auto px-4 mt-6">
                <div class="bg-blue-100 border border-blue-400 text-blue-700 px-4 py-3 rounded-lg relative" role="alert">
                    <div class="flex items-center">
                        <i class="fas fa-info-circle mr-2"></i>
                        <span><?= htmlspecialchars($update) ?></span>
                        <button type="button" class="absolute top-0 bottom-0 right-0 px-4 py-3" onclick="this.parentElement.parentElement.style.display='none'">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </header>

    <div class="container mx-auto px-4">
        <!-- Employee Management Section -->
        <section class="mb-8">
            <div class="bg-white rounded-lg shadow-lg overflow-hidden">
                <div class="bg-gradient-to-r from-indigo-500 to-purple-600 text-white p-6">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <i class="fas fa-users text-2xl mr-4"></i>
                            <div>
                                <h2 class="text-2xl font-bold">Gestionar Empleados</h2>
                                <p class="text-indigo-100">Configura el tipo de empleado, horarios y permisos</p>
                            </div>
                        </div>
                        <div class="flex space-x-3">
                            <span class="bg-white bg-opacity-20 px-3 py-1 rounded-full text-sm" id="activeCount">
                                <i class="fas fa-user-check mr-1"></i>
                                <?= count(array_filter($employees, fn($emp) => $emp['active'])) ?> Activos
                            </span>
                            <span class="bg-white bg-opacity-20 px-3 py-1 rounded-full text-sm">
                                <i class="fas fa-users mr-1"></i>
                                <?= count($employees) ?> Total
                            </span>
                        </div>
                    </div>
                </div>
                
                <div class="p-6">
                    <!-- Estadísticas rápidas -->
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8" id="statsContainer">
                        <div class="flex items-center justify-center p-4 bg-green-50 rounded-lg">
                            <i class="fas fa-user-check text-green-500 text-2xl mr-3"></i>
                            <div class="text-center">
                                <div class="text-2xl font-bold text-green-600" id="activeEmployeesCount"><?= count(array_filter($employees, fn($emp) => $emp['active'])) ?></div>
                                <div class="text-sm text-gray-600">Empleados Activos</div>
                            </div>
                        </div>
                        <div class="flex items-center justify-center p-4 bg-blue-50 rounded-lg">
                            <i class="fas fa-sync-alt text-blue-500 text-2xl mr-3"></i>
                            <div class="text-center">
                                <div class="text-2xl font-bold text-blue-600" id="rotativeEmployeesCount"><?= count(array_filter($employees, fn($emp) => empty($emp['fixed_schedule']) && $emp['active'])) ?></div>
                                <div class="text-sm text-gray-600">Rotativos</div>
                            </div>
                        </div>
                        <div class="flex items-center justify-center p-4 bg-indigo-50 rounded-lg">
                            <i class="fas fa-clock text-indigo-500 text-2xl mr-3"></i>
                            <div class="text-center">
                                <div class="text-2xl font-bold text-indigo-600" id="fixedEmployeesCount"><?= count(array_filter($employees, fn($emp) => !empty($emp['fixed_schedule']) && $emp['active'])) ?></div>
                                <div class="text-sm text-gray-600">Horario Fijo</div>
                            </div>
                        </div>
                        <div class="flex items-center justify-center p-4 bg-yellow-50 rounded-lg">
                            <i class="fas fa-user-tie text-yellow-500 text-2xl mr-3"></i>
                            <div class="text-center">
                                <div class="text-2xl font-bold text-yellow-600" id="supervisorEmployeesCount"><?= count(array_filter($employees, fn($emp) => !empty($emp['supervisor']) && $emp['active'])) ?></div>
                                <div class="text-sm text-gray-600">Supervisores</div>
                            </div>
                        </div>
                    </div>

                    <!-- Formulario de empleados -->
                    <form method="POST" id="employeeForm">
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            <?php foreach ($employees as $index => $employee): ?>
                                <div class="employee-card bg-white border border-gray-200 rounded-lg p-6 shadow-md hover:shadow-lg <?= !$employee['active'] ? 'inactive' : '' ?>" id="employeeCard_<?= $index ?>">
                                    <div class="flex justify-between items-start mb-4">
                                        <h3 class="text-lg font-semibold text-gray-800"><?= htmlspecialchars($employee['name']) ?></h3>
                                        <label class="relative inline-flex items-center cursor-pointer">
                                            <input type="checkbox" class="sr-only peer active-switch" 
                                                   name="active[<?= $index ?>]" 
                                                   id="active_<?= $index ?>" 
                                                   data-index="<?= $index ?>"
                                                   <?= $employee['active'] ? 'checked' : '' ?>>
                                            <div class="relative w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                                            <span class="ml-3 text-sm font-medium text-gray-700">Activo</span>
                                        </label>
                                    </div>

                                    <!-- Tipo de empleado -->
                                    <div class="mb-4">
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Tipo de empleado:</label>
                                        <div class="flex space-x-4">
                                            <label class="flex items-center">
                                                <input type="radio" class="form-radio text-blue-600 employee-type-radio" 
                                                       name="employee_type[<?= $index ?>]" 
                                                       value="rotative" 
                                                       id="rotative_<?= $index ?>" 
                                                       data-index="<?= $index ?>"
                                                       <?= empty($employee['fixed_schedule']) ? 'checked' : '' ?>>
                                                <span class="ml-2 text-sm text-gray-700">Rotativo</span>
                                            </label>
                                            <label class="flex items-center">
                                                <input type="radio" class="form-radio text-blue-600 employee-type-radio" 
                                                       name="employee_type[<?= $index ?>]" 
                                                       value="fixed" 
                                                       id="fixed_<?= $index ?>" 
                                                       data-index="<?= $index ?>"
                                                       <?= !empty($employee['fixed_schedule']) ? 'checked' : '' ?>>
                                                <span class="ml-2 text-sm text-gray-700">Horario Fijo</span>
                                            </label>
                                        </div>
                                    </div>

                                    <!-- Supervisor -->
                                    <div class="mb-4">
                                        <label class="flex items-center">
                                            <input type="checkbox" class="form-checkbox text-blue-600 supervisor-checkbox" 
                                                   name="supervisor[<?= $index ?>]" 
                                                   id="supervisor_<?= $index ?>" 
                                                   data-index="<?= $index ?>"
                                                   <?= !empty($employee['supervisor']) ? 'checked' : '' ?>>
                                            <span class="ml-2 text-sm text-gray-700">Es supervisor</span>
                                        </label>
                                    </div>

                                    <!-- Horarios fijos -->
                                    <div class="time-inputs <?= !empty($employee['fixed_schedule']) ? 'show' : '' ?> mt-4 p-4 bg-gray-50 rounded-lg border" id="timeInputs_<?= $index ?>">
                                        <label class="block text-sm font-medium text-gray-700 mb-3">Horario fijo:</label>
                                        <div class="grid grid-cols-2 gap-3">
                                            <div>
                                                <label class="block text-xs text-gray-600 mb-1">Inicio:</label>
                                                <input type="time" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" 
                                                       name="start_time[<?= $index ?>]" 
                                                       value="<?= $employee['fixed_schedule'][0] ?? '' ?>">
                                            </div>
                                            <div>
                                                <label class="block text-xs text-gray-600 mb-1">Fin:</label>
                                                <input type="time" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" 
                                                       name="end_time[<?= $index ?>]" 
                                                       value="<?= $employee['fixed_schedule'][1] ?? '' ?>">
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Badges de estado -->
                                    <div class="mt-4 badge-container" id="badgeContainer_<?= $index ?>">
                                        <!-- Los badges se actualizarán dinámicamente con JavaScript -->
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Botones de acción -->
                        <div class="flex space-x-4 mt-8">
                            <button type="submit" name="save_employees" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-6 rounded-lg transition duration-200">
                                <i class="fas fa-save mr-2"></i>Guardar Cambios
                            </button>
                            <button type="button" class="bg-gray-500 hover:bg-gray-600 text-white font-medium py-2 px-6 rounded-lg transition duration-200" onclick="resetForm()">
                                <i class="fas fa-undo mr-2"></i>Resetear
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </section>

        <!-- Schedule Generator Section -->
        <section class="mb-8">
            <div class="bg-white rounded-lg shadow-lg overflow-hidden">
                <div class="bg-blue-600 text-white p-6">
                    <h2 class="text-xl font-bold flex items-center">
                        <i class="fas fa-calendar-plus mr-3"></i>Generador de Horarios
                    </h2>
                </div>
                <div class="p-6">
                    <form method="POST" class="grid grid-cols-1 md:grid-cols-3 gap-6 items-end">
                        <div>
                            <label for="month" class="block text-sm font-medium text-gray-700 mb-2">Seleccionar Mes:</label>
                            <input type="month" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" 
                                   name="month" id="month" value="<?= date('Y-m') ?>" required>
                        </div>
                        <div>
                            <label for="shift_type" class="block text-sm font-medium text-gray-700 mb-2">Tipo de Turnos:</label>
                            <select class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" 
                                    name="shift_type" id="shift_type" required>
                                <option value="8hours">Turnos de 8 horas (Sistema anterior)</option>
                                <option value="6hours">Turnos de 6 horas (Nueva ley colombiana)</option>
                            </select>
                            <p class="mt-1 text-xs text-gray-500">
                                <i class="fas fa-info-circle mr-1"></i>
                                6 horas: 4 turnos diarios | 8 horas: 3 turnos + descanso
                            </p>
                        </div>
                        <div>
                            <div class="flex space-x-3">
                                <button type="submit" name="preview_schedule" class="bg-gray-600 hover:bg-gray-700 text-white font-medium py-2 px-4 rounded-lg transition duration-200">
                                    <i class="fas fa-eye mr-2"></i>Vista Previa
                                </button>
                                <button type="submit" name="generate_schedule" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg transition duration-200">
                                    <i class="fas fa-calendar-check mr-2"></i>Generar y Guardar
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </section>

        <!-- Generated Schedule Section -->
        <?php if (!empty($generatedSchedule)): ?>
            <section class="mb-8">
                <div class="bg-white rounded-lg shadow-lg overflow-hidden">
                    <div class="bg-green-600 text-white p-6">
                        <h2 class="text-xl font-bold flex items-center">
                            <i class="fas fa-calendar-alt mr-3"></i>Horario Generado
                            <?php if (isset($generatedSchedule['month'])): ?>
                                - <?= translateMonthToSpanish(date('F Y', strtotime($generatedSchedule['month'] . '-01'))) ?>
                            <?php endif; ?>
                        </h2>
                    </div>
                    <div class="p-6">
                        <?php 
                        $schedule = $generatedSchedule['schedule'] ?? $generatedSchedule;
                        $weeks = organizeScheduleByWeek($schedule);
                        
                        foreach ($weeks as $weekIndex => $week): 
                            $firstDay = null;
                            foreach ($week as $day) {
                                if (!empty($day)) {
                                    $firstDay = new DateTime($day[0]['date']);
                                    break;
                                }
                            }
                            
                            if ($firstDay):
                                $weekStart = clone $firstDay;
                                $weekStart->modify('monday this week');
                                $weekEnd = clone $weekStart;
                                $weekEnd->modify('+6 days');
                        ?>
                            <div class="mb-8">
                                <h3 class="text-lg font-semibold text-gray-700 mb-4">
                                    Semana <?= $weekIndex + 1 ?>: 
                                    <?= translateMonthToSpanish($weekStart->format('d F')) ?> - 
                                    <?= translateMonthToSpanish($weekEnd->format('d F Y')) ?>
                                </h3>
                                
                                <div class="grid grid-cols-7 gap-2">
                                    <?php 
                                    $days = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];
                                    for ($dayNum = 1; $dayNum <= 7; $dayNum++): 
                                        $daySchedule = $week[$dayNum] ?? [];
                                    ?>
                                        <div class="calendar-day border border-gray-200 rounded-lg p-3 bg-gray-50">
                                            <div class="font-semibold text-center mb-2 text-sm">
                                                <?= $days[$dayNum - 1] ?>
                                                <?php if (!empty($daySchedule)): ?>
                                                    <br><span class="text-xs text-gray-500"><?= date('d/m', strtotime($daySchedule[0]['date'])) ?></span>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <?php foreach ($daySchedule as $entry): ?>
                                                <?php
                                                $badgeClass = 'bg-gray-500 text-white';
                                                switch ($entry['shift']) {
                                                    case 'Madrugada': $badgeClass = 'bg-gray-800 text-white'; break;
                                                    case 'Mañana': $badgeClass = 'bg-yellow-400 text-gray-800'; break;
                                                    case 'Tarde': $badgeClass = 'bg-blue-400 text-white'; break;
                                                    case 'Noche': $badgeClass = 'bg-indigo-600 text-white'; break;
                                                    case 'Descanso': $badgeClass = 'bg-green-500 text-white'; break;
                                                    case 'Oficina': $badgeClass = 'bg-gray-100 text-gray-800 border border-gray-300'; break;
                                                }
                                                ?>
                                                <div class="shift-badge <?= $badgeClass ?> px-2 py-1 rounded text-xs mb-1 block">
                                                    <div class="font-semibold"><?= htmlspecialchars($entry['employee']) ?></div>
                                                    <div><?= htmlspecialchars($entry['shift']) ?></div>
                                                    <?php if ($entry['start'] && $entry['end']): ?>
                                                        <div class="text-xs opacity-90"><?= $entry['start'] ?> - <?= $entry['end'] ?></div>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endfor; ?>
                                </div>
                            </div>
                        <?php 
                            endif;
                        endforeach; 
                        ?>
                    </div>
                </div>
            </section>
        <?php endif; ?>
    </div>

    <!-- Footer -->
    <footer class="bg-gray-800 text-white text-center py-6 mt-12">
        <div class="container mx-auto px-4">
            <p>&copy; 2024 Generador de Horarios NOC 2x2. Sistema de gestión de turnos.</p>
        </div>
    </footer>
    
    <script>
        // Función para actualizar badges de un empleado
        function updateEmployeeBadges(index) {
            const badgeContainer = document.getElementById(`badgeContainer_${index}`);
            const isActive = document.getElementById(`active_${index}`).checked;
            const isFixed = document.getElementById(`fixed_${index}`).checked;
            const isSupervisor = document.getElementById(`supervisor_${index}`).checked;
            
            let badges = '';
            
            // Badge de estado activo/inactivo
            if (isActive) {
                badges += '<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800 mr-1 mb-1"><i class="fas fa-check mr-1"></i>Activo</span>';
            } else {
                badges += '<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-800 mr-1 mb-1"><i class="fas fa-pause mr-1"></i>Inactivo</span>';
            }
            
            // Badge de supervisor
            if (isSupervisor) {
                badges += '<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 mr-1 mb-1"><i class="fas fa-user-tie mr-1"></i>Supervisor</span>';
            }
            
            // Badge de tipo de empleado
            if (isFixed) {
                badges += '<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800 mr-1 mb-1"><i class="fas fa-clock mr-1"></i>Horario Fijo</span>';
            } else {
                badges += '<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800 mr-1 mb-1"><i class="fas fa-sync-alt mr-1"></i>Rotativo</span>';
            }
            
            badgeContainer.innerHTML = badges;
        }
        
        // Función para actualizar estadísticas
        function updateStatistics() {
            let activeCount = 0;
            let rotativeCount = 0;
            let fixedCount = 0;
            let supervisorCount = 0;
            
            document.querySelectorAll('.active-switch').forEach((checkbox, index) => {
                const isActive = checkbox.checked;
                const isFixed = document.getElementById(`fixed_${index}`).checked;
                const isSupervisor = document.getElementById(`supervisor_${index}`).checked;
                
                if (isActive) {
                    activeCount++;
                    if (isFixed) {
                        fixedCount++;
                    } else {
                        rotativeCount++;
                    }
                    if (isSupervisor) {
                        supervisorCount++;
                    }
                }
            });
            
            document.getElementById('activeEmployeesCount').textContent = activeCount;
            document.getElementById('rotativeEmployeesCount').textContent = rotativeCount;
            document.getElementById('fixedEmployeesCount').textContent = fixedCount;
            document.getElementById('supervisorEmployeesCount').textContent = supervisorCount;
            document.getElementById('activeCount').innerHTML = `<i class="fas fa-user-check mr-1"></i>${activeCount} Activos`;
        }
        
        // Función para actualizar apariencia de la tarjeta
        function updateCardAppearance(index) {
            const card = document.getElementById(`employeeCard_${index}`);
            const isActive = document.getElementById(`active_${index}`).checked;
            
            if (isActive) {
                card.classList.remove('inactive');
            } else {
                card.classList.add('inactive');
            }
        }
        
        // Inicializar cuando se carga la página
        document.addEventListener('DOMContentLoaded', function() {
            // Inicializar badges para todos los empleados
            document.querySelectorAll('.active-switch').forEach((checkbox, index) => {
                updateEmployeeBadges(index);
            });
            
            // Manejar cambios en el switch de activo/inactivo
            document.querySelectorAll('.active-switch').forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    const index = this.dataset.index;
                    updateEmployeeBadges(index);
                    updateCardAppearance(index);
                    updateStatistics();
                });
            });
            
            // Manejar cambios en el tipo de empleado
            document.querySelectorAll('.employee-type-radio').forEach(radio => {
                radio.addEventListener('change', function() {
                    const index = this.dataset.index;
                    const timeInputs = document.getElementById('timeInputs_' + index);
                    
                    if (this.value === 'fixed' && this.checked) {
                        timeInputs.classList.add('show');
                        // Hacer requeridos los campos de tiempo
                        const timeFields = timeInputs.querySelectorAll('input[type="time"]');
                        timeFields.forEach(field => field.required = true);
                    } else if (this.value === 'rotative' && this.checked) {
                        timeInputs.classList.remove('show');
                        // Quitar requerimiento de los campos de tiempo
                        const timeFields = timeInputs.querySelectorAll('input[type="time"]');
                        timeFields.forEach(field => {
                            field.required = false;
                            field.value = '';
                        });
                    }
                    
                    updateEmployeeBadges(index);
                    updateStatistics();
                });
            });
            
            // Manejar cambios en el checkbox de supervisor
            document.querySelectorAll('.supervisor-checkbox').forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    const index = this.dataset.index;
                    updateEmployeeBadges(index);
                    updateStatistics();
                });
            });
        });

        // Función para resetear el formulario
        function resetForm() {
            if (confirm('¿Está seguro de que desea resetear todos los cambios?')) {
                // Resetear el formulario
                document.getElementById('employeeForm').reset();
                
                // Ocultar todos los campos de tiempo
                document.querySelectorAll('.time-inputs').forEach(div => {
                    div.classList.remove('show');
                });
                
                // Quitar requerimiento de campos de tiempo
                document.querySelectorAll('.time-inputs input[type="time"]').forEach(field => {
                    field.required = false;
                });
                
                // Actualizar badges y estadísticas
                document.querySelectorAll('.active-switch').forEach((checkbox, index) => {
                    updateEmployeeBadges(index);
                    updateCardAppearance(index);
                });
                updateStatistics();
                
                // Recargar la página para restaurar valores originales
                setTimeout(() => {
                    location.reload();
                }, 500);
            }
        }

        // Validación antes de enviar
        document.getElementById('employeeForm').addEventListener('submit', function(e) {
            const fixedEmployees = document.querySelectorAll('input[value="fixed"]:checked');
            let hasError = false;
            
            fixedEmployees.forEach(radio => {
                const index = radio.dataset.index;
                const startTime = document.querySelector(`input[name="start_time[${index}]"]`).value;
                const endTime = document.querySelector(`input[name="end_time[${index}]"]`).value;
                
                if (!startTime || !endTime) {
                    hasError = true;
                }
            });
            
            if (hasError) {
                e.preventDefault();
                alert('Por favor, complete los horarios de inicio y fin para todos los empleados con horario fijo.');
            }
        });
    </script>
</body>
</html>