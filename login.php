<?php
require_once 'connect.php';
$conn = connectToDatabase();

if (session_status() === PHP_SESSION_NONE) session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $role = strtolower($_POST['role'] ?? '');
    $userName = $_POST['userName'] ?? '';
    $password = $_POST['password'] ?? '';
    $action = $_POST['action'] ?? 'login'; // login or reset

    // Choose correct table
    switch ($role) {
        case "teacher":
            $table = "TeacherTable";
            break;
        case "learner":
            $table = "LearnerTable";
            break;
        case "parent":
            $table = "ParentTable";
            break;
        default:
            header("Location: login.html?error=invalid_role");
            exit();
    }

    if ($action === 'login') {
        // Check user credentials
        $sql = "SELECT * FROM $table WHERE userName = ? AND password = ?";
        $params = [$userName, $password];
        $stmt = sqlsrv_query($conn, $sql, $params);

        if ($stmt === false) {
            die("❌ Database error: " . print_r(sqlsrv_errors(), true));
        }

        $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

        if ($row) {
            // Successful login - set session variables
            $_SESSION['userName'] = $userName;
            $_SESSION['role'] = $role;

            $_SESSION['province'] = $row['province'] ?? '';
            $_SESSION['school'] = $row['school'] ?? '';
            $_SESSION['grade'] = $row['grade'] ?? '';
            $_SESSION['fullName'] = $row['fullName'] ?? '';

            if ($role === 'parent') {
                // Fetch linked learner info using learner_id from ParentTable
                $learner_id = $row['learner_id'] ?? null;
                if ($learner_id) {
                    $learnerSql = "SELECT * FROM LearnerTable WHERE learner_id = ?";
                    $learnerStmt = sqlsrv_query($conn, $learnerSql, [$learner_id]);
                    if ($learnerStmt !== false) {
                        $learnerRow = sqlsrv_fetch_array($learnerStmt, SQLSRV_FETCH_ASSOC);
                        if ($learnerRow) {
                            $_SESSION['linkedLearner'] = [
                                'learner_id' => $learnerRow['learner_id'],
                                'fullName' => $learnerRow['fullName'] ?? '',
                                'userName' => $learnerRow['userName'] ?? '',
                                'grade' => $learnerRow['grade'] ?? '',
                                'school' => $learnerRow['school'] ?? '',
                                'province' => $learnerRow['province'] ?? '',
                            ];
                        }
                    }
                }
            }

            header("Location: dashboard.php");
            exit();
        } else {
            header("Location: login.html?error=invalid_login");
            exit();
        }
    } elseif ($action === 'reset') {
        // Reset Password
        $defaultPassword = "password123";
        $sql = "UPDATE $table SET password = ? WHERE userName = ?";
        $params = [$defaultPassword, $userName];
        $stmt = sqlsrv_query($conn, $sql, $params);

        if ($stmt === false) {
            die("❌ Reset error: " . print_r(sqlsrv_errors(), true));
        }

        header("Location: login.html?success=reset_done");
        exit();
    }
} else {
    header("Location: login.html");
    exit();
}
?>
