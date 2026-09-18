// Scoped: shares pages with script.js, so no top-level name may leak to
// window (a `searchInput` collision here once killed this entire script).
(() => {
const searchInput = document.getElementById("employeeSearch");
const deptFilter = document.getElementById("deptFilter");
const statusFilter = document.getElementById("statusFilter");
const typeFilter = document.getElementById("typeFilter");
const resetBtn = document.getElementById("resetFiltersBtn");
const exportBtn = document.getElementById("exportDirectoryBtn");
const prevBtn = document.getElementById("prevPageBtn");
const nextBtn = document.getElementById("nextPageBtn");
const pageIndicator = document.getElementById("pageIndicator");
const paginationSummary = document.getElementById("paginationSummary");
const allRows = Array.from(document.querySelectorAll(".directory-row"));

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
  const type = normFilterValue(typeFilter?.value);

  const matchesSearch = !query || normFilterValue(row.dataset.search).includes(query);
  const matchesDept = !dept || normFilterValue(row.dataset.dept) === dept;
  const matchesStatus = !status || normFilterValue(row.dataset.status) === status;
  const matchesType = !type || normFilterValue(row.dataset.type) === type;
  return matchesSearch && matchesDept && matchesStatus && matchesType;
}

const noResultsBox = document.getElementById("noFilterResults");
const clearFiltersBtn = document.getElementById("clearFiltersBtn");

function render() {
  visibleRows = allRows.filter(matchesFilters);
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
}

[searchInput, deptFilter, statusFilter, typeFilter].forEach((el) => {
  if (!el) return;
  el.addEventListener("input", () => { currentPage = 1; render(); });
  el.addEventListener("change", () => { currentPage = 1; render(); });
});

if (clearFiltersBtn) {
  // Zero-result state reuses the exact Reset path — single source of truth.
  clearFiltersBtn.addEventListener("click", () => { if (resetBtn) resetBtn.click(); });
}

if (resetBtn) {
  resetBtn.addEventListener("click", () => {
    [searchInput, deptFilter, statusFilter, typeFilter].forEach((el) => {
      if (!el) return;
      el.value = "";
      // Native selects: setting .value never fires change, so dispatch it
      // for the filter listeners below.
      el.dispatchEvent(new Event("change", { bubbles: true }));
    });
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
  exportBtn.addEventListener("click", () => {
    const lines = [["Name", "Email", "Role", "Department", "Type", "Status"].join(",")];
    visibleRows.forEach((row) => {
      const name = row.querySelector(".employee-name")?.textContent.trim() || "";
      const email = row.querySelector(".employee-email")?.textContent.trim() || "";
      const cells = row.children;
      const role = cells[1]?.textContent.trim() || "";
      const dept = cells[2]?.textContent.trim() || "";
      const type = cells[3]?.textContent.trim() || "";
      const status = cells[4]?.textContent.trim() || "";
      const row_ = [name, email, role, dept, type, status].map((v) => `"${v.replace(/"/g, '""')}"`);
      lines.push(row_.join(","));
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

document.querySelectorAll(".nav-item").forEach((item) => {
  item.addEventListener("click", (event) => {
    document.querySelectorAll(".nav-item").forEach((navItem) => navItem.classList.remove("active"));
    event.currentTarget.classList.add("active");
  });
});

if (allRows.length > 0) render();
})();