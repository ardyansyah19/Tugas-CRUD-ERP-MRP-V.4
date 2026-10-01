(async function () {
  const app = document.getElementById("app");
  const params = new URLSearchParams(location.search);
  const runId = params.get("run");

  if (runId) return renderDetail(Number(runId));
  return renderList();

  // ---------- Daftar run ----------
  async function renderList() {
    app.innerHTML = `<div class="page-head"><div><h1>MRP</h1><p>Material Requirements Planning: hitung kebutuhan bersih tiap item per minggu dari kebutuhan independen, BOM, stok, dan lead time.</p></div>
      <button class="btn btn-primary" id="btnRun">▶ Jalankan MRP Baru</button></div>
      <div class="card"><div id="list" class="loading">Memuat...</div></div>`;

    async function load() {
      const rows = (await api("api/mrp.php")).data;
      document.getElementById("list").outerHTML = rows.length ? `
        <div class="table-wrapper"><table><thead><tr>
          <th>Kode</th><th>Mulai</th><th>Horizon</th><th>Kelas</th><th>PIC</th><th class="right">Rencana Order</th><th class="right">Belum Dikonversi</th><th>Dibuat</th><th></th>
        </tr></thead><tbody>
          ${rows.map((r) => `<tr>
            <td><a href="mrp.php?run=${r.id}"><strong>${esc(r.kode)}</strong></a></td>
            <td>${fmtTgl(r.tanggal_mulai)}</td><td>${r.horizon_minggu} minggu</td>
            <td>${r.kelas_kode ? statusBadge(r.kelas_kode) : "-"}</td><td>${esc(r.pic_nama || "-")}</td>
            <td class="num">${r.total_planned_order}</td>
            <td class="num">${r.belum_dikonversi ? badge(r.belum_dikonversi, "amber") : badge("0", "green")}</td>
            <td class="small muted">${fmtTgl(r.created_at)}</td>
            <td class="actions"><a class="btn btn-secondary btn-sm" href="mrp.php?run=${r.id}">Lihat</a> <button class="btn btn-danger btn-sm" data-del="${r.id}">Hapus</button></td>
          </tr>`).join("")}
        </tbody></table></div>` : `<div class="empty-state">Belum ada MRP run. Klik "Jalankan MRP Baru" untuk memulai.</div>`;

      document.querySelectorAll("[data-del]").forEach((b) => b.addEventListener("click", async () => {
        if (!(await confirmBox("Hapus hasil MRP ini? PO/WO yang sudah dibuat dari sini tidak ikut terhapus.", "Hapus", true))) return;
        try { const r = await api(`api/mrp.php?id=${b.dataset.del}`, { method: "DELETE" }); toast(r.message); load(); } catch (e) { toastErr(e); }
      }));
    }
    load().catch((e) => (document.getElementById("list").textContent = e.message));

    document.getElementById("btnRun").addEventListener("click", async () => {
      const kelasOpt = await loadOptions("api/kelas.php");
      const m = openModal({
        title: "Jalankan MRP",
        body: `<form id="runForm"><div class="form-grid">
          <label>Tanggal Mulai<input id="r_mulai" type="date" value="${todayStr()}" required></label>
          <label>Horizon (minggu)<input id="r_horizon" type="number" min="4" max="26" value="8" required></label>
          <label>Kelas Penanggung Jawab<select id="r_kelas"><option value="">-- tidak ada --</option>${kelasOpt.map((o) => `<option value="${o.value}">${esc(o.label)}</option>`).join("")}</select></label>
          <label>Mahasiswa PIC<select id="r_pic"><option value="">-- tidak ada --</option></select></label>
          <label style="grid-column:1/-1">Catatan<input id="r_catatan" maxlength="255"></label></div>
          <div class="hint-box">Tanggal mulai akan dibulatkan ke hari Senin pada minggu tersebut (minggu ke-1). Menjalankan MRP akan membuat snapshot baru; hasil sebelumnya tidak berubah.</div>
          <div class="form-actions"><button type="button" class="btn btn-secondary" data-close>Batal</button><button class="btn btn-primary" type="submit">Jalankan</button></div></form>`,
      });
      m.el.querySelector("[data-close]").addEventListener("click", m.close);
      const kelasSel = m.el.querySelector("#r_kelas");
      const picSel = m.el.querySelector("#r_pic");
      kelasSel.addEventListener("change", async () => {
        picSel.innerHTML = `<option value="">-- tidak ada --</option>`;
        if (!kelasSel.value) return;
        const r = await api("api/mahasiswa.php", { params: { kelas: kelasOpt.find((o) => String(o.value) === kelasSel.value).label.replace("Kelas ", ""), limit: 100, sort: "nama", dir: "asc" } });
        picSel.innerHTML += r.data.map((m2) => `<option value="${m2.id}">${esc(m2.nama)} (${esc(m2.nbi)})</option>`).join("");
      });
      m.el.querySelector("#runForm").addEventListener("submit", async (e) => {
        e.preventDefault();
        try {
          const r = await api("api/mrp.php", { method: "POST", params: { action: "run" }, body: {
            tanggal_mulai: m.el.querySelector("#r_mulai").value, horizon_minggu: m.el.querySelector("#r_horizon").value,
            kelas_id: kelasSel.value, pic_mahasiswa_id: picSel.value, catatan: m.el.querySelector("#r_catatan").value,
          } });
          toast(r.message); location.href = `mrp.php?run=${r.data.id}`;
        } catch (err) { toastErr(err); }
      });
    });
  }

  // ---------- Detail run ----------
  async function renderDetail(id) {
    app.innerHTML = `<div class="loading">Memuat hasil MRP...</div>`;
    let d;
    try { d = (await api("api/mrp.php", { params: { id } })).data; } catch (e) { app.innerHTML = `<div class="card empty">${esc(e.message)}</div>`; return; }
    const run = d.run;

    app.innerHTML = `
      <div class="page-head">
        <div><h1>${esc(run.kode)}</h1><p>Mulai ${fmtTgl(run.tanggal_mulai)} · Horizon ${run.horizon_minggu} minggu
          ${run.kelas_kode ? ` · Kelas ${esc(run.kelas_kode)}` : ""}${run.pic_nama ? ` · PIC ${esc(run.pic_nama)} (${esc(run.pic_nbi)})` : ""}
          ${run.catatan ? ` · ${esc(run.catatan)}` : ""}</p></div>
        <div><a class="btn btn-secondary" href="mrp.php">← Daftar MRP</a> <a class="btn btn-secondary" href="api/export_mrp.php?run_id=${run.id}">⬇ Export CSV</a></div>
      </div>
      <div class="tabs">
        <button class="tab active" data-tab="grid">Tabel MRP per Item</button>
        <button class="tab" data-tab="orders">Rencana Order (${d.orders.length})</button>
      </div>
      <div id="tabGrid"></div>
      <div id="tabOrders" class="hidden"></div>`;

    app.querySelectorAll(".tab").forEach((t) => t.addEventListener("click", () => {
      app.querySelectorAll(".tab").forEach((x) => x.classList.remove("active"));
      t.classList.add("active");
      document.getElementById("tabGrid").classList.toggle("hidden", t.dataset.tab !== "grid");
      document.getElementById("tabOrders").classList.toggle("hidden", t.dataset.tab !== "orders");
    }));

    // ---- Grid time-phased per item ----
    const label = { gross: "Gross Requirement", scheduled_receipt: "Scheduled Receipt", proj_on_hand: "Proj. On Hand", net_req: "Net Requirement", planned_receipt: "Planned Receipt", planned_release: "Planned Release" };
    const cellCls = { net_req: "row-net", planned_receipt: "row-plan", planned_release: "row-plan" };
    document.getElementById("tabGrid").innerHTML = `
      <div class="legend">LLC = Low-Level Code (0 = barang jadi, makin besar makin dalam levelnya). Diurutkan dari barang jadi ke bahan baku.</div>
      ${d.items.map((it) => `
      <div class="mrp-item">
        <div class="mrp-item-head">
          <strong>${esc(it.kode)} — ${esc(it.nama)}</strong>
          <span>${statusBadge(it.tipe)} ${badge("LLC " + it.level_bom, "gray")} <span class="small muted">LT ${it.lead_time_minggu}mg · ${it.lot_sizing}${it.lot_sizing === "FOQ" ? " " + fmtNum(it.ukuran_lot, 0) : it.lot_sizing === "POQ" ? " " + it.periode_poq + "mg" : ""} · SS ${fmtNum(it.stok_pengaman)} · Awal ${fmtNum(it.stok_awal)} ${esc(it.satuan)}</span></span>
        </div>
        <div class="mrp-scroll"><table class="mrp">
          <thead><tr><th>Minggu</th>${it.minggu.map((w) => `<th>${w.minggu}<br><span class="small muted">${fmtTglPendek(w.tanggal_awal)}</span></th>`).join("")}</tr></thead>
          <tbody>
            ${["gross", "scheduled_receipt", "proj_on_hand", "net_req", "planned_receipt", "planned_release"].map((k) => `
              <tr class="${cellCls[k] || ""}"><td>${label[k]}</td>${it.minggu.map((w) => `<td class="${Number(w[k]) === 0 ? "zero" : ""}">${fmtNum(w[k])}</td>`).join("")}</tr>`).join("")}
          </tbody>
        </table></div>
      </div>`).join("")}`;

    // ---- Rencana order + konversi ----
    const belum = d.orders.filter((o) => o.status === "planned");
    document.getElementById("tabOrders").innerHTML = `
      ${belum.length ? `<div class="toolbar"><button class="btn btn-primary" id="btnConvertAll">Konversi Semua yang Dipilih ke PO / WO</button><span class="muted small" id="convInfo"></span></div>` : ""}
      <div class="table-wrapper"><table><thead><tr>
        ${belum.length ? `<th class="check-col"><input type="checkbox" id="chkAll"></th>` : ""}
        <th>Item</th><th>Jenis</th><th class="right">Jumlah</th><th>Rilis (minggu)</th><th>Dibutuhkan</th><th class="right">Estimasi Nilai</th><th>Status</th><th>Referensi</th>
      </tr></thead><tbody>
        ${d.orders.length ? d.orders.map((o) => `<tr>
          ${belum.length ? `<td class="check-col">${o.status === "planned" ? `<input type="checkbox" class="chk" value="${o.id}">` : ""}</td>` : ""}
          <td><strong>${esc(o.kode)}</strong> ${esc(o.nama)}</td>
          <td>${statusBadge(o.jenis)}</td>
          <td class="num">${fmtNum(o.qty)} ${esc(o.satuan)}</td>
          <td>Mg ${o.minggu_release} · ${fmtTgl(o.tanggal_release)}</td>
          <td>${fmtTgl(o.tanggal_butuh)} ${Number(o.terlambat) ? badge("Sudah lewat", "red") : ""}</td>
          <td class="num">${fmtRp(o.estimasi_nilai)}</td>
          <td>${statusBadge(o.status)}</td>
          <td class="small">${o.no_po ? `PO ${esc(o.no_po)}` : o.no_wo ? `WO ${esc(o.no_wo)}` : o.jenis === "PO" ? esc(o.supplier_nama || "⚠ tanpa supplier") : "-"}</td>
        </tr>`).join("") : `<tr><td colspan="8" class="empty">Tidak ada rencana order (semua kebutuhan sudah tercukupi).</td></tr>`}
      </tbody></table></div>`;

    if (belum.length) {
      const chkAll = document.getElementById("chkAll");
      const chks = () => Array.from(document.querySelectorAll(".chk"));
      const info = document.getElementById("convInfo");
      const refresh = () => { info.textContent = `${chks().filter((c) => c.checked).length} dipilih`; };
      chkAll.addEventListener("change", () => { chks().forEach((c) => (c.checked = chkAll.checked)); refresh(); });
      chks().forEach((c) => c.addEventListener("change", refresh));
      refresh();
      document.getElementById("btnConvertAll").addEventListener("click", async () => {
        const ids = chks().filter((c) => c.checked).map((c) => Number(c.value));
        if (!ids.length) return toast("Pilih minimal satu rencana order.", "error");
        if (!(await confirmBox(`Konversi ${ids.length} rencana order terpilih menjadi PO (draft) / Work Order (planned)?`))) return;
        try { const r = await api("api/mrp.php", { method: "POST", params: { action: "convert" }, body: { run_id: run.id, order_ids: ids } }); toast(r.message); renderDetail(id); } catch (e) { toastErr(e); }
      });
    }
  }
})();
