<?php
require_once 'functions.php';

$employees = loadEmployees();
$schedules = loadSchedules();
$error = '';
$generatedSchedule = [];
$startDate = date('Y-m-d');

// Procesar formulario (mantener igual que antes)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['generate'])) {
        $startDate = $_POST['start_date'] ?? date('Y-m-d');
        $month = $_POST['month'] ?? null;
        $generatedSchedule = generateRotativeSchedule($employees, $startDate, $month);
        
        if (isset($generatedSchedule['error'])) {
            $error = $generatedSchedule['error'];
            $generatedSchedule = [];
        }
    } elseif (isset($_POST['save'])) {
        $startDate = $_POST['start_date'] ?? date('Y-m-d');
        $month = $_POST['month'] ?? null;
        $generatedSchedule = generateRotativeSchedule($employees, $startDate, $month);
        
        if (!isset($generatedSchedule['error'])) {
            $schedules = array_merge($schedules, $generatedSchedule);
            saveSchedules($schedules);
            header('Location: index.php?success=1');
            exit;
        } else {
            $error = $generatedSchedule['error'];
        }
    } elseif (isset($_POST['update_employees'])) {
        $updatedEmployees = [];
        foreach ($employees as $emp) {
            $name = $emp['name'];
            $active = isset($_POST['active'][$name]) && $_POST['active'][$name] === 'on';
            $emp['active'] = $active;
            $updatedEmployees[] = $emp;
        }
        saveEmployees($updatedEmployees);
        $employees = $updatedEmployees;
    }
}

