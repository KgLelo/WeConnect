<?php
require 'connect.php';
$conn = connectToDatabase();

if (!isset($_SESSION)) session_start();

$role = strtolower($_SESSION['role'] ?? '');
$userName = $_SESSION['userName'] ?? '';
$userSchool = $_SESSION['school'] ?? '';
$userProvince = $_SESSION['province'] ?? '';
$userGrade = $_SESSION['grade'] ?? '';

$fullName = '';

if ($role === 'learner') {
    $query = sqlsrv_query($conn, "SELECT fullName FROM LearnerTable WHERE userName = ?", [$userName]);
    if ($row = sqlsrv_fetch_array($query, SQLSRV_FETCH_ASSOC)) {
        $fullName = $row['fullName'];
    }
} elseif ($role === 'parent') {
    $query = sqlsrv_query($conn, "SELECT fullName, learner_id FROM ParentTable WHERE userName = ?", [$userName]);
    if ($row = sqlsrv_fetch_array($query, SQLSRV_FETCH_ASSOC)) {
        $parentFullName = $row['fullName'];
        $learnerID = $row['learner_id'];

        $learnerName = '';
        if ($learnerID) {
            $learnerQuery = sqlsrv_query($conn, "SELECT fullName FROM LearnerTable WHERE learner_id = ?", [$learnerID]);
            if ($learnerRow = sqlsrv_fetch_array($learnerQuery, SQLSRV_FETCH_ASSOC)) {
                $learnerName = $learnerRow['fullName'];
            }
        }

        $fullName = $parentFullName;
        if ($learnerName) {
            $fullName .= " (Learner: " . $learnerName . ")";
        }
    }
} elseif ($role === 'teacher') {
    $query = sqlsrv_query($conn, "SELECT fullName FROM TeacherTable WHERE userName = ?", [$userName]);
    if ($row = sqlsrv_fetch_array($query, SQLSRV_FETCH_ASSOC)) {
        $fullName = $row['fullName'];
    }
}

$feedbackMessage = '';
$feedbackColor = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action  = $_POST['action'] ?? '';
    $id      = intval($_POST['id'] ?? 0);
    $message = trim($_POST['message'] ?? '');

    if (in_array($role, ['learner', 'parent'])) {
        if ($action === 'add' && $message !== '') {
            $result = sqlsrv_query(
                $conn,
                "INSERT INTO testimonials (province, school, grade, sender_email, sender_name, role, message, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', GETDATE())",
                [$userProvince, $userSchool, $userGrade, $userName, $fullName, $role, $message]
            );
            $feedbackMessage = $result ? "✅ Testimonial submitted and pending approval." : "❌ Error: Failed to submit testimonial.";
            $feedbackColor = $result ? "green" : "red";
        } elseif ($action === 'edit' && $id && $message !== '') {
            $result = sqlsrv_query(
                $conn,
                "UPDATE testimonials SET message=?, status='pending', edited_at=GETDATE() WHERE id=? AND sender_email=?",
                [$message, $id, $userName]
            );
            $feedbackMessage = $result ? "✅ Testimonial edited and pending approval." : "❌ Error: Failed to edit testimonial.";
            $feedbackColor = $result ? "green" : "red";
        } elseif ($action === 'delete' && $id) {
            $result = sqlsrv_query(
                $conn,
                "DELETE FROM testimonials WHERE id=? AND sender_email=?",
                [$id, $userName]
            );
            $feedbackMessage = $result ? "✅ Testimonial deleted." : "❌ Error: Failed to delete testimonial.";
            $feedbackColor = $result ? "green" : "red";
        }
    }

    if ($role === 'teacher' && $id) {
        $approver = $userName;
        if ($action === 'approve') {
            $result = sqlsrv_query(
                $conn,
                "UPDATE testimonials SET status='approved', approved_at=GETDATE(), approved_by=? WHERE id=?",
                [$approver, $id]
            );
            $feedbackMessage = $result ? "✅ Testimonial approved." : "❌ Error: Failed to approve testimonial.";
            $feedbackColor = $result ? "green" : "red";
        } elseif ($action === 'reject') {
            $result = sqlsrv_query(
                $conn,
                "UPDATE testimonials SET status='rejected', approved_at=GETDATE(), approved_by=? WHERE id=?",
                [$approver, $id]
            );
            $feedbackMessage = $result ? "✅ Testimonial rejected." : "❌ Error: Failed to reject testimonial.";
            $feedbackColor = $result ? "green" : "red";
        }
    }
}

$stmt = ($role === 'teacher') ?
    sqlsrv_query($conn, "SELECT * FROM testimonials WHERE province=? AND school=? ORDER BY created_at DESC", [$userProvince, $userSchool]) :
    sqlsrv_query($conn, "SELECT * FROM testimonials WHERE province=? AND school=? AND grade=? AND (status='approved' OR sender_email=?) ORDER BY created_at DESC", [$userProvince, $userSchool, $userGrade, $userName]);
?>

