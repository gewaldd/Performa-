// Supervisor Portal Interactions
// Whole file runs inside one IIFE: no names leak to window.
(() => {
  // Mobile sidebar drawer
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

  // Desktop sidebar rail collapse
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
      const isNowCollapsed = !document.body.classList.contains("sidebar-collapsed");
      document.body.classList.toggle("sidebar-collapsed", isNowCollapsed);
      btn.setAttribute("aria-expanded", String(!isNowCollapsed));
      btn.setAttribute("aria-label", isNowCollapsed ? "Expand navigation" : "Collapse navigation");
      btn.title = isNowCollapsed ? "Expand sidebar" : "Collapse sidebar";
      try { window.localStorage.setItem(KEY, isNowCollapsed ? "1" : "0"); } catch (e) {}
    });
  })();

  // Show/hide toggles for password inputs (settings page only).
  (function initPwToggles() {
    document.querySelectorAll("[data-pw-toggle]").forEach((btn) => {
      const input = document.getElementById(btn.getAttribute("data-pw-toggle"));
      if (!input) return;
      btn.addEventListener("click", () => {
        const show = input.type === "password";
        input.type = show ? "text" : "password";
        btn.textContent = show ? "Hide" : "Show";
        btn.setAttribute("aria-pressed", String(show));
      });
    });
  })();

  // Client-side search for tables
  const searchInput = document.getElementById("dashboardSearch") || document.getElementById("employeeSearch");
  const rows = Array.from(document.querySelectorAll("#evaluationRows .table-row, #directoryRows .table-row, .table-wrap .table-row"));
  const noMatches = document.getElementById("noFilterMatches");

  if (searchInput && rows.length > 0) {
    searchInput.addEventListener("input", () => {
      const query = searchInput.value.trim().toLowerCase();
      let visibleCount = 0;
      rows.forEach((row) => {
        const text = (row.dataset.search || row.textContent || "").toLowerCase();
        const visible = !query || text.includes(query);
        row.hidden = !visible;
        row.style.display = visible ? "" : "none";
        if (visible) visibleCount++;
      });
      if (noMatches) {
        noMatches.hidden = visibleCount > 0;
      }
    });
  }
})();