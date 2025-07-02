<?php
if (!isset($_SESSION)) session_start();
require 'connect.php';
$conn = connectToDatabase();
 
$role = strtolower($_SESSION['role']);
$userName = $_SESSION['userName'];
 
// Fetch Parent and Learner Data
$parents = [];
$learners = [];
$learnersById = [];
$learnerQuery = sqlsrv_query($conn, "SELECT learner_id, fullName, userName, phoneNum FROM LearnerTable");
while ($row = sqlsrv_fetch_array($learnerQuery, SQLSRV_FETCH_ASSOC)) {
    $learners[] = $row;
    $learnersById[$row['learner_id']] = $row;
}
 
$parents = [];
$parentsByLearnerId = [];
$parentQuery = sqlsrv_query($conn, "SELECT fullName, userName, phoneNum, learner_id FROM ParentTable");
while ($row = sqlsrv_fetch_array($parentQuery, SQLSRV_FETCH_ASSOC)) {
    $parents[] = $row;
    $parentsByLearnerId[$row['learner_id']] = $row;
}
 
// Function to simulate Email/SMS (For real use, integrate PHPMailer and SMS API here)
function sendNotification($to, $phone, $subject, $message) {
    echo "<div style='border:1px solid #004aad; padding:10px; margin-top:10px; border-radius:5px;'>
            📧 Email to: <strong>$to</strong> | 📱 SMS to: <strong>$phone</strong><br>
            <strong>Subject:</strong> $subject<br>
            <strong>Message:</strong> $message
          </div>";
}
?>
 
<div class="card" style="background-color: rgba(255,255,255,0.95); padding:20px; border-radius:10px;">
 
<h2 style="color: #004aad;">📅 Teacher Meeting Invitations</h2>
 
