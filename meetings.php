<?php
require_once 'connect.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$conn = connectToDatabase();

$role = strtolower($_SESSION['role'] ?? '');
$userName = $_SESSION['userName'] ?? '';
$province = $_SESSION['province'] ?? '';
$school = $_SESSION['school'] ?? '';
$grade = $_SESSION['grade'] ?? '';
$fullName = $_SESSION['fullName'] ?? '';

// For parent: get linked learner info (fullName, username)
$linkedLearnerFullName = '';
$linkedLearnerUserName = '';

if ($role === 'parent') {
    // Get learner_id linked to this parent and parent's fullName from DB (in case session outdated)
    $parentQuery = sqlsrv_query($conn, "SELECT learner_id, fullName FROM ParentTable WHERE userName = ?", [$userName]);
    if ($parentQuery && ($parentRow = sqlsrv_fetch_array($parentQuery, SQLSRV_FETCH_ASSOC))) {
        $linkedLearnerID = $parentRow['learner_id'];
        $fullName = $parentRow['fullName'];
        $_SESSION['fullName'] = $fullName;

        // Fetch linked learner's fullName and userName, and their province, school, grade
        $learnerQuery = sqlsrv_query($conn, "SELECT fullName, userName, province, school, grade FROM LearnerTable WHERE learner_id = ?", [$linkedLearnerID]);
        if ($learnerQuery && ($learnerRow = sqlsrv_fetch_array($learnerQuery, SQLSRV_FETCH_ASSOC))) {
            $linkedLearnerFullName = $learnerRow['fullName'];
            $linkedLearnerUserName = $learnerRow['userName'];
            $province = $learnerRow['province'];
            $school = $learnerRow['school'];
            $grade = $learnerRow['grade'];

            $_SESSION['province'] = $province;
            $_SESSION['school'] = $school;
            $_SESSION['grade'] = $grade;
        }
    }
}

if (!$userName || !$role) {
    die("<p style='color:red;'>Unauthorized access. Please login.</p>");
}

// === Schedule Meeting Request ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['schedule_meeting'])) {
    $teacher = $_POST['teacher'] ?? '';
    $date = $_POST['date'] ?? '';
    $start_time = $_POST['start_time'] ?? '';
    $end_time = $_POST['end_time'] ?? '';
    $topic = $_POST['topic'] ?? '';
    $link = $_POST['link'] ?? '';

    // Determine actual requester username and role
    if ($role === 'parent') {
        $requestedBy = $_POST['requested_by'] ?? 'parent';

        if ($requestedBy === 'learner' && $linkedLearnerUserName !== '') {
            $requesterUserName = $linkedLearnerUserName;
            $requesterRole = 'learner';
            $requesterFullName = $linkedLearnerFullName;
        } else {
            $requesterUserName = $userName;
            $requesterRole = 'parent';
            $requesterFullName = $fullName;
        }
    } else {
        $requesterUserName = $userName;
        $requesterRole = $role;
        $requesterFullName = $fullName;
    }

    if ($teacher && $date && $start_time && $end_time && $topic && $link) {
        $sql = "INSERT INTO meetings 
            (province, school, grade, fullName, learnerFullName, requester, role, teacher, topic, date, start_time, end_time, link, status) 
            VALUES (?, ?, ?, ?,?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')";

        $params = [$province, $school, $grade, $requesterFullName,  $linkedLearnerFullName, $requesterUserName, $requesterRole, $teacher, $topic, $date, $start_time, $end_time, $link];

        $stmt = sqlsrv_query($conn, $sql, $params);

        if ($stmt) {
            echo "<p style='color:green; font-weight:bold;'>✅ Meeting request sent to <strong>$teacher</strong>.</p>";
        } else {
            echo "<p style='color:red;'>❌ Failed to send request.</p>";
            print_r(sqlsrv_errors());
        }
    } else {
        echo "<p style='color:red;'>❌ Please fill in all fields.</p>";
    }
}

