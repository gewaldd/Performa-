// Whole file runs inside one IIFE: top-level const/function names here must
// NEVER leak to window, or any later script declaring the same name
// (e.g. employees.js `searchInput`) dies with "already been declared" and
// none of that script runs. That exact collision broke Employees filtering.
(() => {
const searchInput = document.getElementById("dashboardSearch");
const sortSelect = document.getElementById("dashboardSort");
const rowsContainer = document.getElementById("evaluationRows");
const rows = Array.from(document.querySelectorAll("#evaluationRows .table-data-row, #evaluationRows .table-row"));
const chips = Array.from(document.querySelectorAll(".tab-chip, .filter-chip"));
const exportBtn = document.getElementById("exportEvaluationsBtn");

let activeFilter = "all";
const noMatches = document.getElementById("noFilterMatches");
const clearFiltersBtn = document.getElementById("clearDashboardFilters");

function getSortKey(row, sortMode) {
  if (sortMode === "days-left") {
    const d = parseInt(row.dataset.daysLeft || "0", 10);
    return isNaN(d) ? 9999 : d;
  }
  if (sortMode === "score-asc") {
    const s = parseFloat(row.dataset.score || "-1");
    // Place unrated (-1) at the end of the list
    return isNaN(s) || s < 0 ? 9999 : s;
  }
  // Default: "attention"
  // Needs review rows first (0), then due-soon (1), then other (2), then unrated (3)
  const isNeedsReview = (row.dataset.filter || "").toLowerCase() === "needs-review";
  const isDueSoon = row.dataset.dueSoon === "1";
  const score = parseFloat(row.dataset.score || "-1");
  if (isNeedsReview) {
    return isNaN(score) || score < 0 ? 0.99 : (score / 10); // lower score = higher attention
  }
  if (isDueSoon) return 1.0;
  if (score >= 0) return 2.0 + (score / 10);
  return 3.0;
}

function applyFiltersAndSort() {
  const query = (searchInput ? searchInput.value : '').trim().toLowerCase();
  const sortMode = (sortSelect ? sortSelect.value : 'attention');

  let visibleCount = 0;
  rows.forEach((row) => {
    const rowText = (row.dataset.search || "").toLowerCase();
    const matchesSearch = !query || rowText.includes(query);
    const rowFilter = (row.dataset.filter || "").trim().toLowerCase();
    const matchesFilter =
      activeFilter === "all" ||
      rowFilter === activeFilter ||
      (activeFilter === "due-soon" && row.dataset.dueSoon === "1");
    const visible = matchesSearch && matchesFilter;
    row.hidden = !visible;
    row.style.display = visible ? "" : "none";
    if (visible) visibleCount++;
  });

  // Re-order rows in DOM according to sortMode
  if (rowsContainer && rows.length > 1) {
    const sorted = [...rows].sort((a, b) => {
      const keyA = getSortKey(a, sortMode);
      const keyB = getSortKey(b, sortMode);
      return keyA - keyB;
    });
    sorted.forEach((r) => rowsContainer.appendChild(r));
  }

  // Empty state only when rows exist but the combination hides them all.
  if (noMatches) {
    noMatches.hidden = !(rows.length > 0 && visibleCount === 0);
  }
}

if (searchInput) {
  searchInput.addEventListener("input", applyFiltersAndSort);
}

if (sortSelect) {
  sortSelect.addEventListener("change", applyFiltersAndSort);
}

chips.forEach((chip) => {
  chip.addEventListener("click", () => {
    activeFilter = chip.dataset.filter || "all";
    chips.forEach((item) => {
      const isActive = (item === chip);
      item.classList.toggle("active", isActive);
      item.setAttribute("aria-selected", String(isActive));
    });
    applyFiltersAndSort();
  });
});

if (clearFiltersBtn) {
  clearFiltersBtn.addEventListener("click", () => {
    activeFilter = "all";
    chips.forEach((item) => {
      const isAll = (item.dataset.filter === "all");
      item.classList.toggle("active", isAll);
      item.setAttribute("aria-selected", String(isAll));
    });
    if (searchInput) searchInput.value = "";
    if (sortSelect) sortSelect.value = "attention";
    applyFiltersAndSort();
    if (searchInput) searchInput.focus({ preventScroll: true });
  });
}

// Mobile sidebar - sole owner of the drawer toggle (custom dropdowns removed; native selects).
(function initPfSidebar() {
  if (window.__pfSidebarBound) return;
  window.__pfSidebarBound = true;
  let lastToggle = null;
  const close = () => {
    if (!document.body.classList.contains("sidebar-open")) return;
    document.body.classList.remove("sidebar-open");
    document.querySelectorAll("[data-sidebar-toggle]").forEach((t) => t.setAttribute("aria-expanded", "false"));
    if (lastToggle && document.contains(lastToggle)) lastToggle.focus({ preventScroll: true });
  };
  const toggle = () => {
    const willOpen = !document.body.classList.contains("sidebar-open");
    document.body.classList.toggle("sidebar-open", willOpen);
    document.querySelectorAll("[data-sidebar-toggle]").forEach((t) => t.setAttribute("aria-expanded", String(willOpen)));
    if (!willOpen && lastToggle && document.contains(lastToggle)) lastToggle.focus({ preventScroll: true });
  };
  document.addEventListener("click", (e) => {
    const openBtn = e.target.closest("[data-sidebar-toggle]");
    if (openBtn) { lastToggle = openBtn; toggle(); return; }
    if (e.target.closest("[data-sidebar-close]") || e.target.closest("[data-sidebar-backdrop]")) { close(); return; }
    if (document.body.classList.contains("sidebar-open")) {
      const sidebar = document.getElementById("pfSidebar");
      if (sidebar && !e.target.closest("#pfSidebar") && !e.target.closest("[data-sidebar-toggle]")) close();
    }
  });
  document.addEventListener("keydown", (e) => { if (e.key === "Escape") close(); });
})();

// Desktop sidebar rail - sole owner of body.sidebar-collapsed. Scoped to
// >=901px via matchMedia so the mobile drawer keeps working untouched.
// Persisted in localStorage; private-mode failures fall back to expanded.
(function initPfCollapse() {
  const KEY = "pf-sidebar-collapsed";
  const mq = window.matchMedia("(min-width: 1101px)");
  const apply = (collapsed) => {
    document.body.classList.toggle("sidebar-collapsed", collapsed && mq.matches);
    document.querySelectorAll("[data-sidebar-collapse]").forEach((b) => {
      b.setAttribute("aria-expanded", String(!collapsed));
      b.setAttribute("aria-label", collapsed ? "Expand navigation" : "Collapse navigation");
      b.title = collapsed ? "Expand sidebar" : "Collapse sidebar";
    });
  };
  let initial = false;
  try { initial = window.localStorage.getItem(KEY) === "1"; } catch (e) { /* expanded */ }
  apply(initial);
  if (typeof mq.addEventListener === "function") {
    mq.addEventListener("change", () => {
      let c = false;
      try { c = window.localStorage.getItem(KEY) === "1"; } catch (e) { /* expanded */ }
      apply(c);
    });
  }
  document.addEventListener("click", (e) => {
    const btn = e.target.closest("[data-sidebar-collapse]");
    if (!btn) return;
    const next = !document.body.classList.contains("sidebar-collapsed");
    try { window.localStorage.setItem(KEY, next ? "1" : "0"); } catch (err) { /* expanded next load */ }
    apply(next);
  });
})();

// Command palette - Ctrl/Cmd+K or the sidebar Search row. Dialog DOM is
// built here (no-JS means no palette; pages keep working). Targets come
// from window.__pfIndex, which each page seeds from data it already loaded
// (zero new reads), plus the five static module destinations.
(function initPfPalette() {
  if (window.__pfPaletteBound) return;
  window.__pfPaletteBound = true;
  const STATIC_TARGETS = [
    { label: "Dashboard", sub: "Go to page", href: "employer_dashboard.php" },
    { label: "Employees", sub: "Go to page", href: "employees.php" },
    { label: "Add Employee", sub: "Go to page", href: "add_employee.php" },
    { label: "KPIs", sub: "Go to page", href: "kpis.php" },
    { label: "Reports", sub: "Go to page", href: "reports.php" },
    { label: "Settings", sub: "Go to page", href: "settings.php" },
  ];
  let lastTrigger = null;
  const targets = () => STATIC_TARGETS.concat(
    Array.isArray(window.__pfIndex) ? window.__pfIndex : []
  );
  const close = () => {
    const bd = document.querySelector(".pf-palette-backdrop");
    if (bd) bd.remove();
    document.removeEventListener("keydown", onKey, true);
    if (lastTrigger && document.contains(lastTrigger)) lastTrigger.focus({ preventScroll: true });
    lastTrigger = null;
  };
  const go = (href) => { window.location.href = href; };
  const render = (backdrop, list, items, activeIdx) => {
    list.innerHTML = "";
    if (!items.length) {
      const li = document.createElement("li");
      li.className = "pf-palette-empty";
      li.textContent = "No matches. Try an employee or report name.";
      list.append(li);
      return;
    }
    items.slice(0, 8).forEach((item, i) => {
      const li = document.createElement("li");
      li.className = "pf-palette-item";
      li.setAttribute("role", "option");
      li.id = "pf-pal-opt-" + i;
      li.setAttribute("aria-selected", String(i === activeIdx));
      const name = document.createElement("span");
      name.textContent = item.label;
      const sub = document.createElement("small");
      sub.textContent = item.sub || "";
      li.append(name, sub);
      li.addEventListener("click", () => go(item.href));
      list.append(li);
    });
  };
  const onKey = (e) => {
    const backdrop = document.querySelector(".pf-palette-backdrop");
    if (!backdrop) return;
    const input = backdrop.querySelector(".pf-palette-input");
    const items = backdrop._pfItems || [];
    if (e.key === "Escape") { e.preventDefault(); close(); return; }
    if (e.key === "ArrowDown" || e.key === "ArrowUp") {
      e.preventDefault();
      let idx = backdrop._pfActive || 0;
      idx = e.key === "ArrowDown" ? idx + 1 : idx - 1;
      if (idx < 0) idx = Math.min(items.length, 8) - 1;
      if (idx >= Math.min(items.length, 8)) idx = 0;
      backdrop._pfActive = idx;
      render(backdrop, backdrop.querySelector(".pf-palette-list"), items, idx);
      const sel = backdrop.querySelector('[aria-selected="true"]');
      if (input && sel) input.setAttribute("aria-activedescendant", sel.id);
      return;
    }
    if (e.key === "Enter") {
      const idx = backdrop._pfActive || 0;
      if (items[idx]) { e.preventDefault(); go(items[idx].href); }
    }
  };
  const open = (trigger) => {
    if (document.querySelector(".pf-palette-backdrop")) return;
    lastTrigger = trigger || null;
    const backdrop = document.createElement("div");
    backdrop.className = "pf-palette-backdrop";
    const dialog = document.createElement("div");
    dialog.className = "pf-palette";
    dialog.setAttribute("role", "dialog");
    dialog.setAttribute("aria-modal", "true");
    dialog.setAttribute("aria-label", "Search employees and reports");
    const input = document.createElement("input");
    input.className = "pf-palette-input";
    input.type = "search";
    input.placeholder = "Search employees, reports, pages...";
    input.setAttribute("aria-label", "Search employees, reports, pages");
    input.setAttribute("role", "combobox");
    input.setAttribute("aria-expanded", "true");
    input.setAttribute("aria-controls", "pf-pal-list");
    input.setAttribute("autocomplete", "off");
    const list = document.createElement("ul");
    list.className = "pf-palette-list";
    list.id = "pf-pal-list";
    list.setAttribute("role", "listbox");
    dialog.append(input, list);
    backdrop.append(dialog);
    document.body.append(backdrop);
    const update = () => {
      const q = input.value.trim().toLowerCase();
      const all = targets();
      const items = !q ? all.slice(0, 8) : all.filter((t) =>
        (t.label + " " + (t.sub || "")).toLowerCase().includes(q)
      );
      backdrop._pfItems = items;
      backdrop._pfActive = 0;
      render(backdrop, list, items, 0);
      input.removeAttribute("aria-activedescendant");
    };
    input.addEventListener("input", update);
    backdrop.addEventListener("click", (ev) => { if (ev.target === backdrop) close(); });
    document.addEventListener("keydown", onKey, true);
    update();
    input.focus();
  };
  document.addEventListener("click", (e) => {
    const t = e.target.closest("[data-palette-open]");
    if (t) open(t);
  });
  document.addEventListener("keydown", (e) => {
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === "k") {
      e.preventDefault();
      open(null);
    }
  });
})();

// Unified toast for feedback and actions (e.g. ?dismissed=... with Undo).
(function initPfToast() {
  let message = null;
  let undoUid = null;
  try {
    const params = new URLSearchParams(window.location.search);
    if (params.has("assigned")) {
      message = "Training course assigned.";
    } else if (params.has("dismissed")) {
      message = "Removed from review queue.";
      undoUid = params.get("dismissed");
    } else if (params.has("restored")) {
      message = "Review queue item restored.";
    }
  } catch (e) { return; }
  if (!message) return;

  const toast = document.createElement("div");
  toast.className = "pf-toast";
  toast.setAttribute("role", "status");
  const text = document.createElement("span");
  text.textContent = message;
  toast.append(text);

  if (undoUid) {
    const undoBtn = document.createElement("button");
    undoBtn.type = "button";
    undoBtn.className = "pf-toast-undo";
    undoBtn.textContent = "Undo";
    undoBtn.style.marginLeft = "12px";
    undoBtn.style.fontWeight = "600";
    undoBtn.style.color = "var(--brand-accent, #1d63ed)";
    undoBtn.style.background = "none";
    undoBtn.style.border = "none";
    undoBtn.style.cursor = "pointer";
    undoBtn.style.textDecoration = "underline";

    undoBtn.addEventListener("click", () => {
      const csrfMeta = document.querySelector('meta[name="csrf-token"]');
      const csrfToken = csrfMeta ? csrfMeta.content : '';
      const form = document.createElement("form");
      form.method = "post";
      form.action = "employer_dashboard.php";
      
      const actInput = document.createElement("input");
      actInput.type = "hidden";
      actInput.name = "action";
      actInput.value = "queue_restore";
      
      const uidInput = document.createElement("input");
      uidInput.type = "hidden";
      uidInput.name = "uid";
      uidInput.value = undoUid;

      const csrfInput = document.createElement("input");
      csrfInput.type = "hidden";
      csrfInput.name = "csrf_token";
      csrfInput.value = csrfToken;

      form.append(actInput, uidInput, csrfInput);
      document.body.append(form);
      form.submit();
    });
    toast.append(undoBtn);
  }

  const closeBtn = document.createElement("button");
  closeBtn.type = "button";
  closeBtn.className = "pf-toast-close";
  closeBtn.setAttribute("aria-label", "Dismiss notification");
  closeBtn.textContent = "x";
  closeBtn.addEventListener("click", () => toast.remove());
  toast.append(closeBtn);
  document.body.append(toast);
  window.setTimeout(() => { if (toast.isConnected) toast.remove(); }, 6000);
})();

document.querySelectorAll(".nav-item").forEach((item) => {
  item.addEventListener("click", (event) => {
    document.querySelectorAll(".nav-item").forEach((navItem) => navItem.classList.remove("active"));
    event.currentTarget.classList.add("active");
  });
});

// Row click-through to profiles (links, buttons and form controls inside
// the row keep their own behavior).
if (rowsContainer) {
  rowsContainer.addEventListener("click", (event) => {
    if (event.target.closest("a, button, input, select, textarea, form")) return;
    const row = event.target.closest(".table-data-row, .table-row");
    const uid = row && row.dataset.uid;
    if (uid) window.location.href = "employee_view.php?uid=" + encodeURIComponent(uid);
  });
}

if (exportBtn) {
  exportBtn.addEventListener("click", () => {
    const visibleRows = rows.filter((row) => !row.hidden);
    const lines = [["Name", "Day", "Days Left", "Score", "Status"].join(",")];
    visibleRows.forEach((row) => {
      const name = (row.querySelector(".employee-name-single") || row.querySelector(".employee-name"))?.textContent.trim() || "";
      const day = row.querySelector(".timeline-day")?.textContent.trim() || "";
      const daysLeft = row.querySelector(".timeline-left")?.textContent.trim() || "";
      const score = (row.querySelector(".score-num") || row.querySelector(".pf-score-value"))?.textContent.trim() || "Unrated";
      const status = row.querySelector(".status-pill")?.textContent.trim() || "";
      const cells = [name, day, daysLeft, score, status].map((v) => `"${v.replace(/"/g, '""')}"`);
      lines.push(cells.join(","));
    });
    const blob = new Blob([lines.join("\n")], { type: "text/csv;charset=utf-8;" });
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = "probationary_employees.csv";
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
  });
}

if (rows.length > 0) applyFiltersAndSort();

document.querySelectorAll("form[data-confirm]").forEach((form) => {
  form.addEventListener("submit", (event) => {
    if (form.dataset.confirmed === "true") return;

    event.preventDefault();
    const submitButton = form.querySelector("[type='submit']");
    const backdrop = document.createElement("div");
    const dialog = document.createElement("div");
    const heading = document.createElement("h2");
    const message = document.createElement("p");
    const actions = document.createElement("div");
    const cancelButton = document.createElement("button");
    const confirmButton = document.createElement("button");

    // Destructive forms opt in via data-confirm-danger: the dialog and its
    // confirm button turn danger-red and the heading names the action.
    const isDanger = form.hasAttribute("data-confirm-danger");

    backdrop.className = "modal-backdrop";
    dialog.className = "confirm-dialog" + (isDanger ? " confirm-danger" : "");
    heading.textContent = isDanger ? "Are you sure?" : "Please confirm";
    message.textContent = form.dataset.confirm;
    actions.className = "confirm-dialog-actions";
    cancelButton.type = "button";
    cancelButton.className = "ghost-button";
    cancelButton.textContent = "Cancel";
    confirmButton.type = "button";
    confirmButton.className = isDanger ? "btn-danger" : "ghost-button";
    confirmButton.textContent = isDanger ? "Deactivate" : "Continue";

    actions.append(cancelButton, confirmButton);
    dialog.append(heading, message, actions);
    backdrop.append(dialog);
    document.body.append(backdrop);

    const closeDialog = () => {
      backdrop.remove();
      submitButton?.focus();
      document.removeEventListener("keydown", handleKeydown);
    };
    const handleKeydown = (keyEvent) => {
      if (keyEvent.key === "Escape") closeDialog();
    };

    cancelButton.addEventListener("click", closeDialog);
    confirmButton.addEventListener("click", () => {
      form.dataset.confirmed = "true";
      closeDialog();
      HTMLFormElement.prototype.submit.call(form);
    });
    document.addEventListener("keydown", handleKeydown);
    confirmButton.focus();
  });
});
})();