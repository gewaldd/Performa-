// Scoped: shares pages with script.js, so no top-level name may leak to
// window (a `searchInput` collision here once killed this entire script).
(() => {
const searchInput = document.getElementById("employeeSearch");
const deptFilter = document.getElementById("deptFilter");
const statusFilter = document.getElementById("statusFilter");
const perfFilter = document.getElementById("perfFilter");
const typeFilter = document.getElementById("typeFilter");
const resetBtn = document.getElementById("resetFiltersBtn");
const sortSelect = document.getElementById("sortDirectory");
const directoryRows = document.getElementById("directoryRows");
const exportBtn = document.getElementById("exportDirectoryBtn");
const prevBtn = document.getElementById("prevPageBtn");
const nextBtn = document.getElementById("nextPageBtn");
const pageIndicator = document.getElementById("pageIndicator");
const paginationSummary = document.getElementById("paginationSummary");
const pageNumbers = document.getElementById("pageNumbers");
const directoryCount = document.getElementById("directoryCount");
const allRows = Array.from(document.querySelectorAll(".directory-row"));
// Home order survives re-appends: default sort restores it via index map.
const homeOrder = new Map(allRows.map((row, i) => [row, i]));

const PAGE_SIZE = 8;
let currentPage = 1;
let visibleRows = allRows;

// Firestore values are case/whitespace-fragile; normalize both sides so one
// stray space or casing difference can't silently break a filter dimension.
const normFilterValue = (v) => (v ?? "").trim().toLowerCase();

function matchesFilters(row) {
  const query = normFilterValue(searchInput?.value);
  const dept = normFilterValue(deptFilter?.value);
  const status = normFilterValue(statusFilter?.value);
  const perf = normFilterValue(perfFilter?.value);
  const type = normFilterValue(typeFilter?.value);

  const matchesSearch = !query || normFilterValue(row.dataset.search).includes(query);
  const matchesDept = !dept || normFilterValue(row.dataset.dept) === dept;
  const matchesStatus = !status || normFilterValue(row.dataset.status) === status;
  const matchesPerf = !perf || normFilterValue(row.dataset.perf) === perf;
  const matchesType = !type || normFilterValue(row.dataset.type) === type;
  return matchesSearch && matchesDept && matchesStatus && matchesPerf && matchesType;
}

const noResultsBox = document.getElementById("noFilterResults");
const clearFiltersBtn = document.getElementById("clearFiltersBtn");

// Numbered page buttons: a window of up to 5 pages around the current one,
// first/last always reachable, with text ellipses where pages are skipped.
// Rebuilt on every render so the active state can never go stale.
function renderPageNumbers(totalPages) {
  if (!pageNumbers) return;
  pageNumbers.innerHTML = "";

  // A single page needs no number chrome; the :empty guard hides the row.
  if (totalPages < 2) return;

  const addButton = (page) => {
    const button = document.createElement("button");
    button.type = "button";
    button.className = "page-num";
    button.textContent = String(page);
    if (page === currentPage) {
      button.classList.add("active");
      button.setAttribute("aria-current", "page");
    }
    button.addEventListener("click", () => { currentPage = page; render(); });
    pageNumbers.append(button);
  };

  const addGap = () => {
    const gap = document.createElement("span");
    gap.className = "page-gap";
    gap.textContent = "...";
    gap.setAttribute("aria-hidden", "true");
    pageNumbers.append(gap);
  };

  const windowSize = 5;
  let start = Math.max(1, currentPage - Math.floor(windowSize / 2));
  let end = Math.min(totalPages, start + windowSize - 1);
  start = Math.max(1, end - windowSize + 1);

  if (start > 1) {
    addButton(1);
    if (start > 2) addGap();
  }
  for (let page = start; page <= end; page += 1) addButton(page);
  if (end < totalPages) {
    if (end < totalPages - 1) addGap();
    addButton(totalPages);
  }
}

function render() {
  visibleRows = allRows.filter(matchesFilters);
  applySort(visibleRows);
  const totalPages = Math.max(1, Math.ceil(visibleRows.length / PAGE_SIZE));
  if (currentPage > totalPages) currentPage = totalPages;

  // Belt and suspenders: hidden carries semantics (backed by the CSS
  // [hidden] guard) AND style.display forces the paint. Either mechanism
  // alone suffices; together they survive any future CSS regression.
  // Mirrors the working dashboard pattern in script.js.
  allRows.forEach((row) => { row.hidden = true; row.style.display = "none"; });

  const start = (currentPage - 1) * PAGE_SIZE;
  const pageRows = visibleRows.slice(start, start + PAGE_SIZE);
  pageRows.forEach((row) => { row.hidden = false; row.style.display = ""; });

  // Distinct from the genuine empty-directory state (which renders instead
  // of #directoryRows entirely): only when rows exist but none match.
  if (noResultsBox && allRows.length > 0) {
    noResultsBox.hidden = visibleRows.length !== 0;
  }

  if (paginationSummary) {
    paginationSummary.innerHTML = `Showing <strong>${pageRows.length}</strong> of <strong>${visibleRows.length}</strong> employees`;
  }
  if (pageIndicator) {
    pageIndicator.textContent = `Page ${currentPage} of ${totalPages}`;
  }
  if (prevBtn) prevBtn.disabled = currentPage <= 1;
  if (nextBtn) nextBtn.disabled = currentPage >= totalPages;

  renderPageNumbers(totalPages);

  if (directoryCount) {
    directoryCount.textContent = `${visibleRows.length} employee${visibleRows.length === 1 ? "" : "s"}`;
  }
}

[searchInput, deptFilter, statusFilter, perfFilter, typeFilter].forEach((el) => {
  if (!el) return;
  el.addEventListener("input", () => { currentPage = 1; render(); });
  el.addEventListener("change", () => { currentPage = 1; render(); });
});

// Client-side reorder of the loaded rows (no new reads). Re-appends in
// sorted order so pagination slices the sorted list. Missing values sort
// last so legacy cached rows (no data-days-left) never jump to the top.
function compareDaysLeft(a, b) {
  const da = a.dataset.daysLeft === "" || a.dataset.daysLeft === undefined ? null : Number(a.dataset.daysLeft);
  const db = b.dataset.daysLeft === "" || b.dataset.daysLeft === undefined ? null : Number(b.dataset.daysLeft);
  if (da === null && db === null) return 0;
  if (da === null) return 1;
  if (db === null) return -1;
  return da - db;
}

function applySort(rows) {
  const mode = sortSelect ? sortSelect.value : "default";
  if (mode === "name") {
    rows.sort((a, b) => (a.dataset.name || "").localeCompare(b.dataset.name || ""));
  } else if (mode === "days-left") {
    rows.sort(compareDaysLeft);
  } else {
    rows.sort((a, b) => (homeOrder.get(a) ?? 0) - (homeOrder.get(b) ?? 0));
  }
  if (directoryRows) {
    rows.forEach((row) => directoryRows.append(row));
  }
}

if (sortSelect) {
  sortSelect.addEventListener("change", () => { currentPage = 1; render(); });
}

if (clearFiltersBtn) {
  // Zero-result state reuses the exact Reset path — single source of truth.
  clearFiltersBtn.addEventListener("click", () => { if (resetBtn) resetBtn.click(); });
}

if (resetBtn) {
  resetBtn.addEventListener("click", () => {
    [searchInput, deptFilter, statusFilter, perfFilter, typeFilter].forEach((el) => {
      if (!el) return;
      el.value = "";
      // Native selects: setting .value never fires change, so dispatch it
      // for the filter listeners below.
      el.dispatchEvent(new Event("change", { bubbles: true }));
    });
    if (sortSelect) sortSelect.value = "default";
    currentPage = 1;
    render();
  });
}

if (prevBtn) {
  prevBtn.addEventListener("click", () => {
    if (currentPage > 1) { currentPage -= 1; render(); }
  });
}
if (nextBtn) {
  nextBtn.addEventListener("click", () => {
    const totalPages = Math.max(1, Math.ceil(visibleRows.length / PAGE_SIZE));
    if (currentPage < totalPages) { currentPage += 1; render(); }
  });
}

if (exportBtn) {
  // Columns come from the row data attributes (plus the performance pill
  // text), never from cell positions: the directory has been re-columned
  // before, and positional reads silently shift every exported value.
  exportBtn.addEventListener("click", () => {
    const lines = [["Name", "Email", "Role", "Department", "Type", "Account Status", "Performance Status", "Days Left", "Score"].join(",")];
    visibleRows.forEach((row) => {
      const name = row.querySelector(".employee-name")?.textContent.trim() || "";
      const email = row.querySelector(".employee-email")?.textContent.trim() || "";
      const perfStatus = row.querySelector('[data-label="Performance"]')?.textContent.trim() || "";
      const csvCells = [
        name,
        email,
        row.dataset.role ?? "",
        row.dataset.dept ?? "",
        row.dataset.type ?? "",
        row.dataset.status ?? "",
        perfStatus,
        row.dataset.daysLeft ?? "",
        row.dataset.score ?? "",
      ].map((v) => `"${v.replace(/"/g, '""')}"`);
      lines.push(csvCells.join(","));
    });
    const blob = new Blob([lines.join("\n")], { type: "text/csv;charset=utf-8;" });
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = "employee_directory.csv";
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
  });
}

// Nav active-state is server-rendered (aria-current="page" via the shared
// shell); the old client-side .nav-item click-toggle fought it (stale flash
// before navigation) and duplicated script.js's own handler — removed.

if (allRows.length > 0) render();
})();