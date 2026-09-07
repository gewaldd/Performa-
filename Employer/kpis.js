document.addEventListener("DOMContentLoaded", () => {
  const kpiSearchInput = document.getElementById("kpiSearch");
  const kpiRows = Array.from(document.querySelectorAll(".kpi-row"));
  const exportKpiBtn = document.getElementById("exportKpiBtn");
  const metricsPanel = document.querySelector(".metrics-panel");

  // Create an in-memory "No Results" element for search empty states
  const emptySearchState = document.createElement("div");
  emptySearchState.className = "kpi-row empty-search-row";
  emptySearchState.style.cssText = "display: none; justify-content: center; padding: 24px; color: var(--muted); grid-column: 1 / -1;";
  emptySearchState.textContent = "No performance indicators match your search.";
  if (metricsPanel) {
    metricsPanel.appendChild(emptySearchState);
  }

  // 1. Instant non-blocking filter with requestAnimationFrame batching
  if (kpiSearchInput && kpiRows.length > 0) {
    let animationFrameId = null;

    kpiSearchInput.addEventListener("input", () => {
      if (animationFrameId) {
        cancelAnimationFrame(animationFrameId);
      }

      animationFrameId = requestAnimationFrame(() => {
        const query = kpiSearchInput.value.trim().toLowerCase();
        let visibleCount = 0;

        kpiRows.forEach((row) => {
          const text = row.dataset.search || row.textContent.toLowerCase();
          const matches = !query || text.includes(query);
          
          row.style.display = matches ? "" : "none";
          if (matches) visibleCount++;
        });

        // Toggle empty search state dynamically
        if (emptySearchState) {
          emptySearchState.style.display = visibleCount === 0 ? "flex" : "none";
        }
      });
    });
  }

  // 2. Client-Side CSV Export (Includes dynamically selected employee name & date)
  if (exportKpiBtn) {
    exportKpiBtn.addEventListener("click", () => {
      const visibleRows = kpiRows.filter((row) => row.style.display !== "none");

      if (visibleRows.length === 0) {
        alert("No visible KPI data to export.");
        return;
      }

      const headers = ["KPI Name", "Target Score", "Current Score", "Status"];
      const csvLines = [headers.map((h) => `"${h}"`).join(",")];

      visibleRows.forEach((row) => {
        const name = row.querySelector(".kpi-name")?.textContent.trim() || "";
        const target = row.querySelector(".kpi-target")?.textContent.trim() || "";
        const current = row.querySelector(".kpi-current strong")?.textContent.trim() || "";
        const status = row.querySelector(".status-pill")?.textContent.trim() || "";

        const cells = [name, target, current, status].map((v) => `"${v.replace(/"/g, '""')}"`);
        csvLines.push(cells.join(","));
      });

      // Extract current employee name from dropdown if available
      const empSelect = document.querySelector(".employee-select-control");
      const selectedEmpText = empSelect ? empSelect.options[empSelect.selectedIndex]?.text.replace(/[^a-z0-9]/gi, '_').toLowerCase() : "employee";
      const dateStr = new Date().toISOString().slice(0, 10);

      const blob = new Blob([csvLines.join("\n")], { type: "text/csv;charset=utf-8;" });
      const url = URL.createObjectURL(blob);
      const link = document.createElement("a");

      link.href = url;
      link.download = `kpi_report_${selectedEmpText}_${dateStr}.csv`;
      document.body.appendChild(link);
      link.click();

      // Clean up DOM and memory allocation
      document.body.removeChild(link);
      URL.revokeObjectURL(url);
    });
  }
});