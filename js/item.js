const itemPage = crudPage({
  title: "Item & BOM",
  subtitle: "Master bahan baku (RM), barang setengah jadi (SFG), dan barang jadi (FG) beserta parameter MRP. Klik <b>BOM</b> untuk mengatur komponen penyusun.",
  api: "api/item.php", addLabel: "+ Tambah Item", searchPlaceholder: "Cari kode atau nama item...",
  defaultSort: "kode", deleteLabel: (r) => `item "${r.nama}"`, modalSize: "modal-wide",
  filters: [{ key: "tipe", label: "Semua tipe", options: [{ value: "FG", label: "FG - Barang jadi" }, { value: "SFG", label: "SFG - Setengah jadi" }, { value: "RM", label: "RM - Bahan baku" }] }],
  columns: [
    { key: "kode", label: "Kode", sort: "kode" },
    { key: "nama", label: "Nama", sort: "nama" },
    { key: "tipe", label: "Tipe", sort: "tipe", render: (r) => statusBadge(r.tipe) },
    { key: "stok", label: "Stok", sort: "stok", num: true, render: (r) => `${fmtNum(r.stok)} ${esc(r.satuan_kode)}` },
    { key: "lead_time_minggu", label: "LT (mgg)", sort: "lead_time_minggu", num: true },
    { key: "lot_sizing", label: "Lot", render: (r) => r.lot_sizing === "FOQ" ? `FOQ ${fmtNum(r.ukuran_lot, 0)}` : r.lot_sizing === "POQ" ? `POQ ${r.periode_poq} mgg` : "L4L" },
    { key: "pengadaan", label: "Sumber", render: (r) => r.pengadaan === "beli" ? esc(r.supplier_nama || "⚠ tanpa supplier") : "Produksi" },
    { key: "aktif", label: "Status", render: (r) => (Number(r.aktif) ? badge("Aktif", "green") : badge("Nonaktif", "gray")) },
  ],
  actions: (r) => (r.tipe === "RM" ? [] : [{ label: `BOM (${r.jumlah_komponen})`, cls: "btn-info", onClick: (row) => openBom(row.id) }]),
  fields: [
    { key: "kode", label: "Kode", required: true, max: 30, placeholder: "RM-008" },
    { key: "nama", label: "Nama Item", required: true, max: 120 },
    { key: "tipe", label: "Tipe", type: "select", required: true, options: [{ value: "FG", label: "FG - Barang jadi" }, { value: "SFG", label: "SFG - Setengah jadi" }, { value: "RM", label: "RM - Bahan baku" }], hint: "RM otomatis dibeli, FG/SFG otomatis diproduksi." },
    { key: "satuan_id", label: "Satuan", type: "select", optionsUrl: "api/satuan.php", required: true },
    { key: "supplier_id", label: "Supplier Default", type: "select", optionsUrl: "api/supplier.php", emptyLabel: "-- tidak ada --", showIf: (v) => v.tipe === "RM", hint: "Wajib agar rencana order bisa dikonversi menjadi PO." },
    { key: "lead_time_minggu", label: "Lead Time (minggu)", type: "number", min: 0, step: 1, default: 1 },
    { key: "lot_sizing", label: "Lot Sizing", type: "select", required: true, options: [{ value: "L4L", label: "L4L - Lot for Lot" }, { value: "FOQ", label: "FOQ - Fixed Order Qty" }, { value: "POQ", label: "POQ - Periodic Order Qty" }] },
    { key: "ukuran_lot", label: "Ukuran Lot (FOQ)", type: "number", min: 0, step: "0.01", showIf: (v) => v.lot_sizing === "FOQ", default: 0 },
    { key: "periode_poq", label: "Periode POQ (minggu)", type: "number", min: 1, step: 1, showIf: (v) => v.lot_sizing === "POQ", default: 1 },
    { key: "stok_pengaman", label: "Stok Pengaman", type: "number", min: 0, step: "0.01", default: 0 },
    { key: "harga_standar", label: "Harga Standar (Rp)", type: "number", min: 0, step: "0.01", default: 0 },
    { key: "stok_awal", label: "Stok Awal", type: "number", min: 0, step: "0.01", createOnly: true, default: 0, hint: "Hanya saat membuat item. Selanjutnya ubah lewat menu Stok." },
    { key: "aktif", label: "Status", type: "checkbox", checkLabel: "Aktif (ikut perhitungan MRP)" },
  ],
  defaults: { tipe: "RM", lot_sizing: "L4L", lead_time_minggu: 1, aktif: 1, ukuran_lot: 0, periode_poq: 1, stok_pengaman: 0, harga_standar: 0 },
});

