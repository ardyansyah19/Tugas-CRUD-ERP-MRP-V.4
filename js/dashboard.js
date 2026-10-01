(async function () {
  const app = document.getElementById("app");
  app.innerHTML = `<div class="page-head"><div><h1>Dashboard</h1><p>Ringkasan data akademik dan operasional MRP-ERP.</p></div></div><div id="dash" class="loading">Memuat...</div>`;
  try {
    const d = (await api("api/dashboard.php")).data;
    const kartu = (v, l, href) => `<a class="stat-card" ${href ? `href="${href}" style="text-decoration:none"` : ""}><span class="stat-value">${v}</span><span class="stat-label">${l}</span></a>`;
    const mrp = d.mrp_terakhir;
    document.getElementById("dash").outerHTML = `
      <section class="stats-grid">
        ${kartu(fmtNum(d.mahasiswa, 0), "Total Mahasiswa", "index.php")}
        ${kartu(fmtNum(d.item, 0), "Item Aktif (" + d.item_per_tipe.map((t) => t.tipe + " " + t.jumlah).join(", ") + ")", "item.php")}
        ${kartu(fmtNum(d.stok_kritis, 0), "Item Stok di Bawah Pengaman", "stok.php")}
        ${kartu(fmtRp(d.nilai_stok), "Nilai Stok (harga standar)", "stok.php")}
        ${kartu(fmtNum(d.po_aktif, 0), "PO Aktif (draft/open)", "po.php")}
        ${kartu(fmtNum(d.wo_aktif, 0), "Work Order Aktif", "wo.php")}
        ${kartu(fmtNum(d.planned_belum_konversi, 0), "Rencana Order Belum Dikonversi", "mrp.php")}
      </section>
      <div class="grid-2">
        <div class="card"><h3 class="section-title">Mahasiswa per Kelas</h3>
          ${d.per_kelas.map((k) => `<div style="margin-bottom:12px"><div style="display:flex;justify-content:space-between"><strong>${esc(k.nama)}</strong><span class="muted">${k.jumlah_mahasiswa} / ${k.kapasitas}</span></div>
            <div class="progress"><span style="width:${Math.min(100, (k.jumlah_mahasiswa / k.kapasitas) * 100)}%"></span></div></div>`).join("")}
        </div>
        <div class="card"><h3 class="section-title">MRP Terakhir</h3>
          ${mrp ? `<dl class="kv"><dt>Kode</dt><dd>${esc(mrp.kode)}</dd><dt>Mulai</dt><dd>${fmtTgl(mrp.tanggal_mulai)}</dd><dt>Horizon</dt><dd>${mrp.horizon_minggu} minggu</dd>
              <dt>Kelas</dt><dd>${esc(mrp.kelas_kode || "-")}</dd><dt>Rencana order</dt><dd>${mrp.total_planned_order}</dd></dl>
              <a class="btn btn-primary" href="mrp.php?run=${mrp.id}">Buka hasil</a>`
            : `<p class="muted">Belum pernah menjalankan MRP.</p><a class="btn btn-primary" href="mrp.php">Jalankan MRP</a>`}
        </div>
      </div>
      <div class="card mt"><h3 class="section-title">Item di Bawah Stok Pengaman</h3>
        ${d.item_kritis.length ? `<div class="table-wrapper"><table class="table-plain"><thead><tr><th>Kode</th><th>Nama</th><th class="right">Stok</th><th class="right">Stok Pengaman</th></tr></thead><tbody>
          ${d.item_kritis.map((i) => `<tr><td>${esc(i.kode)}</td><td>${esc(i.nama)}</td><td class="num">${fmtNum(i.stok)} ${esc(i.satuan)}</td><td class="num">${fmtNum(i.stok_pengaman)} ${esc(i.satuan)}</td></tr>`).join("")}</tbody></table></div>`
          : `<p class="muted">Semua item berada di atas stok pengaman.</p>`}
      </div>`;
  } catch (e) { document.getElementById("dash").textContent = e.message; }
})();