<?php if ($role === 'teacher'): ?>
 
  <?php
  // Handle Delete Request
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_meeting'])) {
      $delId = intval($_POST['meeting_id']);
      $check = sqlsrv_query($conn, "SELECT * FROM MeetingInvitations WHERE id = ? AND teacher_user = ?", [$delId, $userName]);
      if ($check && sqlsrv_has_rows($check)) {
          $delete = sqlsrv_query($conn, "DELETE FROM MeetingInvitations WHERE id = ?", [$delId]);
          if ($delete) {
              echo "<p style='color:green;'>✔ Meeting deleted successfully.</p>";
          } else {
              echo "<p style='color:red;'>❌ Failed to delete meeting.</p>";
          }
      } else {
          echo "<p style='color:red;'>❌ You do not have permission to delete this meeting.</p>";
      }
  }
 
  // Handle Update Request
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_meeting'])) {
      $updId = intval($_POST['meeting_id']);
      $title = trim($_POST['title']);
      $date = $_POST['date'];
      $time = $_POST['time'];
      $link = trim($_POST['meetingLink']);
 
      $check = sqlsrv_query($conn, "SELECT * FROM MeetingInvitations WHERE id = ? AND teacher_user = ? AND status = 'Pending'", [$updId, $userName]);
      if ($check && sqlsrv_has_rows($check)) {
          $update = sqlsrv_query($conn,
              "UPDATE MeetingInvitations SET title = ?, meeting_date = ?, meeting_time = ?, meeting_link = ? WHERE id = ?",
              [$title, $date, $time, $link, $updId]);
 
          if ($update) {
              echo "<p style='color:green;'>✔ Meeting updated successfully.</p>";
          } else {
              echo "<p style='color:red;'>❌ Failed to update meeting.</p>";
          }
      } else {
          echo "<p style='color:red;'>❌ You cannot update this meeting (must be pending and yours).</p>";
      }
  }
 
  // Handle Create Meeting (your existing create meeting code)
  if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_meeting'])) {
      $title = trim($_POST['title']);
      $date = $_POST['date'];
      $time = $_POST['time'];
      $link = trim($_POST['meetingLink']);
 
      $parentUser = $_POST['parentUser'];
      $parentPhone = $_POST['parentPhone'];
 
      $learnerUser = $_POST['learnerUser'];
      $learnerPhone = $_POST['learnerPhone'];
 
      $sql = "INSERT INTO MeetingInvitations
              (title, meeting_date, meeting_time, teacher_user, parent_user, parent_phone, learner_user, learner_phone, meeting_link, status)
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending')";
      $params = [$title, $date, $time, $userName, $parentUser, $parentPhone, $learnerUser, $learnerPhone, $link];
 
      $stmt = sqlsrv_query($conn, $sql, $params);
 
      if ($stmt) {
          sendNotification($parentUser, $parentPhone, "New Meeting Invitation", "Dear Parent, Meeting '$title' scheduled on $date at $time. Link: $link. Please log in to approve/reject.");
          sendNotification($learnerUser, $learnerPhone, "New Meeting Invitation", "Dear Learner, Meeting '$title' scheduled on $date at $time. Link: $link. Please log in to approve/reject.");
          sendNotification($userName, '', "Meeting Created", "Your meeting '$title' was successfully created and invitations sent.");
      } else {
          echo "<p style='color:red;'>❌ Failed to save meeting.</p>";
      }
  }
  ?>
 
  <!-- Create Meeting Form -->
  <form method="POST" style="margin-bottom: 30px;">
    
    <label>Title:</label><br>
    <input type="text" name="title" required style="width:100%; padding:8px;"><br>
 
    <label>Date:</label><br>
    <input type="date" name="date" required style="width:100%; padding:8px;"><br>
 
    <label>Time:</label><br>
    <input type="time" name="time" required style="width:100%; padding:8px;"><br>
 
    <label>Search Learner (Full Name):</label><br>
    <input list="learnerList" id="learnerFullName" oninput="fillLearner(this.value)" style="width:100%; padding:8px;">
    <datalist id="learnerList">
      <?php foreach ($learners as $l): ?>
        <option value="<?php echo htmlspecialchars($l['fullName']); ?>"></option>
      <?php endforeach; ?>
    </datalist>
    <label>Learner Email (Username):</label><br>
    <input type="text" name="learnerUser" id="learnerUser" readonly required style="width:100%; padding:8px;"><br>
    <label>Learner Phone:</label><br>
    <input type="text" name="learnerPhone" id="learnerPhone" readonly required style="width:100%; padding:8px;"><br>
   
    <label>Parent Full Name:</label><br>
