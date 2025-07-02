<?php
require_once 'connect.php';
 
header('Content-Type: application/json');
ob_clean(); // Clear any previous output buffer
 
if (!isset($_GET['province']) || empty($_GET['province'])) {
    echo json_encode([]);
    exit;
}
 
$conn = connectToDatabase();
if ($conn === false) {
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}
 
$province = $_GET['province'];
 
$sql = "SELECT schoolID, schoolName FROM Schools WHERE province = ?";
$params = [$province];
$stmt = sqlsrv_query($conn, $sql, $params);
 
$schools = [];
if ($stmt) {
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $schools[] = [
            'schoolID' => $row['schoolID'],
            'schoolName' => $row['schoolName']
        ];
    }
} else {
    // If query fails, return error message
    echo json_encode(['error' => 'Query execution failed']);
    exit;
}
 
// Ensure no whitespace before/after JSON
echo json_encode($schools);
exit;
?>