<?php
/**
 * Employer shell — single source of truth (Style A: clean light gov-tech).
 * Presentation only. No auth, no Firestore, no business logic here.
 * All pages call: employer_render_shell('Dashboard'|'Employees'|'KPIs'|'Reports'|'Settings')
 */

require_once __DIR__ . '/includes/icons.php';
require_once __DIR__ . '/includes/format_helpers.php';

function employer_layout_icon(string $name): string
{
  // Delegate to shared library so icon SVGs live in exactly one place.
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
    'settings' => '<circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3"/>',
  ];
  $path = $fallbacks[$name] ?? $fallbacks['home'];
  return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $path . '</svg>';
}

function employer_icon(string $name): string
{
  return employer_layout_icon($name);
}

/**
 * First letters of the first two name words ("Ralph Andrew" -> "RA").
 * Used for local initial-chip avatars (zero external requests).
 */
function employer_avatar_initials(string $name): string
{
  $words = preg_split('/\s+/', trim($name));
  $initials = '';

  foreach (array_slice(is_array($words) ? $words : [], 0, 2) as $word) {
    $initials .= strtoupper(substr($word, 0, 1));
  }

  return $initials !== '' ? $initials : 'P';
}

/**
 * Brand chrome for <head>: inline SVG favicon (offline-safe, no new files)
 * + mobile theme color. Call once per page right after the viewport meta.
 */
function employer_brand_head(): void
{
  echo '<script>try{var p=localStorage.getItem("performa-palette");if(/^(midnight|dusk|paper|mist|sage)$/.test(p)){document.documentElement.setAttribute("data-theme",p)}else{var l=localStorage.getItem("performa-theme");var v=l==="dark"?"midnight":l==="light"?"paper":null;if(!v&&window.matchMedia&&matchMedia("(prefers-color-scheme: dark)").matches){v="midnight"}if(v){document.documentElement.setAttribute("data-theme",v)}} }catch(e){}</script>' . "\n";
  echo '<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 64 64\'%3E%3Crect width=\'64\' height=\'64\' rx=\'14\' fill=\'%23245fba\'/%3E%3Cpath d=\'M16 53.3v-18.7M32 53.3V24M49.3 53.3V13.3\' stroke=\'white\' stroke-width=\'7\' stroke-linecap=\'round\' fill=\'none\'/%3E%3C/svg%3E" />' . "\n";
  echo '<meta name="theme-color" content="#142236" />' . "\n";
  echo '<link rel="preconnect" href="https://fonts.googleapis.com" />' . "\n";
  echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />' . "\n";
  echo '<link href="https://fonts.googleapis.com/css2?family=Geist:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet" />' . "\n";
}

/**
 * Cache-busted local asset URL. Appends ?v=<filemtime> so browsers fetch
 * fresh CSS/JS the moment a file changes (localhost stale-cache class of
 * bug). Falls back to the plain path when the file can't be statted.
 * Usage: <link rel="stylesheet" href="<?php echo htmlspecialchars(employer_asset('styles.css'), ENT_QUOTES); ?>" />
 */
function employer_asset(string $relPath): string
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
 * Keeps the same data available to ml/Gemini hooks — visual only.
 */
function employer_score_meter(?float $score, float $max = 5.0, ?float $target = null): string
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
 * Trend badge — replaces ↑↑ / ↔ / ↓↓ text glyphs with an accessible pill.
 */
function employer_trend_badge(string $trend, bool $hasData): string
{
  if (!$hasData) {
    return '<span class="pf-trend pf-trend-none">—<span class="sr-only">No trend data</span></span>';
  }

  $map = [
    'up' => ['label' => 'Improving', 'class' => 'pf-trend-up', 'glyph' => '↗'],
    'down' => ['label' => 'Declining', 'class' => 'pf-trend-down', 'glyph' => '↘'],
    'flat' => ['label' => 'Steady', 'class' => 'pf-trend-flat', 'glyph' => '→'],
  ];
  $info = $map[$trend] ?? $map['flat'];

  return '<span class="pf-trend ' . htmlspecialchars($info['class'], ENT_QUOTES) . '" title="'
    . htmlspecialchars($info['label'], ENT_QUOTES) . '">'
    . '<span aria-hidden="true">' . htmlspecialchars($info['glyph'], ENT_QUOTES) . '</span> '
    . htmlspecialchars($info['label'], ENT_QUOTES) . '</span>';
}

