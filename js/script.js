const API = "api/mahasiswa.php";
const AUTH_API = "api/auth.php";

const modal = document.getElementById("modal");
const confirmModal = document.getElementById("confirmModal");
const form = document.getElementById("mahasiswaForm");
const table = document.getElementById("dataTable");
const searchInput = document.getElementById("searchInput");
const filterJurusan = document.getElementById("filterJurusan");
const limitSelect = document.getElementById("limitSelect");
const alertBox = document.getElementById("alert");
const pagination = document.getElementById("pagination");
const toastContainer = document.getElementById("toastContainer");
const csrfInput = document.getElementById("csrf_token");

let state = {
  page: 1,
  limit: 10,
  sort: "id",
  dir: "desc",
  search: "",
  jurusan: "",
  kelas: "",
};
const filterKelas = document.getElementById("filterKelas");
// Kelas awal bisa diberikan lewat URL, mis. index.php?kelas=B atau index.php?kelas=-
const kelasAwal = new URLSearchParams(location.search).get("kelas");
if (kelasAwal) {
  state.kelas = kelasAwal.toUpperCase();
  filterKelas.value = state.kelas;
}
let pendingDeleteId = null;
let searchDebounce = null;

// ---------- Event bindings ----------
document.getElementById("btnTambah").addEventListener("click", () => openModal());
document.getElementById("btnClose").addEventListener("click", closeModal);
document.getElementById("btnCancel").addEventListener("click", closeModal);
document.getElementById("btnCloseConfirm").addEventListener("click", closeConfirmModal);
document.getElementById("btnCancelDelete").addEventListener("click", closeConfirmModal);
document.getElementById("btnConfirmDelete").addEventListener("click", confirmDelete);
document.getElementById("btnLogout").addEventListener("click", logout);
document.getElementById("btnDarkMode").addEventListener("click", toggleDarkMode);
document.getElementById("btnExport").addEventListener("click", exportCsv);
document.getElementById("foto").addEventListener("change", previewFoto);

searchInput.addEventListener("input", () => {
  clearTimeout(searchDebounce);
  searchDebounce = setTimeout(() => {
    state.search = searchInput.value.trim();
    state.page = 1;
    loadData();
  }, 350);
});

filterJurusan.addEventListener("change", () => {
  state.jurusan = filterJurusan.value;
  state.page = 1;
  loadData();
});

filterKelas.addEventListener("change", () => {
  state.kelas = filterKelas.value;
  state.page = 1;
  loadData();
});

limitSelect.addEventListener("change", () => {
  state.limit = Number(limitSelect.value);
  state.page = 1;
  loadData();
});

document.querySelectorAll("th[data-sort]").forEach((th) => {
  th.style.cursor = "pointer";
  th.addEventListener("click", () => {
    const col = th.dataset.sort;
    if (state.sort === col) {
      state.dir = state.dir === "asc" ? "desc" : "asc";
    } else {
      state.sort = col;
      state.dir = "asc";
    }
    loadData();
  });
});

form.addEventListener("submit", saveData);

// ---------- Init ----------
initDarkMode();
loadJurusanOptions();
loadStats();
loadData();

// ---------- Data loading ----------
async function loadData() {
  table.innerHTML = `<tr><td colspan="9" class="loading">Memuat data...</td></tr>`;
  try {
    const params = new URLSearchParams({
      page: state.page,
      limit: state.limit,
      sort: state.sort,
      dir: state.dir,
      search: state.search,
      jurusan: state.jurusan,
      kelas: state.kelas,
    });
    const response = await fetch(`${API}?${params.toString()}`);
    const result = await response.json();
    if (response.status === 401) return handleUnauthorized();
    if (!result.success) throw new Error(result.message);

    renderTable(result.data);
    renderPagination(result.meta);
    document.getElementById("statHalaman").textContent =
      `${result.meta.page} / ${result.meta.total_pages}`;
  } catch (error) {
    table.innerHTML = `<tr><td colspan="9" class="empty">${escapeHtml(error.message)}</td></tr>`;
  }
}

async function loadStats() {
  try {
    const response = await fetch(`${API}?action=stats`);
    const result = await response.json();
    if (response.status === 401) return handleUnauthorized();
    if (!result.success) return;
    document.getElementById("statTotal").textContent = result.data.total;
    document.getElementById("statJurusanCount").textContent = result.data.per_jurusan.length;
  } catch (error) {
    // Statistik bersifat pelengkap, biarkan senyap jika gagal.
  }
}

