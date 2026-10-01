/* Inti JS untuk halaman ERP: API client, modal, toast, format angka, dan CRUD generik. */
const CSRF = document.getElementById("csrf_token").value;

// ---------- Util ----------
function esc(v) {
  return String(v ?? "").replace(/[&<>"']/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#039;" }[c]));
}
function fmtNum(v, max = 2) {
  const n = Number(v);
  if (!isFinite(n)) return "-";
  return n.toLocaleString("id-ID", { maximumFractionDigits: max });
}
function fmtRp(v) {
  return "Rp " + Number(v || 0).toLocaleString("id-ID", { maximumFractionDigits: 0 });
}
function fmtTgl(s) {
  if (!s) return "-";
  const d = new Date(String(s).slice(0, 10) + "T00:00:00");
  return d.toLocaleDateString("id-ID", { day: "2-digit", month: "short", year: "numeric" });
}
function fmtTglPendek(s) {
  const d = new Date(String(s).slice(0, 10) + "T00:00:00");
  return d.toLocaleDateString("id-ID", { day: "2-digit", month: "short" });
}
function debounce(fn, ms = 350) {
  let t;
  return (...a) => { clearTimeout(t); t = setTimeout(() => fn(...a), ms); };
}
function badge(text, color = "gray") {
  return `<span class="badge badge-${color}">${esc(text)}</span>`;
}
const STATUS_WARNA = {
  draft: "gray", open: "blue", diterima: "green", batal: "red",
  planned: "gray", released: "blue", selesai: "green", dikonversi: "green",
  aman: "green", kritis: "amber", habis: "red",
  FG: "purple", SFG: "blue", RM: "gray", PO: "amber", WO: "blue",
  pesanan: "blue", forecast: "gray",
};
function statusBadge(s) { return badge(s, STATUS_WARNA[s] || "gray"); }
function todayStr() { return new Date().toISOString().slice(0, 10); }

// ---------- API ----------
async function api(url, { method = "GET", body, params } = {}) {
  if (params) {
    const q = new URLSearchParams();
    Object.entries(params).forEach(([k, v]) => { if (v !== undefined && v !== null && v !== "") q.set(k, v); });
    const s = q.toString();
    if (s) url += (url.includes("?") ? "&" : "?") + s;
  }
  const opt = { method, headers: {} };
  if (method !== "GET") opt.headers["X-CSRF-Token"] = CSRF;
  if (body !== undefined) {
    opt.headers["Content-Type"] = "application/json";
    opt.body = JSON.stringify(body);
  }
  const res = await fetch(url, opt);
  if (res.status === 401) { window.location.href = "login.php"; throw new Error("Sesi berakhir."); }
  let json;
  try { json = await res.json(); } catch (e) { throw new Error("Respons server tidak valid."); }
  if (!json.success) throw new Error(json.message || "Terjadi kesalahan.");
  return json;
}
const optionCache = {};
async function loadOptions(url) {
  if (!optionCache[url]) {
    optionCache[url] = api(url, { params: { action: "options" } }).then((r) => r.data);
  }
  return optionCache[url];
}
function clearOptionCache() { Object.keys(optionCache).forEach((k) => delete optionCache[k]); }

// ---------- Toast ----------
function toast(message, type = "success") {
  const box = document.getElementById("toastContainer");
  const el = document.createElement("div");
  el.className = `toast toast-${type}`;
  el.textContent = message;
  box.appendChild(el);
  setTimeout(() => el.classList.add("show"), 10);
  setTimeout(() => { el.classList.remove("show"); setTimeout(() => el.remove(), 300); }, 4200);
}
const toastErr = (e) => toast(e.message || String(e), "error");

