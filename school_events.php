<?php
if (!isset($_SESSION)) session_start();
if (!isset($_SESSION['userName']) || !isset($_SESSION['role'])) {
    header("Location: login.php");
    exit();
}

require 'connect.php';
$conn = connectToDatabase();

$role = strtolower($_SESSION['role']);
$userName = $_SESSION['userName'];
$isTeacher = $role === 'teacher';

// Fetch teacher's province and school
$teacherProvince = '';
$teacherSchool = '';

if ($isTeacher) {
    $tQuery = sqlsrv_query($conn, "SELECT province, school FROM TeacherTable WHERE userName = ?", [$userName]);
    if ($tQuery && ($row = sqlsrv_fetch_array($tQuery, SQLSRV_FETCH_ASSOC))) {
        $teacherProvince = $row['province'];
        $teacherSchool = $row['school'];
    }
}

// Fetch learner/parent province, school, grade
$province = '';
$school = '';
$grade = '';

if ($role === 'learner') {
    $query = sqlsrv_query($conn, "SELECT province, school, grade FROM LearnerTable WHERE userName = ?", [$userName]);
    if ($query && ($row = sqlsrv_fetch_array($query, SQLSRV_FETCH_ASSOC))) {
        $province = $row['province'];
        $school = $row['school'];
        $grade = $row['grade'];
    }
} elseif ($role === 'parent') {
    // Get linked learner_id from ParentTable
    $query = sqlsrv_query($conn, "SELECT learner_id FROM ParentTable WHERE userName = ?", [$userName]);
    if ($query && ($row = sqlsrv_fetch_array($query, SQLSRV_FETCH_ASSOC))) {
        $learner_id = $row['learner_id'];
        // Fetch learner's province, school, grade by learner_id
        $query2 = sqlsrv_query($conn, "SELECT province, school, grade FROM LearnerTable WHERE learner_id = ?", [$learner_id]);
        if ($query2 && ($learner = sqlsrv_fetch_array($query2, SQLSRV_FETCH_ASSOC))) {
            $province = $learner['province'];
            $school = $learner['school'];
            $grade = $learner['grade'];
        } else {
            // fallback to parent's own data if linked learner not found
            $fallbackQuery = sqlsrv_query($conn, "SELECT province, school, grade FROM ParentTable WHERE userName = ?", [$userName]);
            if ($fallbackQuery && ($fb = sqlsrv_fetch_array($fallbackQuery, SQLSRV_FETCH_ASSOC))) {
                $province = $fb['province'];
                $school = $fb['school'];
                $grade = $fb['grade'];
            }
        }
    } else {
        // fallback if no linked learner_id found
        $fallbackQuery = sqlsrv_query($conn, "SELECT province, school, grade FROM ParentTable WHERE userName = ?", [$userName]);
        if ($fallbackQuery && ($fb = sqlsrv_fetch_array($fallbackQuery, SQLSRV_FETCH_ASSOC))) {
            $province = $fb['province'];
            $school = $fb['school'];
            $grade = $fb['grade'];
        }
    }
}

// Handle Add, Update, Delete (Teacher Only)
if ($isTeacher && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add'])) {
        $title = $_POST['title'];
        $date = $_POST['date'];
        $startTime = $_POST['startTime'];
        $endTime = $_POST['endTime'];
        $desc = $_POST['description'];

        sqlsrv_query($conn, "INSERT INTO SchoolEvents (eventTitle, eventDate, startTime, endTime, eventDescription, province, school) VALUES (?, ?, ?, ?, ?, ?, ?)", [
            $title, $date, $startTime, $endTime, $desc, $teacherProvince, $teacherSchool
        ]);
    }

    if (isset($_POST['update'])) {
        $id = $_POST['eventID'];
        $title = $_POST['title'];
        $date = $_POST['date'];
        $startTime = $_POST['startTime'];
        $endTime = $_POST['endTime'];
        $desc = $_POST['description'];
        $prov = $_POST['province'];
        $sch = $_POST['school'];

        sqlsrv_query($conn, "UPDATE SchoolEvents SET eventTitle=?, eventDate=?, startTime=?, endTime=?, eventDescription=?, province=?, school=? WHERE eventID=?", [
            $title, $date, $startTime, $endTime, $desc, $prov, $sch, $id
        ]);
    }

    if (isset($_POST['delete'])) {
        $id = $_POST['eventID'];
        sqlsrv_query($conn, "DELETE FROM SchoolEvents WHERE eventID=?", [$id]);
    }
}

// Fetch Events (Filter for learner/parent based on province, school, grade)
$params = [];
if ($isTeacher) {
    $eventQuery = "SELECT * FROM SchoolEvents WHERE province = ? AND school = ? ORDER BY eventDate, startTime";
    $params = [$teacherProvince, $teacherSchool];
} elseif ($role === 'learner' || $role === 'parent') {
    $eventQuery = "SELECT * FROM SchoolEvents WHERE province = ? AND school = ? ORDER BY eventDate, startTime";
    $params = [$province, $school];
} else {
    $eventQuery = "SELECT * FROM SchoolEvents WHERE 1=0";
}

$result = sqlsrv_query($conn, $eventQuery, $params);
?>

