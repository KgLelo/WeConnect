<?php
//session_start();
require_once 'connect.php';

if (!isset($_SESSION['userName']) || !isset($_SESSION['role'])) {
    header("Location: login.php");
    exit();
}

$conn = connectToDatabase();
$userName = $_SESSION['userName'];
$role = strtolower($_SESSION['role']);

$tableMap = [
    'teacher' => 'TeacherTable',
    'learner' => 'LearnerTable',
    'parent' => 'ParentTable'
];

$table = $tableMap[$role] ?? '';

if (!$table) {
    echo "Invalid role.";
    exit();
}

// Update data if submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fields = array_keys($_POST);
    $setPart = implode(', ', array_map(fn($f) => "$f = ?", $fields));
    $params = array_values($_POST);
    $params[] = $userName; // where condition

    $updateQuery = "UPDATE $table SET $setPart WHERE userName = ?";
    $stmt = sqlsrv_prepare($conn, $updateQuery, $params);
    if (sqlsrv_execute($stmt)) {
        echo "<p style='color:green;'>✅ Profile updated successfully.</p>";
    } else {
        echo "<p style='color:red;'>❌ Failed to update profile.</p>";
    }
}

// Fetch user data
$query = "SELECT * FROM $table WHERE userName = ?";
$stmt = sqlsrv_prepare($conn, $query, [$userName]);
sqlsrv_execute($stmt);
$row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

if (!$row) {
    echo "<p style='color:red;'>❌ User data not found.</p>";
    exit();
}
?>

<h2 style="color:#004aad;">👤 My Profile</h2>
<form method="POST" style="max-width: 600px;">
  <?php
  foreach ($row as $key => $value) {
      if ($key === 'userName' || $key === 'password') continue;
      echo "<label style='font-weight:bold;color:#004aad;'>" . ucfirst($key) . ":</label>";
      echo "<input type='text' name='$key' value='" . htmlspecialchars($value) . "' style='width:100%;padding:8px;margin-bottom:10px;border:1px solid #ccc;border-radius:5px;'>";
  }
  ?>
  <button type="submit" style="background-color:#004aad;color:white;padding:10px 20px;border:none;border-radius:5px;font-size:14px;">Update Profile</button>
</form>
