<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require 'connect.php';
$conn = connectToDatabase();

$userName = $_SESSION['userName'];
$role = strtolower($_SESSION['role']);
$isTeacher = $role === 'teacher';
$message = '';

// ===== Fetch user school, province, grade =====
$province = '';
$school = '';
$grade = '';

if ($isTeacher) {
    $query = sqlsrv_query($conn, "SELECT province, school FROM TeacherTable WHERE userName = ?", [$userName]);
    if ($row = sqlsrv_fetch_array($query, SQLSRV_FETCH_ASSOC)) {
        $province = $row['province'];
        $school = $row['school'];
    }
} elseif ($role === 'learner') {
    $query = sqlsrv_query($conn, "SELECT province, school, grade FROM LearnerTable WHERE userName = ?", [$userName]);
    if ($row = sqlsrv_fetch_array($query, SQLSRV_FETCH_ASSOC)) {
        $province = $row['province'];
        $school = $row['school'];
        // Extract digits from grade string, e.g. "Grade 10" -> 10
        $grade = intval(preg_replace('/\D/', '', $row['grade']));
    }
} elseif ($role === 'parent') {
    // Get learner_id linked to this parent
    $query = sqlsrv_query($conn, "SELECT learner_id FROM ParentTable WHERE userName = ?", [$userName]);
    if ($row = sqlsrv_fetch_array($query, SQLSRV_FETCH_ASSOC)) {
        $learner_id = $row['learner_id'];

        // Get learner's province, school, grade by learner_id
        $query2 = sqlsrv_query($conn, "SELECT province, school, grade FROM LearnerTable WHERE learner_id = ?", [$learner_id]);
        if ($learner = sqlsrv_fetch_array($query2, SQLSRV_FETCH_ASSOC)) {
            $province = $learner['province'];
            $school = $learner['school'];
            $grade = intval(preg_replace('/\D/', '', $learner['grade']));
        } else {
            $message = "❌ Could not find linked learner information.";
        }
    } else {
        $message = "❌ Could not find learner linked to this parent.";
    }
}

// ===== Upload Material (Teacher only) =====
if ($isTeacher && $_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES["material"]) && isset($_POST["upload"])) {
    $selectedGrade = intval($_POST["grade"]);
    $title = trim($_POST["title"]);
    $fileName = basename($_FILES["material"]["name"]);
    $targetDir = "uploads/";

    if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);

    $targetFile = $targetDir . time() . "_" . preg_replace('/[^a-zA-Z0-9_\.-]/', '_', $fileName);

    if (move_uploaded_file($_FILES["material"]["tmp_name"], $targetFile)) {
        $stmt = sqlsrv_query($conn, 
            "INSERT INTO study_materials (grade, title, filename, uploaded_by, province, school, uploaded_at) VALUES (?, ?, ?, ?, ?, ?, GETDATE())", 
            [$selectedGrade, $title, $targetFile, $userName, $province, $school]
        );
        $message = $stmt ? "✅ Study material uploaded successfully!" : "❌ Database error occurred.";
    } else {
        $message = "❌ File upload failed.";
    }
}

// ===== Update Material Title (Teacher only) =====
if ($isTeacher && isset($_POST['update']) && isset($_POST['id'])) {
    $id = intval($_POST['id']);
    $title = trim($_POST['title']);
    $stmt = sqlsrv_prepare($conn, "UPDATE study_materials SET title = ? WHERE id = ? AND uploaded_by = ?", [$title, $id, $userName]);
    if ($stmt && sqlsrv_execute($stmt)) {
        header("Location: dashboard.php?page=study_materials");
        exit();
    } else {
        $message = "❌ Failed to update material title.";
    }
}

// ===== Delete Material (Teacher only) =====
if ($isTeacher && isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = sqlsrv_query($conn, "SELECT filename FROM study_materials WHERE id = ? AND uploaded_by = ?", [$id, $userName]);
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    if ($row && file_exists($row['filename'])) unlink($row['filename']);
    sqlsrv_query($conn, "DELETE FROM study_materials WHERE id = ? AND uploaded_by = ?", [$id, $userName]);
    header("Location: dashboard.php?page=study_materials");
    exit();
}

// ===== Fetch Materials per Grade =====
$materials = [];

for ($g = 8; $g <= 12; $g++) {
    if ($isTeacher) {
        $stmt = sqlsrv_query($conn, "SELECT * FROM study_materials WHERE grade = ? AND uploaded_by = ?", [$g, $userName]);
    } elseif ($role === 'learner' || $role === 'parent') {
        if ($g != $grade) continue;  // Only show their grade
        $stmt = sqlsrv_query($conn, "SELECT * FROM study_materials WHERE grade = ? AND province = ? AND school = ?", [$g, $province, $school]);
    } else {
        continue;
    }

    if ($stmt) {
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $materials[$g][] = $row;
        }
    }
}
?>

