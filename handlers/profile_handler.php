<?php
session_start();
header('Content-Type: application/json');

// Database connection
$conn = new mysqli('localhost', 'root', '', 'New_RecruitWarX'); // Change DB name

if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

$action = $_GET['action'] ?? null;

if ($action === 'get_profile') {
    $sql = "SELECT first_name, last_name, email, phone, location, bio, role, profile_completion, work_experience, skills, education 
            FROM users WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();

    if ($result) {
        echo json_encode(['success' => true, 'profile' => $result]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Profile not found']);
    }
    $stmt->close();
    $conn->close();
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action']);
$conn->close();
exit;
?>
