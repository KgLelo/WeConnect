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

$teacherProvince = '';
$teacherSchoolName = '';
$teacherSchoolID = '';

$learnerProvince = '';
$learnerSchoolName = '';
$learnerGrade = '';
$learnerSchoolID = '';

$parentProvince = '';
$parentSchoolName = '';
$parentGrade = '';
$parentSchoolID = '';

if ($role === 'teacher') {
    $teacherQuery = sqlsrv_query($conn, "SELECT province, school FROM TeacherTable WHERE userName = ?", [$userName]);
    if ($teacherQuery && ($row = sqlsrv_fetch_array($teacherQuery, SQLSRV_FETCH_ASSOC))) {
        $teacherProvince = $row['province'];
        $teacherSchoolName = $row['school'];
        $schoolQuery = sqlsrv_query($conn, "SELECT schoolID FROM Schools WHERE schoolName = ?", [$teacherSchoolName]);
        if ($schoolRow = sqlsrv_fetch_array($schoolQuery, SQLSRV_FETCH_ASSOC)) {
            $teacherSchoolID = $schoolRow['schoolID'];
        }
    }
} elseif ($role === 'learner') {
    $learnerQuery = sqlsrv_query($conn, "SELECT province, school, grade FROM LearnerTable WHERE userName = ?", [$userName]);
    if ($learnerQuery && ($row = sqlsrv_fetch_array($learnerQuery, SQLSRV_FETCH_ASSOC))) {
        $learnerProvince = $row['province'];
        $learnerSchoolName = $row['school'];
        $learnerGrade = $row['grade'];
        $schoolQuery = sqlsrv_query($conn, "SELECT schoolID FROM Schools WHERE schoolName = ?", [$learnerSchoolName]);
        if ($schoolRow = sqlsrv_fetch_array($schoolQuery, SQLSRV_FETCH_ASSOC)) {
            $learnerSchoolID = $schoolRow['schoolID'];
        }
    }
} elseif ($role === 'parent') {
    // Get linked learner_id from ParentTable
    $parentQuery = sqlsrv_query($conn, "SELECT learner_id FROM ParentTable WHERE userName = ?", [$userName]);
    if ($parentQuery && ($parentRow = sqlsrv_fetch_array($parentQuery, SQLSRV_FETCH_ASSOC))) {
        $linkedLearnerID = $parentRow['learner_id'];
        // Fetch learner's province, school, grade by learner_id
        $learnerQuery = sqlsrv_query($conn, "SELECT province, school, grade FROM LearnerTable WHERE learner_id = ?", [$linkedLearnerID]);
        if ($learnerQuery && ($learnerRow = sqlsrv_fetch_array($learnerQuery, SQLSRV_FETCH_ASSOC))) {
            $parentProvince = $learnerRow['province'];
            $parentSchoolName = $learnerRow['school'];
            $parentGrade = $learnerRow['grade'];
            $schoolQuery = sqlsrv_query($conn, "SELECT schoolID FROM Schools WHERE schoolName = ?", [$parentSchoolName]);
            if ($schoolRow = sqlsrv_fetch_array($schoolQuery, SQLSRV_FETCH_ASSOC)) {
                $parentSchoolID = $schoolRow['schoolID'];
            }
        } else {
            // fallback to parent's own info if linked learner not found
            $fallbackQuery = sqlsrv_query($conn, "SELECT province, school, grade FROM ParentTable WHERE userName = ?", [$userName]);
            if ($fallbackQuery && ($fallbackRow = sqlsrv_fetch_array($fallbackQuery, SQLSRV_FETCH_ASSOC))) {
                $parentProvince = $fallbackRow['province'];
                $parentSchoolName = $fallbackRow['school'];
                $parentGrade = $fallbackRow['grade'];
                $schoolQuery = sqlsrv_query($conn, "SELECT schoolID FROM Schools WHERE schoolName = ?", [$parentSchoolName]);
                if ($schoolRow = sqlsrv_fetch_array($schoolQuery, SQLSRV_FETCH_ASSOC)) {
                    $parentSchoolID = $schoolRow['schoolID'];
                }
            }
        }
    } else {
        // fallback if no linked learner_id
        $fallbackQuery = sqlsrv_query($conn, "SELECT province, school, grade FROM ParentTable WHERE userName = ?", [$userName]);
        if ($fallbackQuery && ($fallbackRow = sqlsrv_fetch_array($fallbackQuery, SQLSRV_FETCH_ASSOC))) {
            $parentProvince = $fallbackRow['province'];
            $parentSchoolName = $fallbackRow['school'];
            $parentGrade = $fallbackRow['grade'];
            $schoolQuery = sqlsrv_query($conn, "SELECT schoolID FROM Schools WHERE schoolName = ?", [$parentSchoolName]);
            if ($schoolRow = sqlsrv_fetch_array($schoolQuery, SQLSRV_FETCH_ASSOC)) {
                $parentSchoolID = $schoolRow['schoolID'];
            }
        }
    }
}

