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

    backdrop.className = "modal-backdrop";
    dialog.className = "confirm-dialog";
    heading.textContent = "Please confirm";
    message.textContent = form.dataset.confirm;
    actions.className = "confirm-dialog-actions";
    cancelButton.type = "button";
    cancelButton.className = "ghost-button";
    cancelButton.textContent = "Cancel";
    confirmButton.type = "button";
    confirmButton.className = "btn-cancel";
    confirmButton.textContent = "Continue";

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