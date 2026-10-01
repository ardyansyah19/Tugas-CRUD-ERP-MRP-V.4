(async function () {
  const app = document.getElementById("app");

  async function render() {
    const r = await api("api/kelas.php");
    const tanpa = r.meta.tanpa_kelas;
    app.innerHTML = `
      <div class="page-head">
        <div><h1>Kelas</h1><p>Kelas ditentukan otomatis dari rentang NBI. Mahasiswa yang ditambah atau diubah NBI-nya akan dipetakan ke kelas yang sesuai. Kelas juga dipakai sebagai penanggung jawab pada MRP, PO, dan Work Order.</p></div>
        <button class="btn btn-secondary" id="btnSync">↻ Sinkronkan kelas mahasiswa</button>
      </div>
      ${tanpa ? `<div class="warn-box">${tanpa} mahasiswa memiliki NBI di luar semua rentang kelas. <a href="index.php?kelas=-">Lihat daftarnya</a>.</div>` : ""}
      <div class="kelas-grid">
        ${r.data.map((k) => {
          const pct = Math.min(100, (k.jumlah_mahasiswa / k.kapasitas) * 100);
          return `<div class="kelas-card">
            <h3>${esc(k.nama)}</h3>
            <div class="big">${k.jumlah_mahasiswa}<span class="muted small"> / ${k.kapasitas} mahasiswa</span></div>
            <div class="progress"><span style="width:${pct}%"></span></div>
            <div class="small muted">NBI ${esc(k.nbi_awal)} – ${esc(k.nbi_akhir)}</div>
            <div style="margin-top:12px"><a class="btn btn-primary btn-sm" href="index.php?kelas=${esc(k.kode)}">Lihat mahasiswa</a>
              <button class="btn btn-secondary btn-sm" data-edit="${k.id}">Ubah rentang</button></div>
          </div>`;
        }).join("")}
      </div>`;

    app.querySelector("#btnSync").addEventListener("click", async () => {
      try { const x = await api("api/kelas.php", { method: "POST", params: { action: "sync" } }); toast(x.message); render(); } catch (e) { toastErr(e); }
    });
    app.querySelectorAll("[data-edit]").forEach((b) => b.addEventListener("click", () => {
      const k = r.data.find((x) => String(x.id) === b.dataset.edit);
      const m = openModal({
        title: `Ubah ${k.nama}`,
        body: `<form id="kForm"><div class="form-grid">
          <label>Nama<input id="k_nama" value="${esc(k.nama)}" maxlength="50" required></label>
          <label>Kapasitas<input id="k_kap" type="number" min="1" max="500" value="${k.kapasitas}" required></label>
          <label>NBI Awal<input id="k_awal" value="${esc(k.nbi_awal)}" pattern="[0-9]{6,20}" required></label>
          <label>NBI Akhir<input id="k_akhir" value="${esc(k.nbi_akhir)}" pattern="[0-9]{6,20}" required></label></div>
          <div class="hint-box">Mengubah rentang akan langsung memindahkan mahasiswa ke kelas yang sesuai. Rentang antar kelas tidak boleh bertumpuk.</div>
          <div class="form-actions"><button type="button" class="btn btn-secondary" data-close>Batal</button><button class="btn btn-primary">Simpan</button></div></form>`,
      });
      m.el.querySelector("[data-close]").addEventListener("click", m.close);
      m.el.querySelector("#kForm").addEventListener("submit", async (e) => {
        e.preventDefault();
        try {
          const x = await api(`api/kelas.php?id=${k.id}`, { method: "PUT", body: { nama: m.el.querySelector("#k_nama").value, kapasitas: m.el.querySelector("#k_kap").value, nbi_awal: m.el.querySelector("#k_awal").value, nbi_akhir: m.el.querySelector("#k_akhir").value } });
          toast(x.message); m.close(); render();
        } catch (err) { toastErr(err); }
      });
    }));
  }
  render().catch((e) => (app.innerHTML = `<div class="card empty">${esc(e.message)}</div>`));
})();
