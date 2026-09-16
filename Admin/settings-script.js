const deadlineSettingsForm = document.getElementById("deadlineSettingsForm");

if (deadlineSettingsForm) {
  deadlineSettingsForm.addEventListener("submit", async (event) => {
    event.preventDefault();

    const submitButton = deadlineSettingsForm.querySelector('button[type="submit"]');
    if (submitButton) submitButton.disabled = true;
    try {
      const response = await fetch("settings.php", {
        method: "POST",
        body: new URLSearchParams(new FormData(deadlineSettingsForm)),
      });
      const body = await response.json();
      if (!response.ok) throw new Error(body.error || "Unable to save settings.");
      alert("Settings saved.");
    } catch (error) {
      console.error("Failed to save deadline settings", error);
      alert(error.message || "Something went wrong saving the settings. Please try again.");
    } finally {
      if (submitButton) submitButton.disabled = false;
    }
  });
}
