// ── Role filter chips ──────────────────────────────────────────────
const chips = Array.from(document.querySelectorAll(".filter-chip"));
const rows = Array.from(document.querySelectorAll(".table-row"));

chips.forEach((chip) => {
  chip.addEventListener("click", () => {
    chips.forEach((c) => c.classList.toggle("active", c === chip));
    const filter = chip.dataset.filter;
    rows.forEach((row) => {
      row.hidden = filter !== "all" && row.dataset.filter !== filter;
    });
  });
});

// ── Create account form ──────────────────────────────────────────────
const createAccountForm = document.getElementById("createAccountForm");

if (createAccountForm) {
  createAccountForm.addEventListener("submit", (event) => {
    event.preventDefault();

    const name = document.getElementById("accName").value.trim();
    const email = document.getElementById("accEmail").value.trim();
    const role = document.getElementById("accRole").value;
    const tempPassword = document.getElementById("accTempPassword").value;

    if (!name || !email || !role || !tempPassword) {
      alert("Please fill out every field before creating the account.");
      return;
    }

    // ── TODO(firebase): replace with a real account creation flow ──
    //
    // Typical pattern: an Admin-only Cloud Function that calls
    // admin.auth().createUser({ email, password: tempPassword }),
    // then writes a matching profile doc to Firestore:
    //
    // await setDoc(doc(db, "Users", newUser.uid), {
    //   name, email, role, status: "active", createdAt: serverTimestamp(),
    // });
    //
    // Client-side createUserWithEmailAndPassword() is NOT used here
    // because it would sign the Admin OUT and sign the new user IN —
    // account creation by an Admin must happen server-side.

    alert(`(Demo) Account created for ${name} (${role}). Firebase not yet connected.`);
    createAccountForm.reset();
  });
}
