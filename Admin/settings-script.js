const deadlineSettingsForm = document.getElementById("deadlineSettingsForm");

if (deadlineSettingsForm) {
  deadlineSettingsForm.addEventListener("submit", (event) => {
    event.preventDefault();

    const probationPeriodDays = document.getElementById("probationPeriodDays").value;
    const alertAt1 = document.getElementById("alertAt1").value;
    const alertAt2 = document.getElementById("alertAt2").value;
    const alertAt3 = document.getElementById("alertAt3").value;

    // ── TODO(firebase): replace with a write to a single
    // `systemSettings/deadlineTracker` doc in Firestore:
    //
    // await setDoc(doc(db, "systemSettings", "deadlineTracker"), {
    //   probationPeriodDays: Number(probationPeriodDays),
    //   alertAt1: Number(alertAt1),
    //   alertAt2: Number(alertAt2),
    //   alertAt3: Number(alertAt3),
    //   updatedAt: serverTimestamp(),
    // });

    alert("(Demo) Settings saved. Firebase not yet connected.");
  });
}