async function loadJurusanOptions() {
  try {
    const response = await fetch(`${API}?action=jurusan_list`);
    const result = await response.json();
    if (response.status === 401) return handleUnauthorized();
    if (!result.success) return;

    filterJurusan.innerHTML =
      `<option value="">Semua Jurusan</option>` +
      result.data.map((j) => `<option value="${escapeHtml(j)}">${escapeHtml(j)}</option>`).join("");

    const datalist = document.getElementById("jurusanOptions");
    datalist.innerHTML = result.data.map((j) => `<option value="${escapeHtml(j)}">`).join("");
  } catch (error) {
    // Abaikan; dropdown tetap bisa diketik manual.
  }
}

function renderTable(data) {
  if (!data.length) {
    table.innerHTML = `<tr><td colspan="9" class="empty">Data tidak ditemukan.</td></tr>`;
    return;
  }

  table.innerHTML = data
    .map(
      (item) => `
    <tr>
      <td>${
        item.foto_url
          ? `<img class="avatar" src="${escapeHtml(item.foto_url)}" alt="Foto ${escapeHtml(item.nama)}">`
          : `<span class="avatar avatar-placeholder">${escapeHtml(item.nama.charAt(0))}</span>`
      }</td>
      <td>${escapeHtml(item.nbi)}</td>
      <td>${escapeHtml(item.nama)}</td>
      <td>${item.kelas ? `<span class="badge badge-blue">${escapeHtml(item.kelas)}</span>` : `<span class="muted small">-</span>`}</td>
      <td>${escapeHtml(item.jurusan)}</td>
      <td>${escapeHtml(item.angkatan || "-")}</td>
      <td>${escapeHtml(item.email)}</td>
      <td>${escapeHtml(item.no_hp || "-")}</td>
      <td class="actions">
        <button class="btn btn-warning" onclick="editData(${item.id})">Edit</button>
        <button class="btn btn-danger" onclick="askDelete(${item.id})">Hapus</button>
      </td>
    </tr>
  `
    )
    .join("");

  window.__rowsCache = data;
}

function renderPagination(meta) {
  const { page, total_pages, total } = meta;
  let html = `<span class="pagination-info">Total ${total} data</span><div class="pagination-buttons">`;

  html += `<button class="btn btn-secondary" ${page <= 1 ? "disabled" : ""} onclick="goToPage(${page - 1})">&laquo; Sebelumnya</button>`;

  const maxButtons = 5;
  let start = Math.max(1, page - Math.floor(maxButtons / 2));
  let end = Math.min(total_pages, start + maxButtons - 1);
  start = Math.max(1, end - maxButtons + 1);

  for (let i = start; i <= end; i++) {
    html += `<button class="btn ${i === page ? "btn-primary" : "btn-secondary"}" onclick="goToPage(${i})">${i}</button>`;
  }

  html += `<button class="btn btn-secondary" ${page >= total_pages ? "disabled" : ""} onclick="goToPage(${page + 1})">Berikutnya &raquo;</button>`;
  html += `</div>`;

  pagination.innerHTML = html;
}

window.goToPage = function (page) {
  state.page = page;
  loadData();
};

// ---------- Modal / form ----------
function openModal(item = null) {
  form.reset();
  document.getElementById("id").value = item ? item.id : "";
  document.getElementById("nbi").value = item ? item.nbi : "";
  document.getElementById("nama").value = item ? item.nama : "";
  document.getElementById("jurusan").value = item ? item.jurusan : "";
  document.getElementById("angkatan").value = item ? item.angkatan || "" : "";
  document.getElementById("email").value = item ? item.email : "";
  document.getElementById("no_hp").value = item ? item.no_hp || "" : "";
  document.getElementById("alamat").value = item ? item.alamat || "" : "";
  document.getElementById("hapus_foto").checked = false;
  document.getElementById("modalTitle").textContent = item ? "Edit Mahasiswa" : "Tambah Mahasiswa";

  const previewWrap = document.getElementById("fotoPreviewWrap");
  const previewImg = document.getElementById("fotoPreview");
  if (item && item.foto_url) {
    previewImg.src = item.foto_url;
    previewWrap.classList.remove("hidden");
  } else {
    previewWrap.classList.add("hidden");
    previewImg.src = "";
  }

  modal.classList.remove("hidden");
}

function closeModal() {
  modal.classList.add("hidden");
}

function previewFoto(event) {
  const file = event.target.files[0];
  if (!file) return;
  const reader = new FileReader();
  reader.onload = (e) => {
    document.getElementById("fotoPreview").src = e.target.result;
    document.getElementById("fotoPreviewWrap").classList.remove("hidden");
    document.getElementById("hapus_foto").checked = false;
  };
  reader.readAsDataURL(file);
}