/**
 * Shared page header — one markup pattern for every Employer page.
 *
 * $leadHtml is trusted author HTML for the eyebrow/crumb row (e.g. an
 * .eyebrow span or .ph-crumb nav); $title is escaped plain text;
 * $descHtml is trusted author HTML for the lede paragraph ('' omits it);
 * $actionsHtml is trusted author HTML for .ph-actions, typically captured
 * with ob_start()/ob_get_clean() around the page's verbatim actions block
 * (conditionals render before the call, so output is identical).
 */
function employer_page_header(
  string $titleId,
  string $title,
  string $leadHtml,
  string $descHtml,
  string $actionsHtml,
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

/**
 * Item 5 — reusable shell chrome, decoupled for later cross-module reuse.
 * Each function below renders exactly what employer_render_shell() used to
 * inline; the shell now composes them with zero output change.
 */
function employer_nav_groups(): array
{
  return [
    [
      'label' => 'Manage',
      'items' => [
        ['label' => 'Dashboard', 'href' => 'employer_dashboard.php', 'key' => 'Dashboard', 'icon' => 'home'],
        ['label' => 'Employees', 'href' => 'employees.php', 'key' => 'Employees', 'icon' => 'users'],
        ['label' => 'KPIs', 'href' => 'kpis.php', 'key' => 'KPIs', 'icon' => 'target'],
        ['label' => 'Review plans', 'href' => 'review_recommendations.php', 'key' => 'Review', 'icon' => 'cap'],
        ['label' => 'Reports', 'href' => 'reports.php', 'key' => 'Reports', 'icon' => 'chart'],
      ],
    ],
    [
      'label' => 'Account',
      'items' => [
        ['label' => 'Settings', 'href' => 'settings.php', 'key' => 'Settings', 'icon' => 'gear'],
      ],
    ],
  ];
}

function employer_nav_badge(string $key): ?string
{
  // Session-stashed counts from pages that already load the data; absent
  // values render no badge. Zero reads — shell stays presentation-only.
  if ($key === 'Employees') {
    $n = isset($_SESSION['pf_nav_employees']) ? (int) $_SESSION['pf_nav_employees'] : null;
    return ($n !== null && $n > 0) ? (string) $n : null;
  }
  if ($key === 'Dashboard') {
    $n = isset($_SESSION['pf_nav_deadline']) ? (int) $_SESSION['pf_nav_deadline'] : null;
    return ($n !== null && $n > 0) ? (string) $n : null;
  }
  if ($key === 'Review') {
    $n = isset($_SESSION['pf_nav_reviews']) ? (int) $_SESSION['pf_nav_reviews'] : null;
    return ($n !== null && $n > 0) ? (string) $n : null;
  }
  return null;
}

function employer_render_nav_item(array $item, string $active): void
{
  $badge = employer_nav_badge($item['key']);
  $isHot = $item['key'] === 'Review';
  $isPage = $item['key'] === $active;
  $tip = htmlspecialchars($item['label'] . ($badge !== null ? ' (' . $badge . ')' : ''), ENT_QUOTES);
  ?>
  <a class="nav-item<?php echo $isPage ? ' active' : ''; ?>"
    href="<?php echo htmlspecialchars($item['href'], ENT_QUOTES); ?>"
    data-p="<?php echo htmlspecialchars($item['key'], ENT_QUOTES); ?>"
    data-tip="<?php echo $tip; ?>"
    <?php echo $isPage ? ' aria-current="page"' : ''; ?>>
    <svg class="i" aria-hidden="true"><use href="#i-<?php echo htmlspecialchars($item['icon'], ENT_QUOTES); ?>"/></svg>
    <span class="lbl nav-label"><?php echo htmlspecialchars($item['label'], ENT_QUOTES); ?></span>
    <?php if ($badge !== null): ?>
      <span class="bd<?php echo $isHot ? ' hot' : ''; ?> mono nav-badge"><?php echo htmlspecialchars($badge, ENT_QUOTES); ?></span>
      <span class="sr-only"><?php echo htmlspecialchars($badge, ENT_QUOTES); ?></span>
    <?php endif; ?>
  </a>
  <?php
}

function employer_render_avatar(string $name): void
{
  ?>
  <span class="av" title="<?php echo htmlspecialchars($name, ENT_QUOTES); ?>"
    aria-hidden="true"><?php echo htmlspecialchars(employer_avatar_initials($name), ENT_QUOTES); ?></span>
  <?php
}

function employer_render_shell(string $active): void
{
  if (session_status() === PHP_SESSION_NONE) {
    session_start();
  }
  $profileName = $_SESSION['name'] ?? 'Employer';
  $profileRole = ucwords(str_replace('_', ' ', $_SESSION['role'] ?? 'Employer'));
  $initials = employer_avatar_initials($profileName);
  $navGroups = employer_nav_groups();
  ?>
  <svg width="0" height="0" style="position:absolute" aria-hidden="true">
    <symbol id="i-chart" viewBox="0 0 24 24"><path d="M3 3v16a2 2 0 0 0 2 2h16M18 17V9M13 17V5M8 17v-3"/></symbol>
    <symbol id="i-home" viewBox="0 0 24 24"><path d="M15 21v-8a1 1 0 0 0-1-1h-4a1 1 0 0 0-1 1v8"/><path d="M3 10a2 2 0 0 1 .709-1.528l7-5.999a2 2 0 0 1 2.582 0l7 5.999A2 2 0 0 1 21 10v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></symbol>
    <symbol id="i-users" viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></symbol>
    <symbol id="i-target" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/></symbol>
    <symbol id="i-cap" viewBox="0 0 24 24"><path d="M21.42 10.922a1 1 0 0 0-.019-1.838L12.83 5.18a2 2 0 0 0-1.66 0L2.6 9.08a1 1 0 0 0 0 1.832l8.57 3.908a2 2 0 0 0 1.66 0z"/><path d="M22 10v6M6 12.5V16a6 3 0 0 0 12 0v-3.5"/></symbol>
    <symbol id="i-gear" viewBox="0 0 24 24"><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/></symbol>
    <symbol id="i-search" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></symbol>
    <symbol id="i-sun" viewBox="0 0 24 24"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2m-7.07-2.93 1.41-1.41M17.66 6.34l1.41-1.41M2 12h2m16 0h2M4.93 4.93l1.41 1.41m11.32 11.32 1.41 1.41"/></symbol>
    <symbol id="i-moon" viewBox="0 0 24 24"><path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/></symbol>
    <symbol id="i-out" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/></symbol>
    <symbol id="i-panel" viewBox="0 0 24 24"><rect width="18" height="18" x="3" y="3" rx="2"/><path d="M9 3v18"/></symbol>
    <symbol id="i-plus" viewBox="0 0 24 24"><path d="M5 12h14M12 5v14"/></symbol>
    <symbol id="i-up" viewBox="0 0 24 24"><path d="m5 12 7-7 7 7M12 19V5"/></symbol>
    <symbol id="i-x" viewBox="0 0 24 24"><path d="M18 6 6 18M6 6l12 12"/></symbol>
    <symbol id="i-eye" viewBox="0 0 24 24"><path d="M2.06 12.35a1 1 0 0 1 0-.7 10.75 10.75 0 0 1 19.88 0 1 1 0 0 1 0 .7 10.75 10.75 0 0 1-19.88 0"/><circle cx="12" cy="12" r="3"/></symbol>
    <symbol id="i-eyeoff" viewBox="0 0 24 24"><path d="M10.73 5.08a10.74 10.74 0 0 1 11.21 6.57 1 1 0 0 1 0 .7 10.75 10.75 0 0 1-14.44 2.49M14.08 14.16a3 3 0 0 1-4.24-4.24M17.48 17.5a10.75 10.75 0 0 1-15.42-5.15 1 1 0 0 1 0-.7 10.75 10.75 0 0 1 4.45-5.14M2 2l20 20"/></symbol>
    <symbol id="i-arch" viewBox="0 0 24 24"><rect width="20" height="5" x="2" y="3" rx="1"/><path d="M4 8v11a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8M10 12h4"/></symbol>
    <symbol id="i-dl" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/></symbol>
    <symbol id="i-shield" viewBox="0 0 24 24"><path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/><path d="m9 12 2 2 4-4"/></symbol>
    <symbol id="i-go" viewBox="0 0 24 24"><path d="m9 18 6-6-6-6"/></symbol>
    <symbol id="i-back" viewBox="0 0 24 24"><path d="m12 19-7-7 7-7M19 12H5"/></symbol>
    <symbol id="i-chev" viewBox="0 0 24 24"><path d="m9 18 6-6-6-6"/></symbol>
    <symbol id="i-check" viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5"/></symbol>
    <symbol id="i-copy" viewBox="0 0 24 24"><rect width="14" height="14" x="8" y="8" rx="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/></symbol>
    <symbol id="i-print" viewBox="0 0 24 24"><path d="M6 9V2h12v7M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect width="12" height="8" x="6" y="14"/></symbol>
    <symbol id="i-info" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></symbol>
    <symbol id="i-lock" viewBox="0 0 24 24"><rect width="18" height="11" x="3" y="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></symbol>
    <symbol id="i-mon" viewBox="0 0 24 24"><rect width="20" height="14" x="2" y="3" rx="2"/><path d="M8 21h8M12 17v4"/></symbol>
  </svg>
  <div class="pf-sidebar-backdrop" data-sidebar-backdrop aria-hidden="true"></div>
  <aside class="side sidebar" id="pfSidebar" aria-label="Main">
    <div class="head">
      <div class="brand">
        <span class="tile"><svg class="i" aria-hidden="true"><use href="#i-chart"/></svg></span>
        <span class="nm">Performa</span>
      </div>
      <button class="ibtn pf-sidebar-collapse" id="col" type="button" data-sidebar-collapse aria-label="Collapse sidebar" aria-expanded="true" title="Collapse ( [ )">
        <svg class="i" aria-hidden="true"><use href="#i-panel"/></svg>
      </button>
      <button class="pf-sidebar-close" type="button" data-sidebar-close aria-label="Close navigation">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>

    <div class="sec">Manage</div>
    <nav class="nav" aria-label="Manage">
      <?php foreach ($navGroups[0]['items'] as $item): ?>
        <?php employer_render_nav_item($item, $active); ?>
      <?php endforeach; ?>
    </nav>

    <div class="sec">Account</div>
    <nav class="nav" aria-label="Account">
      <?php foreach ($navGroups[1]['items'] as $item): ?>
        <?php employer_render_nav_item($item, $active); ?>
      <?php endforeach; ?>
      <a href="#" id="srch" class="nav-item pf-palette-trigger" data-palette-open data-tip="Search (Ctrl K)">
        <svg class="i" aria-hidden="true"><use href="#i-search"/></svg>
        <span class="lbl nav-label">Search</span>
        <kbd class="kbd nav-kbd">Ctrl K</kbd>
      </a>
    </nav>

    <div class="foot">
      <span class="av" title="<?php echo htmlspecialchars($profileName, ENT_QUOTES); ?>"><?php echo htmlspecialchars($initials, ENT_QUOTES); ?></span>
      <div class="me">
        <b><?php echo htmlspecialchars($profileName, ENT_QUOTES); ?></b>
        <span><?php echo htmlspecialchars($profileRole, ENT_QUOTES); ?></span>
      </div>
      <button class="ibtn" id="mode" type="button" data-tip="Toggle theme" aria-label="Switch light or dark" title="Light / dark">
        <svg class="i" aria-hidden="true"><use href="#i-sun"/></svg>
      </button>
      <a class="ibtn" id="out" href="../logout.php" data-tip="Sign out" aria-label="Sign out" title="Sign out">
        <svg class="i" aria-hidden="true"><use href="#i-out"/></svg>
      </a>
    </div>
  </aside>

  <div class="cmd" id="cmd" hidden>
    <div class="cp" role="dialog" aria-label="Search" aria-modal="true">
      <input id="ci" placeholder="Search pages, employees, actions" autocomplete="off" aria-label="Search pages, employees, actions">
      <div class="cl" id="cl" role="listbox"></div>
    </div>
  </div>
  <?php
}