// === Teacher Approval/Decline with Reason ===
if ($role === 'teacher' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['decision'])) {
    $decision = $_POST['decision'];
    $meeting_id = intval($_POST['meeting_id'] ?? 0);

    if ($decision === 'approve') {
        $update = sqlsrv_query($conn, "UPDATE meetings SET status = 'approved' WHERE id = ? AND teacher = ?", [
            $meeting_id, $userName
        ]);
        if ($update) {
            echo "<p style='color:green;'>✅ Meeting approved.</p>";
        } else {
            echo "<p style='color:red;'>❌ Failed to approve meeting.</p>";
            print_r(sqlsrv_errors());
        }
    } elseif ($decision === 'reject') {
        $decline_reason = trim($_POST['decline_reason'] ?? '');

        if (empty($decline_reason)) {
            echo "<p style='color:red;'>❌ Please provide a reason for declining.</p>";
        } else {
            $update = sqlsrv_query($conn, "UPDATE meetings SET status = 'declined', decline_reason = ? WHERE id = ? AND teacher = ?", [
                $decline_reason, $meeting_id, $userName
            ]);
            if ($update) {
                echo "<p style='color:orange;'>❌ Meeting declined with reason.</p>";
            } else {
                echo "<p style='color:red;'>❌ Failed to decline meeting.</p>";
                print_r(sqlsrv_errors());
            }
        }
    }
}


        // === Learner/Parent: Update Meeting ===
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_meeting'])) {
            $meeting_id = intval($_POST['meeting_id'] ?? 0);
            $new_topic = $_POST['topic'] ?? '';
            $new_date = $_POST['date'] ?? '';
            $new_start_time = $_POST['start_time'] ?? '';
            $new_end_time = $_POST['end_time'] ?? '';
            $new_link = $_POST['link'] ?? '';

            // Only allow update if meeting is still pending and created by this user
            $update = sqlsrv_query($conn, "UPDATE meetings SET topic = ?, date = ?, start_time = ?, end_time = ?, link = ? 
                WHERE id = ? AND requester = ? AND status = 'pending'", [
                $new_topic, $new_date, $new_start_time, $new_end_time, $new_link, $meeting_id, $userName
            ]);

            if ($update) {
                echo "<p style='color:green;'>✅ Meeting updated successfully.</p>";
            } else {
                echo "<p style='color:red;'>❌ Failed to update meeting.</p>";
                print_r(sqlsrv_errors());
            }
        }

        // === Learner/Parent: Delete Meeting ===
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_meeting'])) {
            $meeting_id = intval($_POST['meeting_id'] ?? 0);

            // Only allow delete if meeting is still pending and created by this user
            $delete = sqlsrv_query($conn, "DELETE FROM meetings WHERE id = ? AND requester = ? AND status = 'pending'", [
                $meeting_id, $userName
            ]);

            if ($delete) {
                echo "<p style='color:green;'>✅ Meeting deleted successfully.</p>";
            } else {
                echo "<p style='color:red;'>❌ Failed to delete meeting.</p>";
                print_r(sqlsrv_errors());
            }
        }

?>


<div style="padding: 20px;">

<?php if ($role === 'parent'): ?>
    <div style="max-width:600px; margin:auto; background:#f9f9f9; padding:15px; border-radius:10px; box-shadow:0 0 5px rgba(0,0,0,0.1); margin-bottom:20px;">
        <label>Parent Full Name:</label>
        <input type="text" value="<?= htmlspecialchars($fullName) ?>" readonly style="width:100%; padding:8px; margin-bottom:10px; background:#f0f0f0;">

        <label>Learner Full Name:</label>
        <input type="text" value="<?= htmlspecialchars($linkedLearnerFullName) ?>" readonly style="width:100%; padding:8px; margin-bottom:10px; background:#f0f0f0;">

        <label>Province:</label>
        <input type="text" value="<?= htmlspecialchars($province) ?>" readonly style="width:100%; padding:8px; margin-bottom:10px; background:#f0f0f0;">

        <label>School:</label>
        <input type="text" value="<?= htmlspecialchars($school) ?>" readonly style="width:100%; padding:8px; margin-bottom:10px; background:#f0f0f0;">

        <label>Grade:</label>
        <input type="text" value="<?= htmlspecialchars($grade) ?>" readonly style="width:100%; padding:8px; margin-bottom:10px; background:#f0f0f0;">
    </div>

    <div style="background:#fff; padding:20px; border-radius:10px; margin-bottom:30px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
        <h3 style="color:#004aad;">📅 Request a Meeting with a Teacher</h3>
        <form method="post">
            <label for="requested_by">Requesting as:</label>
            <select name="requested_by" required style="width:100%; padding:10px; margin-bottom:10px;">
                <option value="parent">Parent (You)</option>
                <option value="learner">Learner (<?= htmlspecialchars($linkedLearnerFullName) ?>)</option>
            </select>

            <label for="teacher">Teacher Email:</label>
            <input type="text" name="teacher" required placeholder="e.g. johndoe@school.com" style="width:100%; padding:10px; margin-bottom:10px;">

            <label for="date">Date:</label>
            <input type="date" name="date" required style="width:100%; padding:10px; margin-bottom:10px;">

            <label for="start_time">Start Time:</label>
            <input type="time" name="start_time" required style="width:100%; padding:10px; margin-bottom:10px;">

            <label for="end_time">End Time:</label>
            <input type="time" name="end_time" required style="width:100%; padding:10px; margin-bottom:10px;">

            <label for="link">Meeting Link:</label>
            <input type="url" name="link" required placeholder="e.g. https://meet.link/abc" style="width:100%; padding:10px; margin-bottom:10px;">

            <label for="topic">Topic:</label>
            <textarea name="topic" rows="4" required style="width:100%; padding:10px; margin-bottom:10px;"></textarea>

            <button type="submit" name="schedule_meeting" style="background:#004aad; color:white; padding:10px 20px; border:none; border-radius:5px;">Send Request</button>
        </form>
    </div>

