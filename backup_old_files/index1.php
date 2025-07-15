<?php
require_once 'functions.php';

$employees = loadEmployees();
$schedules = loadSchedules();
$error = '';
$generatedSchedule = [];
$startDate = date('Y-m-d');

// Procesar formulario
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
        // Actualizar estado de empleados
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

// Mostrar mensaje de éxito
$success = isset($_GET['success']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generador de Horarios NOC 2x2</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .container { max-width: 1200px; margin: 0 auto; }
        .error { color: red; }
        .success { color: green; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .fixed { background-color: #e6f7ff; }
        .rest { background-color: #fff2e6; }
        .morning { background-color: #e6ffe6; }
        .afternoon { background-color: #fff9e6; }
        .night { background-color: #f0e6ff; }
        form { margin-bottom: 20px; }
        .form-group { margin-bottom: 10px; }
        .week-container { margin-bottom: 30px; border: 1px solid #ddd; padding: 10px; }
        .calendar-day { vertical-align: top; height: 150px; border: 1px solid #ddd; padding: 5px; }
        .day-header { font-weight: bold; background: #f0f0f0; padding: 5px; margin-bottom: 5px; }
        .shift-entry { margin: 3px 0; padding: 3px; font-size: 0.9em; border-radius: 3px; }
        .shift-time { font-size: 0.8em; color: #555; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Generador de Horarios NOC 2x2</h1>
        
        <?php if ($error): ?>
            <p class="error"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <p class="success">Horario guardado exitosamente!</p>
        <?php endif; ?>
        
        <h2>Gestionar Empleados</h2>
        <form method="post">
            <table>
                <tr>
                    <th>Nombre</th>
                    <th>Activo</th>
                    <th>Tipo</th>
                </tr>
                <?php foreach ($employees as $emp): ?>
                    <tr>
                        <td><?= htmlspecialchars($emp['name']) ?></td>
                        <td>
                            <input type="checkbox" name="active[<?= htmlspecialchars($emp['name']) ?>]" 
                                <?= $emp['active'] ? 'checked' : '' ?>>
                        </td>
                        <td>
                            <?= !empty($emp['fixed_schedule']) ? 'Horario Fijo' : 'Rotativo' ?>
                            <?= !empty($emp['supervisor']) ? '(Supervisor)' : '' ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
            <div class="form-group">
                <button type="submit" name="update_employees">Actualizar Empleados</button>
            </div>
        </form>
        
        <h2>Generar Horario</h2>
        <form method="post">

<div class="form-group">
    <label for="month">Mes a Generar:</label>
    <input type="month" id="month" name="month" value="<?= date('Y-m') ?>" required>
</div>
            <div class="form-group">
                <button type="submit" name="generate">Previsualizar Horario</button>
                <?php if (!empty($generatedSchedule)): ?>
                    <button type="submit" name="save">Guardar Horario</button>
                <?php endif; ?>
            </div>
        </form>
        
        <?php if (!empty($generatedSchedule)): ?>
            <h2>Horario Generado - Vista de Calendario</h2>
            <?php 
            // Organizar por semanas
            $weeks = [];
            $currentWeek = [];
            
            foreach ($generatedSchedule as $entry) {
                $date = new DateTime($entry['date']);
                $weekNumber = $date->format('W-Y');
                $dayOfWeek = $date->format('N'); // 1 (Lunes) a 7 (Domingo)
                
                if (!isset($currentWeek['number']) || $currentWeek['number'] !== $weekNumber) {
                    if (!empty($currentWeek)) {
                        $weeks[] = $currentWeek;
                    }
                    $currentWeek = [
                        'number' => $weekNumber,
                        'start_date' => clone $date,
                        'days' => array_fill(1, 7, [])
                    ];
                    // Ajustar fecha de inicio al lunes de esa semana
                    $currentWeek['start_date']->modify('-' . ($dayOfWeek - 1) . ' days');
                }
                
                $currentWeek['days'][$dayOfWeek][] = $entry;
            }
            
            if (!empty($currentWeek)) {
                $weeks[] = $currentWeek;
            }
            
            foreach ($weeks as $week): 
                $endDate = clone $week['start_date'];
                $endDate->modify('+6 days');
            ?>
                <div class="week-container">
                    <h3>Semana del <?= $week['start_date']->format('d/m/Y') ?> al <?= $endDate->format('d/m/Y') ?></h3>
                    <table>
                        <thead>
                            <tr>
                                <th style="width: 14%;">Lunes</th>
                                <th style="width: 14%;">Martes</th>
                                <th style="width: 14%;">Miércoles</th>
                                <th style="width: 14%;">Jueves</th>
                                <th style="width: 14%;">Viernes</th>
                                <th style="width: 14%;">Sábado</th>
                                <th style="width: 14%;">Domingo</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <?php for ($i = 1; $i <= 7; $i++): ?>
                                    <td class="calendar-day">
                                        <?php if (!empty($week['days'][$i])): 
                                            $dayDate = new DateTime($week['days'][$i][0]['date']);
                                        ?>
                                            <div class="day-header">
                                                <?= $dayDate->format('d/m/Y') ?>
                                            </div>
                                            <?php foreach ($week['days'][$i] as $entry): 
                                                $shiftClass = '';
                                                if ($entry['shift'] === 'Oficina') $shiftClass = 'fixed';
                                                elseif ($entry['shift'] === 'Descanso') $shiftClass = 'rest';
                                                elseif ($entry['shift'] === 'Mañana') $shiftClass = 'morning';
                                                elseif ($entry['shift'] === 'Tarde') $shiftClass = 'afternoon';
                                                elseif ($entry['shift'] === 'Noche') $shiftClass = 'night';
                                            ?>
                                                <div class="shift-entry <?= $shiftClass ?>">
                                                    <strong><?= htmlspecialchars($entry['employee']) ?></strong><br>
                                                    <?= htmlspecialchars($entry['shift']) ?>
                                                    <?php if ($entry['shift'] !== 'Descanso'): ?>
                                                        <div class="shift-time">
                                                            <?= htmlspecialchars($entry['start']) ?> - <?= htmlspecialchars($entry['end']) ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </td>
                                <?php endfor; ?>
                            </tr>
                        </tbody>
                    </table>
                </div>
            <?php endforeach; ?>
            
            <h3>Vista Detallada</h3>
            <table>
                <tr>
                    <th>Fecha</th>
                    <th>Día</th>
                    <th>Empleado</th>
                    <th>Turno</th>
                    <th>Hora Inicio</th>
                    <th>Hora Fin</th>
                </tr>
                <?php foreach ($generatedSchedule as $entry): 
                    $date = new DateTime($entry['date']);
                    $dayName = translateDayToSpanish($date->format('l'));
                    $isFixed = $entry['shift'] === 'Oficina';
                    $isRest = $entry['shift'] === 'Descanso';
                ?>
                    <tr class="<?= $isFixed ? 'fixed' : ($isRest ? 'rest' : '') ?>">
                        <td><?= $date->format('d/m/Y') ?></td>
                        <td><?= htmlspecialchars($dayName) ?></td>
                        <td><?= htmlspecialchars($entry['employee']) ?></td>
                        <td><?= htmlspecialchars($entry['shift']) ?></td>
                        <td><?= htmlspecialchars($entry['start'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($entry['end'] ?? '-') ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>
        
<?php if (!empty($generatedSchedule)): ?>
    <h2>Horario Generado - Mes Completo</h2>
    <?php
    // Organizar por meses
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
        $dayOfWeek = $date->format('N'); // 1 (Lunes) a 7 (Domingo)
        
        if (!isset($currentMonth['weeks'][$weekNumber])) {
            $currentMonth['weeks'][$weekNumber] = [
                'number' => $weekNumber,
                'start_date' => clone $date,
                'days' => array_fill(1, 7, [])
            ];
            // Ajustar fecha de inicio al lunes de esa semana
            $currentMonth['weeks'][$weekNumber]['start_date']->modify('-' . ($dayOfWeek - 1) . ' days');
        }
        
        $currentMonth['weeks'][$weekNumber]['days'][$dayOfWeek][] = $entry;
    }
    
    if (!empty($currentMonth)) {
        $months[] = $currentMonth;
    }
    
    foreach ($months as $month): ?>
        <h3><?= ucfirst(translateMonthToSpanish($month['name'])) ?></h3>
        <?php foreach ($month['weeks'] as $week): 
            $endDate = clone $week['start_date'];
            $endDate->modify('+6 days');
        ?>
            <div class="week-container">
                <h4>Semana del <?= $week['start_date']->format('d/m') ?> al <?= $endDate->format('d/m') ?></h4>
                <table>
                    <thead>
                        <tr>
                            <th>Lunes</th>
                            <th>Martes</th>
                            <th>Miércoles</th>
                            <th>Jueves</th>
                            <th>Viernes</th>
                            <th>Sábado</th>
                            <th>Domingo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <?php for ($i = 1; $i <= 7; $i++): 
                                $dayDate = clone $week['start_date'];
                                $dayDate->modify('+' . ($i - 1) . ' days');
                            ?>
                                <td class="calendar-day <?= $dayDate->format('m') != substr($month['id'], 0, 2) ? 'other-month' : '' ?>">
                                    <div class="day-header">
                                        <?= $dayDate->format('d') ?> <?= substr(translateDayToSpanish($dayDate->format('l')), 0, 3) ?>
                                    </div>
                                    <?php if (!empty($week['days'][$i])): ?>
                                        <?php foreach ($week['days'][$i] as $entry): 
                                            $shiftClass = strtolower($entry['shift']);
                                        ?>
                                            <div class="shift-entry <?= $shiftClass ?>">
                                                <strong><?= htmlspecialchars($entry['employee']) ?></strong>
                                                <div><?= htmlspecialchars($entry['shift']) ?></div>
                                                <?php if ($entry['shift'] !== 'Descanso'): ?>
                                                    <div class="shift-time">
                                                        <?= htmlspecialchars($entry['start']) ?>-<?= htmlspecialchars($entry['end']) ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </td>
                            <?php endfor; ?>
                        </tr>
                    </tbody>
                </table>
            </div>
        <?php endforeach; ?>
    <?php endforeach; ?>
<?php endif; ?>
    </div>
</body>
</html>