<input type="text" name="parentFullName" id="parentFullName" readonly style="width:100%; padding:8px;"><br>
<label>Parent Email (Username):</label><br>
<input type="text" name="parentUser" id="parentUser" readonly required style="width:100%; padding:8px;"><br>
<label>Parent Phone:</label><br>
<input type="text" name="parentPhone" id="parentPhone" readonly required style="width:100%; padding:8px;"><br>
 
    <label>Meeting Link (URL):</label><br>
    <input type="url" name="meetingLink" required style="width:100%; padding:8px;" placeholder="https://meeting-link.com/"><br><br>
 
    <button type="submit" name="create_meeting" style="background-color:#004aad; color:white; padding:10px 20px;">Create & Notify</button>
  </form>
 
  <script>
    const parents = <?php echo json_encode($parents); ?>;
    const learners = <?php echo json_encode($learners); ?>;
    const parentsByLearnerId = <?php echo json_encode($parentsByLearnerId); ?>;
 
    /*function fillParent(fullName) {
      const parent = parents.find(p => p.fullName === fullName);
      if (parent) {
        document.getElementById('parentUser').value = parent.userName;
        document.getElementById('parentPhone').value = parent.phoneNum;
      } else {
        document.getElementById('parentUser').value = '';
        document.getElementById('parentPhone').value = '';
      }
    }*/
 
    function fillLearner(fullName) {
  const learner = learners.find(l => l.fullName === fullName);
  if (learner) {
    document.getElementById('learnerUser').value = learner.userName;
    document.getElementById('learnerPhone').value = learner.phoneNum;
 
    // Auto-fill parent if linked
    const parent = parentsByLearnerId[learner.learner_id];
    if (parent) {
      document.getElementById('parentFullName').value = parent.fullName;
      document.getElementById('parentUser').value = parent.userName;
      document.getElementById('parentPhone').value = parent.phoneNum;
    } else {
      document.getElementById('parentFullName').value = '';
      document.getElementById('parentUser').value = '';
      document.getElementById('parentPhone').value = '';
    }
  } else {
    document.getElementById('learnerUser').value = '';
    document.getElementById('learnerPhone').value = '';
    document.getElementById('parentFullName').value = '';
    document.getElementById('parentUser').value = '';
    document.getElementById('parentPhone').value = '';
  }
}
  </script>
 
  <!-- Show Teacher Created Meetings with Update/Delete options -->
  <h3 style="color:#004aad; margin-top:30px;">📋 Your Created Meetings</h3>
  <?php
  $meetings = sqlsrv_query($conn, "SELECT * FROM MeetingInvitations WHERE teacher_user = ? ORDER BY meeting_date DESC", [$userName]);
 
  while ($meeting = sqlsrv_fetch_array($meetings, SQLSRV_FETCH_ASSOC)) {
      $canDelete = ($meeting['parent_response'] === 'Rejected' || $meeting['learner_response'] === 'Rejected');
      $canUpdate = ($meeting['status'] === 'Pending');
      ?>
      <div style="border:1px solid #ccc; padding:10px; margin-bottom:10px; border-radius:5px;">
      <?php if ($canUpdate): ?>
          <form method="POST" style="margin-bottom:10px;">
            <input type="hidden" name="meeting_id" value="<?php echo $meeting['id']; ?>">
 
            <label>Title:</label><br>
            <input type="text" name="title" value="<?php echo htmlspecialchars($meeting['title']); ?>" required style="width:100%; padding:5px;"><br>
 
            <label>Date:</label><br>
            <input type="date" name="date" value="<?php echo $meeting['meeting_date']->format('Y-m-d'); ?>" required style="width:100%; padding:5px;"><br>
 
            <label>Time:</label><br>
            <input type="time" name="time" value="<?php echo $meeting['meeting_time']; ?>" required style="width:100%; padding:5px;"><br>
 
            <label>Meeting Link:</label><br>
            <input type="url" name="meetingLink" value="<?php echo htmlspecialchars($meeting['meeting_link']); ?>" required style="width:100%; padding:5px;"><br><br>
 
            <button type="submit" name="update_meeting" style="background-color:#004aad; color:white; padding:8px 15px;">Update Meeting</button>
          </form>
      <?php else: ?>
          <strong>Title:</strong> <?php echo htmlspecialchars($meeting['title']); ?><br>
          <strong>Date:</strong> <?php echo $meeting['meeting_date']->format('Y-m-d'); ?><br>
          <strong>Time:</strong> <?php echo $meeting['meeting_time']; ?><br>
          <strong>Link:</strong> <a href="<?php echo $meeting['meeting_link']; ?>" target="_blank"><?php echo $meeting['meeting_link']; ?></a><br>
      <?php endif; ?>
 
      <strong>Parent:</strong> <?php echo htmlspecialchars($meeting['parent_user']); ?> | 📞 <?php echo htmlspecialchars($meeting['parent_phone']); ?><br>
      <strong>Parent Response:</strong> <?php echo $meeting['parent_response'] ?? 'Pending'; ?><br>
      <?php if (!empty($meeting['parent_reason'])): ?>
        <strong>Parent Reason:</strong> <?php echo htmlspecialchars($meeting['parent_reason']); ?><br>
      <?php endif; ?>
 
      <strong>Learner:</strong> <?php echo htmlspecialchars($meeting['learner_user']); ?> | 📞 <?php echo htmlspecialchars($meeting['learner_phone']); ?><br>
      <strong>Learner Response:</strong> <?php echo $meeting['learner_response'] ?? 'Pending'; ?><br>
      <?php if (!empty($meeting['learner_reason'])): ?>
        <strong>Learner Reason:</strong> <?php echo htmlspecialchars($meeting['learner_reason']); ?><br>
      <?php endif; ?>
 
      <strong>Status:</strong> <?php echo htmlspecialchars($meeting['status']); ?><br><br>
 
      <?php if ($canDelete): ?>
          <form method="POST" onsubmit="return confirm('Are you sure you want to delete this rejected meeting?');" style="display:inline;">
            <input type="hidden" name="meeting_id" value="<?php echo $meeting['id']; ?>">
            <button type="submit" name="delete_meeting" style="background-color:#d9534f; color:white; padding:5px 15px;">Delete Rejected Meeting</button>
          </form>
      <?php endif; ?>
 
      </div>
  <?php
  }
  ?>
 
