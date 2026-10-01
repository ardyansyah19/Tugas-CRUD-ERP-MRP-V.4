<?php
require_once __DIR__ . '/partials/layout.php';
page_start('Mahasiswa', 'mahasiswa');
?>
    <div class="page-head">
      <div><h1>Data Mahasiswa</h1><p>Kelas ditentukan otomatis dari rentang NBI (lihat menu Kelas). Data mahasiswa dipakai sebagai penanggung jawab (PIC) pada MRP dan Work Order.</p></div>
      <button id="btnTambah" class="btn btn-primary">+ Tambah Data</button>
    </div>

    <section class="stats-grid" id="statsGrid">
      <div class="stat-card">
        <span class="stat-value" id="statTotal">-</span>
        <span class="stat-label">Total Mahasiswa</span>
      </div>
      <div class="stat-card" id="statJurusanWrap">
        <span class="stat-value" id="statJurusanCount">-</span>
        <span class="stat-label">Jumlah Jurusan</span>
      </div>
      <div class="stat-card">
        <span class="stat-value" id="statHalaman">-</span>
        <span class="stat-label">Halaman Saat Ini</span>
      </div>
    </section>

    <main class="card">
      <div id="alert"></div>

      <div class="toolbar">
        <input id="searchInput" type="search" placeholder="Cari nama, NBI, atau jurusan...">
        <select id="filterKelas">
          <option value="">Semua Kelas</option>
          <option value="A">Kelas A</option>
          <option value="B">Kelas B</option>
          <option value="C">Kelas C</option>
          <option value="D">Kelas D</option>
          <option value="-">Tanpa Kelas</option>
        </select>
        <select id="filterJurusan">
          <option value="">Semua Jurusan</option>
        </select>
        <select id="limitSelect">
          <option value="10">10 / halaman</option>
          <option value="25">25 / halaman</option>
          <option value="50">50 / halaman</option>
          <option value="100">100 / halaman</option>
        </select>
        <button id="btnExport" class="btn btn-secondary">⬇ Export CSV</button>
      </div>

      <div class="table-wrapper">
        <table>
          <thead>
            <tr>
              <th>Foto</th>
              <th data-sort="nbi">NBI</th>
              <th data-sort="nama">Nama</th>
              <th data-sort="kelas">Kelas</th>
              <th data-sort="jurusan">Jurusan</th>
              <th data-sort="angkatan">Angkatan</th>
              <th data-sort="email">Email</th>
              <th>No. HP</th>
              <th>Aksi</th>
            </tr>
          </thead>
          <tbody id="dataTable">
            <tr><td colspan="9" class="loading">Memuat data...</td></tr>
          </tbody>
        </table>
      </div>

      <div class="pagination" id="pagination"></div>
    </main>

  <!-- Modal form tambah/edit -->
  <div id="modal" class="modal hidden">
    <div class="modal-content">
      <div class="modal-header">
        <h2 id="modalTitle">Tambah Mahasiswa</h2>
        <button id="btnClose" class="close">&times;</button>
      </div>
      <form id="mahasiswaForm">
        <input type="hidden" id="id">
        <div class="form-grid">
          <label>NBI
            <input id="nbi" required maxlength="20" pattern="[0-9]{6,20}" title="Angka 6-20 digit">
            <span class="field-hint">Kelas akan ditentukan otomatis dari rentang NBI (lihat menu Kelas).</span>
          </label>
          <label>Nama Lengkap
            <input id="nama" required maxlength="100">
          </label>
          <label>Jurusan
            <input id="jurusan" required maxlength="100" list="jurusanOptions">
            <datalist id="jurusanOptions"></datalist>
          </label>
          <label>Angkatan
            <input id="angkatan" type="number" min="2000" max="2100" placeholder="mis. 2024">
          </label>
          <label>Email
            <input id="email" type="email" required maxlength="100">
          </label>
          <label>No. HP
            <input id="no_hp" maxlength="20" placeholder="081234567890">
          </label>
        </div>
        <label>Alamat
          <textarea id="alamat" maxlength="255" rows="2"></textarea>
        </label>
        <label>Foto Profil (opsional, maks 2MB, JPG/PNG/WEBP)
          <input id="foto" type="file" accept="image/jpeg,image/png,image/webp">
        </label>
        <div id="fotoPreviewWrap" class="foto-preview-wrap hidden">
          <img id="fotoPreview" alt="Preview foto">
          <label class="checkbox-label"><input type="checkbox" id="hapus_foto"> Hapus foto ini</label>
        </div>
        <div class="form-actions">
          <button type="button" id="btnCancel" class="btn btn-secondary">Batal</button>
          <button type="submit" class="btn btn-primary" id="btnSubmit">Simpan</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Modal konfirmasi hapus -->
  <div id="confirmModal" class="modal hidden">
    <div class="modal-content modal-small">
      <div class="modal-header">
        <h2>Konfirmasi Hapus</h2>
        <button id="btnCloseConfirm" class="close">&times;</button>
      </div>
      <p id="confirmText">Yakin ingin menghapus data ini?</p>
      <div class="form-actions">
        <button type="button" id="btnCancelDelete" class="btn btn-secondary">Batal</button>
        <button type="button" id="btnConfirmDelete" class="btn btn-danger">Hapus</button>
      </div>
    </div>
  </div>

<?php page_end(['js/script.js']); ?>
