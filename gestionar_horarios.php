<?php
require_once 'config.php';
require_once 'functions.php';

// Procesar acciones
$message = '';
$messageType = '';

if ($_POST['action'] ?? '' === 'delete_all') {
    // Eliminar todos los horarios
    $file = DATA_DIR . '/schedules.json';
    $emptyData = ['schedules' => []];
    if (file_put_contents($file, json_encode($emptyData, JSON_PRETTY_PRINT))) {
        $message = '✅ Todos los horarios han sido eliminados correctamente.';
        $messageType = 'success';
    } else {
        $message = '❌ Error al eliminar los horarios.';
        $messageType = 'error';
    }
}

if ($_POST['action'] ?? '' === 'delete_specific') {
    $indexToDelete = (int)($_POST['schedule_index'] ?? -1);
    $schedules = loadSchedules();
    
    if ($indexToDelete >= 0 && $indexToDelete < count($schedules)) {
        // Eliminar horario específico
        array_splice($schedules, $indexToDelete, 1);
        
        if (saveSchedules($schedules)) {
            $message = '✅ Horario eliminado correctamente.';
            $messageType = 'success';
        } else {
            $message = '❌ Error al eliminar el horario.';
            $messageType = 'error';
        }
    } else {
        $message = '❌ Horario no encontrado.';
        $messageType = 'error';
    }
}

// Cargar horarios actualizados
$schedules = loadSchedules();

function getMonthName($monthNumber) {
    $months = [
        '01' => 'Enero', '02' => 'Febrero', '03' => 'Marzo', '04' => 'Abril',
        '05' => 'Mayo', '06' => 'Junio', '07' => 'Julio', '08' => 'Agosto',
        '09' => 'Septiembre', '10' => 'Octubre', '11' => 'Noviembre', '12' => 'Diciembre'
    ];
    return $months[$monthNumber] ?? $monthNumber;
}

