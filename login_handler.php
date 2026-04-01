<?php
header('Content-Type: application/json');
require_once 'config.php';

$conn = getDbConnection();
$data = json_decode(file_get_contents('php://input'), true);

$email = $conn->real_escape_string($data['email']);
$password = $data['password'];

$sql = "SELECT id, name, email, password FROM users WHERE email = '$email'";
$result = $conn->query($sql);
if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();
    if (password_verify($password, $user['password'])) {
        echo json_encode([
            "success" => true,
            "user" => [
                "id" => $user['id'],
                "name" => $user['name'],
                "email" => $user['email']
            ]
        ]);
    } else {
        echo json_encode(["success" => false, "message" => "Incorrect password."]);
    }
} else {
    echo json_encode(["success" => false, "message" => "User not found."]);
}

$conn->close();
?>