<div style="max-width:900px; margin:auto;">
    <h2 style="color:#004aad; margin-bottom:20px;">📢 Testimonials - <?= ucfirst(htmlspecialchars($role)); ?></h2>

    <div style="margin-bottom: 15px;">
        <label>Province:</label>
        <input type="text" value="<?= htmlspecialchars($userProvince) ?>" readonly style="width:100%; margin-bottom:10px;">

        <label>School:</label>
        <input type="text" value="<?= htmlspecialchars($userSchool) ?>" readonly style="width:100%; margin-bottom:10px;">
    </div>

    <?php if (in_array($role, ['learner', 'parent'])): ?>
        <form method="post" style="margin-bottom:30px;">
            <label>Full Name:</label>
            <input type="text" name="fullName" value="<?= htmlspecialchars($fullName) ?>" readonly style="width:100%; padding:8px; margin-bottom:10px;">

            <textarea name="message" rows="4" style="width:100%; padding:10px; margin-bottom:10px;" placeholder="Share your testimonial..." required></textarea>
            <input type="hidden" name="action" value="add" />
            <button type="submit" style="background-color:#004aad; color:white; padding:8px 16px; border:none; border-radius:6px; cursor:pointer;">➕ Submit Testimonial</button>
        </form>

        <?php if ($feedbackMessage): ?>
            <div style="background-color: <?= $feedbackColor === 'green' ? '#e6ffed' : '#ffe6e6' ?>; border: 1px solid <?= $feedbackColor ?>; padding: 10px; border-radius: 6px; margin-bottom: 15px;">
                <?= $feedbackMessage ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <?php while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)):
        $status       = strtolower($row['status']);
        $isOwner      = $row['sender_email'] === $userName;
        $dateCreated  = $row['created_at'] ? $row['created_at']->format("Y-m-d H:i") : '';
        $dateEdited   = $row['edited_at'] ? $row['edited_at']->format("Y-m-d H:i") : '';
        $dateApproved = $row['approved_at'] ? $row['approved_at']->format("Y-m-d H:i") : '';

        $bgColor = match($status) {
            'approved' => '#e6ffed',
            'pending'  => '#fff9e6',
            'rejected' => '#ffe6e6',
            default    => '#f5f5f5',
        };
        $borderColor = match($status) {
            'approved' => '#28a745',
            'pending'  => '#ffc107',
            'rejected' => '#dc3545',
            default    => '#ccc',
        };
    ?>
        <div style="background-color: <?= $bgColor ?>; border-left: 6px solid <?= $borderColor ?>; padding: 15px 20px; margin-bottom: 25px; border-radius: 6px;">
            <div style="font-size: 0.9em; color: #666; margin-bottom: 8px;">
                <strong><?= htmlspecialchars($row['sender_name'] ?? $row['sender_email']) ?></strong> (<?= htmlspecialchars($row['role']) ?>)
                &nbsp;&bull;&nbsp; Submitted: <?= $dateCreated ?>
                <?php if ($dateEdited): ?> &nbsp;&bull;&nbsp; Edited: <?= $dateEdited ?> <?php endif; ?>
                <?php if ($status !== 'pending' && $role === 'teacher'): ?>
                    &nbsp;&bull;&nbsp; <?= ucfirst($status) ?> by <?= htmlspecialchars($row['approved_by']) ?> on <?= $dateApproved ?>
                <?php endif; ?>
            </div>

            <div style="white-space: pre-wrap; margin-bottom: 10px;"><?= htmlspecialchars($row['message']) ?></div>

            <div>
                <?php if ($role === 'teacher' && $status === 'pending'): ?>
                    <form method="post" style="display:inline-block; margin-right:10px;">
                        <input type="hidden" name="id" value="<?= $row['id'] ?>" />
                        <button type="submit" name="action" value="approve" style="background-color:#28a745; color:#fff; padding:6px 12px; border:none; border-radius:5px;">✅ Approve</button>
                    </form>
                    <form method="post" style="display:inline-block;">
                        <input type="hidden" name="id" value="<?= $row['id'] ?>" />
                        <button type="submit" name="action" value="reject" style="background-color:#dc3545; color:#fff; padding:6px 12px; border:none; border-radius:5px;">❌ Reject</button>
                    </form>
                <?php elseif ($isOwner && in_array($role, ['learner', 'parent'])): ?>
                    <form method="post" style="margin-top:10px;">
                        <input type="hidden" name="id" value="<?= $row['id'] ?>" />
                        <textarea name="message" rows="3" required style="width:100%; padding:8px; border:1px solid #aaa; border-radius:5px;"><?= htmlspecialchars($row['message']) ?></textarea>
                        <button type="submit" name="action" value="edit" style="background-color:#004aad; color:#fff; padding:6px 12px; border:none; border-radius:5px; margin-right:8px;">✏️ Save Edit</button>
                        <button type="submit" name="action" value="delete" onclick="return confirm('Are you sure you want to delete this testimonial?')" style="background-color:crimson; color:#fff; padding:6px 12px; border:none; border-radius:5px;">🗑️ Delete</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    <?php endwhile; ?>
</div>
