<?php
require 'connect.php';
$conn = connectToDatabase();

if (!isset($_SESSION)) session_start();
if (!isset($_SESSION['userName']) || !isset($_SESSION['role'])) {
    header("Location: login.php");
    exit();
}

$role = strtolower($_SESSION['role']);
$userName = $_SESSION['userName'];

// Initialize province and school variables
$province = $_SESSION['province'] ?? '';
$school = $_SESSION['school'] ?? '';

// If empty, fetch from DB according to role
if (empty($province) || empty($school)) {
    if ($role === 'teacher') {
        $table = 'TeacherTable';
        $userCol = 'userName';
        $provinceCol = 'province';
        $schoolCol = 'school';

        $sql = "SELECT $provinceCol, $schoolCol FROM $table WHERE $userCol = ?";
        $params = [$userName];
        $stmt = sqlsrv_query($conn, $sql, $params);
        if ($stmt === false) {
            die(print_r(sqlsrv_errors(), true));
        }
        if ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $province = $row[$provinceCol];
            $school = $row[$schoolCol];
            $_SESSION['province'] = $province;
            $_SESSION['school'] = $school;
        } else {
            die("User data not found.");
        }
    } elseif ($role === 'learner') {
        $table = 'LearnerTable';
        $userCol = 'userName';
        $provinceCol = 'province';
        $schoolCol = 'school';

        $sql = "SELECT $provinceCol, $schoolCol FROM $table WHERE $userCol = ?";
        $params = [$userName];
        $stmt = sqlsrv_query($conn, $sql, $params);
        if ($stmt === false) {
            die(print_r(sqlsrv_errors(), true));
        }
        if ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $province = $row[$provinceCol];
            $school = $row[$schoolCol];
            $_SESSION['province'] = $province;
            $_SESSION['school'] = $school;
        } else {
            die("User data not found.");
        }
    } elseif ($role === 'parent') {
        // For parents, fetch linked learner_id first
        $parentQuery = sqlsrv_query($conn, "SELECT learner_id FROM ParentTable WHERE userName = ?", [$userName]);
        if ($parentQuery && ($parentRow = sqlsrv_fetch_array($parentQuery, SQLSRV_FETCH_ASSOC))) {
            $linkedLearnerID = $parentRow['learner_id'];
            // Fetch linked learner's province and school
            $learnerQuery = sqlsrv_query($conn, "SELECT province, school FROM LearnerTable WHERE learner_id = ?", [$linkedLearnerID]);
            if ($learnerQuery && ($learnerRow = sqlsrv_fetch_array($learnerQuery, SQLSRV_FETCH_ASSOC))) {
                $province = $learnerRow['province'];
                $school = $learnerRow['school'];
                $_SESSION['province'] = $province;
                $_SESSION['school'] = $school;
            } else {
                // fallback to parent's own info if learner not found
                $fallbackQuery = sqlsrv_query($conn, "SELECT province, school FROM ParentTable WHERE userName = ?", [$userName]);
                if ($fallbackQuery && ($fallbackRow = sqlsrv_fetch_array($fallbackQuery, SQLSRV_FETCH_ASSOC))) {
                    $province = $fallbackRow['province'];
                    $school = $fallbackRow['school'];
                    $_SESSION['province'] = $province;
                    $_SESSION['school'] = $school;
                } else {
                    die("User data not found.");
                }
            }
        } else {
            // fallback if no linked learner_id
            $fallbackQuery = sqlsrv_query($conn, "SELECT province, school FROM ParentTable WHERE userName = ?", [$userName]);
            if ($fallbackQuery && ($fallbackRow = sqlsrv_fetch_array($fallbackQuery, SQLSRV_FETCH_ASSOC))) {
                $province = $fallbackRow['province'];
                $school = $fallbackRow['school'];
                $_SESSION['province'] = $province;
                $_SESSION['school'] = $school;
            } else {
                die("User data not found.");
            }
        }
    } else {
        die("Access denied: unknown user role.");
    }
}

// Initialize message variable for feedback
$message = '';

if ($role === 'teacher') {
    if (isset($_POST['add'])) {
        $title = trim($_POST['title']);
        $content = trim($_POST['content']);
        if ($title !== '' && $content !== '') {
            $sql = "INSERT INTO news (title, content, province, school, created_by, created_at) VALUES (?, ?, ?, ?, ?, GETDATE())";
            $stmt = sqlsrv_prepare($conn, $sql, [$title, $content, $province, $school, $userName]);
            if ($stmt && sqlsrv_execute($stmt)) {
                $message = "<div style='color:green; font-weight:bold; margin-bottom:15px;'>✅ News published successfully.</div>";
            } else {
                $errors = sqlsrv_errors();
                $message = "<div style='color:red; font-weight:bold; margin-bottom:15px;'>❌ Failed to publish news.";
                if ($errors) {
                    $message .= " Error: " . htmlspecialchars(print_r($errors, true));
                }
                $message .= "</div>";
            }
        } else {
            $message = "<div style='color:red; font-weight:bold; margin-bottom:15px;'>❌ Title and Content cannot be empty.</div>";
        }
    }

    if (isset($_POST['delete'])) {
        $id = intval($_POST['id']);
        if ($id > 0) {
            // Delete only if created by this user
            $sql = "DELETE FROM news WHERE id = ? AND created_by = ?";
            $stmt = sqlsrv_prepare($conn, $sql, [$id, $userName]);
            if ($stmt && sqlsrv_execute($stmt)) {
                $message = "<div style='color:green; font-weight:bold; margin-bottom:15px;'>🗑️ News deleted successfully.</div>";
            } else {
                $errors = sqlsrv_errors();
                $message = "<div style='color:red; font-weight:bold; margin-bottom:15px;'>❌ Failed to delete news.";
                if ($errors) {
                    $message .= " Error: " . htmlspecialchars(print_r($errors, true));
                }
                $message .= "</div>";
            }
        } else {
            $message = "<div style='color:red; font-weight:bold; margin-bottom:15px;'>❌ Invalid news ID for deletion.</div>";
        }
    }
}

