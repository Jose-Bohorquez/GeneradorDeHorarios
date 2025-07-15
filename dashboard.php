<?php
require_once 'functions.php';

// Función para obtener nombre del mes en español
function getMonthName($monthNumber) {
    $months = [
        1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
        5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
        9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
    ];
    return $months[$monthNumber] ?? 'Mes ' . $monthNumber;
}

// Función para obtener horarios únicos
function getUniqueSchedules() {
    $allSchedules = loadSchedules();
    $schedules = [];
    $seenSchedules = [];
    
    foreach ($allSchedules as $index => $scheduleData) {
        if (isset($scheduleData['month'], $scheduleData['generated_date'], $scheduleData['schedule'])) {
            $monthParts = explode('-', $scheduleData['month']);
            $year = $monthParts[0];
            $month = intval($monthParts[1]);
            
            $shiftType = detectShiftType($scheduleData);
            $uniqueKey = $scheduleData['month'] . '_' . $shiftType;
            
            if (!isset($seenSchedules[$uniqueKey])) {
                $seenSchedules[$uniqueKey] = true;
                $schedules[] = [
                    'index' => $index,
                    'schedule_data' => $scheduleData,
                    'month_name' => getMonthName($month),
                    'year' => $year,
                    'month' => $month,
                    'shift_type' => $shiftType,
                    'generated_date' => $scheduleData['generated_date']
                ];
            }
        }
    }
    
    usort($schedules, function($a, $b) {
        return strtotime($b['generated_date']) - strtotime($a['generated_date']);
    });
    
    return $schedules;
}

function detectShiftType($scheduleData) {
    if (!$scheduleData || !isset($scheduleData['schedule'])) {
        return '8h';
    }
    
    foreach ($scheduleData['schedule'] as $entry) {
        if (isset($entry['shift']) && $entry['shift'] === 'Madrugada') {
            return '6h';
        }
    }
    
    return '8h';
}

// Cargar datos
$employees = loadEmployees();
$schedules = getUniqueSchedules();

// Variables para el horario seleccionado
$selectedSchedule = null;
$stats = null;
$chartData = null;

