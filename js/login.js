const loginForm = document.getElementById("loginForm");
const alertBox = document.getElementById("alert");

loginForm.addEventListener("submit", async (event) => {
  event.preventDefault();

  const payload = {
    username: document.getElementById("username").value.trim(),
    password: document.getElementById("password").value,
    csrf_token: document.getElementById("csrf_token").value,
  };

  const submitBtn = loginForm.querySelector('button[type="submit"]');
  submitBtn.disabled = true;
  submitBtn.textContent = "Memproses...";

  try {
    const response = await fetch("api/auth.php?action=login", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload),
    });
    const result = await response.json();

    if (!result.success) {
      alertBox.innerHTML = `<div class="alert alert-error">${escapeHtml(result.message)}</div>`;
      submitBtn.disabled = false;
      submitBtn.textContent = "Masuk";
      return;
    }

    window.location.href = "index.php";
  } catch (error) {
    alertBox.innerHTML = `<div class="alert alert-error">Tidak dapat terhubung ke server.</div>`;
    submitBtn.disabled = false;
    submitBtn.textContent = "Masuk";
  }
});

function escapeHtml(value) {
  return String(value ?? "").replace(/[&<>"']/g, (char) => ({
    "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#039;",
  }[char]));
}
