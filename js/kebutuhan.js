crudPage({
  title: "Kebutuhan Independen (MPS)",
  subtitle: "Pesanan pelanggan atau forecast untuk barang jadi. Ini adalah input utama MRP: kebutuhan komponen dihitung dari sini melalui BOM. Tanggal dibulatkan ke minggu ke-n sejak awal horizon MRP.",
  api: "api/kebutuhan.php", addLabel: "+ Tambah Kebutuhan", searchPlaceholder: "Cari item, referensi, keterangan...",
  defaultSort: "tanggal_dibutuhkan", deleteLabel: (r) => `kebutuhan ${r.item_nama} (${fmtTgl(r.tanggal_dibutuhkan)})`,
  filters: [{ key: "jenis", label: "Semua jenis", options: [{ value: "pesanan", label: "Pesanan" }, { value: "forecast", label: "Forecast" }] }],
  columns: [
    { key: "tanggal_dibutuhkan", label: "Tanggal", sort: "tanggal_dibutuhkan", render: (r) => fmtTgl(r.tanggal_dibutuhkan) },
    { key: "item", label: "Item", sort: "item", render: (r) => `<strong>${esc(r.item_kode)}</strong> ${esc(r.item_nama)}` },
    { key: "qty", label: "Jumlah", sort: "qty", num: true, render: (r) => `${fmtNum(r.qty)} ${esc(r.satuan_kode)}` },
    { key: "jenis", label: "Jenis", sort: "jenis", render: (r) => statusBadge(r.jenis) },
    { key: "no_referensi", label: "Referensi" },
    { key: "keterangan", label: "Keterangan" },
  ],
  fields: [
    { key: "item_id", label: "Item", type: "select", optionsUrl: "api/item.php", required: true, hint: "Umumnya barang jadi (FG)." },
    { key: "tanggal_dibutuhkan", label: "Tanggal Dibutuhkan", type: "date", required: true },
    { key: "qty", label: "Jumlah", type: "number", step: "0.01", min: 0.01, required: true },
    { key: "jenis", label: "Jenis", type: "select", required: true, options: [{ value: "pesanan", label: "Pesanan" }, { value: "forecast", label: "Forecast" }] },
    { key: "no_referensi", label: "No. Referensi", max: 50, placeholder: "SO-0005" },
    { key: "keterangan", label: "Keterangan", max: 255 },
  ],
  defaults: { jenis: "pesanan" },
});
