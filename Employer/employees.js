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

function matchesFilters(row) {
  const query = (searchInput?.value || "").trim().toLowerCase();
  const dept = deptFilter?.value || "";
  const status = statusFilter?.value || "";
  const type = typeFilter?.value || "";

  const matchesSearch = !query || (row.dataset.search || "").includes(query);
  const matchesDept = !dept || row.dataset.dept === dept;
  const matchesStatus = !status || row.dataset.status === status;
  const matchesType = !type || row.dataset.type === type;
  return matchesSearch && matchesDept && matchesStatus && matchesType;
}

function render() {
  visibleRows = allRows.filter(matchesFilters);
  const totalPages = Math.max(1, Math.ceil(visibleRows.length / PAGE_SIZE));
  if (currentPage > totalPages) currentPage = totalPages;

  allRows.forEach((row) => { row.hidden = true; });

  const start = (currentPage - 1) * PAGE_SIZE;
  const pageRows = visibleRows.slice(start, start + PAGE_SIZE);
  pageRows.forEach((row) => { row.hidden = false; });

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

if (resetBtn) {
  resetBtn.addEventListener("click", () => {
    [searchInput, deptFilter, statusFilter, typeFilter].forEach((el) => {
      if (!el) return;
      el.value = "";
      // Setting .value programmatically does not fire a native change
      // event, so dropdowns.js (which listens for "change" to refresh the
      // visible trigger label) would otherwise leave the old label showing.
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