async function saveData(event) {
  event.preventDefault();
  const id = document.getElementById("id").value;
  const submitBtn = document.getElementById("btnSubmit");

  const formData = new FormData();
  formData.append("nbi", document.getElementById("nbi").value.trim());
  formData.append("nama", document.getElementById("nama").value.trim());
  formData.append("jurusan", document.getElementById("jurusan").value.trim());
  formData.append("angkatan", document.getElementById("angkatan").value.trim());
  formData.append("email", document.getElementById("email").value.trim());
  formData.append("no_hp", document.getElementById("no_hp").value.trim());
  formData.append("alamat", document.getElementById("alamat").value.trim());
  formData.append("csrf_token", csrfInput.value);

  const fotoFile = document.getElementById("foto").files[0];
  if (fotoFile) formData.append("foto", fotoFile);
  if (document.getElementById("hapus_foto").checked) formData.append("hapus_foto", "1");

  let url = API;
  if (id) {
    formData.append("_method", "PUT");
    url = `${API}?id=${id}`;
  }

  submitBtn.disabled = true;
  submitBtn.textContent = "Menyimpan...";

  try {
    const response = await fetch(url, { method: "POST", body: formData });
    if (response.status === 401) return handleUnauthorized();
    const result = await response.json();
    if (!result.success) throw new Error(result.message);

    closeModal();
    showToast(result.message, "success");
    await Promise.all([loadData(), loadStats(), loadJurusanOptions()]);
  } catch (error) {
    showToast(error.message, "error");
  } finally {
    submitBtn.disabled = false;
    submitBtn.textContent = "Simpan";
  }
}

window.editData = function (id) {
  const item = (window.__rowsCache || []).find((row) => Number(row.id) === Number(id));
  if (item) openModal(item);
};

// ---------- Delete flow (dengan modal konfirmasi, bukan native confirm) ----------
window.askDelete = function (id) {
  const item = (window.__rowsCache || []).find((row) => Number(row.id) === Number(id));
  if (!item) return;
  pendingDeleteId = id;
  document.getElementById("confirmText").textContent = `Yakin ingin menghapus data "${item.nama}"? Tindakan ini tidak dapat dibatalkan.`;
  confirmModal.classList.remove("hidden");
};

function closeConfirmModal() {
  confirmModal.classList.add("hidden");
  pendingDeleteId = null;
}

async function confirmDelete() {
  if (!pendingDeleteId) return;
  const id = pendingDeleteId;

  try {
    const response = await fetch(`${API}?id=${id}`, {
      method: "DELETE",
      headers: { "X-CSRF-Token": csrfInput.value },
    });
    if (response.status === 401) return handleUnauthorized();
    const result = await response.json();
    if (!result.success) throw new Error(result.message);

    showToast(result.message, "success");
    closeConfirmModal();
    await Promise.all([loadData(), loadStats()]);
  } catch (error) {
    showToast(error.message, "error");
    closeConfirmModal();
  }
}

// ---------- Export ----------
function exportCsv() {
  const params = new URLSearchParams({ search: state.search, jurusan: state.jurusan, kelas: state.kelas });
  window.location.href = `api/export.php?${params.toString()}`;
}

// ---------- Auth ----------
async function logout() {
  try {
    await fetch(`${AUTH_API}?action=logout`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ csrf_token: csrfInput.value }),
    });
  } finally {
    window.location.href = "login.php";
  }
}

function handleUnauthorized() {
  window.location.href = "login.php";
}

// ---------- Dark mode ----------
function initDarkMode() {
  // Kelas "dark" sudah diset lebih awal oleh inline script di <head> (lihat partials/assets.php).
}

function toggleDarkMode() {
  document.documentElement.classList.toggle("dark");
  localStorage.setItem("dark_mode", document.documentElement.classList.contains("dark") ? "1" : "0");
}

// ---------- Toast ----------
function showToast(message, type) {
  const toast = document.createElement("div");
  toast.className = `toast toast-${type}`;
  toast.textContent = message;
  toastContainer.appendChild(toast);
  setTimeout(() => toast.classList.add("show"), 10);
  setTimeout(() => {
    toast.classList.remove("show");
    setTimeout(() => toast.remove(), 300);
  }, 3500);
}

function showAlert(message, type) {
  alertBox.innerHTML = `<div class="alert alert-${type}">${escapeHtml(message)}</div>`;
  setTimeout(() => (alertBox.innerHTML = ""), 3000);
}

function escapeHtml(value) {
  return String(value ?? "").replace(/[&<>"']/g, (char) => ({
    "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#039;",
  }[char]));
}