if ($role === 'teacher' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add'])) {
        $subject = $_POST['subject'];
        $date = $_POST['date'];
        $time = $_POST['time'];  
        $desc = $_POST['description'];
        $grade = $_POST['grade'];

        // Get school name from Schools table
        $schoolName = '';
        $schoolNameQuery = sqlsrv_query($conn, "SELECT schoolName FROM Schools WHERE schoolID = ?", [$teacherSchoolID]);
        if ($schoolNameQuery && ($schoolNameRow = sqlsrv_fetch_array($schoolNameQuery, SQLSRV_FETCH_ASSOC))) {
            $schoolName = $schoolNameRow['schoolName'];
        }

        sqlsrv_query($conn, "INSERT INTO ExamsTests (subject, examDate, examTime, description, grade, schoolID, school) VALUES (?, ?, ?, ?, ?, ?, ?)", [
            $subject, $date, $time, $desc, $grade, $teacherSchoolID, $schoolName
        ]);
    }
    if (isset($_POST['update'])) {
        $examID = $_POST['examID'];
        $subject = $_POST['subject'];
        $date = $_POST['date'];
        $time = $_POST['time']; 
        $desc = $_POST['description'];
        $grade = $_POST['grade'];

        // Get school name from Schools table
        $schoolName = '';
        $schoolNameQuery = sqlsrv_query($conn, "SELECT schoolName FROM Schools WHERE schoolID = ?", [$teacherSchoolID]);
        if ($schoolNameQuery && ($schoolNameRow = sqlsrv_fetch_array($schoolNameQuery, SQLSRV_FETCH_ASSOC))) {
            $schoolName = $schoolNameRow['schoolName'];
        }

        sqlsrv_query($conn, "UPDATE ExamsTests SET subject=?, examDate=?, examTime=?, description=?, grade=?, school=? WHERE examID=?", [
            $subject, $date, $time, $desc, $grade, $schoolName, $examID
        ]);
    }
    if (isset($_POST['delete'])) {
        $examID = $_POST['examID'];
        sqlsrv_query($conn, "DELETE FROM ExamsTests WHERE examID=?", [$examID]);
    }
}

$params = [];
if ($role === 'teacher') {
    $query = "SELECT et.*, s.schoolName FROM ExamsTests et JOIN Schools s ON et.schoolID = s.schoolID WHERE et.schoolID = ? ORDER BY examDate, examTime";
    $params = [$teacherSchoolID];
} elseif ($role === 'learner') {
    $query = "SELECT et.*, s.schoolName FROM ExamsTests et JOIN Schools s ON et.schoolID = s.schoolID WHERE et.schoolID = ? AND et.grade = ? ORDER BY examDate, examTime";
    $params = [$learnerSchoolID, $learnerGrade];
} elseif ($role === 'parent') {
    $query = "SELECT et.*, s.schoolName FROM ExamsTests et JOIN Schools s ON et.schoolID = s.schoolID WHERE et.schoolID = ? AND et.grade = ? ORDER BY examDate, examTime";
    $params = [$parentSchoolID, $parentGrade];
} else {
    $query = "SELECT * FROM ExamsTests WHERE 1=0";
}

$result = sqlsrv_query($conn, $query, $params);
?>

<div style="max-width:900px; margin:auto; color:#333;">
<h1 style="color:#004aad; text-align:center;">📝 Exams & Tests</h1>

<?php if ($role === 'learner' || $role === 'parent'): ?>
<div style="background:#fff; padding:20px; border-radius:10px; box-shadow:0 0 10px rgba(0,0,0,0.1); margin-bottom:30px;">
<label><strong>Province:</strong></label>
<input type="text" value="<?= htmlspecialchars(($role === 'learner') ? $learnerProvince : $parentProvince) ?>" readonly style="width:100%; padding:10px; margin-bottom:10px; background-color:#f1f1f1; border:1px solid #ccc; border-radius:5px;">

<label><strong>School:</strong></label>
<input type="text" value="<?= htmlspecialchars(($role === 'learner') ? $learnerSchoolName : $parentSchoolName) ?>" readonly style="width:100%; padding:10px; margin-bottom:10px; background-color:#f1f1f1; border:1px solid #ccc; border-radius:5px;">

<label><strong>Grade:</strong></label>
<input type="text" value="<?= htmlspecialchars(($role === 'learner') ? $learnerGrade : $parentGrade) ?>" readonly style="width:100%; padding:10px; margin-bottom:10px; background-color:#f1f1f1; border:1px solid #ccc; border-radius:5px;">

