<?php
/**
 * Supervisor shell — inherits Style A (clean light gov-tech) from Employer module.
 * Presentation only. No auth, no Firestore, no business logic here.
 * Pages call: supervisor_render_shell('Dashboard'|'My Employees'|'Rating Entry'|'Reports'|'Notifications'|'Settings')
 */

require_once __DIR__ . '/../Employer/includes/icons.php';
require_once __DIR__ . '/../Employer/includes/format_helpers.php';

function supervisor_layout_icon(string $name): string
{
  if (function_exists('render_icon')) {
    $svg = render_icon($name);
    if ($svg !== '') {
      return $svg;
    }
  }

  $fallbacks = [
    'home' => '<path d="m3 10 9-7 9 7v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><path d="M9 22V12h6v10"/>',
    'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
    'target' => '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="4"/><path d="M12 3v2M21 12h-2M12 21v-2M3 12h2"/>',
    'reports' => '<path d="M4 19V5M4 19h16"/><path d="m7 15 3-4 3 2 4-6"/>',
    'bar-chart' => '<path d="M18 20V10M12 20V4M6 20v-6"/>',
    'bell' => '<path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/>',
    'settings' => '<circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3"/>',
  ];
  $path = $fallbacks[$name] ?? $fallbacks['home'];
  return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $path . '</svg>';
}

function supervisor_icon(string $name): string
{
  return supervisor_layout_icon($name);
}

/**
 * Assignment scoping — single source of truth for every supervisor list.
 * A supervisor sees staff explicitly assigned to them (Users.supervisorId);
 * legacy/unassigned staff (empty supervisorId) stay visible so existing
 * pilots keep working. Staff assigned to a DIFFERENT supervisor are out of
 * scope. Pure function: unit-tested in tests/supervisor_scope_test.php.
 */
function supervisor_is_in_scope(array $userDoc, string $supervisorUid): bool
{
    $assigned = trim((string) ($userDoc['supervisorId'] ?? ''));
    return $assigned === '' || $assigned === $supervisorUid;
}

/**
 * Filter a Users-style document list down to in-scope staff.
 */
function supervisor_filter_docs(array $docs, string $supervisorUid): array
{
    return array_values(array_filter(
        $docs,
        static fn($doc): bool => is_array($doc) && supervisor_is_in_scope($doc, $supervisorUid)
    ));
}

/**
 * First letters of the supervisor's name ("John Doe" -> "JD").
 */
function supervisor_avatar_initials(string $name): string
{
  $words = preg_split('/\s+/', trim($name));
  $initials = '';

  foreach (array_slice(is_array($words) ? $words : [], 0, 2) as $word) {
    $initials .= strtoupper(substr($word, 0, 1));
  }

  return $initials !== '' ? $initials : 'S';
}

/**
 * Brand chrome for <head>: inline SVG favicon + theme color + Google Fonts.
 */
function supervisor_brand_head(string $pageTitle = 'Performa | Supervisor'): void
{
  ?>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64'%3E%3Crect width='64' height='64' rx='14' fill='%23245fba'/%3E%3Ctext x='32' y='44' font-family='Arial,sans-serif' font-size='36' font-weight='bold' text-anchor='middle' fill='white'%3EP%3C/text%3E%3C/svg%3E" />
  <meta name="theme-color" content="#142236" />
  <title><?php echo htmlspecialchars($pageTitle, ENT_QUOTES); ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="<?php echo htmlspecialchars(supervisor_asset('../Employer/styles.css'), ENT_QUOTES); ?>" />
  <link rel="stylesheet" href="<?php echo htmlspecialchars(supervisor_asset('../ui-refresh.css'), ENT_QUOTES); ?>" />
  <link rel="stylesheet" href="<?php echo htmlspecialchars(supervisor_asset('styles.css'), ENT_QUOTES); ?>" />
  <?php
}

/**
 * Cache-busted local asset URL.
 */
function supervisor_asset(string $relPath): string
{
  $full = __DIR__ . '/' . ltrim($relPath, '/');

  if (is_file($full)) {
    $mtime = @filemtime($full);
    if ($mtime !== false) {
      return $relPath . '?v=' . $mtime;
    }
  }

  return $relPath;
}