$success = isset($_GET['success']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generador de Horarios NOC 2x2</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        morning: {
                            light: '#dcfce7',
                            DEFAULT: '#4ade80',
                            dark: '#16a34a'
                        },
                        afternoon: {
                            light: '#fef9c3',
                            DEFAULT: '#facc15',
                            dark: '#ca8a04'
                        },
                        night: {
                            light: '#e9d5ff',
                            DEFAULT: '#a855f7',
                            dark: '#7e22ce'
                        },
                        office: {
                            light: '#dbeafe',
                            DEFAULT: '#3b82f6',
                            dark: '#1d4ed8'
                        },
                        rest: {
                            light: '#fee2e2',
                            DEFAULT: '#ef4444',
                            dark: '#b91c1c'
                        }
                    }
                }
            }
        }
    </script>
    <style>
        .shift-badge {
            transition: all 0.2s ease;
        }
        .shift-badge:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }
        @media (max-width: 768px) {
            .calendar-day {
                min-height: 100px;
            }
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="container mx-auto px-4 py-8 max-w-7xl">
        <!-- Header -->
        <header class="mb-8 text-center">
            <h1 class="text-3xl font-bold text-indigo-700 mb-2">
                <i class="fas fa-calendar-alt mr-2"></i>Generador de Horarios NOC 2x2
            </h1>
            <p class="text-gray-600">Sistema de rotación de turnos para equipos de monitoreo</p>
            
            <?php if ($error): ?>
                <div class="mt-4 bg-red-100 border-l-4 border-red-500 text-red-700 p-4" role="alert">
                    <p><?= htmlspecialchars($error) ?></p>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="mt-4 bg-green-100 border-l-4 border-green-500 text-green-700 p-4" role="alert">
                    <p>Horario guardado exitosamente!</p>
                </div>
            <?php endif; ?>
        </header>

        <!-- Employee Management Section -->
        <section class="mb-12 bg-white rounded-lg shadow-md overflow-hidden">
            <div class="px-6 py-4 bg-indigo-600 text-white">
                <h2 class="text-xl font-semibold">
                    <i class="fas fa-users mr-2"></i>Gestionar Empleados
                </h2>
            </div>
            <div class="p-6">
                <form method="post">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nombre</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Activo</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tipo</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($employees as $emp): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div class="flex-shrink-0 h-10 w-10 bg-indigo-100 rounded-full flex items-center justify-center">
                                                    <span class="text-indigo-600 font-medium"><?= substr($emp['name'], 0, 1) ?></span>
                                                </div>
                                                <div class="ml-4">
                                                    <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($emp['name']) ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <label class="inline-flex items-center">
                                                <input type="checkbox" name="active[<?= htmlspecialchars($emp['name']) ?>]" 
                                                    <?= $emp['active'] ? 'checked' : '' ?>
                                                    class="form-checkbox h-5 w-5 text-indigo-600 transition duration-150 ease-in-out">
                                            </label>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                <?= !empty($emp['fixed_schedule']) ? 'bg-blue-100 text-blue-800' : 'bg-purple-100 text-purple-800' ?>">
                                                <?= !empty($emp['fixed_schedule']) ? 'Horario Fijo' : 'Rotativo' ?>
                                                <?= !empty($emp['supervisor']) ? ' (Supervisor)' : '' ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-4 flex justify-end">
                        <button type="submit" name="update_employees" 
                            class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            <i class="fas fa-save mr-2"></i>Actualizar Empleados
                        </button>
                    </div>
                </form>
            </div>
        </section>

        <!-- Schedule Generator Section -->
        <section class="mb-12 bg-white rounded-lg shadow-md overflow-hidden">
            <div class="px-6 py-4 bg-indigo-600 text-white">
                <h2 class="text-xl font-semibold">
                    <i class="fas fa-calendar-plus mr-2"></i>Generar Horario
                </h2>
            </div>
            <div class="p-6">
                <form method="post">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="month" class="block text-sm font-medium text-gray-700 mb-1">Mes a Generar</label>
                            <div class="mt-1 relative rounded-md shadow-sm">
                                <input type="month" id="month" name="month" value="<?= date('Y-m') ?>" required
                                    class="focus:ring-indigo-500 focus:border-indigo-500 block w-full pl-3 pr-12 py-2 sm:text-sm border-gray-300 rounded-md">
                            </div>
                        </div>
                    </div>
                    <div class="mt-6 flex flex-wrap gap-3">
                        <button type="submit" name="generate" 
                            class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            <i class="fas fa-eye mr-2"></i>Previsualizar Horario
                        </button>
                        <?php if (!empty($generatedSchedule)): ?>
                            <button type="submit" name="save" 
                                class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                                <i class="fas fa-save mr-2"></i>Guardar Horario
                            </button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </section>

        <!-- Generated Schedule Section -->
        <?php if (!empty($generatedSchedule)): ?>
            <section class="mb-12">
                <div class="mb-6 text-center">
                    <h2 class="text-2xl font-bold text-gray-800">
                        <i class="fas fa-calendar-check mr-2 text-indigo-600"></i>Horario Generado
                    </h2>
                    <p class="text-gray-600 mt-1">Visualización completa del mes</p>
                </div>

                <?php
                // Organizar por meses (código anterior)
                $months = [];
                $currentMonth = [];
                
                foreach ($generatedSchedule as $entry) {
                    $date = new DateTime($entry['date']);
                    $monthYear = $date->format('m-Y');
                    
                    if (!isset($currentMonth['id']) || $currentMonth['id'] !== $monthYear) {
                        if (!empty($currentMonth)) {
                            $months[] = $currentMonth;
                        }
                        $currentMonth = [
                            'id' => $monthYear,
                            'name' => $date->format('F Y'),
                            'weeks' => []
                        ];
                    }
                    
                    // Organizar por semanas
                    $weekNumber = $date->format('W');
                    $dayOfWeek = $date->format('N');
                    
                    if (!isset($currentMonth['weeks'][$weekNumber])) {
                        $currentMonth['weeks'][$weekNumber] = [
                            'number' => $weekNumber,
                            'start_date' => clone $date,
                            'days' => array_fill(1, 7, [])
                        ];
                        $currentMonth['weeks'][$weekNumber]['start_date']->modify('-' . ($dayOfWeek - 1) . ' days');
                    }
                    
                    $currentMonth['weeks'][$weekNumber]['days'][$dayOfWeek][] = $entry;
                }
                
                if (!empty($currentMonth)) {
                    $months[] = $currentMonth;
                }
                
                foreach ($months as $month): ?>
                    <div class="mb-10">
                        <div class="bg-indigo-600 text-white px-4 py-3 rounded-t-lg">
                            <h3 class="text-lg font-semibold">
                                <?= ucfirst(translateMonthToSpanish($month['name'])) ?>
                            </h3>
                        </div>
                        
                        <div class="bg-white shadow overflow-hidden rounded-b-lg">
                            <?php foreach ($month['weeks'] as $week): 
                                $endDate = clone $week['start_date'];
                                $endDate->modify('+6 days');
                            ?>
                                <div class="mb-6 border-b border-gray-200 last:border-b-0">
                                    <div class="px-4 py-2 bg-gray-50">
                                        <h4 class="text-sm font-medium text-gray-500">
                                            Semana del <?= $week['start_date']->format('d/m') ?> al <?= $endDate->format('d/m') ?>
                                        </h4>
                                    </div>
                                    
                                    <div class="grid grid-cols-7 gap-px bg-gray-200">
                                        <?php for ($i = 1; $i <= 7; $i++): 
                                            $dayDate = clone $week['start_date'];
                                            $dayDate->modify('+' . ($i - 1) . ' days');
                                            $isOtherMonth = $dayDate->format('m') != substr($month['id'], 0, 2);
                                        ?>
                                            <div class="calendar-day bg-white <?= $isOtherMonth ? 'opacity-60' : '' ?>">
                                                <div class="p-2 border-b border-gray-200">
                                                    <div class="text-center">
                                                        <span class="text-sm font-medium <?= $isOtherMonth ? 'text-gray-400' : 'text-gray-700' ?>">
                                                            <?= substr(translateDayToSpanish($dayDate->format('l')), 0, 3) ?>
                                                        </span>
                                                        <span class="block text-lg font-bold <?= $isOtherMonth ? 'text-gray-400' : 'text-gray-800' ?>">
                                                            <?= $dayDate->format('d') ?>
                                                        </span>
                                                    </div>
                                                </div>
                                                
                                                <div class="p-2 space-y-2">
                                                    <?php if (!empty($week['days'][$i])): ?>
                                                        <?php foreach ($week['days'][$i] as $entry): 
                                                            $shiftClass = strtolower($entry['shift']);
                                                        ?>
                                                            <div class="shift-badge p-2 rounded-lg shadow-xs 
                                                                <?= $shiftClass === 'mañana' ? 'bg-morning-light text-morning-dark border border-morning' : '' ?>
                                                                <?= $shiftClass === 'tarde' ? 'bg-afternoon-light text-afternoon-dark border border-afternoon' : '' ?>
                                                                <?= $shiftClass === 'noche' ? 'bg-night-light text-night-dark border border-night' : '' ?>
                                                                <?= $shiftClass === 'oficina' ? 'bg-office-light text-office-dark border border-office' : '' ?>
                                                                <?= $shiftClass === 'descanso' ? 'bg-rest-light text-rest-dark border border-rest' : '' ?>">
                                                                <div class="font-semibold text-sm truncate">
                                                                    <?= htmlspecialchars($entry['employee']) ?>
                                                                </div>
                                                                <div class="flex justify-between items-center">
                                                                    <span class="text-xs font-medium">
                                                                        <?= htmlspecialchars($entry['shift']) ?>
                                                                    </span>
                                                                    <?php if ($entry['shift'] !== 'Descanso'): ?>
                                                                        <span class="text-xs">
                                                                            <?= htmlspecialchars($entry['start']) ?>-<?= htmlspecialchars($entry['end']) ?>
                                                                        </span>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    <?php else: ?>
                                                        <div class="text-center text-gray-400 text-sm py-2">
                                                            Sin turnos
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </section>

            <!-- Detailed View Section -->
            <section class="mb-12 bg-white rounded-lg shadow-md overflow-hidden">
                <div class="px-6 py-4 bg-indigo-600 text-white">
                    <h2 class="text-xl font-semibold">
                        <i class="fas fa-list-alt mr-2"></i>Vista Detallada
                    </h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fecha</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Día</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Empleado</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Turno</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Horario</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($generatedSchedule as $entry): 
                                $date = new DateTime($entry['date']);
                                $dayName = translateDayToSpanish($date->format('l'));
                                $shiftClass = strtolower($entry['shift']);
                            ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        <?= $date->format('d/m/Y') ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?= htmlspecialchars($dayName) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?= htmlspecialchars($entry['employee']) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                            <?= $shiftClass === 'mañana' ? 'bg-morning text-white' : '' ?>
                                            <?= $shiftClass === 'tarde' ? 'bg-afternoon text-white' : '' ?>
                                            <?= $shiftClass === 'noche' ? 'bg-night text-white' : '' ?>
                                            <?= $shiftClass === 'oficina' ? 'bg-office text-white' : '' ?>
                                            <?= $shiftClass === 'descanso' ? 'bg-rest text-white' : '' ?>">
                                            <?= htmlspecialchars($entry['shift']) ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?= $entry['shift'] !== 'Descanso' ? htmlspecialchars($entry['start']) . ' - ' . htmlspecialchars($entry['end']) : '-' ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        <?php endif; ?>
    </div>

    <!-- Footer -->
    <footer class="bg-gray-800 text-white py-6">
        <div class="container mx-auto px-4 text-center">
            <p>Sistema de Generación de Horarios NOC 2x2 &copy; <?= date('Y') ?></p>
        </div>
    </footer>
</body>
</html>