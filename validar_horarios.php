<?php
require_once 'config.php';
require_once 'functions.php';

// Función para obtener nombre del mes en español
function getMonthName($monthNumber) {
    $months = [
        '01' => 'Enero', '02' => 'Febrero', '03' => 'Marzo', '04' => 'Abril',
        '05' => 'Mayo', '06' => 'Junio', '07' => 'Julio', '08' => 'Agosto',
        '09' => 'Septiembre', '10' => 'Octubre', '11' => 'Noviembre', '12' => 'Diciembre'
    ];
    return $months[$monthNumber] ?? 'Mes ' . $monthNumber;
}

// Función para detectar tipo de turno
function detectShiftType($schedule) {
    foreach ($schedule as $entry) {
        if ($entry['shift'] === 'Madrugada') {
            return '6h';
        }
    }
    return '8h';
}

// Función para obtener nombre del día en español
function getDayName($date) {
    $days = [
        'Monday' => 'Lunes',
        'Tuesday' => 'Martes', 
        'Wednesday' => 'Miércoles',
        'Thursday' => 'Jueves',
        'Friday' => 'Viernes',
        'Saturday' => 'Sábado',
        'Sunday' => 'Domingo'
    ];
    
    $englishDay = date('l', strtotime($date));
    return $days[$englishDay] ?? $englishDay;
}

// Cargar horarios
$schedules = loadSchedules();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Validación de Horarios - Generador de Horarios</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #007bff;
        }
        .schedule-info {
            background: #e3f2fd;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .schedule-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        .schedule-table th,
        .schedule-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: center;
        }
        .schedule-table th {
            background-color: #007bff;
            color: white;
        }
        .shift-manana { background-color: #fff3cd; }
        .shift-tarde { background-color: #d4edda; }
        .shift-noche { background-color: #d1ecf1; }
        .shift-madrugada { background-color: #f8d7da; }
        .shift-descanso { background-color: #f8f9fa; }
        .shift-oficina { background-color: #e2e3e5; }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background-color: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 10px 5px;
        }
        .btn:hover {
            background-color: #0056b3;
        }
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        .stat-card {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            text-align: center;
        }
        .no-schedules {
            text-align: center;
            padding: 50px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🗓️ Validación de Horarios Almacenados</h1>
            <p>Aquí puedes revisar todos los horarios generados y guardados en el sistema</p>
        </div>

        <div style="text-align: center; margin-bottom: 20px;">
            <a href="indexFinal.php" class="btn">🏠 Volver al Generador</a>
            <a href="dashboard.php" class="btn">📊 Ver Dashboard</a>
        </div>

        <?php if (empty($schedules)): ?>
            <div class="no-schedules">
                <h3>📋 No hay horarios almacenados</h3>
                <p>Aún no se han generado horarios. Ve al generador para crear el primer horario.</p>
                <a href="indexFinal.php" class="btn">Generar Horario</a>
            </div>
        <?php else: ?>
            <div class="stats">
                <div class="stat-card">
                    <h3><?php echo count($schedules); ?></h3>
                    <p>Horarios Generados</p>
                </div>
                <div class="stat-card">
                    <h3><?php echo count(array_unique(array_column($schedules, 'month'))); ?></h3>
                    <p>Meses Cubiertos</p>
                </div>
                <div class="stat-card">
                    <h3><?php 
                        $allEmployees = [];
                        foreach ($schedules as $schedule) {
                            foreach ($schedule['schedule'] as $entry) {
                                $allEmployees[] = $entry['employee'];
                            }
                        }
                        echo count(array_unique($allEmployees));
                    ?></h3>
                    <p>Empleados Asignados</p>
                </div>
            </div>

            <?php foreach ($schedules as $index => $scheduleData): ?>
                <?php
                $month = $scheduleData['month'];
                $monthParts = explode('-', $month);
                $monthName = getMonthName($monthParts[1]);
                $year = $monthParts[0];
                $shiftType = detectShiftType($scheduleData['schedule']);
                
                // Organizar por fechas
                $scheduleByDate = [];
                foreach ($scheduleData['schedule'] as $entry) {
                    $scheduleByDate[$entry['date']][] = $entry;
                }
                ksort($scheduleByDate);
                
                // Obtener empleados únicos
                $employees = array_unique(array_column($scheduleData['schedule'], 'employee'));
                sort($employees);
                ?>

                <div class="schedule-info">
                    <h3>📅 Horario de <?php echo $monthName . ' ' . $year; ?> (Sistema <?php echo $shiftType; ?>)</h3>
                    <p><strong>Generado:</strong> <?php echo $scheduleData['generated_date']; ?></p>
                    <p><strong>Empleados:</strong> <?php echo implode(', ', $employees); ?></p>
                    <p><strong>Total de días:</strong> <?php echo count($scheduleByDate); ?></p>
                </div>

                <table class="schedule-table">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Día</th>
                            <?php foreach ($employees as $employee): ?>
                                <th><?php echo $employee; ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($scheduleByDate as $date => $daySchedule): ?>
                            <tr>
                                <td><?php echo date('d/m/Y', strtotime($date)); ?></td>
                                <td><?php echo getDayName($date); ?></td>
                                <?php foreach ($employees as $employee): ?>
                                    <td>
                                        <?php
                                        $employeeShift = null;
                                        foreach ($daySchedule as $entry) {
                                            if ($entry['employee'] === $employee) {
                                                $employeeShift = $entry;
                                                break;
                                            }
                                        }
                                        
                                        if ($employeeShift) {
                                            $shiftClass = 'shift-' . strtolower(str_replace(['ñ', 'á'], ['n', 'a'], $employeeShift['shift']));
                                            echo '<div class="' . $shiftClass . '">';
                                            echo $employeeShift['shift'];
                                            if ($employeeShift['start'] && $employeeShift['end']) {
                                                echo '<br><small>' . $employeeShift['start'] . ' - ' . $employeeShift['end'] . '</small>';
                                            }
                                            echo '</div>';
                                        } else {
                                            echo '<div class="shift-descanso">-</div>';
                                        }
                                        ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <?php if ($index < count($schedules) - 1): ?>
                    <hr style="margin: 40px 0;">
                <?php endif; ?>
            <?php endforeach; ?>
        <?php endif; ?>

        <div style="text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd;">
            <p><small>💾 Los horarios se almacenan en: <code>data/schedules.json</code></small></p>
        </div>
    </div>
</body>
</html>