// Fetch news for current school
$query = "SELECT * FROM news WHERE school = ? ORDER BY created_at DESC";
$stmt = sqlsrv_query($conn, $query, [$school]);
if ($stmt === false) {
    die(print_r(sqlsrv_errors(), true));
}
?>

<div style="max-width:900px; margin:auto;">

    <h2 style="color:#004aad; margin-bottom: 25px;">
      📢 School News & Announcements for:
    </h2>
    <div style="margin-bottom: 20px;">
        <label for="school" style="font-weight: bold; color: #004aad;">School:</label><br />
        <input type="text" id="school" name="school" value="<?= htmlspecialchars($school) ?>" readonly
            style="width: 100%; padding: 8px; margin-bottom: 10px; border: 1px solid #ccc; border-radius: 4px; font-size: 15px;" />
        
        <label for="province" style="font-weight: bold; color: #004aad;">Province:</label><br />
        <input type="text" id="province" name="province" value="<?= htmlspecialchars($province) ?>" readonly
            style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; font-size: 15px;" />
    </div>

    <?php
    // Show success/failure message here
    if ($message !== '') {
        echo $message;
    }
    ?>

    <?php if ($role === 'teacher'): ?>
        <form method="POST" style="background:#fff; padding:20px; border-radius:8px; box-shadow:0 2px 5px rgba(0,0,0,0.05); margin-bottom:30px;">
            <h3 style="margin-top:0; color:#004aad;">📝 Add New Announcement</h3>
            <input
                type="text"
                name="title"
                placeholder="News Title"
                required
                style="width:100%; padding:10px; margin:8px 0 15px 0; border:1px solid #ccc; border-radius:6px; font-size:15px;"
            />
            <textarea
                name="content"
                rows="4"
                placeholder="News Content..."
                required
                style="width:100%; padding:10px; margin-bottom:15px; border:1px solid #ccc; border-radius:6px; font-size:15px; resize:vertical;"
            ></textarea>
            <button
                type="submit"
                name="add"
                style="background:#004aad; color:#fff; padding:10px 20px; border:none; border-radius:4px; cursor:pointer; font-weight:bold;"
            >
                Publish News
            </button>
        </form>
    <?php endif; ?>

    <?php while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)): ?>
        <div style="background:#fff; padding:20px; margin-bottom:20px; border-left:5px solid #004aad; border-radius:6px; box-shadow:0 1px 4px rgba(0,0,0,0.08);">
            <h3 style="margin-top:0; margin-bottom:10px; color:#222;"><?= htmlspecialchars($row['title']) ?></h3>
            <p style="margin-bottom:10px; line-height:1.6; white-space: pre-wrap;"><?= nl2br(htmlspecialchars($row['content'])) ?></p>
            <small style="color:#666;">
                📅 <?= $row['created_at'] instanceof DateTime ? $row['created_at']->format('Y-m-d H:i') : htmlspecialchars($row['created_at']) ?>
                | 👤 <?= htmlspecialchars($row['created_by']) ?>
            </small>

            <?php if ($role === 'teacher' && $row['created_by'] === $userName): ?>
                <form method="POST" style="margin-top:15px; background:#f9f9f9; padding:15px; border-radius:5px;">
                    <input type="hidden" name="id" value="<?= $row['id'] ?>" />
                    <input
                        type="text"
                        name="title"
                        value="<?= htmlspecialchars($row['title']) ?>"
                        required
                        style="width:100%; padding:10px; margin-bottom:12px; border:1px solid #ccc; border-radius:6px; font-size:15px;"
                    />
                    <textarea
                        name="content"
                        rows="3"
                        required
                        style="width:100%; padding:10px; margin-bottom:12px; border:1px solid #ccc; border-radius:6px; font-size:15px; resize:vertical;"
                    ><?= htmlspecialchars($row['content']) ?></textarea>
                    <button
                        type="submit"
                        name="update"
                        style="background:#004aad; color:#fff; padding:8px 16px; border:none; border-radius:5px; cursor:pointer; font-weight:bold;"
                    >
                        Update
                    </button>
                    <button
                        type="submit"
                        name="delete"
                        onclick="return confirm('Delete this announcement?')"
                        style="background:#d60000; color:#fff; padding:8px 16px; border:none; border-radius:5px; cursor:pointer; font-weight:bold; margin-left:8px;"
                    >
                        Delete
                    </button>
                </form>
            <?php endif; ?>
        </div>
    <?php endwhile; ?>

</div>