// ---------- Editor BOM ----------
async function openBom(itemId) {
  const m = openModal({ title: "Bill of Materials", body: `<div class="loading">Memuat...</div>`, size: "modal-wide" });

  async function draw() {
    const d = (await api("api/bom.php", { params: { parent_id: itemId } })).data;
    const semua = await loadOptions("api/item.php");
    const kandidat = semua.filter((o) => Number(o.value) !== Number(itemId) && o.tipe !== "FG");
    m.body.innerHTML = `
      <p><strong>${esc(d.induk.kode)}</strong> — ${esc(d.induk.nama)} ${statusBadge(d.induk.tipe)}<span class="muted"> · kebutuhan komponen per 1 ${esc(d.induk.satuan)}</span></p>
      <div class="table-wrapper"><table><thead><tr><th>Komponen</th><th>Tipe</th><th class="right">Qty per</th><th class="right">Scrap %</th><th>Catatan</th><th></th></tr></thead><tbody>
        ${d.komponen.length ? d.komponen.map((k) => `<tr>
          <td><strong>${esc(k.kode)}</strong> ${esc(k.nama)}</td><td>${statusBadge(k.tipe)}</td>
          <td class="num"><input class="mini" data-q="${k.id}" type="number" step="0.0001" min="0.0001" value="${Number(k.qty_per)}" style="width:90px"> ${esc(k.satuan)}</td>
          <td class="num"><input class="mini" data-s="${k.id}" type="number" step="0.01" min="0" max="100" value="${Number(k.scrap_persen)}" style="width:70px"></td>
          <td>${esc(k.catatan || "")}</td>
          <td class="actions"><button class="btn btn-secondary btn-sm" data-save="${k.id}">Simpan</button> <button class="btn btn-danger btn-sm" data-del="${k.id}">Hapus</button></td></tr>`).join("")
          : `<tr><td colspan="6" class="empty">Belum ada komponen.</td></tr>`}
      </tbody></table></div>
      <h3 class="section-title mt">Tambah komponen</h3>
      <form id="bomForm" class="inline-form">
        <label>Komponen<select id="b_child" required><option value="">-- pilih --</option>${kandidat.map((o) => `<option value="${o.value}">${esc(o.label)}</option>`).join("")}</select></label>
        <label>Qty per<input id="b_qty" type="number" step="0.0001" min="0.0001" required></label>
        <label>Scrap %<input id="b_scrap" type="number" step="0.01" min="0" max="100" value="0"></label>
        <label>Catatan<input id="b_cat" maxlength="255"></label>
        <label><button class="btn btn-primary" type="submit">+ Tambah</button></label>
      </form>
      <h3 class="section-title mt">Ledakan BOM (kebutuhan total untuk 1 ${esc(d.induk.satuan)} ${esc(d.induk.kode)})</h3>
      <div class="table-wrapper"><table class="table-plain"><thead><tr><th>Komponen</th><th>Tipe</th><th class="right">Kebutuhan total</th></tr></thead><tbody>
        ${d.pohon.length ? d.pohon.map((p) => `<tr class="tree-l${Math.min(p.level, 4)}"><td>${p.level > 1 ? "↳ " : ""}<strong>${esc(p.kode)}</strong> ${esc(p.nama)}</td><td>${statusBadge(p.tipe)}</td><td class="num">${fmtNum(p.qty_total, 4)} ${esc(p.satuan)}</td></tr>`).join("") : `<tr><td colspan="3" class="empty">-</td></tr>`}
      </tbody></table></div>`;

    m.body.querySelector("#bomForm").addEventListener("submit", async (e) => {
      e.preventDefault();
      try {
        await api("api/bom.php", { method: "POST", body: { parent_item_id: itemId, child_item_id: m.body.querySelector("#b_child").value, qty_per: m.body.querySelector("#b_qty").value, scrap_persen: m.body.querySelector("#b_scrap").value, catatan: m.body.querySelector("#b_cat").value } });
        toast("Komponen BOM ditambahkan."); await draw(); itemPage.reload();
      } catch (err) { toastErr(err); }
    });
    m.body.querySelectorAll("[data-save]").forEach((b) => b.addEventListener("click", async () => {
      const id = b.dataset.save;
      try {
        await api(`api/bom.php?id=${id}`, { method: "PUT", body: { qty_per: m.body.querySelector(`[data-q="${id}"]`).value, scrap_persen: m.body.querySelector(`[data-s="${id}"]`).value } });
        toast("Komponen diperbarui."); draw();
      } catch (err) { toastErr(err); }
    }));
    m.body.querySelectorAll("[data-del]").forEach((b) => b.addEventListener("click", async () => {
      if (!(await confirmBox("Hapus komponen ini dari BOM?", "Hapus", true))) return;
      try { await api(`api/bom.php?id=${b.dataset.del}`, { method: "DELETE" }); toast("Komponen dihapus."); await draw(); itemPage.reload(); } catch (err) { toastErr(err); }
    }));
  }
  draw().catch((e) => { m.body.innerHTML = `<p class="empty">${esc(e.message)}</p>`; });
}
