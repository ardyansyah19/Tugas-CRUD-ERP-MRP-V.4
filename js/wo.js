(async function () {
  const app = document.getElementById("app");
  const state = { page: 1, limit: 10, search: "", status: "" };

  app.innerHTML = `
    <div class="page-head">
      <div><h1>Work Order</h1><p>Perintah produksi barang jadi (FG) atau setengah jadi (SFG). Bisa dibuat manual atau otomatis dari hasil MRP. Saat "Selesai", komponen BOM otomatis terpotong dari stok dan hasil produksi masuk ke stok.</p></div>
      <button class="btn btn-primary" id="btnAdd">+ Buat Work Order</button>
    </div>
    <main class="card">
      <div class="toolbar">
        <input type="search" id="wSearch" placeholder="Cari no. WO, item, atau PIC...">
        <select id="wStatus"><option value="">Semua status</option><option value="planned">Planned</option><option value="released">Released</option><option value="selesai">Selesai</option><option value="batal">Batal</option></select>
        <select id="wLimit"><option value="10">10 / halaman</option><option value="25">25</option><option value="50">50</option></select>
      </div>
      <div class="table-wrapper"><table><thead><tr><th>No. WO</th><th>Item</th><th class="right">Qty</th><th>Mulai</th><th>Selesai</th><th>Kelas</th><th>PIC</th><th>Status</th><th></th></tr></thead><tbody id="wBody"></tbody></table></div>
      <div class="pagination" id="wPager"></div>
    </main>`;

  const body = document.getElementById("wBody");
  async function load() {
    body.innerHTML = `<tr><td colspan="9" class="loading">Memuat...</td></tr>`;
    try {
      const r = await api("api/wo.php", { params: { page: state.page, limit: state.limit, search: state.search, status: state.status } });
      body.innerHTML = r.data.length ? r.data.map((w) => `<tr>
        <td><a href="#" data-view="${w.id}"><strong>${esc(w.no_wo)}</strong></a></td>
        <td>${esc(w.item_kode)} ${esc(w.item_nama)}</td>
        <td class="num">${fmtNum(w.qty)} ${esc(w.satuan)}</td>
        <td>${fmtTgl(w.tanggal_mulai)}</td><td>${fmtTgl(w.tanggal_selesai)}</td>
        <td>${w.kelas_kode ? statusBadge(w.kelas_kode) : "-"}</td><td>${esc(w.pic_nama || "-")}</td>
        <td>${statusBadge(w.status)}</td>
        <td class="actions"><button class="btn btn-secondary btn-sm" data-view="${w.id}">Detail</button></td>
      </tr>`).join("") : `<tr><td colspan="9" class="empty">Belum ada work order.</td></tr>`;
      renderPager(document.getElementById("wPager"), r.meta, (pg) => { state.page = pg; load(); });
      body.querySelectorAll("[data-view]").forEach((b) => b.addEventListener("click", (e) => { e.preventDefault(); openDetail(Number(b.dataset.view)); }));
    } catch (e) { body.innerHTML = `<tr><td colspan="9" class="empty">${esc(e.message)}</td></tr>`; }
  }
  document.getElementById("wSearch").addEventListener("input", debounce((e) => { state.search = e.target.value.trim(); state.page = 1; load(); }));
  document.getElementById("wStatus").addEventListener("change", (e) => { state.status = e.target.value; state.page = 1; load(); });
  document.getElementById("wLimit").addEventListener("change", (e) => { state.limit = Number(e.target.value); state.page = 1; load(); });

  async function pilihKelasPic(container, kelasVal, picVal) {
    const kelasOpt = await loadOptions("api/kelas.php");
    container.innerHTML = `
      <label>Kelas<select id="w_kelas"><option value="">-- tidak ada --</option>${kelasOpt.map((o) => `<option value="${o.value}" ${String(o.value) === String(kelasVal) ? "selected" : ""}>${esc(o.label)}</option>`).join("")}</select></label>
      <label>Mahasiswa PIC<select id="w_pic"><option value="">-- tidak ada --</option></select></label>`;
    const kelasSel = container.querySelector("#w_kelas");
    const picSel = container.querySelector("#w_pic");
    async function refreshPic(keep) {
      picSel.innerHTML = `<option value="">-- tidak ada --</option>`;
      if (!kelasSel.value) return;
      const label = kelasOpt.find((o) => String(o.value) === kelasSel.value).label.replace("Kelas ", "");
      const r = await api("api/mahasiswa.php", { params: { kelas: label, limit: 100, sort: "nama", dir: "asc" } });
      picSel.innerHTML += r.data.map((m) => `<option value="${m.id}" ${keep && String(m.id) === String(keep) ? "selected" : ""}>${esc(m.nama)} (${esc(m.nbi)})</option>`).join("");
    }
    kelasSel.addEventListener("change", () => refreshPic());
    if (kelasVal) await refreshPic(picVal);
  }

  document.getElementById("btnAdd").addEventListener("click", async () => {
    const itemOpt = (await loadOptions("api/item.php")).filter((o) => o.tipe !== "RM");
    const m = openModal({
      title: "Buat Work Order", size: "modal-wide",
      body: `<form id="woForm"><div class="form-grid">
        <label>Item (FG/SFG)<select id="w_item" required><option value="">-- pilih --</option>${itemOpt.map((o) => `<option value="${o.value}">${esc(o.label)}</option>`).join("")}</select></label>
        <label>Jumlah<input id="w_qty" type="number" min="0.01" step="0.01" required></label>
        <label>Tanggal Mulai<input id="w_mulai" type="date" value="${todayStr()}" required></label>
        <label>Tanggal Selesai<input id="w_selesai" type="date" required></label>
        <div id="w_kelaspic" style="display:contents"></div>
        <label style="grid-column:1/-1">Catatan<input id="w_catatan" maxlength="255"></label></div>
        <div class="form-actions"><button type="button" class="btn btn-secondary" data-close>Batal</button><button class="btn btn-primary" type="submit">Simpan</button></div></form>`,
    });
    m.el.querySelector("[data-close]").addEventListener("click", m.close);
    await pilihKelasPic(m.el.querySelector("#w_kelaspic"), "", "");
    m.el.querySelector("#woForm").addEventListener("submit", async (e) => {
      e.preventDefault();
      try {
        const r = await api("api/wo.php", { method: "POST", body: {
          item_id: m.el.querySelector("#w_item").value, qty: m.el.querySelector("#w_qty").value,
          tanggal_mulai: m.el.querySelector("#w_mulai").value, tanggal_selesai: m.el.querySelector("#w_selesai").value,
          kelas_id: m.el.querySelector("#w_kelas").value, pic_mahasiswa_id: m.el.querySelector("#w_pic").value,
          catatan: m.el.querySelector("#w_catatan").value,
        } });
        toast(r.message); m.close(); load();
      } catch (err) { toastErr(err); }
    });
  });

  async function openDetail(id) {
    const m = openModal({ title: "Work Order", body: `<div class="loading">Memuat...</div>`, size: "modal-wide" });
    async function draw() {
      const w = (await api("api/wo.php", { params: { id } })).data;
      const aksi = [];
      if (w.status === "planned") { aksi.push(`<button class="btn btn-success" data-a="release">Release</button>`); aksi.push(`<button class="btn btn-primary" data-a="selesai">Tandai Selesai</button>`); aksi.push(`<button class="btn btn-danger" data-a="batal">Batalkan</button>`); }
      if (w.status === "released") { aksi.push(`<button class="btn btn-primary" data-a="selesai">Tandai Selesai</button>`); aksi.push(`<button class="btn btn-danger" data-a="batal">Batalkan</button>`); }
      m.body.innerHTML = `
        <dl class="kv"><dt>No. WO</dt><dd><strong>${esc(w.no_wo)}</strong></dd>
          <dt>Item</dt><dd>${esc(w.item_kode)} — ${esc(w.item_nama)}</dd>
          <dt>Jumlah</dt><dd>${fmtNum(w.qty)} ${esc(w.satuan)}</dd>
          <dt>Periode</dt><dd>${fmtTgl(w.tanggal_mulai)} s/d ${fmtTgl(w.tanggal_selesai)}</dd>
          <dt>Kelas / PIC</dt><dd>${w.kelas_kode ? esc(w.kelas_kode) : "-"} ${w.pic_nama ? `· ${esc(w.pic_nama)} (${esc(w.pic_nbi)})` : ""}</dd>
          <dt>Status</dt><dd>${statusBadge(w.status)}</dd>${w.catatan ? `<dt>Catatan</dt><dd>${esc(w.catatan)}</dd>` : ""}</dl>
        <h3 class="section-title">Kebutuhan Komponen (BOM)</h3>
        <div class="table-wrapper"><table class="table-plain"><thead><tr><th>Komponen</th><th class="right">Stok Saat Ini</th><th class="right">Dibutuhkan</th><th></th></tr></thead><tbody>
          ${w.komponen.length ? w.komponen.map((k) => `<tr><td><strong>${esc(k.kode)}</strong> ${esc(k.nama)}</td><td class="num">${fmtNum(k.stok)} ${esc(k.satuan)}</td><td class="num">${fmtNum(k.dibutuhkan)} ${esc(k.satuan)}</td><td>${Number(k.stok) < Number(k.dibutuhkan) ? badge("Kurang", "red") : badge("Cukup", "green")}</td></tr>`).join("") : `<tr><td colspan="4" class="empty">Item ini tidak punya komponen BOM.</td></tr>`}
        </tbody></table></div>
        ${aksi.length ? `<div class="form-actions">${aksi.join(" ")}</div>` : ""}`;
      m.body.querySelectorAll("[data-a]").forEach((b) => b.addEventListener("click", async () => {
        const act = b.dataset.a;
        const teks = { release: "Release work order ini?", selesai: "Tandai selesai? Komponen BOM akan dipotong dari stok dan hasil produksi ditambahkan ke stok.", batal: "Batalkan work order ini?" };
        if (!(await confirmBox(teks[act], "Ya", act === "batal"))) return;
        try { const r = await api(`api/wo.php?id=${id}`, { method: "POST", params: { action: act } }); toast(r.message); await draw(); load(); } catch (e) { toastErr(e); }
      }));
    }
    draw().catch((e) => { m.body.innerHTML = `<p class="empty">${esc(e.message)}</p>`; });
  }

  load();
})();
