const searchInput = document.getElementById("dashboardSearch");
const rows = Array.from(document.querySelectorAll(".table-row"));
const chips = Array.from(document.querySelectorAll(".filter-chip"));
const exportBtn = document.getElementById("exportEvaluationsBtn");

let activeFilter = "all";

function applyFilters() {
  const query = (searchInput ? searchInput.value : '').trim().toLowerCase();

  rows.forEach((row) => {
    const rowText = row.dataset.search || "";
    const matchesSearch = !query || rowText.includes(query);
    const matchesFilter = activeFilter === "all" || row.dataset.filter === activeFilter;
    row.hidden = !(matchesSearch && matchesFilter);
  });
}

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