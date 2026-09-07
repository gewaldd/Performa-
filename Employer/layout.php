<?php
/**
 * Employer/layout.php — Master layout (Bootstrap 5)
 * ---------------------------------------------------------------
 * Usage from any Employer view:
 *
 *   $pageTitle = 'Employee Reports';
 *   $activeNav = 'Reports';
 *   ob_start();
 *   ?>
 *     ... page-specific markup ...
 *   <?php
 *   $content = ob_get_clean();
 *   require __DIR__ . '/layout.php';
 *
 * This file only renders presentation (HTML skeleton, sidebar, topbar).
 * It does NOT run auth checks or touch Firebase/Firestore — the calling
 * view is still responsible for require_login()/require_role() and all
 * business logic, exactly as before. $content must already be fully
 * rendered (escaped) HTML by the time it reaches this file.
 */

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

$pageTitle = $pageTitle ?? 'Performa';
$activeNav = $activeNav ?? '';
$content = $content ?? '';

$profileName = $_SESSION['name'] ?? 'Employer';
$profileRole = ucwords(str_replace('_', ' ', $_SESSION['role'] ?? 'Employer'));

$navItems = [
  ['label' => 'Dashboard', 'href' => 'employer_dashboard.php', 'icon' => 'bi-speedometer2'],
  ['label' => 'Employees', 'href' => 'employees.php', 'icon' => 'bi-people'],
  ['label' => 'KPIs', 'href' => 'kpis.php', 'icon' => 'bi-bullseye'],
  ['label' => 'Reports', 'href' => 'reports.php', 'icon' => 'bi-bar-chart-line'],
  ['label' => 'Settings', 'href' => 'settings.php', 'icon' => 'bi-gear'],
];
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Performa | <?php echo htmlspecialchars($pageTitle, ENT_QUOTES); ?></title>

  <!-- Bootstrap 5 CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Bootstrap Icons -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

  <!-- Performa typography: IBM Plex Sans (UI text), JetBrains Mono (numeric/tabular data) -->
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600;700&display=swap"
    rel="stylesheet" />

  <style>
    body,
    .btn,
    .form-control,
    .form-select,
    .nav-link {
      font-family: "IBM Plex Sans", "Segoe UI", sans-serif;
    }

    /* Numeric/tabular data (scores, targets, counts) — opt-in via .font-mono,
       not applied to every table cell, since most cells are names/labels. */
    .font-mono {
      font-family: "JetBrains Mono", "Cascadia Code", monospace;
      font-variant-numeric: tabular-nums;
    }
    body {
      min-height: 100vh;
      background-color: #f5f7fb;
    }

    .sidebar {
      min-height: 100vh;
      background-color: #10182d;
    }

    .sidebar .nav-link {
      color: rgba(255, 255, 255, 0.65);
      font-weight: 500;
      border-radius: 0.5rem;
      padding: 0.6rem 0.9rem;
    }

    .sidebar .nav-link:hover {
      color: #fff;
      background-color: rgba(255, 255, 255, 0.06);
    }

    .sidebar .nav-link.active {
      color: #fff;
      background-color: #2f6df6;
    }

    .sidebar .nav-link i {
      width: 1.25rem;
      text-align: center;
      margin-right: 0.5rem;
    }

    .brand-badge {
      width: 32px;
      height: 32px;
      border-radius: 0.6rem;
      background: linear-gradient(145deg, #3c78ff, #70a2ff);
    }
  </style>
</head>

<body>
  <div class="d-flex">

    <!-- Desktop sidebar -->
    <nav class="sidebar d-none d-md-flex flex-column flex-shrink-0 p-3" style="width: 240px;">
      <a href="employer_dashboard.php" class="d-flex align-items-center gap-2 mb-4 text-decoration-none">
        <span class="brand-badge"></span>
        <span class="fs-5 fw-bold text-white">Performa</span>
      </a>
      <ul class="nav nav-pills flex-column mb-auto gap-1">
        <?php foreach ($navItems as $item): ?>
          <li class="nav-item">
            <a href="<?php echo htmlspecialchars($item['href'], ENT_QUOTES); ?>"
              class="nav-link<?php echo $item['label'] === $activeNav ? ' active' : ''; ?>"
              <?php echo $item['label'] === $activeNav ? 'aria-current="page"' : ''; ?>>
              <i class="bi <?php echo htmlspecialchars($item['icon'], ENT_QUOTES); ?>"></i>
              <?php echo htmlspecialchars($item['label'], ENT_QUOTES); ?>
            </a>
          </li>
        <?php endforeach; ?>
      </ul>
      <hr class="text-white-50">
      <div class="d-flex align-items-center gap-2 text-white">
        <div class="rounded-circle bg-secondary" style="width:36px;height:36px;"></div>
        <div>
          <div class="fw-semibold small"><?php echo htmlspecialchars($profileName, ENT_QUOTES); ?></div>
          <div class="text-white-50" style="font-size:0.75rem;"><?php echo htmlspecialchars($profileRole, ENT_QUOTES); ?></div>
        </div>
      </div>
    </nav>

    <!-- Mobile offcanvas sidebar -->
    <div class="offcanvas offcanvas-start bg-dark" tabindex="-1" id="mobileSidebar" aria-labelledby="mobileSidebarLabel">
      <div class="offcanvas-header">
        <span class="fs-5 fw-bold text-white" id="mobileSidebarLabel">Performa</span>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close"></button>
      </div>
      <div class="offcanvas-body">
        <ul class="nav nav-pills flex-column gap-1">
          <?php foreach ($navItems as $item): ?>
            <li class="nav-item">
              <a href="<?php echo htmlspecialchars($item['href'], ENT_QUOTES); ?>"
                class="nav-link<?php echo $item['label'] === $activeNav ? ' active' : ''; ?>">
                <i class="bi <?php echo htmlspecialchars($item['icon'], ENT_QUOTES); ?>"></i>
                <?php echo htmlspecialchars($item['label'], ENT_QUOTES); ?>
              </a>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>

    <!-- Main column -->
    <div class="flex-grow-1 min-vw-0">
      <!-- Top header -->
      <nav class="navbar navbar-expand navbar-light bg-white border-bottom px-3">
        <button class="btn btn-outline-secondary d-md-none me-2" type="button" data-bs-toggle="offcanvas"
          data-bs-target="#mobileSidebar" aria-controls="mobileSidebar" aria-label="Open menu">
          <i class="bi bi-list"></i>
        </button>
        <span class="navbar-brand mb-0 h1 fs-6 d-md-none">Performa</span>
        <div class="ms-auto d-flex align-items-center gap-2">
          <button class="btn btn-outline-secondary btn-sm" type="button" aria-label="Notifications">
            <i class="bi bi-bell"></i>
          </button>
          <a class="btn btn-outline-secondary btn-sm" href="../logout.php">Sign out</a>
        </div>
      </nav>

      <!-- Page content, injected from the calling view -->
      <main class="container-fluid p-3 p-md-4">
        <?php echo $content; ?>
      </main>
    </div>
  </div>

  <!-- Bootstrap 5 JS bundle (includes Popper) -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>