<div style="padding: 20px;">
    <h2 style="color:#004aad; text-align:center;">🎉 School Events</h2>

    <?php if ($isTeacher): ?>
    <div style="max-width:700px; margin:auto; background:#fff; padding:20px; border-radius:10px; box-shadow:0 2px 10px rgba(0,0,0,0.1); margin-bottom:30px;">
        <h3 style="color:#004aad;">➕ Add New Event</h3>
        <form method="POST">
            <input type="text" name="title" placeholder="Event Title" required style="width:100%; padding:10px; margin-bottom:10px;">
            <input type="date" name="date" required style="width:100%; padding:10px; margin-bottom:10px;">
            <input type="time" name="startTime" required style="width:100%; padding:10px; margin-bottom:10px;">
            <input type="time" name="endTime" required style="width:100%; padding:10px; margin-bottom:10px;">
            <textarea name="description" placeholder="Event Description" required style="width:100%; padding:10px; margin-bottom:10px;"></textarea>

            <input type="text" value="<?= htmlspecialchars($teacherProvince) ?>" readonly style="width:100%; padding:10px; margin-bottom:10px; background:#f0f0f0;">
            <input type="text" value="<?= htmlspecialchars($teacherSchool) ?>" readonly style="width:100%; padding:10px; margin-bottom:10px; background:#f0f0f0;">

            <button type="submit" name="add" style="background:#004aad; color:white; padding:10px 20px; border:none; border-radius:5px;">Add Event</button>
        </form>
    </div>
    <?php endif; ?>

    <!-- Learner/Parent Info Display -->
    <?php if ($role === 'learner' || $role === 'parent'): ?>
    <div style="max-width:600px; margin:auto; background:#f9f9f9; padding:15px; border-radius:10px; box-shadow:0 0 5px rgba(0,0,0,0.1); margin-bottom:20px;">
        <label>Your Province:</label>
        <input type="text" value="<?= htmlspecialchars($province) ?>" readonly style="width:100%; padding:8px; margin-bottom:10px; background:#f0f0f0;">
        <label>Your School:</label>
        <input type="text" value="<?= htmlspecialchars($school) ?>" readonly style="width:100%; padding:8px; margin-bottom:10px; background:#f0f0f0;">
        <label>Your Grade:</label>
        <input type="text" value="<?= htmlspecialchars($grade) ?>" readonly style="width:100%; padding:8px; margin-bottom:10px; background:#f0f0f0;">
    </div>
    <?php endif; ?>

    <table style="width:100%; border-collapse:collapse; background:#fff; box-shadow:0 2px 10px rgba(0,0,0,0.05); border-radius:8px;">
        <thead>
            <tr style="background:#004aad; color:white;">
                <th style="padding:10px;">Date</th>
                <th>Start Time</th>
                <th>End Time</th>
                <th>Title</th>
                <th>Description</th>
                <th>Province</th>
                <th>School</th>
                <?php if ($isTeacher): ?><th>Actions</th><?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC)): ?>
            <tr style="border-bottom:1px solid #ddd;">
                <form method="POST">
                    <td><input type="date" name="date" value="<?= $row['eventDate']->format('Y-m-d') ?>" <?= !$isTeacher ? 'readonly' : '' ?> style="padding:6px;"></td>
                    <td><input type="time" name="startTime" value="<?= $row['startTime'] ? $row['startTime']->format('H:i') : '' ?>" <?= !$isTeacher ? 'readonly' : '' ?> style="padding:6px;"></td>
                    <td><input type="time" name="endTime" value="<?= $row['endTime'] ? $row['endTime']->format('H:i') : '' ?>" <?= !$isTeacher ? 'readonly' : '' ?> style="padding:6px;"></td>
                    <td><input type="text" name="title" value="<?= htmlspecialchars($row['eventTitle']) ?>" <?= !$isTeacher ? 'readonly' : '' ?> style="padding:6px;"></td>
                    <td><textarea name="description" <?= !$isTeacher ? 'readonly' : '' ?> style="padding:6px;"><?= htmlspecialchars($row['eventDescription']) ?></textarea></td>
                    <td><input type="text" name="province" value="<?= htmlspecialchars($row['province']) ?>" <?= !$isTeacher ? 'readonly' : '' ?> style="padding:6px;"></td>
                    <td><input type="text" name="school" value="<?= htmlspecialchars($row['school']) ?>" <?= !$isTeacher ? 'readonly' : '' ?> style="padding:6px;"></td>
                    <?php if ($isTeacher): ?>
                    <td>
                        <input type="hidden" name="eventID" value="<?= $row['eventID'] ?>">
                        <button type="submit" name="update" style="background:#004aad; color:white; padding:5px 10px; border:none; margin-right:5px; border-radius:4px;">Update</button>
                        <button type="submit" name="delete" onclick="return confirm('Are you sure?')" style="background:red; color:white; padding:5px 10px; border:none; border-radius:4px;">Delete</button>
                    </td>
                    <?php endif; ?>
                </form>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>

   <a href="dashboard.php" style="display: inline-block;text-align: center;margin-top: 30px;background-color: #004aad;color: white;padding: 10px 20px;text-decoration: none;border-radius: 6px;box-shadow: 0 2px 5px rgba(0,0,0,0.2);transition: background-color 0.3s ease;"
      onmouseover="this.style.backgroundColor='#00337a';" onmouseout="this.style.backgroundColor='#004aad';">
    ← About Us!
   </a>

</div>