<?php elseif ($role === 'parent' || $role === 'learner'): ?>
 
  <!-- Parent or Learner Approval Section -->
  <?php
  $field = $role === 'parent' ? 'parent_user' : 'learner_user';
  $responseCol = $role === 'parent' ? 'parent_response' : 'learner_response';
  $reasonCol = $role === 'parent' ? 'parent_reason' : 'learner_reason';
 
  if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['respond_meeting'])) {
      $meetingID = $_POST['meeting_id'];
      $response = $_POST['response'];
      $reason = ($response == 'Rejected') ? trim($_POST['reason']) : NULL;
 
      $update = "UPDATE MeetingInvitations SET $responseCol = ?, $reasonCol = ? WHERE id = ?";
      sqlsrv_query($conn, $update, [$response, $reason, $meetingID]);
 
      $tStmt = sqlsrv_query($conn, "SELECT teacher_user FROM MeetingInvitations WHERE id = ?", [$meetingID]);
      $teacherRow = sqlsrv_fetch_array($tStmt, SQLSRV_FETCH_ASSOC);
      $teacher = $teacherRow['teacher_user'];
 
      sendNotification($teacher, '', "Meeting Response", "$role '$userName' has $response your meeting.".($reason ? " Reason: $reason" : ""));
  }
 
  $stmt = sqlsrv_query($conn, "SELECT * FROM MeetingInvitations WHERE $field = ? AND status = 'Pending'", [$userName]);
 
  echo "<h3 style='color:#004aad;'>Pending Meetings for You</h3>";
 
  while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
  ?>
    <form method="POST" style="border:1px solid #ddd; padding:10px; margin-bottom:10px; border-radius:5px;">
      <strong>Title:</strong> <?php echo $row['title']; ?><br>
      <strong>Date:</strong> <?php echo $row['meeting_date']->format('Y-m-d'); ?><br>
      <strong>Time:</strong> <?php echo $row['meeting_time']; ?><br>
      <strong>Link:</strong> <a href="<?php echo $row['meeting_link']; ?>" target="_blank"><?php echo $row['meeting_link']; ?></a><br>
 
      <input type="hidden" name="meeting_id" value="<?php echo $row['id']; ?>">
 
      <label>Response:</label><br>
      <select name="response" required onchange="toggleReason(this.value, '<?php echo $row['id']; ?>')">
        <option value="">Select...</option>
        <option value="Approved">Approve ✅</option>
        <option value="Rejected">Reject ❌</option>
      </select><br>
 
      <div id="reasonBox_<?php echo $row['id']; ?>" style="display:none;">
        <label>Reason (for rejection only):</label>
        <textarea name="reason" placeholder="Explain reason..."></textarea>
      </div>
 
      <button type="submit" name="respond_meeting" style="background-color:#004aad; color:white; padding:5px 15px; margin-top:5px;">Submit</button>
    </form>
 
    <script>
      function toggleReason(value, id) {
        document.getElementById('reasonBox_' + id).style.display = (value == 'Rejected') ? 'block' : 'none';
      }
    </script>
 
  <?php
  }
endif;
?>
</div>
