<?php
// dashboard.php
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Pharmacy Dashboard</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
  <style>
    body {
      font-family: 'Poppins', sans-serif;
      background-color: #f5f6f8;
      margin: 0;
      overflow: hidden;
      color: #333;
    }

    /* Sidebar */
    .sidebar {
      height: 100vh;
      width: 240px;
      position: fixed;
      top: 0;
      left: 0;
      background: #1f2937; /* Dark gray */
      color: #e5e7eb;
      display: flex;
      flex-direction: column;
      padding: 20px 15px;
      box-shadow: 2px 0 6px rgba(0,0,0,0.1);
    }
    .sidebar h4 {
      text-align: center;
      margin-bottom: 30px;
      font-weight: 600;
      color: #60a5fa; /* Muted Blue */
    }
    .sidebar a {
      display: flex;
      align-items: center;
      padding: 12px 15px;
      margin: 6px 0;
      text-decoration: none;
      color: #e5e7eb;
      border-radius: 8px;
      transition: all 0.3s ease;
      font-size: 15px;
    }
    .sidebar a i {
      margin-right: 12px;
      font-size: 18px;
      color: #9ca3af;
    }
    .sidebar a:hover {
      background-color: #374151;
      transform: translateX(5px);
      color: #ffffff;
    }
    .sidebar a.active {
      background-color: #2563eb; /* Professional blue */
      color: white;
      font-weight: 600;
    }
    .sidebar a.active i {
      color: white;
    }

    /* Content */
    .content {
      margin-left: 240px;
      height: 100vh;
      background-color: #f9fafb;
      display: flex;
      flex-direction: column;
    }

    /* Topbar */
    .topbar {
      height: 60px;
      background: white;
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 0 20px;
      box-shadow: 0 2px 6px rgba(0,0,0,0.05);
      border-bottom: 1px solid #e5e7eb;
    }
    .topbar h5 {
      margin: 0;
      font-weight: 600;
      color: #1f2937;
    }
    .topbar span {
      font-size: 14px;
      color: #6b7280;
    }

    /* Frame */
    iframe {
      flex: 1;
      width: 100%;
      border: none;
      background: #ffffff;
    }
  </style>
</head>
<body>
  <!-- Sidebar -->
  <div class="sidebar">
    <h4><img src="med.jpg" alt="Medi-Vault Logo" style="width:150px; height:120px; vertical-align:middle; margin-right:10px;">
    <a href="welcome.php" target="mainFrame" class="active"><i class="fa-solid fa-house"></i> About Us</a>
    <a href="inventory/index.php" target="mainFrame"><i class="fa-solid fa-capsules"></i> Inventory</a>
    <a href="customers/index.php" target="mainFrame"><i class="fa-solid fa-users"></i> Customers</a>
    <a href="sales/sales.php" target="mainFrame"><i class="fa-solid fa-sack-dollar"></i> Sales Module</a>
    <a href="waitlist/index.php" target="mainFrame"><i class="fa-solid fa-clipboard-list"></i> Waitlist</a>
    <a href="reports/sales_report.php" target="mainFrame"><i class="fa-solid fa-chart-line"></i> Reports</a>
    <a href="users/index.php" target="mainFrame"><i class="fa-solid fa-user-gear"></i> Users</a>
</div>

  <!-- Content -->
  <div class="content">
    <div class="topbar">
      <h5>Pharmacy Management System</h5>
      <span>Welcome, Admin</span>
    </div>
    <iframe name="mainFrame" src="welcome.php"></iframe>
  </div>

  <script>
    // Sidebar active state
    document.querySelectorAll('.sidebar a').forEach(link => {
      link.addEventListener('click', function() {
        document.querySelectorAll('.sidebar a').forEach(l => l.classList.remove('active'));
        this.classList.add('active');
      });
    });
  </script>
</body>
</html>