</div>
<?php endif; ?>

<?php if ($role === 'teacher'): ?>
<div style="background:#fff; padding:20px; border-radius:10px; margin-bottom:30px;">
  <h3 style="color:#004aad;">➕ Add Exam/Test</h3>
  <form method="POST">
    <input type="text" name="subject" placeholder="Subject" required style="width:100%; padding:10px; margin-bottom:10px;">
    <input type="date" name="date" required style="width:100%; padding:10px; margin-bottom:10px;">
    <input type="time" name="time" required style="width:100%; padding:10px; margin-bottom:10px;"> 
    <textarea name="description" placeholder="Description" required style="width:100%; padding:10px; margin-bottom:10px;"></textarea>
    
    <select name="grade" required style="width:100%; padding:10px; margin-bottom:10px;">
      <option value="">-- Select Grade --</option>
      <?php for ($i = 8; $i <= 12; $i++): ?>
        <option value="Grade <?= $i ?>">Grade <?= $i ?></option>
      <?php endfor; ?>
    </select>

  <label><strong>Province:</strong></label>
  <input type="text" value="<?= htmlspecialchars($teacherProvince) ?>" readonly style="width:100%; padding:10px; margin-bottom:10px; background-color:#f1f1f1; border:1px solid #ccc; border-radius:5px;">

  <label><strong>School:</strong></label>
  <input type="text" value="<?= htmlspecialchars($teacherSchoolName) ?>" readonly style="width:100%; padding:10px; margin-bottom:10px; background-color:#f1f1f1; border:1px solid #ccc; border-radius:5px;">

    <button type="submit" name="add" style="background:#004aad; color:#fff; padding:10px 20px;">Add</button>
  </form>
</div>
<?php endif; ?>

<table style="width:100%; border-collapse:collapse; background:#fff;">
  <thead>
    <tr style="background:#004aad; color:#fff;">
      <th style="padding:10px;">Date</th>
      <th style="padding:10px;">Time</th>
      <th style="padding:10px;">School</th>
      <th style="padding:10px;">Grade</th>
      <th style="padding:10px;">Subject</th>
      <th style="padding:10px;">Description</th>
      <?php if ($role === 'teacher'): ?><th style="padding:10px;">Actions</th><?php endif; ?>
    </tr>
  </thead>
  <tbody>
    <?php while ($row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC)): ?>
      <tr>
        <form method="POST">
          <td style="padding:10px;">
            <input type="date" name="date" value="<?= $row['examDate']->format('Y-m-d') ?>" <?= ($role !== 'teacher') ? 'readonly' : '' ?>>
          </td>
          <td style="padding:10px;">
            <input type="time" name="time" value="<?= $row['examTime'] ? $row['examTime']->format('H:i') : '' ?>" <?= ($role !== 'teacher') ? 'readonly' : '' ?>>
          </td>
          <td style="padding:10px;"><?= htmlspecialchars($row['schoolName']) ?></td>
          <td style="padding:10px;">
            <?php if ($role === 'teacher'): ?>
              <select name="grade">
                <?php for ($i = 8; $i <= 12; $i++): $g = "Grade $i"; ?>
                  <option value="<?= $g ?>" <?= $g == $row['grade'] ? 'selected' : '' ?>><?= $g ?></option>
                <?php endfor; ?>
              </select>
            <?php else: ?>
              <?= htmlspecialchars($row['grade']) ?>
            <?php endif; ?>
          </td>
          <td style="padding:10px;">
            <input type="text" name="subject" value="<?= htmlspecialchars($row['subject']) ?>" <?= ($role !== 'teacher') ? 'readonly' : '' ?>>
          </td>
          <td style="padding:10px;">
            <textarea name="description" <?= ($role !== 'teacher') ? 'readonly' : '' ?>><?= htmlspecialchars($row['description']) ?></textarea>
          </td>
          <?php if ($role === 'teacher'): ?>
            <td style="padding:10px;">
              <input type="hidden" name="examID" value="<?= $row['examID'] ?>">
             <button type="submit" name="update" style="background:#004aad; color:#fff; border:none; padding:6px 12px; border-radius:5px; cursor:pointer;">Update</button>
              <button type="submit" name="delete" onclick="return confirm('Are you sure you want to delete?')" style="background:#d60000; color:#fff; border:none; padding:6px 12px; border-radius:5px; cursor:pointer;">Delete</button>
            </td>
          <?php endif; ?>
        </form>
      </tr>
    <?php endwhile; ?>
  </tbody>
</table>

<a href="dashboard.php" style="display:inline-block; text-align:center; margin-top:20px; background:#004aad; color:#fff; padding:10px 20px; text-decoration:none; border-radius:5px; box-shadow:0 4px 6px rgba(0,0,0,0.1);">← About Us!</a>
</div>
