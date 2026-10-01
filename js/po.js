(async function () {
  const app = document.getElementById("app");
  const state = { page: 1, limit: 10, search: "", status: "" };

  app.innerHTML = `
    <div class="page-head"><div><h1>Purchase Order</h1><p>PO umumnya dibuat otomatis dari hasil MRP (menu MRP → tab Rencana Order → Konversi). PO diterbitkan lalu diterima untuk menambah stok bahan baku.</p></div></div>
    <main class="card">
      <div class="toolbar">
        <input type="search" id="pSearch" placeholder="Cari no. PO atau supplier...">
        <select id="pStatus"><option value="">Semua status</option><option value="draft">Draft</option><option value="open">Open</option><option value="diterima">Diterima</option><option value="batal">Batal</option></select>
        <select id="pLimit"><option value="10">10 / halaman</option><option value="25">25</option><option value="50">50</option></select>
      </div>
      <div class="table-wrapper"><table><thead><tr><th>No. PO</th><th>Tanggal</th><th>Supplier</th><th class="right">Item</th><th class="right">Total</th><th>Status</th><th>Kelas</th><th></th></tr></thead><tbody id="pBody"></tbody></table></div>
      <div class="pagination" id="pPager"></div>
    </main>`;

  const body = document.getElementById("pBody");
  async function load() {
    body.innerHTML = `<tr><td colspan="8" class="loading">Memuat...</td></tr>`;
    try {
      const r = await api("api/po.php", { params: { page: state.page, limit: state.limit, search: state.search, status: state.status } });
      body.innerHTML = r.data.length ? r.data.map((p) => `<tr>
        <td><a href="#" data-view="${p.id}"><strong>${esc(p.no_po)}</strong></a></td>
        <td>${fmtTgl(p.tanggal_po)}</td><td>${esc(p.supplier_nama)}</td>
        <td class="num">${p.jumlah_item}</td><td class="num">${fmtRp(p.total)}</td>
        <td>${statusBadge(p.status)}</td><td>${p.kelas_kode ? statusBadge(p.kelas_kode) : "-"}</td>
        <td class="actions"><button class="btn btn-secondary btn-sm" data-view="${p.id}">Detail</button></td>
      </tr>`).join("") : `<tr><td colspan="8" class="empty">Belum ada purchase order.</td></tr>`;
      renderPager(document.getElementById("pPager"), r.meta, (pg) => { state.page = pg; load(); });
      body.querySelectorAll("[data-view]").forEach((b) => b.addEventListener("click", (e) => { e.preventDefault(); openDetail(Number(b.dataset.view)); }));
    } catch (e) { body.innerHTML = `<tr><td colspan="8" class="empty">${esc(e.message)}</td></tr>`; }
  }
  document.getElementById("pSearch").addEventListener("input", debounce((e) => { state.search = e.target.value.trim(); state.page = 1; load(); }));
  document.getElementById("pStatus").addEventListener("change", (e) => { state.status = e.target.value; state.page = 1; load(); });
  document.getElementById("pLimit").addEventListener("change", (e) => { state.limit = Number(e.target.value); state.page = 1; load(); });

  async function openDetail(id) {
    const m = openModal({ title: "Purchase Order", body: `<div class="loading">Memuat...</div>`, size: "modal-wide" });
    async function draw() {
      const p = (await api("api/po.php", { params: { id } })).data;
      const totalStr = fmtRp(p.total);
      const aksi = [];
      if (p.status === "draft") { aksi.push(`<button class="btn btn-success" data-a="terbitkan">Terbitkan</button>`); aksi.push(`<button class="btn btn-danger" data-a="batal">Batalkan</button>`); }
      if (p.status === "open") { aksi.push(`<button class="btn btn-success" data-a="terima">Terima Barang</button>`); aksi.push(`<button class="btn btn-danger" data-a="batal">Batalkan</button>`); }
      m.body.innerHTML = `
        <dl class="kv"><dt>No. PO</dt><dd><strong>${esc(p.no_po)}</strong></dd><dt>Tanggal</dt><dd>${fmtTgl(p.tanggal_po)}</dd>
          <dt>Supplier</dt><dd>${esc(p.supplier.nama)} ${p.supplier.telepon ? `· ${esc(p.supplier.telepon)}` : ""}</dd>
          <dt>Status</dt><dd>${statusBadge(p.status)}</dd>${p.catatan ? `<dt>Catatan</dt><dd>${esc(p.catatan)}</dd>` : ""}</dl>
        <div class="table-wrapper"><table class="table-plain"><thead><tr><th>Item</th><th class="right">Qty</th><th class="right">Diterima</th><th class="right">Harga</th><th class="right">Subtotal</th><th>Estimasi Terima</th></tr></thead><tbody>
          ${p.detail.map((d) => `<tr><td><strong>${esc(d.kode)}</strong> ${esc(d.nama)}</td><td class="num">${fmtNum(d.qty)} ${esc(d.satuan)}</td><td class="num">${fmtNum(d.qty_diterima)}</td><td class="num">${fmtRp(d.harga)}</td><td class="num">${fmtRp(d.subtotal)}</td><td>${fmtTgl(d.tanggal_terima)}</td></tr>`).join("")}
        </tbody><tfoot><tr><td colspan="4" class="right"><strong>Total</strong></td><td class="num"><strong>${totalStr}</strong></td><td></td></tr></tfoot></table></div>
        ${aksi.length ? `<div class="form-actions">${aksi.join(" ")}</div>` : ""}`;
      m.body.querySelectorAll("[data-a]").forEach((b) => b.addEventListener("click", async () => {
        const act = b.dataset.a;
        const teks = { terbitkan: "Terbitkan PO ini?", terima: "Tandai barang sudah diterima? Stok bahan baku akan bertambah otomatis.", batal: "Batalkan PO ini?" };
        if (!(await confirmBox(teks[act], "Ya", act === "batal"))) return;
        try { const r = await api(`api/po.php?id=${id}`, { method: "POST", params: { action: act } }); toast(r.message); await draw(); load(); } catch (e) { toastErr(e); }
      }));
    }
    draw().catch((e) => { m.body.innerHTML = `<p class="empty">${esc(e.message)}</p>`; });
  }

  load();
})();
