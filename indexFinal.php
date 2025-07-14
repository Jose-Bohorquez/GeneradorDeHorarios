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
        
        if (!empty($selectedMonth)) {
            $monthDate = $selectedMonth . '-01';
            $schedule = generateRotativeSchedule($employees, $monthDate, $monthDate);
            
            if (isset($schedule['error'])) {
                $error = $schedule['error'];
            } else {
                if (isset($_POST['generate_schedule'])) {
                    // Guardar horario
                    $scheduleData = [
                        'month' => $selectedMonth,
                        'generated_date' => date('Y-m-d H:i:s'),
                        'schedule' => $schedule,
                        'rotation_position' => $schedule[0]['rotation_position'] ?? 0
                    ];
                    
                    $schedules[] = $scheduleData;
                    
                    if (saveSchedules($schedules)) {
                        $success = "Horario generado y guardado correctamente para " . date('F Y', strtotime($monthDate));
                    } else {
                        $error = "Error al guardar el horario.";
                    }
                } else {
                    // Solo preview
                    $previewSchedule = $schedule;
                    $update = "Vista previa del horario para " . date('F Y', strtotime($monthDate));
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
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        :root {
            --bs-primary: #6366f1;
            --bs-primary-rgb: 99, 102, 241;
        }
        
        body {
            background-color: #f8f9fa;
        }
        
        .employee-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            border: 1px solid #e5e7eb;
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }
        
        .employee-card:hover {
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.15);
            transform: translateY(-2px);
        }
        
        .employee-card.inactive {
            opacity: 0.6;
            background-color: #f8f9fa;
        }
        
        .time-inputs {
            display: none;
            margin-top: 15px;
            padding: 15px;
            background: #f8fafc;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
        }
        
        .time-inputs.show {
            display: block;
        }
        
        .accordion-button:not(.collapsed) {
            background-color: #6366f1;
            color: white;
        }
        
        .accordion-button:focus {
            box-shadow: 0 0 0 0.25rem rgba(99, 102, 241, 0.25);
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
<body>
    <div class="container-fluid">
        <!-- Header -->
        <header class="header-gradient text-white py-4 mb-4">
            <div class="container">
                <div class="text-center">
                    <h1 class="display-5 fw-bold mb-2">
                        <i class="fas fa-calendar-alt me-3"></i>Generador de Horarios NOC 2x2
                    </h1>
                    <p class="lead mb-0">Sistema de rotación de turnos para equipos de monitoreo</p>
                </div>
            </div>
            
            <!-- Mensajes de alerta -->
            <?php if ($error): ?>
                <div class="container mt-4">
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <?= htmlspecialchars($error) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="container mt-4">
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i>
                        <?= htmlspecialchars($success) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if ($update): ?>
                <div class="container mt-4">
                    <div class="alert alert-info alert-dismissible fade show" role="alert">
                        <i class="fas fa-info-circle me-2"></i>
                        <?= htmlspecialchars($update) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                </div>
            <?php endif; ?>
        </header>

        <div class="container">
            <!-- Employee Management Accordion -->
            <section class="mb-4">
                <div class="accordion" id="employeeAccordion">
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="employeeHeading">
                            <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#employeeCollapse" aria-expanded="true" aria-controls="employeeCollapse">
                                <div class="d-flex align-items-center w-100">
                                    <i class="fas fa-users me-3"></i>
                                    <div class="flex-grow-1">
                                        <div class="fw-bold fs-5">Gestionar Empleados</div>
                                        <small class="text-light opacity-75">Configura el tipo de empleado, horarios y permisos</small>
                                    </div>
                                    <div class="ms-3">
                                        <span class="badge bg-light text-dark me-2" id="activeCount">
                                            <i class="fas fa-user-check me-1"></i>
                                            <?= count(array_filter($employees, fn($emp) => $emp['active'])) ?> Activos
                                        </span>
                                        <span class="badge bg-light text-dark">
                                            <i class="fas fa-users me-1"></i>
                                            <?= count($employees) ?> Total
                                        </span>
                                    </div>
                                </div>
                            </button>
                        </h2>
                        <div id="employeeCollapse" class="accordion-collapse collapse show" aria-labelledby="employeeHeading" data-bs-parent="#employeeAccordion">
                            <div class="accordion-body">
                                <!-- Estadísticas rápidas -->
                                <div class="row text-center mb-4" id="statsContainer">
                                    <div class="col-md-3">
                                        <div class="d-flex align-items-center justify-content-center">
                                            <i class="fas fa-user-check text-success me-2 fs-4"></i>
                                            <div>
                                                <div class="fw-bold text-success fs-5" id="activeEmployeesCount"><?= count(array_filter($employees, fn($emp) => $emp['active'])) ?></div>
                                                <small class="text-muted">Empleados Activos</small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="d-flex align-items-center justify-content-center">
                                            <i class="fas fa-sync-alt text-info me-2 fs-4"></i>
                                            <div>
                                                <div class="fw-bold text-info fs-5" id="rotativeEmployeesCount"><?= count(array_filter($employees, fn($emp) => empty($emp['fixed_schedule']) && $emp['active'])) ?></div>
                                                <small class="text-muted">Rotativos</small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="d-flex align-items-center justify-content-center">
                                            <i class="fas fa-clock text-primary me-2 fs-4"></i>
                                            <div>
                                                <div class="fw-bold text-primary fs-5" id="fixedEmployeesCount"><?= count(array_filter($employees, fn($emp) => !empty($emp['fixed_schedule']) && $emp['active'])) ?></div>
                                                <small class="text-muted">Horario Fijo</small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="d-flex align-items-center justify-content-center">
                                            <i class="fas fa-user-tie text-warning me-2 fs-4"></i>
                                            <div>
                                                <div class="fw-bold text-warning fs-5" id="supervisorEmployeesCount"><?= count(array_filter($employees, fn($emp) => !empty($emp['supervisor']) && $emp['active'])) ?></div>
                                                <small class="text-muted">Supervisores</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Formulario de empleados -->
                                <form method="POST" id="employeeForm">
                                    <div class="row">
                                        <?php foreach ($employees as $index => $employee): ?>
                                            <div class="col-md-6 col-lg-4 mb-3">
                                                <div class="employee-card <?= !$employee['active'] ? 'inactive' : '' ?>" id="employeeCard_<?= $index ?>">
                                                    <div class="d-flex justify-content-between align-items-start mb-3">
                                                        <h6 class="fw-bold mb-0"><?= htmlspecialchars($employee['name']) ?></h6>
                                                        <div class="form-check form-switch">
                                                            <input class="form-check-input active-switch" type="checkbox" 
                                                                   name="active[<?= $index ?>]" 
                                                                   id="active_<?= $index ?>" 
                                                                   data-index="<?= $index ?>"
                                                                   <?= $employee['active'] ? 'checked' : '' ?>>
                                                            <label class="form-check-label" for="active_<?= $index ?>">
                                                                <small>Activo</small>
                                                            </label>
                                                        </div>
                                                    </div>

                                                    <!-- Tipo de empleado -->
                                                    <div class="mb-3">
                                                        <label class="form-label small fw-bold">Tipo de empleado:</label>
                                                        <div class="d-flex gap-3">
                                                            <div class="form-check">
                                                                <input class="form-check-input employee-type-radio" 
                                                                       type="radio" 
                                                                       name="employee_type[<?= $index ?>]" 
                                                                       value="rotative" 
                                                                       id="rotative_<?= $index ?>" 
                                                                       data-index="<?= $index ?>"
                                                                       <?= empty($employee['fixed_schedule']) ? 'checked' : '' ?>>
                                                                <label class="form-check-label small" for="rotative_<?= $index ?>">
                                                                    Rotativo
                                                                </label>
                                                            </div>
                                                            <div class="form-check">
                                                                <input class="form-check-input employee-type-radio" 
                                                                       type="radio" 
                                                                       name="employee_type[<?= $index ?>]" 
                                                                       value="fixed" 
                                                                       id="fixed_<?= $index ?>" 
                                                                       data-index="<?= $index ?>"
                                                                       <?= !empty($employee['fixed_schedule']) ? 'checked' : '' ?>>
                                                                <label class="form-check-label small" for="fixed_<?= $index ?>">
                                                                    Horario Fijo
                                                                </label>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <!-- Supervisor -->
                                                    <div class="form-check mb-3">
                                                        <input class="form-check-input supervisor-checkbox" type="checkbox" 
                                                               name="supervisor[<?= $index ?>]" 
                                                               id="supervisor_<?= $index ?>" 
                                                               data-index="<?= $index ?>"
                                                               <?= !empty($employee['supervisor']) ? 'checked' : '' ?>>
                                                        <label class="form-check-label small" for="supervisor_<?= $index ?>">
                                                            Es supervisor
                                                        </label>
                                                    </div>

                                                    <!-- Horarios fijos (oculto por defecto) -->
                                                    <div class="time-inputs <?= !empty($employee['fixed_schedule']) ? 'show' : '' ?>" id="timeInputs_<?= $index ?>">
                                                        <label class="form-label small fw-bold">Horario fijo:</label>
                                                        <div class="row">
                                                            <div class="col-6">
                                                                <label class="form-label small">Inicio:</label>
                                                                <input type="time" class="form-control form-control-sm" 
                                                                       name="start_time[<?= $index ?>]" 
                                                                       value="<?= $employee['fixed_schedule'][0] ?? '' ?>">
                                                            </div>
                                                            <div class="col-6">
                                                                <label class="form-label small">Fin:</label>
                                                                <input type="time" class="form-control form-control-sm" 
                                                                       name="end_time[<?= $index ?>]" 
                                                                       value="<?= $employee['fixed_schedule'][1] ?? '' ?>">
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <!-- Badges de estado -->
                                                    <div class="mt-3 badge-container" id="badgeContainer_<?= $index ?>">
                                                        <!-- Los badges se actualizarán dinámicamente con JavaScript -->
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>

                                    <!-- Botones de acción -->
                                    <div class="d-flex gap-2 mt-4">
                                        <button type="submit" name="save_employees" class="btn btn-primary">
                                            <i class="fas fa-save me-2"></i>Guardar Cambios
                                        </button>
                                        <button type="button" class="btn btn-secondary" onclick="resetForm()">
                                            <i class="fas fa-undo me-2"></i>Resetear
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Schedule Generator Section -->
            <section class="mb-4">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-calendar-plus me-2"></i>Generador de Horarios
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" class="row g-3 align-items-end">
                            <div class="col-md-4">
                                <label for="month" class="form-label">Seleccionar Mes:</label>
                                <input type="month" class="form-control" name="month" id="month" 
                                       value="<?= date('Y-m') ?>" required>
                            </div>
                            <div class="col-md-8">
                                <div class="d-flex gap-2">
                                    <button type="submit" name="preview_schedule" class="btn btn-outline-primary">
                                        <i class="fas fa-eye me-2"></i>Vista Previa
                                    </button>
                                    <button type="submit" name="generate_schedule" class="btn btn-primary">
                                        <i class="fas fa-calendar-check me-2"></i>Generar y Guardar
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </section>

            <!-- Generated Schedule Section -->
            <?php if (!empty($generatedSchedule)): ?>
                <section class="mb-4">
                    <div class="card">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0">
                                <i class="fas fa-calendar-alt me-2"></i>Horario Generado
                                <?php if (isset($generatedSchedule['month'])): ?>
                                    - <?= translateMonthToSpanish(date('F Y', strtotime($generatedSchedule['month'] . '-01'))) ?>
                                <?php endif; ?>
                            </h5>
                        </div>
                        <div class="card-body">
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
                                <div class="mb-4">
                                    <h6 class="text-muted mb-3">
                                        Semana <?= $weekIndex + 1 ?>: 
                                        <?= translateMonthToSpanish($weekStart->format('d F')) ?> - 
                                        <?= translateMonthToSpanish($weekEnd->format('d F Y')) ?>
                                    </h6>
                                    
                                    <div class="row">
                                        <?php 
                                        $days = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];
                                        for ($dayNum = 1; $dayNum <= 7; $dayNum++): 
                                            $daySchedule = $week[$dayNum] ?? [];
                                        ?>
                                            <div class="col">
                                                <div class="calendar-day border rounded p-2 bg-light">
                                                    <div class="fw-bold text-center mb-2 small">
                                                        <?= $days[$dayNum - 1] ?>
                                                        <?php if (!empty($daySchedule)): ?>
                                                            <br><small class="text-muted"><?= date('d/m', strtotime($daySchedule[0]['date'])) ?></small>
                                                        <?php endif; ?>
                                                    </div>
                                                    
                                                    <?php foreach ($daySchedule as $entry): ?>
                                                        <?php
                                                        $badgeClass = 'bg-secondary';
                                                        switch ($entry['shift']) {
                                                            case 'Mañana': $badgeClass = 'bg-warning text-dark'; break;
                                                            case 'Tarde': $badgeClass = 'bg-info text-dark'; break;
                                                            case 'Noche': $badgeClass = 'bg-dark'; break;
                                                            case 'Descanso': $badgeClass = 'bg-success'; break;
                                                            case 'Oficina': $badgeClass = 'bg-primary'; break;
                                                        }
                                                        ?>
                                                        <div class="shift-badge badge <?= $badgeClass ?> d-block text-start mb-1">
                                                            <div class="fw-bold"><?= htmlspecialchars($entry['employee']) ?></div>
                                                            <div><?= htmlspecialchars($entry['shift']) ?></div>
                                                            <?php if ($entry['start'] && $entry['end']): ?>
                                                                <small><?= $entry['start'] ?> - <?= $entry['end'] ?></small>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
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
    </div>

    <!-- Footer -->
    <footer class="bg-dark text-white text-center py-3 mt-5">
        <div class="container">
            <p class="mb-0">&copy; 2024 Generador de Horarios NOC 2x2. Sistema de gestión de turnos.</p>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
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
                badges += '<span class="badge bg-success me-1"><i class="fas fa-check me-1"></i>Activo</span>';
            } else {
                badges += '<span class="badge bg-secondary me-1"><i class="fas fa-pause me-1"></i>Inactivo</span>';
            }
            
            // Badge de supervisor
            if (isSupervisor) {
                badges += '<span class="badge bg-warning text-dark me-1"><i class="fas fa-user-tie me-1"></i>Supervisor</span>';
            }
            
            // Badge de tipo de empleado
            if (isFixed) {
                badges += '<span class="badge bg-info me-1"><i class="fas fa-clock me-1"></i>Horario Fijo</span>';
            } else {
                badges += '<span class="badge bg-primary me-1"><i class="fas fa-sync-alt me-1"></i>Rotativo</span>';
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
            document.getElementById('activeCount').innerHTML = `<i class="fas fa-user-check me-1"></i>${activeCount} Activos`;
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