const kpiSearchInput = document.getElementById("kpiSearch");
const kpiRows = Array.from(document.querySelectorAll(".kpi-row"));
const exportKpiBtn = document.getElementById("exportKpiBtn");

if (kpiSearchInput) {
  kpiSearchInput.addEventListener("input", () => {
    const query = kpiSearchInput.value.trim().toLowerCase();
    kpiRows.forEach((row) => {
      const text = row.dataset.search || "";
      row.style.display = !query || text.includes(query) ? "" : "none";
    });
  });
}

if (exportKpiBtn) {
  exportKpiBtn.addEventListener("click", () => {
    const visible = kpiRows.filter((row) => row.style.display !== "none");
    const lines = [["KPI Name", "Target", "Current Score", "Status"].join(",")];
    visible.forEach((row) => {
      const name = row.querySelector(".kpi-name")?.textContent.trim() || "";
      const target = row.querySelector(".kpi-target")?.textContent.trim() || "";
      const current = row.querySelector(".kpi-current strong")?.textContent.trim() || "";
      const status = row.querySelector(".status-pill")?.textContent.trim() || "";
      const cells = [name, target, current, status].map((v) => `"${v.replace(/"/g, '""')}"`);
      lines.push(cells.join(","));
    });
    const blob = new Blob([lines.join("\n")], { type: "text/csv;charset=utf-8;" });
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = "kpi_report.csv";
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
  });
}