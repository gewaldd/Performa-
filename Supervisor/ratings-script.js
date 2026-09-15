import { initializeApp } from "https://www.gstatic.com/firebasejs/9.22.0/firebase-app.js";
import { getAuth } from "https://www.gstatic.com/firebasejs/9.22.0/firebase-auth.js";
import { getFirestore, addDoc, collection, serverTimestamp } from "https://www.gstatic.com/firebasejs/9.22.0/firebase-firestore.js";

const firebaseConfig = {
  apiKey: "AIzaSyD44yfH2zeaGMh8icQol4XamDJGQ_h0XBE",
  authDomain: "performa-36cc9.firebaseapp.com",
  projectId: "performa-36cc9",
  storageBucket: "performa-36cc9.firebasestorage.app",
  messagingSenderId: "349595710839",
  appId: "1:349595710839:web:6839edb20b31fd760a9d72",
};

const app = initializeApp(firebaseConfig);
const auth = getAuth(app);
const db = getFirestore(app);

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

if (ratingForm) {
ratingForm.addEventListener("submit", async (event) => {
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

  try {
    await addDoc(collection(db, "evaluations"), {
      employeeId,
      weekEnding,
      notes,
      kpiScores,
      ratedBy: auth.currentUser?.uid || document.body.dataset.uid || null,
      ratedByRole: "supervisor",
      createdAt: serverTimestamp(),
    });
    saveConfirmation.classList.add("visible");
    ratingForm.reset();
    submitRatingBtn.disabled = false;
    submitRatingBtn.textContent = "Submit Rating";
  } catch (error) {
    console.error("Failed to save evaluation", error);
    alert("Something went wrong saving this rating. Please try again.");
    submitRatingBtn.disabled = false;
    submitRatingBtn.textContent = "Submit Rating";
  }
});
}