// ---------- Modal ----------
function openModal({ title, body, size = "", footer = "" }) {
  const wrap = document.createElement("div");
  wrap.className = "modal";
  wrap.innerHTML = `
    <div class="modal-content ${size}">
      <div class="modal-header"><h2>${esc(title)}</h2><button type="button" class="close" data-close>&times;</button></div>
      <div class="modal-body">${body}</div>
      ${footer ? `<div class="form-actions">${footer}</div>` : ""}
    </div>`;
  document.body.appendChild(wrap);
  const close = () => wrap.remove();
  wrap.querySelectorAll("[data-close]").forEach((b) => b.addEventListener("click", close));
  wrap.addEventListener("mousedown", (e) => { if (e.target === wrap) close(); });
  return { el: wrap, close, body: wrap.querySelector(".modal-body") };
}
function confirmBox(message, okText = "Ya, lanjutkan", danger = false) {
  return new Promise((resolve) => {
    const m = openModal({
      title: "Konfirmasi",
      body: `<p>${esc(message)}</p>`,
      size: "modal-small",
      footer: `<button type="button" class="btn btn-secondary" data-close>Batal</button>
               <button type="button" class="btn ${danger ? "btn-danger" : "btn-primary"}" id="cbOk">${esc(okText)}</button>`,
    });
    let done = false;
    m.el.querySelector("#cbOk").addEventListener("click", () => { done = true; m.close(); resolve(true); });
    const obs = new MutationObserver(() => { if (!document.body.contains(m.el)) { obs.disconnect(); if (!done) resolve(false); } });
    obs.observe(document.body, { childList: true });
  });
}