/**
 * Accessible score meter: numeric value (mono) + progress bar.
 */
function supervisor_score_meter(?float $score, float $max = 5.0, ?float $target = null): string
{
  if ($score === null) {
    return '<div class="pf-score"><strong class="pf-score-value pf-score-empty">—</strong>'
      . '<span class="pf-score-note">No ratings yet</span></div>';
  }

  $pct = max(0, min(100, (int) round(($score / $max) * 100)));
  $label = number_format($score, 1) . ' out of ' . number_format($max, 1);
  $targetAttr = $target !== null
    ? ' data-target="' . htmlspecialchars(number_format($target, 1), ENT_QUOTES) . '"'
    : '';

  return '<div class="pf-score"' . $targetAttr . '>'
    . '<strong class="pf-score-value font-mono">' . htmlspecialchars(number_format($score, 1), ENT_QUOTES) . '</strong>'
    . '<span class="pf-score-scale font-mono">/ ' . htmlspecialchars(number_format($max, 1), ENT_QUOTES) . '</span>'
    . '<span class="pf-meter" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="'
    . $pct . '" aria-label="KPI score ' . htmlspecialchars($label, ENT_QUOTES) . '">'
    . '<span class="pf-meter-fill" style="width:' . $pct . '%"></span></span>'
    . '<span class="sr-only">' . htmlspecialchars($label, ENT_QUOTES) . '</span>'
    . '</div>';
}

/**
 * Shared page header — same markup pattern as Employer.
 */
function supervisor_page_header(
  string $titleId,
  string $title,
  string $leadHtml,
  string $descHtml,
  string $actionsHtml = '',
  string $extraClass = '',
  string $tag = 'section'
): void {
  $tag = $tag === 'header' ? 'header' : 'section';
  $class = trim('page-header ' . $extraClass);
  ?>
  <<?php echo $tag; ?> class="<?php echo htmlspecialchars($class, ENT_QUOTES); ?>" aria-labelledby="<?php echo htmlspecialchars($titleId, ENT_QUOTES); ?>">
    <button class="icon-button pf-menu-btn" type="button" data-sidebar-toggle aria-label="Open navigation" aria-expanded="false">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" aria-hidden="true"><line x1="4" y1="7" x2="20" y2="7"/><line x1="4" y1="12" x2="20" y2="12"/><line x1="4" y1="17" x2="20" y2="17"/></svg>
    </button>
    <div class="ph-main">
      <?php echo $leadHtml; ?>
      <h1 id="<?php echo htmlspecialchars($titleId, ENT_QUOTES); ?>"><?php echo htmlspecialchars($title, ENT_QUOTES); ?></h1>
      <?php if ($descHtml !== ''): ?>
        <p><?php echo $descHtml; ?></p>
      <?php endif; ?>
    </div>
    <?php if (trim($actionsHtml) !== ''): ?>
    <div class="ph-actions">
      <?php echo $actionsHtml; ?>
    </div>
    <?php endif; ?>
  </<?php echo $tag; ?>>
  <?php
}

function supervisor_nav_groups(): array
{
  return [
    [
      'label' => 'Manage',
      'items' => [
        ['label' => 'Dashboard', 'href' => 'supervisor_dashboard.php', 'key' => 'Dashboard', 'icon' => 'home'],
        ['label' => 'My Employees', 'href' => 'employees.php', 'key' => 'My Employees', 'icon' => 'users'],
        ['label' => 'Rating Entry', 'href' => 'ratings.php', 'key' => 'Rating Entry', 'icon' => 'target'],
        ['label' => 'Reports', 'href' => 'reports.php', 'key' => 'Reports', 'icon' => 'bar-chart'],
        ['label' => 'Notifications', 'href' => 'notifications.php', 'key' => 'Notifications', 'icon' => 'bell'],
      ],
    ],
    [
      'label' => 'Account',
      'items' => [
        ['label' => 'Settings', 'href' => 'settings.php', 'key' => 'Settings', 'icon' => 'settings'],
      ],
    ],
  ];
}