function detectShiftType($schedule) {
    foreach ($schedule as $entry) {
        if ($entry['shift'] === 'Madrugada') {
            return '6h';
        }
    }
    return '8h';
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestionar Horarios Almacenados</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 1000px;
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
            border-bottom: 2px solid #dc3545;
        }
        .message {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .message.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .message.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .schedule-card {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .schedule-header {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 15px;
        }
        .schedule-info {
            flex-grow: 1;
        }
        .schedule-actions {
            margin-left: 20px;
        }
        .btn {
            display: inline-block;
            padding: 8px 16px;
            text-decoration: none;
            border-radius: 4px;
            border: none;
            cursor: pointer;
            font-size: 14px;
            margin: 2px;
        }
        .btn-primary {
            background-color: #007bff;
            color: white;
        }
        .btn-danger {
            background-color: #dc3545;
            color: white;
        }
        .btn-success {
            background-color: #28a745;
            color: white;
        }
        .btn-warning {
            background-color: #ffc107;
            color: #212529;
        }
        .btn:hover {
            opacity: 0.8;
        }
        .danger-zone {
            background: #fff5f5;
            border: 2px solid #fed7d7;
            border-radius: 8px;
            padding: 20px;
            margin-top: 30px;
        }
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        .stat-card {
            background: #e3f2fd;
            padding: 15px;
            border-radius: 5px;
            text-align: center;
        }
        .no-schedules {
            text-align: center;
            padding: 50px;
            color: #666;
        }
        .confirm-delete {
            display: none;
            background: #fff;
            border: 2px solid #dc3545;
            border-radius: 8px;
            padding: 20px;
            margin-top: 15px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🗑️ Gestionar Horarios Almacenados</h1>
            <p>Aquí puedes ver y eliminar los horarios guardados en el sistema</p>
        </div>

        <div style="text-align: center; margin-bottom: 20px;">
            <a href="indexFinal.php" class="btn btn-primary">🏠 Volver al Generador</a>
            <a href="validar_horarios.php" class="btn btn-success">👁️ Ver Horarios</a>
            <a href="dashboard.php" class="btn btn-warning">📊 Dashboard</a>
        </div>

        <?php if ($message): ?>
            <div class="message <?php echo $messageType; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <?php if (empty($schedules)): ?>
            <div class="no-schedules">
                <h3>📋 No hay horarios almacenados</h3>
                <p>El sistema está limpio. Puedes generar nuevos horarios para probar el almacenamiento.</p>
                <a href="indexFinal.php" class="btn btn-primary">Generar Nuevo Horario</a>
            </div>
        <?php else: ?>
            <div class="stats">
                <div class="stat-card">
                    <h3><?php echo count($schedules); ?></h3>
                    <p>Horarios Almacenados</p>
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
                    <p>Empleados Únicos</p>
                </div>
                <div class="stat-card">
                    <h3><?php 
                        $totalEntries = 0;
                        foreach ($schedules as $schedule) {
                            $totalEntries += count($schedule['schedule']);
                        }
                        echo $totalEntries;
                    ?></h3>
                    <p>Total Asignaciones</p>
                </div>
            </div>

            <h3>📋 Horarios Individuales</h3>
            <?php foreach ($schedules as $index => $scheduleData): ?>
                <?php
                $month = $scheduleData['month'];
                $monthParts = explode('-', $month);
                $monthName = getMonthName($monthParts[1]);
                $year = $monthParts[0];
                $shiftType = detectShiftType($scheduleData['schedule']);
                
                $employees = array_unique(array_column($scheduleData['schedule'], 'employee'));
                $totalDays = count(array_unique(array_column($scheduleData['schedule'], 'date')));
                ?>

                <div class="schedule-card">
                    <div class="schedule-header">
                        <div class="schedule-info">
                            <h4>📅 <?php echo $monthName . ' ' . $year; ?> (Sistema <?php echo $shiftType; ?>)</h4>
                            <p><strong>Generado:</strong> <?php echo $scheduleData['generated_date']; ?></p>
                            <p><strong>Empleados:</strong> <?php echo implode(', ', $employees); ?> (<?php echo count($employees); ?> total)</p>
                            <p><strong>Días cubiertos:</strong> <?php echo $totalDays; ?> días</p>
                            <p><strong>Total asignaciones:</strong> <?php echo count($scheduleData['schedule']); ?></p>
                        </div>
                        <div class="schedule-actions">
                            <button onclick="toggleConfirm(<?php echo $index; ?>)" class="btn btn-danger">
                                🗑️ Eliminar
                            </button>
                        </div>
                    </div>
                    
                    <div id="confirm-<?php echo $index; ?>" class="confirm-delete">
                        <h4 style="color: #dc3545;">⚠️ Confirmar Eliminación</h4>
                        <p>¿Estás seguro de que quieres eliminar el horario de <strong><?php echo $monthName . ' ' . $year; ?></strong>?</p>
                        <p><small>Esta acción no se puede deshacer.</small></p>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="action" value="delete_specific">
                            <input type="hidden" name="schedule_index" value="<?php echo $index; ?>">
                            <button type="submit" class="btn btn-danger">✅ Sí, Eliminar</button>
                            <button type="button" onclick="toggleConfirm(<?php echo $index; ?>)" class="btn btn-primary">❌ Cancelar</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>

            <div class="danger-zone">
                <h3 style="color: #dc3545;">⚠️ Zona Peligrosa</h3>
                <p>Las siguientes acciones son <strong>irreversibles</strong>. Úsalas solo para pruebas o limpieza completa.</p>
                
                <button onclick="toggleConfirmAll()" class="btn btn-danger">
                    🗑️ Eliminar TODOS los Horarios
                </button>
                
                <div id="confirm-all" class="confirm-delete">
                    <h4 style="color: #dc3545;">⚠️ ELIMINAR TODOS LOS HORARIOS</h4>
                    <p>¿Estás seguro de que quieres eliminar <strong>TODOS</strong> los horarios almacenados?</p>
                    <p><strong>Esto eliminará <?php echo count($schedules); ?> horario(s) permanentemente.</strong></p>
                    <p><small>Esta acción no se puede deshacer.</small></p>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="delete_all">
                        <button type="submit" class="btn btn-danger">✅ Sí, Eliminar TODO</button>
                        <button type="button" onclick="toggleConfirmAll()" class="btn btn-primary">❌ Cancelar</button>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <div style="text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd;">
            <p><small>💾 Archivo de datos: <code>data/schedules.json</code></small></p>
            <p><small>🔄 Después de eliminar, puedes generar nuevos horarios para probar el almacenamiento</small></p>
        </div>
    </div>

    <script>
        function toggleConfirm(index) {
            const confirmDiv = document.getElementById('confirm-' + index);
            confirmDiv.style.display = confirmDiv.style.display === 'block' ? 'none' : 'block';
        }
        
        function toggleConfirmAll() {
            const confirmDiv = document.getElementById('confirm-all');
            confirmDiv.style.display = confirmDiv.style.display === 'block' ? 'none' : 'block';
        }
    </script>
</body>
</html>