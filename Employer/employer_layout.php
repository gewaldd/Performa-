<?php
function employer_layout_icon(string $name): string
{
  $paths = [
    'home' => '<path d="m3 10 9-7 9 7v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><path d="M9 22V12h6v10"/>',
    'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
    'target' => '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="4"/><path d="M12 3v2M21 12h-2M12 21v-2M3 12h2"/>',
    'reports' => '<path d="M4 19V5M4 19h16"/><path d="m7 15 3-4 3 2 4-6"/>',
    'settings' => '<path d="M12 15.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Z"/><path d="M19.4 15a1.7 1.7 0 0 0 .34 1.88l.05.05-2.27 2.27-.05-.05a1.7 1.7 0 0 0-1.88-.34 1.7 1.7 0 0 0-1.03 1.56V21h-3.2v-.63a1.7 1.7 0 0 0-1.03-1.56 1.7 1.7 0 0 0-1.88.34l-.05.05-2.27-2.27.05-.05A1.7 1.7 0 0 0 6.52 15a1.7 1.7 0 0 0-1.56-1.03H4.3v-3.2h.66A1.7 1.7 0 0 0 6.52 9a1.7 1.7 0 0 0-.34-1.88l-.05-.05L8.4 4.8l.05.05a1.7 1.7 0 0 0 1.88.34 1.7 1.7 0 0 0 1.03-1.56V3h3.2v.63a1.7 1.7 0 0 0 1.03 1.56 1.7 1.7 0 0 0 1.88-.34l.05-.05 2.27 2.27-.05.05A1.7 1.7 0 0 0 19.4 9a1.7 1.7 0 0 0 1.56 1.03h.64v3.2h-.64A1.7 1.7 0 0 0 19.4 15Z"/>',
  ];
  $path = $paths[$name] ?? $paths['home'];
  return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $path . '</svg>';
}

function employer_render_shell(string $active): void
{
  if (session_status() === PHP_SESSION_NONE) {
    session_start();
  }
  $profileName = $_SESSION['name'] ?? 'Employer';
  $profileRole = ucwords(str_replace('_', ' ', $_SESSION['role'] ?? 'Employer'));
  $items = [
    ['label' => 'Dashboard', 'href' => 'employer_dashboard.php', 'key' => 'Dashboard', 'icon' => 'home'],
    ['label' => 'Employees', 'href' => 'employees.php', 'key' => 'Employees', 'icon' => 'users'],
    ['label' => 'KPIs', 'href' => 'kpis.php', 'key' => 'KPIs', 'icon' => 'target'],
    ['label' => 'Reports', 'href' => 'reports.php', 'key' => 'Reports', 'icon' => 'reports'],
    ['label' => 'Settings', 'href' => 'settings.php', 'key' => 'Settings', 'icon' => 'settings'],
  ];
  ?>
  <aside class="sidebar">
    <div>
      <div class="brand">
        <span class="brand-mark"><span class="brand-mark-dot"></span></span>
        <span class="brand-name">Performa</span>
      </div>
      <nav class="nav" aria-label="Primary">
        <?php foreach ($items as $item): ?>
          <a class="nav-item<?php echo $item['key'] === $active ? ' active' : ''; ?>" href="<?php echo htmlspecialchars($item['href'], ENT_QUOTES); ?>"<?php echo $item['key'] === $active ? ' aria-current="page"' : ''; ?>>
            <span class="nav-icon"><?php echo employer_layout_icon($item['icon']); ?></span>
            <span><?php echo htmlspecialchars($item['label'], ENT_QUOTES); ?></span>
          </a>
        <?php endforeach; ?>
      </nav>
    </div>
    <div class="sidebar-footer">
      <div class="profile-avatar" aria-hidden="true"></div>
      <div>
        <div class="profile-name"><?php echo htmlspecialchars($profileName, ENT_QUOTES); ?></div>
        <div class="profile-role"><?php echo htmlspecialchars($profileRole, ENT_QUOTES); ?></div>
      </div>
    </div>
  </aside>
  <?php
}
?>
