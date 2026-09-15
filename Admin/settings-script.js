import { initializeApp } from "https://www.gstatic.com/firebasejs/9.22.0/firebase-app.js";
import { getFirestore, doc, setDoc, serverTimestamp } from "https://www.gstatic.com/firebasejs/9.22.0/firebase-firestore.js";

const firebaseConfig = {
  apiKey: "AIzaSyD44yfH2zeaGMh8icQol4XamDJGQ_h0XBE",
  authDomain: "performa-36cc9.firebaseapp.com",
  projectId: "performa-36cc9",
  storageBucket: "performa-36cc9.firebasestorage.app",
  messagingSenderId: "349595710839",
  appId: "1:349595710839:web:6839edb20b31fd760a9d72",
};

const db = getFirestore(initializeApp(firebaseConfig));
const deadlineSettingsForm = document.getElementById("deadlineSettingsForm");

if (deadlineSettingsForm) {
  deadlineSettingsForm.addEventListener("submit", async (event) => {
    event.preventDefault();

    const probationPeriodDays = document.getElementById("probationPeriodDays").value;
    const alertAt1 = document.getElementById("alertAt1").value;
    const alertAt2 = document.getElementById("alertAt2").value;
    const alertAt3 = document.getElementById("alertAt3").value;

    const submitButton = deadlineSettingsForm.querySelector('button[type="submit"]');
    if (submitButton) submitButton.disabled = true;
    try {
      await setDoc(doc(db, "systemSettings", "deadlineTracker"), {
        probationPeriodDays: Number(probationPeriodDays),
        alertAt1: Number(alertAt1),
        alertAt2: Number(alertAt2),
        alertAt3: Number(alertAt3),
        updatedAt: serverTimestamp(),
      });
      alert("Settings saved.");
    } catch (error) {
      console.error("Failed to save deadline settings", error);
      alert("Something went wrong saving the settings. Please try again.");
    } finally {
      if (submitButton) submitButton.disabled = false;
    }
  });
}
