const searchInput = document.getElementById("dashboardSearch");
const rows = Array.from(document.querySelectorAll(".table-row"));
const chips = Array.from(document.querySelectorAll(".filter-chip"));
const assignButton = document.getElementById("assignCourseButton");
const recommendationTitle = document.getElementById("recommendationTitle");

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

const navItems = Array.from(document.querySelectorAll(".nav-item"));
const tabSections = Array.from(document.querySelectorAll("[data-tab-section]"));
const tabGroups = Array.from(document.querySelectorAll("[data-tab-group]"));
const validTabs = new Set(["dashboard", ...tabSections.map((section) => section.dataset.tabSection)]);

function setActiveSection(sectionId, updateHash = true) {
  const activeSection = validTabs.has(sectionId) ? sectionId : "dashboard";

  navItems.forEach((navItem) => {
    const isActive = navItem.dataset.section === activeSection;
    navItem.classList.toggle("active", isActive);
    if (isActive) {
      navItem.setAttribute("aria-current", "page");
    } else {
      navItem.removeAttribute("aria-current");
    }
  });

  tabSections.forEach((section) => {
    section.hidden = activeSection !== "dashboard" && section.dataset.tabSection !== activeSection;
  });

  tabGroups.forEach((group) => {
    group.hidden = activeSection !== "dashboard" && !group.querySelector(`[data-tab-section="${activeSection}"]`);
  });

  if (updateHash) {
    const nextHash = activeSection === "dashboard" ? "" : `#${activeSection}`;
    history.replaceState(null, "", `${window.location.pathname}${window.location.search}${nextHash}`);
  }
}

navItems.forEach((item) => {
  item.addEventListener("click", (event) => {
    event.preventDefault();
    setActiveSection(item.dataset.section);
  });
});

window.addEventListener("hashchange", () => {
  setActiveSection(window.location.hash.slice(1), false);
});

setActiveSection(window.location.hash.slice(1), false);

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

if (assignButton && recommendationTitle) {
  assignButton.addEventListener("click", () => {
    const completedLabel = assignButton.dataset.completedLabel || "Completed";
    const confirmationText = assignButton.dataset.confirmText || "Action completed.";

    recommendationTitle.textContent = confirmationText;
    assignButton.textContent = completedLabel;
    assignButton.disabled = true;
  });
}

const acknowledgementButtons = Array.from(document.querySelectorAll('.acknowledge-button'));
acknowledgementButtons.forEach((button) => {
  button.addEventListener('click', () => {
    if (button.disabled) {
      return;
    }

    const row = button.closest('.ack-row');
    if (row) {
      const status = row.querySelector('.ack-status');
      const timestamp = row.querySelector('.ack-timestamp');
      if (status) {
        status.textContent = 'Acknowledged';
      }
      if (timestamp) {
        const now = new Date();
        timestamp.textContent = now.toLocaleString();
      }
      button.textContent = 'Acknowledged';
      button.disabled = true;
      button.classList.add('acknowledged');
    }
  });
});

if (rows.length > 0) applyFilters();
