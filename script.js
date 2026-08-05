const searchInput = document.getElementById("dashboardSearch");
const rows = Array.from(document.querySelectorAll(".table-row"));
const chips = Array.from(document.querySelectorAll(".filter-chip"));
const assignButton = document.getElementById("assignCourseButton");
const recommendationTitle = document.getElementById("recommendationTitle");

let activeFilter = "all";

function applyFilters() {
  const query = searchInput.value.trim().toLowerCase();

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

searchInput.addEventListener("input", applyFilters);

chips.forEach((chip) => {
  chip.addEventListener("click", () => {
    activeFilter = chip.dataset.filter;
    chips.forEach((item) => item.classList.toggle("active", item === chip));
    applyFilters();
  });
});

assignButton.addEventListener("click", () => {
  recommendationTitle.textContent = "Training assigned to Maria Clara for the next review cycle.";
  assignButton.textContent = "Assigned";
  assignButton.disabled = true;
});

applyFilters();