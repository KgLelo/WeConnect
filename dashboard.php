<?php
session_start();
if (!isset($_SESSION['userName']) || !isset($_SESSION['role'])) {
  header("Location: login.php");
  exit();
}
$userName = $_SESSION['userName'];
$role = strtolower($_SESSION['role']); // make role lowercase for easy checking

$allowed_pages = [
  'school_calendar',
  'study_materials',
  'meetings',
  'school_events',
  'exams/Tests',
  'news/Announcements',
  'testimonials',
  'teacher_meetinginvitation'
];

$page = isset($_GET['page']) && in_array($_GET['page'], $allowed_pages) ? $_GET['page'] : 'welcome';

$page_file_map = [
  'school_calendar' => 'school_calendar.php',
  'study_materials' => 'study_materials.php',
  'meetings' => 'meetings.php',  // Used for both roles, depending on menu
  'school_events' => 'school_events.php',
  'exams/Tests' => 'exams_tests.php',
  'news/Announcements' => 'news.php',
  'testimonials' => 'testimonials.php',
  'teacher_meetinginvitation' => 'teacher_meetinginvitation.php',
  'welcome' => 'welcome.php'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>WeConnect Dashboard</title>
  <style>
    body {
      font-family: Arial, sans-serif;
      margin: 0; padding: 0;
      display: flex;
      background-image: url('images/img11.jpg');
      background-size: cover;
      background-position: center;
      background-attachment: fixed;
      min-height: 100vh;
      color: #333;
    }
    .sidebar {
      width: 250px;
      background-color: rgba(0, 74, 173, 0.9);
      color: white;
      padding-top: 20px;
      height: 100vh;
      position: fixed;
      overflow-y: auto;
    }
    .sidebar h2 {
      text-align: center;
      margin-bottom: 20px;
    }
    .sidebar .menu {
      display: flex;
      flex-direction: column;
      align-items: stretch;
    }
    .sidebar .menu a {
      padding: 10px 20px;
      color: white;
      text-decoration: none;
      display: block;
    }
    .sidebar .menu a:hover,
    .sidebar .menu a.active {
      background-color: rgba(255, 255, 255, 0.2);
      font-weight: bold;
    }
    .dropdown-content {
      display: none;
      flex-direction: column;
      background-color: rgba(0, 74, 173, 0.8);
      margin-left: 10px;
    }
    .dropdown:hover .dropdown-content {
      display: flex;
    }
    .dropdown .dropdown-link {
      padding: 8px 20px;
      color: white;
      text-decoration: none;
      font-size: 14px;
    }
    .dropdown .dropdown-link:hover {
      background-color: rgba(255, 255, 255, 0.2);
    }
    .main-content {
      margin-left: 250px;
      padding: 20px;
      width: calc(100% - 250px);
      min-height: 100vh;
      background-color: rgba(255, 255, 255, 0.9);
      overflow-y: auto;
    }
    header {
      background-color: rgba(0, 74, 173, 0.85);
      color: white;
      padding: 10px 30px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 20px;
      border-radius: 5px;
    }
    .profile-card {
      background-color: rgba(255, 255, 255, 0.95);
      color: #004aad;
      padding: 10px 15px;
      border-radius: 10px;
      box-shadow: 0 0 5px rgba(0,0,0,0.2);
      font-size: 14px;
      text-align: right;
    }
    .logout-btn {
      background-color: #004aad;
      color: white;
      padding: 6px 12px;
      text-decoration: none;
      border-radius: 5px;
      font-weight: bold;
      font-size: 12px;
      transition: background-color 0.3s ease;
      margin-top: 5px;
      display: inline-block;
    }
    .logout-btn:hover {
      background-color: #003b80;
    }
    .system-info h2, .system-info h3 {
      color: #004aad;
    }
    .branding {
      text-align: center;
      margin-bottom: 30px;
    }
    .branding img {
      max-width: 150px;
      height: auto;
    }
    .branding h3 {
      font-size: 20px;
      color: #004aad;
      margin-top: 10px;
    }
  </style>
</head>
<body>

  <div class="sidebar">
    <h2>📘 Menu</h2>
    <div class="menu">
      <?php
        $icons = [
          'school_calendar' => '🗓️',
          'study_materials' => '📚',
          'meetings' => '🧑‍🏫',
          'school_events' => '🎉',
          'exams/Tests' => '📝',
          'news/Announcements' => '📰',
          'testimonials' => '💬'
        ];

        foreach ($allowed_pages as $p) {
          $active = ($page === $p) ? 'active' : '';
          $label = ucwords(str_replace('_', ' ', $p));
          $icon = $icons[$p] ?? '📌';

          if ($p == 'meetings') {
            echo "<div class='dropdown'>
                    <a href='#' class='$active'>$icon Meetings ▼</a>
                    <div class='dropdown-content'>";
            if ($role == 'teacher') {
              echo "<a href='dashboard.php?page=teacher_meetinginvitation' class='dropdown-link'>📅 Request a Meeting</a>";
              echo "<a href='dashboard.php?page=meetings' class='dropdown-link'>👥 View Meeting Requests</a>";
            } elseif ($role == 'parent' || $role == 'learner') {
              echo "<a href='dashboard.php?page=meetings' class='dropdown-link'>📅 Request a Meeting</a>";
              echo "<a href='dashboard.php?page=teacher_meetinginvitation' class='dropdown-link'>👥 View Meeting Requests</a>";
            }
            echo "</div></div>";
          } elseif ($p !== 'teacher_meetinginvitation') {
            echo "<a href='dashboard.php?page=$p' class='$active'>$icon $label</a>";
          }
        }
      ?>
      <a href="logout.php" style="margin-top:20px; color:white;">🚪 Logout</a>
    </div>
  </div>

  <div class="main-content">
    <header>
      <div>
        Dear <strong><?php echo ucfirst(htmlspecialchars($role)); ?></strong>, Welcome to WeConnect Dashboard
      </div>
      <div class="profile-card">
        👤 <strong><?php echo htmlspecialchars($userName); ?></strong><br />
        Role: <?php echo ucfirst(htmlspecialchars($role)); ?>
      </div>
    </header>

    <div class="branding">
      <img src="images/logo.png" alt="WeConnect Logo">
      <h3>The Future is Bright With Us!</h3>
    </div>

    <section class="content">
      <?php
        $include_file = $page_file_map[$page] ?? '';

        if ($include_file && file_exists($include_file)) {
          include $include_file;
        } else {
          echo '
          <div class="system-info">
            <h2>🌐 Welcome to WeConnect</h2>
            <p><strong>Mission:</strong> To connect learners, teachers, and parents in a collaborative online environment that empowers every learner to reach their full academic potential.</p>
            <p><strong>Vision:</strong> To become the leading digital education platform in Africa by promoting inclusive, quality education and leveraging technology to bridge communication gaps between learners, parents, and educators.</p>
            <h3>📖 Background</h3>
            <p>WeConnect is an innovative online platform designed to empower learners by connecting them with teachers and parents to enhance their educational experience.</p>
          </div>';
        }
      ?>
    </section>
  </div>

</body>
</html>
