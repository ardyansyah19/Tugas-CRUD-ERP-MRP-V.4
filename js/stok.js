(async function () {
  const app = document.getElementById("app");
  const state = { search: "", tipe: "", status: "" };

  app.innerHTML = `
    <div class="page-head"><div><h1>Stok</h1><p>Saldo stok tiap item. Perubahan normal terjadi otomatis lewat penerimaan PO dan penyelesaian Work Order; gunakan penyesuaian manual hanya untuk koreksi (stok opname, kerusakan, dsb).</p></div></div>
    <main class="card">
      <div class="toolbar">
        <input type="search" id="sSearch" placeholder="Cari kode atau nama item...">
        <select id="sTipe"><option value="">Semua tipe</option><option value="FG">FG</option><option value="SFG">SFG</option><option value="RM">RM</option></select>
        <select id="sStatus"><option value="">Semua status stok</option><option value="aman">Aman</option><option value="kritis">Kritis</option><option value="habis">Habis</option></select>
      </div>
      <div class="table-wrapper"><table><thead><tr><th>Kode</th><th>Nama</th><th>Tipe</th><th class="right">Stok</th><th class="right">Stok Pengaman</th><th class="right">Nilai Stok</th><th>Status</th><th></th></tr></thead><tbody id="sBody"></tbody></table></div>
    </main>`;

  const body = document.getElementById("sBody");
  async function load() {
    body.innerHTML = `<tr><td colspan="8" class="loading">Memuat...</td></tr>`;
    try {
      const rows = (await api("api/stok.php", { params: state })).data;
      body.innerHTML = rows.length ? rows.map((i) => `<tr>
        <td><strong>${esc(i.kode)}</strong></td><td>${esc(i.nama)}</td><td>${statusBadge(i.tipe)}</td>
        <td class="num">${fmtNum(i.stok)} ${esc(i.satuan)}</td><td class="num">${fmtNum(i.stok_pengaman)} ${esc(i.satuan)}</td>
        <td class="num">${fmtRp(i.nilai_stok)}</td><td>${statusBadge(i.status_stok)}</td>
        <td class="actions"><button class="btn btn-secondary btn-sm" data-riwayat="${i.id}">Riwayat</button> <button class="btn btn-warning btn-sm" data-sesuai="${i.id}" data-nama="${esc(i.nama)}" data-satuan="${esc(i.satuan)}">Sesuaikan</button></td>
      </tr>`).join("") : `<tr><td colspan="8" class="empty">Data tidak ditemukan.</td></tr>`;
      body.querySelectorAll("[data-riwayat]").forEach((b) => b.addEventListener("click", () => openRiwayat(Number(b.dataset.riwayat))));
      body.querySelectorAll("[data-sesuai]").forEach((b) => b.addEventListener("click", () => openSesuaikan(Number(b.dataset.sesuai), b.dataset.nama, b.dataset.satuan)));
    } catch (e) { body.innerHTML = `<tr><td colspan="8" class="empty">${esc(e.message)}</td></tr>`; }
  }
  document.getElementById("sSearch").addEventListener("input", debounce((e) => { state.search = e.target.value.trim(); load(); }));
  document.getElementById("sTipe").addEventListener("change", (e) => { state.tipe = e.target.value; load(); });
  document.getElementById("sStatus").addEventListener("change", (e) => { state.status = e.target.value; load(); });

  const TIPE_MUTASI = { masuk: "green", keluar: "red", penyesuaian: "amber" };
  async function openRiwayat(itemId, page = 1) {
    const m = openModal({ title: "Riwayat Mutasi Stok", body: `<div class="loading">Memuat...</div>`, size: "modal-wide" });
    async function draw(p) {
      const r = await api("api/stok.php", { params: { action: "mutasi", item_id: itemId, page: p, limit: 15 } });
      m.body.innerHTML = `<div class="table-wrapper"><table class="table-plain"><thead><tr><th>Waktu</th><th>Tipe</th><th class="right">Jumlah</th><th class="right">Saldo Setelah</th><th>Referensi</th><th>Keterangan</th></tr></thead><tbody>
        ${r.data.length ? r.data.map((x) => `<tr><td class="small">${fmtTgl(x.created_at)}</td><td>${badge(x.tipe, TIPE_MUTASI[x.tipe])}</td>
          <td class="num">${Number(x.qty) > 0 ? "+" : ""}${fmtNum(x.qty)}</td><td class="num">${fmtNum(x.stok_setelah)}</td>
          <td class="small">${x.ref_tipe ? `${esc(x.ref_tipe)}${x.ref_id ? " #" + x.ref_id : ""}` : "-"}</td><td class="small">${esc(x.keterangan || "-")}</td></tr>`).join("") : `<tr><td colspan="6" class="empty">Belum ada mutasi.</td></tr>`}
      </tbody></table></div><div class="pagination" id="rPager"></div>`;
      renderPager(m.body.querySelector("#rPager"), r.meta, (pg) => draw(pg));
    }
    draw(page).catch((e) => { m.body.innerHTML = `<p class="empty">${esc(e.message)}</p>`; });
  }

  function openSesuaikan(itemId, nama, satuan) {
    const m = openModal({
      title: `Sesuaikan Stok — ${nama}`,
      body: `<form id="adjForm"><div class="form-grid">
        <label>Perubahan (${esc(satuan)})<input id="a_qty" type="number" step="0.01" required placeholder="mis. 5 atau -3"></label>
        <label style="grid-column:1/-1">Keterangan<input id="a_ket" maxlength="255" required placeholder="mis. Hasil stok opname"></label></div>
        <div class="hint-box">Gunakan angka positif untuk menambah stok, negatif untuk mengurangi. Setiap penyesuaian tercatat di riwayat mutasi.</div>
        <div class="form-actions"><button type="button" class="btn btn-secondary" data-close>Batal</button><button class="btn btn-primary" type="submit">Simpan</button></div></form>`,
    });
    m.el.querySelector("[data-close]").addEventListener("click", m.close);
    m.el.querySelector("#adjForm").addEventListener("submit", async (e) => {
      e.preventDefault();
      try {
        const r = await api("api/stok.php", { method: "POST", body: { item_id: itemId, qty: m.el.querySelector("#a_qty").value, keterangan: m.el.querySelector("#a_ket").value } });
        toast(r.message); m.close(); load();
      } catch (err) { toastErr(err); }
    });
  }

  load();
})();