// Procesar selección de horario
if (isset($_GET['schedule']) && is_numeric($_GET['schedule'])) {
    $scheduleIndex = intval($_GET['schedule']);
    $allSchedules = loadSchedules();
    
    if (isset($allSchedules[$scheduleIndex])) {
        $selectedSchedule = $allSchedules[$scheduleIndex];
        
        if ($selectedSchedule && isset($selectedSchedule['schedule'])) {
            $stats = calculateEmployeeHoursStats($selectedSchedule['schedule']);
            $chartData = getChartData($selectedSchedule['schedule']);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard de Estadísticas - Generador de Horarios</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .chart-container {
            position: relative;
            height: 400px;
            width: 100%;
        }
        
        .schedule-card {
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .schedule-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        
        .schedule-card.selected {
            border: 2px solid #3B82F6;
            background: linear-gradient(135deg, #EBF4FF 0%, #DBEAFE 100%);
        }
        
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .stat-card-green {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
        }
        
        .stat-card-orange {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }
        
        .stat-card-purple {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }
    </style>
</head>
<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-8">
        <!-- Header -->
        <div class="bg-white rounded-lg shadow-lg p-6 mb-8">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-3xl font-bold text-gray-800">
                        <i class="fas fa-chart-line mr-3 text-blue-500"></i>Dashboard de Estadísticas
                    </h1>
                    <p class="text-gray-600 mt-2">Análisis detallado de horas trabajadas y estadísticas del equipo</p>
                </div>
                <div class="flex space-x-3">
                    <a href="indexFinal.php" class="bg-blue-500 hover:bg-blue-600 text-white px-6 py-3 rounded-lg transition-colors duration-200">
                        <i class="fas fa-arrow-left mr-2"></i>Generador
                    </a>
                    <a href="validar_horarios.php" class="bg-green-500 hover:bg-green-600 text-white px-6 py-3 rounded-lg transition-colors duration-200">
                        <i class="fas fa-eye mr-2"></i>Ver Horarios
                    </a>
                    <a href="gestionar_horarios.php" class="bg-red-500 hover:bg-red-600 text-white px-6 py-3 rounded-lg transition-colors duration-200">
                        <i class="fas fa-cog mr-2"></i>Gestionar
                    </a>
                </div>
            </div>
        </div>

        <!-- Estadísticas Generales -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="stat-card rounded-lg p-6 text-center">
                <i class="fas fa-calendar-alt text-3xl mb-3"></i>
                <h3 class="text-2xl font-bold"><?= count($schedules) ?></h3>
                <p class="text-sm opacity-90">Horarios Generados</p>
            </div>
            <div class="stat-card-green rounded-lg p-6 text-center text-white">
                <i class="fas fa-users text-3xl mb-3"></i>
                <h3 class="text-2xl font-bold"><?= count(array_filter($employees, fn($emp) => $emp['active'])) ?></h3>
                <p class="text-sm opacity-90">Empleados Activos</p>
            </div>
            <div class="stat-card-orange rounded-lg p-6 text-center text-white">
                <i class="fas fa-sync-alt text-3xl mb-3"></i>
                <h3 class="text-2xl font-bold"><?= count(array_filter($employees, fn($emp) => empty($emp['fixed_schedule']) && $emp['active'])) ?></h3>
                <p class="text-sm opacity-90">Empleados Rotativos</p>
            </div>
            <div class="stat-card-purple rounded-lg p-6 text-center text-white">
                <i class="fas fa-clock text-3xl mb-3"></i>
                <h3 class="text-2xl font-bold"><?= count(array_filter($employees, fn($emp) => !empty($emp['fixed_schedule']) && $emp['active'])) ?></h3>
                <p class="text-sm opacity-90">Horario Fijo</p>
            </div>
        </div>

        <!-- Selector de Horarios -->
        <div class="bg-white rounded-lg shadow-lg p-6 mb-8">
            <h2 class="text-xl font-bold text-gray-800 mb-4">
                <i class="fas fa-calendar-alt mr-2 text-green-500"></i>Seleccionar Horario para Análisis
            </h2>
            
            <?php if (empty($schedules)): ?>
                <div class="text-center py-8">
                    <i class="fas fa-calendar-times text-gray-400 text-4xl mb-4"></i>
                    <p class="text-gray-500 text-lg">No hay horarios generados disponibles.</p>
                    <a href="indexFinal.php" class="bg-blue-500 hover:bg-blue-600 text-white px-6 py-3 rounded-lg mt-4 inline-block transition-colors duration-200">
                        <i class="fas fa-plus mr-2"></i>Generar Primer Horario
                    </a>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <?php foreach ($schedules as $schedule): ?>
                        <div class="schedule-card bg-gradient-to-br from-white to-gray-50 rounded-lg p-4 border border-gray-200 <?= (isset($_GET['schedule']) && $_GET['schedule'] == $schedule['index']) ? 'selected' : '' ?>"
                             onclick="window.location.href='?schedule=<?= $schedule['index'] ?>'">
                            <div class="flex justify-between items-start mb-2">
                                <h3 class="font-bold text-gray-800"><?= htmlspecialchars($schedule['month_name']) ?> <?= $schedule['year'] ?></h3>
                                <span class="bg-<?= $schedule['shift_type'] === '6h' ? 'green' : 'blue' ?>-100 text-<?= $schedule['shift_type'] === '6h' ? 'green' : 'blue' ?>-800 px-2 py-1 rounded-full text-xs font-medium">
                                    Sistema <?= htmlspecialchars($schedule['shift_type']) ?>
                                </span>
                            </div>
                            <p class="text-sm text-gray-600">
                                <i class="fas fa-clock mr-1"></i>Generado: <?= date('d/m/Y H:i', strtotime($schedule['generated_date'])) ?>
                            </p>
                            <div class="mt-3 flex justify-between items-center">
                                <span class="text-xs text-gray-500">
                                    <i class="fas fa-users mr-1"></i>
                                    <?= count(array_unique(array_column($schedule['schedule_data']['schedule'], 'employee'))) ?> empleados
                                </span>
                                <i class="fas fa-chevron-right text-gray-400"></i>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Análisis del Horario Seleccionado -->
        <?php if ($selectedSchedule && $stats && $chartData): ?>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
                <!-- Estadísticas por Empleado -->
                <div class="bg-white rounded-lg shadow-lg p-6">
                    <h3 class="text-xl font-bold text-gray-800 mb-4">
                        <i class="fas fa-user-clock mr-2 text-blue-500"></i>Horas por Empleado
                    </h3>
                    <div class="chart-container">
                        <canvas id="employeeHoursChart"></canvas>
                    </div>
                </div>

                <!-- Distribución de Turnos -->
                <div class="bg-white rounded-lg shadow-lg p-6">
                    <h3 class="text-xl font-bold text-gray-800 mb-4">
                        <i class="fas fa-pie-chart mr-2 text-green-500"></i>Distribución de Turnos
                    </h3>
                    <div class="chart-container">
                        <canvas id="shiftDistributionChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Tabla de Estadísticas Detalladas -->
            <div class="bg-white rounded-lg shadow-lg p-6">
                <h3 class="text-xl font-bold text-gray-800 mb-4">
                    <i class="fas fa-table mr-2 text-purple-500"></i>Estadísticas Detalladas
                </h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full table-auto">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Empleado</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Horas</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Días Trabajados</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Días Descanso</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Promedio Horas/Día</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($stats as $employee => $empStats): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($employee); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo number_format($empStats['total_hours'] ?? 0, 1); ?>h</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo $empStats['work_days'] ?? 0; ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo $empStats['rest_days'] ?? 0; ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo number_format($empStats['avg_hours_per_day'] ?? 0, 1); ?>h</td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Scripts para los gráficos -->
            <script>
                // Gráfico de Horas por Empleado
                const employeeCtx = document.getElementById('employeeHoursChart').getContext('2d');
                new Chart(employeeCtx, {
                    type: 'bar',
                    data: {
                        labels: <?= json_encode(array_keys($stats)) ?>,
                        datasets: [{
                            label: 'Horas Trabajadas',
                            data: <?= json_encode(array_column($stats, 'total_hours')) ?>,
                            backgroundColor: 'rgba(59, 130, 246, 0.8)',
                            borderColor: 'rgba(59, 130, 246, 1)',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true,
                                title: {
                                    display: true,
                                    text: 'Horas'
                                }
                            }
                        },
                        plugins: {
                            legend: {
                                display: false
                            },
                            title: {
                                display: true,
                                text: 'Total de Horas por Empleado'
                            }
                        }
                    }
                });

                // Gráfico de Distribución de Turnos
                const shiftCtx = document.getElementById('shiftDistributionChart').getContext('2d');
                new Chart(shiftCtx, {
                    type: 'doughnut',
                    data: {
                        labels: <?= json_encode(array_keys($chartData['shift_distribution'] ?? [])) ?>,
                        datasets: [{
                            data: <?= json_encode(array_values($chartData['shift_distribution'] ?? [])) ?>,
                            backgroundColor: [
                                'rgba(255, 215, 0, 0.8)',    // Mañana - Dorado
                                'rgba(255, 107, 53, 0.8)',   // Tarde - Naranja
                                'rgba(74, 144, 226, 0.8)',   // Noche - Azul
                                'rgba(142, 68, 173, 0.8)',   // Madrugada - Púrpura
                                'rgba(80, 200, 120, 0.8)',   // Oficina - Verde
                                'rgba(155, 89, 182, 0.8)'    // Supervisor - Violeta
                            ],
                            borderColor: [
                                'rgba(255, 215, 0, 1)',
                                'rgba(255, 107, 53, 1)',
                                'rgba(74, 144, 226, 1)',
                                'rgba(142, 68, 173, 1)',
                                'rgba(80, 200, 120, 1)',
                                'rgba(155, 89, 182, 1)'
                            ],
                            borderWidth: 2
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom'
                            },
                            title: {
                                display: true,
                                text: 'Distribución de Turnos'
                            }
                        }
                    }
                });
            </script>
        <?php endif; ?>
    </div>
</body>
</html>