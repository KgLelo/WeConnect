<?php 
require_once 'connect.php';
$conn = connectToDatabase();

// Collect form data
$role      = $_POST['role'] ?? '';
$fullName  = $_POST['fullName'] ?? '';
$userName  = $_POST['userName'] ?? '';
$password  = $_POST['password'] ?? '';
$phoneNum  = $_POST['phoneNum'] ?? '';
$address   = $_POST['address'] ?? '';
$childUserName = $_POST['childUserName']??'';
$school    = $_POST['schools'] ?? null;
$grade     = $_POST['grade'] ?? null;

// === Step 1: Check if username exists in ANY role ===
$checkSql = "
    SELECT userName FROM TeacherTable WHERE userName = ?
    UNION
    SELECT userName FROM ParentTable WHERE userName = ?
    UNION
    SELECT userName FROM LearnerTable WHERE userName = ?
";

$checkParams = [$userName, $userName, $userName];
$checkStmt = sqlsrv_prepare($conn, $checkSql, $checkParams);

$learnerSql = "SELECT learner_id FROM LearnerTable WHERE userName = ?";
            $learnerParams = array($childUserName);
            $learnerStmt = sqlsrv_query($conn, $learnerSql, $learnerParams);
 
            if ($learnerStmt === false || !($learnerRow = sqlsrv_fetch_array($learnerStmt, SQLSRV_FETCH_ASSOC))) {
                header("Location: register.html?error=invalid_child_username");
                exit();
            }
 
            $learner_id = $learnerRow['learner_id'];

if (!$checkStmt || !sqlsrv_execute($checkStmt)) {
    // Redirect with SQL error
    header("Location: register.html?error=" . urlencode("❌ Database check failed. Please try again."));
    exit();
}

// If user already exists in any table, prevent duplicate registration
if (sqlsrv_fetch($checkStmt)) {
    $message = "❌ Username \"$userName\" is already registered under another role. Please choose a different username.";
    header("Location: register.html?error=" . urlencode($message));
    exit();
}

// === Step 2: Prepare insert based on role ===
switch ($role) {
    case "teacher":
        $insertSql = "INSERT INTO TeacherTable (fullName, userName, password, phoneNum, province, address, school)
                      VALUES (?, ?, ?, ?, ?, ?, ?)";
        $params = [$fullName, $userName, $password, $phoneNum, $province, $address, $school];
        break;

    case "learner":
        $insertSql = "INSERT INTO LearnerTable (fullName, userName, password, phoneNum, province, address, school, grade)
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $params = [$fullName, $userName, $password, $phoneNum, $province, $address, $school, $grade];
        break;

    case "parent":
        $insertSql = "INSERT INTO ParentTable (fullName, userName, password, phoneNum, address, learner_id)
                      VALUES (?, ?, ?, ?, ?, ?)";
        $params = [$fullName, $userName, $password, $phoneNum, $address, $learner_id];
        break;

    default:
        header("Location: register.html?error=" . urlencode("❌ Invalid role selected."));
        exit();
}

// === Step 3: Insert the user ===
$insertStmt = sqlsrv_prepare($conn, $insertSql, $params);
if (!$insertStmt || !sqlsrv_execute($insertStmt)) {
    header("Location: register.html?error=" . urlencode("❌ Failed to register. Please try again."));
    exit();
}

// === Step 4: Redirect on success ===
header("Location: login.html?success=" . urlencode("✅ Registration successful! You may now login."));
exit();
?>
