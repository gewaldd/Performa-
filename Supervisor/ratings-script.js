// ── Star rating interaction ────────────────────────────────────────
const starInputs = Array.from(document.querySelectorAll(".star-input"));

starInputs.forEach((starGroup) => {
  const stars = Array.from(starGroup.querySelectorAll(".star"));

  stars.forEach((star) => {
    star.addEventListener("click", () => {
      const value = parseInt(star.dataset.value, 10);
      starGroup.dataset.score = value;

      stars.forEach((s) => {
        s.classList.toggle("active", parseInt(s.dataset.value, 10) <= value);
      });
    });
  });
});

// ── Form submit ─────────────────────────────────────────────────────
const ratingForm = document.getElementById("ratingForm");
const saveConfirmation = document.getElementById("saveConfirmation");
const submitRatingBtn = document.getElementById("submitRatingBtn");

ratingForm.addEventListener("submit", (event) => {
  event.preventDefault();

  const employeeId = document.getElementById("employeeSelect").value;
  const weekEnding = document.getElementById("weekEnding").value;
  const notes = document.getElementById("notes").value;

  // Collect each KPI's star score
  const kpiScores = Array.from(document.querySelectorAll(".kpi-score-row")).map((row) => ({
    kpiId: row.dataset.kpiId,
    score: parseInt(row.querySelector(".star-input").dataset.score, 10) || 0,
  }));

  const unratedKpis = kpiScores.filter((k) => k.score === 0);
  if (!employeeId || !weekEnding || unratedKpis.length > 0) {
    alert("Please select an employee, a week-ending date, and rate every KPI before submitting.");
    return;
  }

  submitRatingBtn.disabled = true;
  submitRatingBtn.textContent = "Submitting...";

  // ── TODO(firebase): replace this block with a real Firestore write ──
  //
  // import { addDoc, collection, serverTimestamp } from "firebase/firestore";
  //
  // addDoc(collection(db, "evaluations"), {
  //   employeeId,
  //   weekEnding,
  //   notes,
  //   kpiScores,
  //   ratedBy: auth.currentUser.uid,
  //   ratedByRole: "supervisor",
  //   createdAt: serverTimestamp(),
  // })
  //   .then(() => {
  //     saveConfirmation.classList.add("visible");
  //     ratingForm.reset();
  //     submitRatingBtn.disabled = false;
  //     submitRatingBtn.textContent = "Submit Rating";
  //   })
  //   .catch((error) => {
  //     alert("Something went wrong saving this rating. Please try again.");
  //     submitRatingBtn.disabled = false;
  //     submitRatingBtn.textContent = "Submit Rating";
  //   });

  // ── Temporary stand-in so the UI is demoable before Firebase is wired ──
  setTimeout(() => {
    saveConfirmation.classList.add("visible");
    submitRatingBtn.disabled = false;
    submitRatingBtn.textContent = "Submit Rating";
  }, 500);
});
