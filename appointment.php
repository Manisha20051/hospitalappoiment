<?php
session_start();
require 'config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $hospital_id = filter_input(INPUT_POST, 'hospital_id', FILTER_SANITIZE_NUMBER_INT);
    $department = filter_input(INPUT_POST, 'department', FILTER_SANITIZE_STRING);
    $appointment_date = filter_input(INPUT_POST, 'date', FILTER_SANITIZE_STRING);
    $time_slot = filter_input(INPUT_POST, 'time', FILTER_SANITIZE_STRING);
    $reason = filter_input(INPUT_POST, 'reason', FILTER_SANITIZE_STRING);

    // Validate inputs
    if (empty($hospital_id) || empty($department) || empty($appointment_date) || empty($time_slot)) {
        echo json_encode(['success' => false, 'message' => 'All fields are required']);
        exit;
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $appointment_date)) {
        echo json_encode(['success' => false, 'message' => 'Invalid date format']);
        exit;
    }
    $valid_times = ['morning', 'afternoon', 'evening'];
    if (!in_array($time_slot, $valid_times)) {
        echo json_encode(['success' => false, 'message' => 'Invalid time slot']);
        exit;
    }
    $valid_departments = ['cardiology', 'orthopedics', 'neurology', 'pediatrics', 'oncology'];
    if (!in_array(strtolower($department), $valid_departments)) {
        echo json_encode(['success' => false, 'message' => 'Invalid department']);
        exit;
    }

    try {
        // Check hospital exists
        $stmt = $pdo->prepare("SELECT id FROM hospitals WHERE id = ?");
        $stmt->execute([$hospital_id]);
        if (!$stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Invalid hospital']);
            exit;
        }

        // Check slot availability (example: prevent double-booking)
        $stmt = $pdo->prepare("
            SELECT id FROM appointments
            WHERE hospital_id = ? AND appointment_date = ? AND time_slot = ? AND status != 'Cancelled'
        ");
        $stmt->execute([$hospital_id, $appointment_date, $time_slot]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Time slot already booked']);
            exit;
        }

        // Book appointment
        $stmt = $pdo->prepare("
            INSERT INTO appointments (user_id, hospital_id, department, appointment_date, time_slot, reason, status)
            VALUES (?, ?, ?, ?, ?, ?, 'Scheduled')
        ");
        $stmt->execute([$user_id, $hospital_id, $department, $appointment_date, $time_slot, $reason ?: null]);

        echo json_encode(['success' => true, 'message' => 'Appointment booked successfully']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
}
?>