<div>
  <h2 style="color: #004aad;">📚 Study Materials</h2>

  <?php if ($message): ?>
    <div style="background:#e0f7fa; border-left: 5px solid #004aad; padding: 10px 15px; margin-bottom: 20px; border-radius: 5px;">
      <?= htmlspecialchars($message) ?>
    </div>
  <?php endif; ?>

  <?php if ($isTeacher): ?>
    <div style="background:white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); margin-bottom: 30px;">
      <h3 style="color: #004aad;">Upload New Material</h3>

      <div style="margin-bottom:10px; padding:10px; border:1px solid #ddd; border-radius:5px; max-width: 350px;">
        <label style="display:block; margin-bottom:8px; font-weight:bold; color:#333;">
          Province:
          <input type="text" value="<?= htmlspecialchars($province) ?>" readonly
            style="width:100%; padding:6px; margin-top:4px; border:1px solid #ccc; border-radius:4px; background:#fff; color:#555;">
        </label>
        <label style="display:block; font-weight:bold; color:#333;">
          School:
          <input type="text" value="<?= htmlspecialchars($school) ?>" readonly
            style="width:100%; padding:6px; margin-top:4px; border:1px solid #ccc; border-radius:4px; background:#fff; color:#555;">
        </label>
      </div>

      <form method="post" enctype="multipart/form-data">
        <label>
          Grade:
          <select name="grade" required>
            <?php for ($i = 8; $i <= 12; $i++): ?>
              <option value="<?= $i ?>">Grade <?= $i ?></option>
            <?php endfor; ?>
          </select>
        </label><br><br>

        <label>
          Title:
          <input type="text" name="title" placeholder="e.g. Grade 10 Biology Notes" required style="width: 100%;" />
        </label><br><br>

        <label>
          Upload File:
          <input type="file" name="material" accept=".pdf,.doc,.docx" required />
        </label><br><br>

        <button type="submit" name="upload" style="background: #004aad; color: white; padding: 10px 20px; border: none; border-radius: 5px;">Upload</button>
      </form>
    </div>
  <?php endif; ?>

  <?php if ($role === 'learner' || $role === 'parent'): ?>
    <div style="background:#f9f9f9; padding:15px; border:1px solid #ddd; border-radius:8px; margin-bottom:20px; max-width: 400px;">
      <label style="display:block; margin-bottom:10px; font-weight:bold; color:#333;">
        Province:
        <input type="text" value="<?= htmlspecialchars($province) ?>" readonly style="width:100%; padding:8px; margin-top:5px; border:1px solid #ccc; border-radius:4px; background:#fff; color:#555;">
      </label>
      <label style="display:block; margin-bottom:10px; font-weight:bold; color:#333;">
        School:
        <input type="text" value="<?= htmlspecialchars($school) ?>" readonly style="width:100%; padding:8px; margin-top:5px; border:1px solid #ccc; border-radius:4px; background:#fff; color:#555;">
      </label>
      <label style="display:block; font-weight:bold; color:#333;">
        Grade:
        <input type="text" value="Grade <?= $grade ?>" readonly style="width:100%; padding:8px; margin-top:5px; border:1px solid #ccc; border-radius:4px; background:#fff; color:#555;">
      </label>
    </div>
  <?php endif; ?>

  <?php foreach ($materials as $grade => $list): ?>
    <div style="margin-bottom: 40px;">
      <h3 style="color: #004aad;">📘 Grade <?= $grade ?></h3>
      <?php if (!empty($list)): ?>
        <table style="width:100%; border-collapse: collapse; background:white;">
          <thead style="background:#004aad; color:white;">
            <tr>
              <th style="padding: 8px;">Title</th>
              <th>Uploaded By</th>
              <th>Date</th>
              <th>Download</th>
              <?php if ($isTeacher): ?><th>Actions</th><?php endif; ?>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($list as $material): ?>
              <tr style="border-bottom: 1px solid #ccc;">
                <td style="padding: 8px;">
                  <?php if ($isTeacher && $material['uploaded_by'] === $userName): ?>
                    <form method="post" style="display: flex; gap: 10px;">
                      <input type="hidden" name="id" value="<?= $material['id'] ?>">
                      <input type="text" name="title" value="<?= htmlspecialchars($material['title']) ?>" required style="flex:1;">
                      <button type="submit" name="update" style="background:#004aad; border:none; color:white; padding:4px 10px; border-radius:5px;">Update</button>
                    </form>
                  <?php else: ?>
                    <?= htmlspecialchars($material['title']) ?>
                  <?php endif; ?>
                </td>
                <td><?= htmlspecialchars($material['uploaded_by']) ?></td>
                <td><?= $material['uploaded_at'] instanceof DateTime ? $material['uploaded_at']->format('Y-m-d') : htmlspecialchars($material['uploaded_at']) ?></td>
                <td><a href="<?= htmlspecialchars($material['filename']) ?>" download style="color: green; font-weight: bold;">Download</a></td>
                <?php if ($isTeacher): ?>
                  <td>
                    <?php if ($material['uploaded_by'] === $userName): ?>
                      <a href="?delete=<?= $material['id'] ?>" onclick="return confirm('Are you sure you want to delete this file?')" style="color:red; font-weight: bold;">Delete</a>
                    <?php else: ?>
                      &mdash;
                    <?php endif; ?>
                  </td>
                <?php endif; ?>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?>
        <p style="font-style: italic;">No study materials uploaded for Grade <?= $grade ?>.</p>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
</div>