// ---------- Tema & logout ----------
// Kelas "dark" sudah di-set lebih awal oleh inline script di <head> (lihat partials/assets.php)
// agar tidak "kedip"; di sini kita hanya menautkan tombol toggle-nya.
document.getElementById("btnDarkMode").addEventListener("click", () => {
  document.documentElement.classList.toggle("dark");
  localStorage.setItem("dark_mode", document.documentElement.classList.contains("dark") ? "1" : "0");
});
document.getElementById("btnLogout").addEventListener("click", async () => {
  try {
    await fetch("api/auth.php?action=logout", { method: "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify({ csrf_token: CSRF }) });
  } finally { window.location.href = "login.php"; }
});

// ---------- Form builder ----------
function renderPager(el, meta, goTo) {
  const { page, total_pages, total } = meta;
  let h = `<span class="pagination-info">Total ${fmtNum(total, 0)} data</span><div class="pagination-buttons">`;
  h += `<button class="btn btn-secondary" ${page <= 1 ? "disabled" : ""} data-p="${page - 1}">&laquo;</button>`;
  let start = Math.max(1, page - 2), end = Math.min(total_pages, start + 4);
  start = Math.max(1, end - 4);
  for (let i = start; i <= end; i++) h += `<button class="btn ${i === page ? "btn-primary" : "btn-secondary"}" data-p="${i}">${i}</button>`;
  h += `<button class="btn btn-secondary" ${page >= total_pages ? "disabled" : ""} data-p="${page + 1}">&raquo;</button></div>`;
  el.innerHTML = h;
  el.querySelectorAll("[data-p]").forEach((b) => b.addEventListener("click", () => goTo(Number(b.dataset.p))));
}

async function buildForm(fields, values, { isCreate }) {
  const parts = [];
  for (const f of fields) {
    if (f.createOnly && !isCreate) continue;
    const v = values[f.key] ?? f.default ?? "";
    const id = `f_${f.key}`;
    const label = `${esc(f.label)}${f.required ? '<span class="req"></span>' : ""}`;
    let input = "";
    if (f.type === "select") {
      const opts = f.options || (f.optionsUrl ? await loadOptions(f.optionsUrl) : []);
      input = `<select id="${id}" ${f.required ? "required" : ""}>` +
        (f.required && !f.emptyLabel ? `<option value="">-- pilih --</option>` : `<option value="">${esc(f.emptyLabel || "-- kosong --")}</option>`) +
        opts.map((o) => `<option value="${esc(o.value)}" ${String(o.value) === String(v) ? "selected" : ""}>${esc(o.label)}</option>`).join("") + `</select>`;
    } else if (f.type === "textarea") {
      input = `<textarea id="${id}" rows="2" maxlength="${f.max || 255}">${esc(v)}</textarea>`;
    } else if (f.type === "checkbox") {
      input = `<span class="checkbox-label"><input type="checkbox" id="${id}" ${Number(v) ? "checked" : ""}> ${esc(f.checkLabel || "Ya")}</span>`;
    } else {
      input = `<input id="${id}" type="${f.type || "text"}" value="${esc(v)}" ${f.required ? "required" : ""}
               ${f.step ? `step="${f.step}"` : ""} ${f.min !== undefined ? `min="${f.min}"` : ""} ${f.max ? `maxlength="${f.max}"` : ""}
               ${f.placeholder ? `placeholder="${esc(f.placeholder)}"` : ""}>`;
    }
    parts.push(`<label data-field="${f.key}" style="${f.full ? "grid-column:1/-1" : ""}">${label}${input}${f.hint ? `<span class="field-hint">${esc(f.hint)}</span>` : ""}</label>`);
  }
  return `<div class="form-grid">${parts.join("")}</div>`;
}
function readForm(root, fields, isCreate) {
  const out = {};
  fields.forEach((f) => {
    if (f.createOnly && !isCreate) return;
    const el = root.querySelector(`#f_${f.key}`);
    if (!el) return;
    out[f.key] = f.type === "checkbox" ? (el.checked ? 1 : 0) : el.value.trim();
  });
  return out;
}
function bindShowIf(root, fields) {
  const apply = () => {
    const vals = readForm(root, fields, true);
    fields.forEach((f) => {
      if (!f.showIf) return;
      const lab = root.querySelector(`[data-field="${f.key}"]`);
      if (lab) lab.classList.toggle("hidden", !f.showIf(vals));
    });
  };
  root.addEventListener("input", apply);
  root.addEventListener("change", apply);
  apply();
}

// ---------- CRUD generik ----------
function crudPage(cfg) {
  const root = document.querySelector(cfg.root || "#app");
  const state = { page: 1, limit: 10, sort: cfg.defaultSort, dir: cfg.defaultDir || "asc", search: "", filters: {} };

  root.innerHTML = `
    <div class="page-head">
      <div><h1>${esc(cfg.title)}</h1><p>${cfg.subtitle || ""}</p></div>
      ${cfg.readOnly ? "" : `<button class="btn btn-primary" id="cAdd">${esc(cfg.addLabel || "+ Tambah")}</button>`}
    </div>
    <main class="card">
      <div class="toolbar">
        <input type="search" id="cSearch" placeholder="${esc(cfg.searchPlaceholder || "Cari...")}">
        ${(cfg.filters || []).map((f) => `<select data-filter="${f.key}"><option value="">${esc(f.label)}</option>${f.options.map((o) => `<option value="${esc(o.value)}">${esc(o.label)}</option>`).join("")}</select>`).join("")}
        <select id="cLimit"><option value="10">10 / halaman</option><option value="25">25 / halaman</option><option value="50">50 / halaman</option><option value="100">100 / halaman</option></select>
      </div>
      <div class="table-wrapper"><table><thead><tr>
        ${cfg.columns.map((c) => `<th ${c.sort ? `data-sort="${c.sort}" style="cursor:pointer"` : ""} class="${c.num ? "right" : ""}">${esc(c.label)}</th>`).join("")}
        <th>Aksi</th></tr></thead><tbody id="cBody"></tbody></table></div>
      <div class="pagination" id="cPager"></div>
    </main>`;

  const body = root.querySelector("#cBody");
  let rows = [];

  async function load() {
    body.innerHTML = `<tr><td colspan="${cfg.columns.length + 1}" class="loading">Memuat data...</td></tr>`;
    try {
      const r = await api(cfg.api, { params: { page: state.page, limit: state.limit, sort: state.sort, dir: state.dir, search: state.search, ...state.filters } });
      rows = r.data;
      if (!rows.length) {
        body.innerHTML = `<tr><td colspan="${cfg.columns.length + 1}" class="empty">Data tidak ditemukan.</td></tr>`;
      } else {
        body.innerHTML = rows.map((row, i) => `<tr>
          ${cfg.columns.map((c) => `<td class="${c.num ? "num" : ""}">${c.render ? c.render(row) : esc(row[c.key] ?? "-")}</td>`).join("")}
          <td class="actions">
            ${(cfg.actions ? cfg.actions(row) : []).map((a, j) => `<button class="btn ${a.cls || "btn-secondary"} btn-sm" data-act="${i}:${j}">${esc(a.label)}</button>`).join("")}
            ${cfg.readOnly ? "" : `<button class="btn btn-warning btn-sm" data-edit="${i}">Edit</button><button class="btn btn-danger btn-sm" data-del="${i}">Hapus</button>`}
          </td></tr>`).join("");
      }
      renderPager(root.querySelector("#cPager"), r.meta, (p) => { state.page = p; load(); });
    } catch (e) {
      body.innerHTML = `<tr><td colspan="${cfg.columns.length + 1}" class="empty">${esc(e.message)}</td></tr>`;
    }
  }

  body.addEventListener("click", async (ev) => {
    const b = ev.target.closest("button");
    if (!b) return;
    if (b.dataset.edit !== undefined) openForm(rows[Number(b.dataset.edit)]);
    if (b.dataset.del !== undefined) {
      const row = rows[Number(b.dataset.del)];
      if (!(await confirmBox(`Hapus ${cfg.deleteLabel ? cfg.deleteLabel(row) : "data ini"}? Tindakan ini tidak dapat dibatalkan.`, "Hapus", true))) return;
      try { const r = await api(`${cfg.api}?id=${row.id}`, { method: "DELETE" }); toast(r.message); clearOptionCache(); load(); } catch (e) { toastErr(e); }
    }
    if (b.dataset.act !== undefined) {
      const [i, j] = b.dataset.act.split(":").map(Number);
      cfg.actions(rows[i])[j].onClick(rows[i], load);
    }
  });

  async function openForm(row = null) {
    const isCreate = !row;
    const html = await buildForm(cfg.fields, row || cfg.defaults || {}, { isCreate });
    const m = openModal({
      title: isCreate ? (cfg.addLabel || "Tambah").replace(/^\+\s*/, "") : `Edit ${cfg.title}`,
      body: `<form id="cForm">${html}<div class="form-actions">
        <button type="button" class="btn btn-secondary" data-close>Batal</button>
        <button type="submit" class="btn btn-primary">Simpan</button></div></form>`,
      size: cfg.modalSize || "",
    });
    m.el.querySelectorAll("[data-close]").forEach((x) => x.addEventListener("click", m.close));
    const form = m.el.querySelector("#cForm");
    bindShowIf(form, cfg.fields);
    (cfg.fields.filter((f) => f.onChange) || []).forEach((f) => {
      const el = form.querySelector(`#f_${f.key}`);
      el && el.addEventListener("change", () => f.onChange(el.value, (k, v) => { const t = form.querySelector(`#f_${k}`); if (t) { t.value = v; t.dispatchEvent(new Event("change")); } }));
    });
    form.addEventListener("submit", async (e) => {
      e.preventDefault();
      const btn = form.querySelector('button[type="submit"]');
      btn.disabled = true;
      try {
        const payload = readForm(form, cfg.fields, isCreate);
        const r = await api(isCreate ? cfg.api : `${cfg.api}?id=${row.id}`, { method: isCreate ? "POST" : "PUT", body: payload });
        toast(r.message); clearOptionCache(); m.close(); load();
        cfg.onSaved && cfg.onSaved();
      } catch (err) { toastErr(err); btn.disabled = false; }
    });
  }

  if (!cfg.readOnly) root.querySelector("#cAdd").addEventListener("click", () => openForm());
  root.querySelector("#cSearch").addEventListener("input", debounce((e) => { state.search = e.target.value.trim(); state.page = 1; load(); }));
  root.querySelector("#cLimit").addEventListener("change", (e) => { state.limit = Number(e.target.value); state.page = 1; load(); });
  root.querySelectorAll("[data-filter]").forEach((s) => s.addEventListener("change", () => { state.filters[s.dataset.filter] = s.value; state.page = 1; load(); }));
  root.querySelectorAll("th[data-sort]").forEach((th) => th.addEventListener("click", () => {
    if (state.sort === th.dataset.sort) state.dir = state.dir === "asc" ? "desc" : "asc"; else { state.sort = th.dataset.sort; state.dir = "asc"; }
    load();
  }));

  load();
  return { reload: load, openForm };
}