function supervisor_nav_badge(string $key): ?string
{
  if ($key === 'Notifications') {
    $n = isset($_SESSION['pf_nav_unrated']) ? (int) $_SESSION['pf_nav_unrated'] : null;
    return ($n !== null && $n > 0) ? (string) $n : null;
  }
  if ($key === 'Dashboard') {
    $n = isset($_SESSION['pf_nav_deadline']) ? (int) $_SESSION['pf_nav_deadline'] : null;
    return ($n !== null && $n > 0) ? (string) $n : null;
  }
  return null;
}

function supervisor_render_nav_item(array $item, string $active): void
{
  $badge = supervisor_nav_badge($item['key']);
  ?>
  <a class="nav-item<?php echo $item['key'] === $active ? ' active' : ''; ?>"
    href="<?php echo htmlspecialchars($item['href'], ENT_QUOTES); ?>" <?php echo $item['key'] === $active ? ' aria-current="page"' : ''; ?>
    title="<?php echo htmlspecialchars($item['label'], ENT_QUOTES); ?>">
    <span class="nav-icon" aria-hidden="true"><?php echo supervisor_layout_icon($item['icon']); ?></span>
    <span class="nav-label"><?php echo htmlspecialchars($item['label'], ENT_QUOTES); ?></span>
    <?php if ($badge !== null): ?>
      <span class="nav-badge" aria-hidden="true"><?php echo htmlspecialchars($badge, ENT_QUOTES); ?></span>
      <span class="sr-only"><?php echo htmlspecialchars($badge, ENT_QUOTES); ?></span>
    <?php endif; ?>
  </a>
  <?php
}

function supervisor_render_avatar(string $name): void
{
  ?>
  <div class="profile-avatar" title="<?php echo htmlspecialchars($name, ENT_QUOTES); ?>"
    aria-hidden="true"><span class="profile-initials" aria-hidden="true"><?php echo htmlspecialchars(supervisor_avatar_initials($name), ENT_QUOTES); ?></span></div>
  <?php
}

function supervisor_render_collapse_button(): void
{
  ?>
  <button class="pf-sidebar-collapse" type="button" data-sidebar-collapse aria-label="Collapse navigation" aria-expanded="true" title="Collapse sidebar">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="15 18 9 12 15 6"/></svg>
  </button>
  <?php
}

function supervisor_render_shell(string $active): void
{
  if (session_status() === PHP_SESSION_NONE) {
    session_start();
  }
  $profileName = $_SESSION['name'] ?? 'Supervisor';
  $profileRole = 'Shift Supervisor';
  $navGroups = supervisor_nav_groups();
  ?>
  <div class="pf-sidebar-backdrop" data-sidebar-backdrop aria-hidden="true"></div>
  <aside class="sidebar" id="pfSidebar" aria-label="Supervisor navigation">
    <div class="sidebar-top">
      <div class="brand">
        <span class="brand-mark" aria-hidden="true"><span class="brand-mark-dot"></span></span>
        <span class="brand-name">Performa</span>
        <?php supervisor_render_collapse_button(); ?>
        <button class="pf-sidebar-close" type="button" data-sidebar-close aria-label="Close navigation">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" aria-hidden="true"><line x1="6" y1="6" x2="18" y2="18"/><line x1="18" y1="6" x2="6" y2="18"/></svg>
        </button>
      </div>
      <nav class="nav" aria-label="Primary">
        <?php foreach ($navGroups as $group): ?>
          <span class="nav-group-label" aria-hidden="true"><?php echo htmlspecialchars($group['label'], ENT_QUOTES); ?></span>
          <?php foreach ($group['items'] as $item): ?>
            <?php supervisor_render_nav_item($item, $active); ?>
          <?php endforeach; ?>
        <?php endforeach; ?>
      </nav>
    </div>
    <div class="sidebar-footer">
      <?php supervisor_render_avatar($profileName); ?>
      <div class="profile-meta">
        <div class="profile-name"><?php echo htmlspecialchars($profileName, ENT_QUOTES); ?></div>
        <div class="profile-role"><?php echo htmlspecialchars($profileRole, ENT_QUOTES); ?></div>
      </div>
      <a class="profile-signout" href="../logout.php" aria-label="Sign out" title="Sign out">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
      </a>
    </div>
  </aside>
  <?php
}

