<?php
require_once 'includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $startDate = $_POST['start_date'] ?? date('Y-m-d');
    $weeks = intval($_POST['weeks'] ?? 4);
    $employees = loadEmployees();
    
    $generatedSchedule = generateRotativeSchedule($employees, $startDate, $weeks);
    
    if (!isset($generatedSchedule['error'])) {
        $schedules = loadSchedules();
        $schedules = array_merge($schedules, $generatedSchedule);
        saveSchedules($schedules);
        header('Location: index.php?success=1');
        exit;
    } else {
        header('Location: index.php?error=' . urlencode($generatedSchedule['error']));
        exit;
    }
} else {
    header('Location: index.php');
    exit;
}