<?php elseif ($role === 'learner'): ?>
    <div style="max-width:600px; margin:auto; background:#f9f9f9; padding:15px; border-radius:10px; box-shadow:0 0 5px rgba(0,0,0,0.1); margin-bottom:20px;">
        <label>Full Name:</label>
        <input type="text" value="<?= htmlspecialchars($fullName) ?>" readonly style="width:100%; padding:8px; margin-bottom:10px; background:#f0f0f0;">
        <label>Province:</label>
        <input type="text" value="<?= htmlspecialchars($province) ?>" readonly style="width:100%; padding:8px; margin-bottom:10px; background:#f0f0f0;">
        <label>School:</label>
        <input type="text" value="<?= htmlspecialchars($school) ?>" readonly style="width:100%; padding:8px; margin-bottom:10px; background:#f0f0f0;">
        <label>Grade:</label>
        <input type="text" value="<?= htmlspecialchars($grade) ?>" readonly style="width:100%; padding:8px; margin-bottom:10px; background:#f0f0f0;">
    </div>

    <div style="background:#fff; padding:20px; border-radius:10px; margin-bottom:30px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
        <h3 style="color:#004aad;">📅 Request a Meeting with a Teacher</h3>
        <form method="post">
            <label for="teacher">Teacher Email:</label>
            <input type="text" name="teacher" required placeholder="e.g. johndoe@school.com" style="width:100%; padding:10px; margin-bottom:10px;">
            <label for="date">Date:</label>
            <input type="date" name="date" required style="width:100%; padding:10px; margin-bottom:10px;">
            <label for="start_time">Start Time:</label>
            <input type="time" name="start_time" required style="width:100%; padding:10px; margin-bottom:10px;">
            <label for="end_time">End Time:</label>
            <input type="time" name="end_time" required style="width:100%; padding:10px; margin-bottom:10px;">
            <label for="link">Meeting Link:</label>
            <input type="url" name="link" required placeholder="e.g. https://meet.link/abc" style="width:100%; padding:10px; margin-bottom:10px;">
            <label for="topic">Topic:</label>
            <textarea name="topic" rows="4" required style="width:100%; padding:10px; margin-bottom:10px;"></textarea>
            <button type="submit" name="schedule_meeting" style="background:#004aad; color:white; padding:10px 20px; border:none; border-radius:5px;">Send Request</button>
        </form>
    </div>

<?php else: ?>
<?php endif; ?>

