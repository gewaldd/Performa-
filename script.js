const evaluations = [
  {
    name: "Maria Clara",
    role: "Customer Support Spec.",
    avatar: "https://images.unsplash.com/photo-1494790108377-be9c29b29330?auto=format&fit=crop&w=160&q=80",
    timeline: "Day 135 45 days left",
    progress: 72,
    score: 3.8,
    stars: 4,
    status: "Needs Review",
    statusClass: "status-warning",
    progressColor: "#f0a11b"
  },
  {
    name: "Jose Rizal",
    role: "Software Engineer",
    avatar: "https://images.unsplash.com/photo-1500648767791-00dcc994a43e?auto=format&fit=crop&w=160&q=80",
    timeline: "Day 90 90 days left",
    progress: 54,
    score: 4.5,
    stars: 5,
    status: "On Track",
    statusClass: "status-good",
    progressColor: "#2f6df6"
  },
  {
    name: "Gabriela Silang",
    role: "Marketing Associate",
    avatar: "https://images.unsplash.com/photo-1524504388940-b1c1722653e1?auto=format&fit=crop&w=160&q=80",
    timeline: "Day 178 2 days left",
    progress: 94,
    score: 4.8,
    stars: 5,
    status: "Ready for Reg.",
    statusClass: "status-ready",
    progressColor: "#ed5b57"
  }
];

const template = document.getElementById("evaluationRowTemplate");
const rowsContainer = document.getElementById("evaluationRows");

function starString(count) {
  return "★★★★★☆☆☆☆☆".slice(5 - count, 10 - count);
}

rowsContainer.innerHTML = "";

evaluations.forEach((employee) => {
  const row = template.content.firstElementChild.cloneNode(true);
  row.querySelector(".avatar").style.backgroundImage = `url('${employee.avatar}')`;
  row.querySelector(".employee-name").textContent = employee.name;
  row.querySelector(".employee-role").textContent = employee.role;
  row.querySelector(".timeline-text").textContent = employee.timeline;
  row.querySelector(".timeline-bar span").style.width = `${employee.progress}%`;
  row.querySelector(".timeline-bar span").style.background = employee.progressColor;
  row.querySelector(".score-value").textContent = employee.score.toFixed(1);
  row.querySelector(".stars").textContent = starString(employee.stars);
  const status = row.querySelector(".status-pill");
  status.textContent = employee.status;
  status.classList.add(employee.statusClass);
  rowsContainer.appendChild(row);
});

document.querySelectorAll(".nav-item").forEach((item) => {
  item.addEventListener("click", (event) => {
    document.querySelectorAll(".nav-item").forEach((navItem) => navItem.classList.remove("active"));
    event.currentTarget.classList.add("active");
  });
});