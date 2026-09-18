// Whole file runs inside one IIFE: top-level const/function names here must
// NEVER leak to window, or any later script declaring the same name
// (e.g. employees.js `searchInput`) dies with "already been declared" and
// none of that script runs. That exact collision broke Employees filtering.
(() => {
const searchInput = document.getElementById("dashboardSearch");
const rows = Array.from(document.querySelectorAll("#evaluationRows .table-row"));
const chips = Array.from(document.querySelectorAll(".filter-chip"));
const exportBtn = document.getElementById("exportEvaluationsBtn");

let activeFilter = "all";

function applyFilters() {
  const query = (searchInput ? searchInput.value : '').trim().toLowerCase();

  rows.forEach((row) => {
    const rowText = row.dataset.search || "";
    const matchesSearch = !query || rowText.includes(query);
    const rowFilter = (row.dataset.filter || "").trim().toLowerCase();
    const matchesFilter = activeFilter === "all" || rowFilter === activeFilter;
    row.hidden = !(matchesSearch && matchesFilter);
    row.style.display = matchesSearch && matchesFilter ? "" : "none";
  });
}

// Mobile sidebar — sole owner of the drawer toggle (custom dropdowns removed; native selects).
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

document.querySelectorAll(".nav-item").forEach((item) => {
  item.addEventListener("click", (event) => {
    document.querySelectorAll(".nav-item").forEach((navItem) => navItem.classList.remove("active"));
    event.currentTarget.classList.add("active");
  });
});

if (searchInput) {
  searchInput.addEventListener("input", applyFilters);
}

chips.forEach((chip) => {
  chip.addEventListener("click", () => {
    activeFilter = chip.dataset.filter;
    chips.forEach((item) => item.classList.toggle("active", item === chip));
    applyFilters();
  });
});

if (exportBtn) {
  exportBtn.addEventListener("click", () => {
    const visibleRows = rows.filter((row) => !row.hidden);
    const lines = [["Name", "Role", "Day", "Days Left", "Score", "Status"].join(",")];
    visibleRows.forEach((row) => {
      const name = row.querySelector(".employee-name")?.textContent.trim() || "";
      const role = row.querySelector(".employee-role")?.textContent.trim() || "";
      const day = row.querySelector(".timeline-day")?.textContent.trim() || "";
      const daysLeft = row.querySelector(".timeline-left")?.textContent.trim() || "";
      const score = row.querySelector(".score-value")?.textContent.trim() || "";
      const status = row.querySelector(".status-pill")?.textContent.trim() || "";
      const cells = [name, role, day, daysLeft, score, status].map((v) => `"${v.replace(/"/g, '""')}"`);
      lines.push(cells.join(","));
    });
    const blob = new Blob([lines.join("\n")], { type: "text/csv;charset=utf-8;" });
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = "active_evaluations.csv";
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
  });
}

if (rows.length > 0) applyFilters();

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
    confirmButton.className = isDanger ? "btn-danger" : "btn-cancel";
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