<!-- Meeting Requests Table (same for all roles) -->
<div style="background:#fff; padding:20px; border-radius:10px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
    <h3 style="color:#004aad;">
        <?= $role === 'teacher' ? '📝Meeting Requests' : '📋 Your Meeting Requests' ?>
    </h3>

    <table style="width:100%; border-collapse: collapse; margin-top:15px;">
        <thead>
            <tr style="background:#004aad; color:white;">
                <th style="padding:10px;">Full Name</th>
                <th>Learner Full Name</th> <!-- ✅ New Column -->
                <th>Requester (Username)</th>
                <th>Role</th>
                <th>Grade</th>
                <th>Topic</th>
                <th>Date</th>
                <th>Start Time</th>
                <th>End Time</th>
                <th>Status</th>
                <th>Link</th>
                <?php if ($role === 'teacher') echo '<th>Action</th>'; ?>
                <?php if ($role !== 'teacher') echo '<th>Decline Reason</th>'; ?>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $query = $role === 'teacher'
                ? sqlsrv_query($conn, "SELECT * FROM meetings WHERE teacher = ? ORDER BY date DESC", [$userName])
                : sqlsrv_query($conn, "SELECT * FROM meetings WHERE requester = ? ORDER BY date DESC", [$userName]);

            while ($row = sqlsrv_fetch_array($query, SQLSRV_FETCH_ASSOC)):
            ?>
            <tr style="border-bottom: 1px solid #ddd;">
                <td><?= htmlspecialchars($row['fullName']) ?></td>
                <td><?= htmlspecialchars($row['learnerFullName'] ?? '-') ?></td> <!-- ✅ Show learner name -->
                <td><?= htmlspecialchars($row['requester']) ?></td>
                <td><?= ucfirst(htmlspecialchars($row['role'])) ?></td>
                <td><?= htmlspecialchars($row['grade']) ?></td>
                <td><?= htmlspecialchars($row['topic']) ?></td>
                <td><?= date_format($row['date'], 'Y-m-d') ?></td>
                <td><?= date_format($row['start_time'], 'H:i') ?></td>
                <td><?= date_format($row['end_time'], 'H:i') ?></td>
                <td><strong><?= ucfirst($row['status']) ?></strong></td>
                <td>
                    <?php if (!empty($row['link'])): ?>
                        <a href="<?= htmlspecialchars($row['link']) ?>" target="_blank" style="color:blue; text-decoration:underline;">Join</a>
                    <?php else: ?>
                        -
                    <?php endif; ?>
                </td>

                <?php if ($role === 'teacher' && $row['status'] === 'pending'): ?>
                <td style="text-align:center;">
                    <form method="post" style="display:inline-block; margin-bottom:5px;">
                        <input type="hidden" name="meeting_id" value="<?= $row['id'] ?>">
                        <button type="submit" name="decision" value="approve" style="background:green; color:white; padding:5px 10px; border:none; border-radius:4px;">✅ Approve</button>
                    </form>
                    <form method="post" style="display:inline-block;">
                        <input type="hidden" name="meeting_id" value="<?= $row['id'] ?>">
                        <textarea name="decline_reason" placeholder="Reason for decline..." required style="width:100%; padding:5px; margin-bottom:5px;"></textarea>
                        <button type="submit" name="decision" value="reject" style="background:red; color:white; padding:5px 10px; border:none; border-radius:4px;">❌ Decline</button>
                    </form>
                </td>
                <?php elseif ($role === 'teacher'): ?>
                <td>-</td>
                <?php endif; ?>

                <?php if ($role !== 'teacher'): ?>
                <td>
                    <?= $row['status'] === 'declined' ? nl2br(htmlspecialchars($row['decline_reason'] ?? '')) : '-' ?>
                </td>

                <?php if ($role !== 'teacher' && $row['status'] === 'pending'): ?>
                <td>
                    <!-- Update Form -->
                    <form method="post" style="margin-bottom:5px;">
                        <input type="hidden" name="meeting_id" value="<?= $row['id'] ?>">
                        <input type="text" name="topic" value="<?= htmlspecialchars($row['topic']) ?>" required placeholder="New Topic" style="width:100%; margin-bottom:5px;">
                        <input type="date" name="date" value="<?= date_format($row['date'], 'Y-m-d') ?>" required style="width:100%; margin-bottom:5px;">
                        <input type="time" name="start_time" value="<?= date_format($row['start_time'], 'H:i') ?>" required style="width:100%; margin-bottom:5px;">
                        <input type="time" name="end_time" value="<?= date_format($row['end_time'], 'H:i') ?>" required style="width:100%; margin-bottom:5px;">
                        <input type="url" name="link" value="<?= htmlspecialchars($row['link']) ?>" required placeholder="New Meeting Link" style="width:100%; margin-bottom:5px;">
                        <button type="submit" name="update_meeting" style="background:#004aad; color:white; padding:5px 10px; border:none; border-radius:4px;">✏️ Update</button>
                    </form>

                    <!-- Delete Form -->
                    <form method="post" onsubmit="return confirm('Are you sure you want to delete this meeting?');">
                        <input type="hidden" name="meeting_id" value="<?= $row['id'] ?>">
                        <button type="submit" name="delete_meeting" style="background:red; color:white; padding:5px 10px; border:none; border-radius:4px;">🗑️ Delete</button>
                    </form>
                </td>
                <?php elseif ($role !== 'teacher'): ?>
                <td>-</td>
                <?php endif; ?>

                <?php endif; ?>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>
