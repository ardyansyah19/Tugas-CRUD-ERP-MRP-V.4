# Tugas CRUD ERP MRP V.4
By Ahmad Riko Dyansyah
1462400002

Versi pengembangan dari project CRUD Data Mahasiswa sebelumnya. Struktur dasar
(PHP + PDO + MySQL + JS Fetch API) dipertahankan agar tetap mudah dijalankan di
XAMPP/Laragon, namun ditambah sejumlah fitur agar lebih lengkap dan lebih aman.

## 1. Apa yang Baru Dibanding Versi Sebelumnya

| Kategori | Penambahan |
|---|---|
| **Autentikasi** | Login admin (session, bcrypt), proteksi seluruh halaman & API, rate limit percobaan login |
| **Keamanan** | CSRF token di setiap request non-GET, rate limiting sederhana, validasi input lebih ketat (regex NBI/HP/angkatan), `.htaccess` anti-eksekusi script di folder upload |
| **Data** | Kolom baru: `angkatan`, `alamat`, `foto` (upload gambar profil) + tabel `users` |
| **Tampilan Data** | Pagination server-side, sorting per kolom (klik header), filter jurusan (dropdown), pencarian dengan debounce |
| **Dashboard** | Kartu ringkasan: total mahasiswa, jumlah jurusan, posisi halaman |
| **Export** | Export data (sesuai hasil pencarian/filter aktif) ke CSV |
| **UX** | Notifikasi toast (bukan `alert()`), modal konfirmasi hapus (bukan `confirm()`), dark mode (tersimpan di localStorage), preview foto sebelum upload |
| **Upload Foto** | Validasi tipe MIME asli file & ukuran maks 2MB, nama file di-random agar tidak bentrok, opsi hapus foto lama |

Struktur tabel `mahasiswa` lama tetap kompatibel — kolom baru bersifat nullable,
jadi data lama dari versi sebelumnya tidak akan rusak jika Anda meng-import ulang
dari `database/crud_mahasiswa.sql` (disarankan install baru/database baru).

## 2. Struktur Folder

```text
crud_mahasiswa_v2/
├── api/
│   ├── auth.php          # login, logout, cek sesi
│   ├── mahasiswa.php      # CRUD + pagination + filter + upload foto
│   └── export.php         # export CSV
├── config/
│   ├── config.php         # session, CSRF, rate limit, konstanta upload
│   └── database.php       # koneksi PDO (mendukung env var)
├── css/
│   └── style.css
├── database/
│   └── crud_mahasiswa.sql
├── js/
│   ├── script.js
│   └── login.js
├── uploads/
│   └── foto/               # tempat foto profil disimpan (+ .htaccess proteksi)
├── index.php               # halaman utama (butuh login)
├── login.php                # halaman login
└── README.md
```

## 3. Instalasi XAMPP

1. Install XAMPP, jalankan **Apache** dan **MySQL**.
2. Salin folder `crud_mahasiswa_v2` ke `C:\xampp\htdocs\`.
3. Import `database/crud_mahasiswa.sql` melalui phpMyAdmin atau CMD:

   ```bat
   cd C:\xampp\mysql\bin
   mysql -u root -p < C:\xampp\htdocs\crud_mahasiswa_v2\database\crud_mahasiswa.sql
   ```

4. Pastikan folder `uploads/foto` bisa ditulis (writable) oleh web server.
5. Buka `http://localhost/crud_mahasiswa_v2/`.

## 4. Instalasi Laragon

Salin folder ke `C:\laragon\www\`, jalankan Apache/Nginx + MySQL dari Laragon,
lalu jalankan langkah import database yang sama seperti di atas.

## 5. Konfigurasi Database

Buka `config/database.php`. Secara default menggunakan:

```php
$host = "localhost";
$db   = "crud_mahasiswa";
$user = "root";
$pass = "";
```

Nilai ini juga bisa dioverride lewat environment variable `DB_HOST`, `DB_NAME`,
`DB_USER`, `DB_PASS`, `DB_PORT` — berguna saat deployment agar kredensial tidak
perlu ditulis langsung di source code.

## 6. Login

Akun default setelah import database:

```text
Username: admin
Password: admin123
```

**Segera ganti password ini** setelah login pertama (lihat bagian Keamanan di
bawah untuk cara menambah fitur ganti password bila ingin dikembangkan lebih
lanjut). Untuk saat ini, cara tercepat mengganti password adalah meng-generate
hash bcrypt baru (misalnya dengan `password_hash('password_baru', PASSWORD_BCRYPT)`
di file PHP sementara) lalu meng-update kolom `password` pada tabel `users`
lewat phpMyAdmin.

## 7. Fitur

- Login admin dengan session, proteksi semua halaman & endpoint API
- Menampilkan data dengan pagination (10/25/50/100 per halaman)
- Sorting per kolom (klik header tabel)
- Filter berdasarkan jurusan + pencarian gabungan (nama, NBI, jurusan, email)
- Tambah, edit, hapus data mahasiswa, termasuk upload foto profil
- Dashboard ringkasan jumlah mahasiswa & jurusan
- Export data (sesuai filter aktif) ke CSV
- Notifikasi toast dan modal konfirmasi hapus
- Dark mode
- Validasi field wajib, format email, format NBI/HP/angkatan, baik di client
  maupun di server
- CSRF token untuk semua request POST/PUT/DELETE
- Rate limiting sederhana berbasis session untuk endpoint yang mengubah data
- Prepared Statement PDO di semua query
- Proteksi folder upload dari eksekusi script

## 8. API Endpoint

Base URL: `/api/mahasiswa.php` (semua endpoint mensyaratkan sesi login aktif).

### GET — Menampilkan data (dengan pagination/sort/filter/search)

```http
GET /api/mahasiswa.php?page=1&limit=10&sort=nama&dir=asc&search=budi&jurusan=Teknik+Informatika
```

### GET — Statistik dashboard

```http
GET /api/mahasiswa.php?action=stats
```

### GET — Daftar jurusan unik (untuk dropdown filter)

```http
GET /api/mahasiswa.php?action=jurusan_list
```

### POST — Menambah data (multipart/form-data, agar bisa sertakan foto)

```http
POST /api/mahasiswa.php
Content-Type: multipart/form-data
```

Field: `nbi`, `nama`, `jurusan`, `angkatan`, `email`, `no_hp`, `alamat`,
`foto` (file, opsional), `csrf_token`.

### PUT (via POST + `_method=PUT`) — Mengubah data

```http
POST /api/mahasiswa.php?id=1
Content-Type: multipart/form-data
```

Sertakan field `_method=PUT`. Field `hapus_foto=1` untuk menghapus foto tanpa
mengganti dengan yang baru.

### DELETE — Menghapus data

```http
DELETE /api/mahasiswa.php?id=1
X-CSRF-Token: <token>
```

### Auth

```http
POST /api/auth.php?action=login    { "username", "password", "csrf_token" }
POST /api/auth.php?action=logout
GET  /api/auth.php?action=check
```

### Export

```http
GET /api/export.php?search=..&jurusan=..
```

## 9. Jika Muncul Error Koneksi Database

1. MySQL/MariaDB sudah berjalan.
2. Nama database adalah `crud_mahasiswa`.
3. Username dan password di `config/database.php` benar.
4. Port MySQL sesuai konfigurasi komputer Anda (default DSN memakai 3306,
   ubah lewat env var `DB_PORT` bila berbeda).
5. Folder project berada di `htdocs` XAMPP atau `www` Laragon.
6. PHP yang digunakan mendukung PDO MySQL dan ekstensi `fileinfo` (untuk
   validasi upload foto).

## 10. Catatan Keamanan Lanjutan

Project ini sudah jauh lebih siap dibanding versi awal (login, CSRF, rate
limit, validasi ketat, proteksi folder upload), namun untuk deployment
production sesungguhnya tetap disarankan menambahkan:

- Role/permission bertingkat (mis. admin vs staf input data)
- Fitur ganti password & reset password dari dalam aplikasi
- Rate limiting di level server/proxy (mis. Nginx `limit_req`, atau Cloudflare)
- HTTPS wajib + `Secure` cookie flag
- Logging aktivitas (audit trail) untuk create/update/delete
- Backup database terjadwal
- Pengaturan CORS yang lebih ketat jika API diakses dari domain lain

## Lisensi

Bebas digunakan dan dimodifikasi untuk pembelajaran dan pengembangan project.

---

## 11. Tambahan V3: Modul Akademik (Kelas) & MRP-ERP

Versi ini menambahkan sistem **MRP (Material Requirements Planning) / ERP** di atas
CRUD Data Mahasiswa, dengan pembagian **kelas otomatis berdasarkan rentang NBI**.
File `database/crud_mahasiswa.sql` **dirombak total** (drop & create ulang) dan sudah
berisi 120 data mahasiswa contoh + data ERP contoh (pabrik mebel kecil).

### 11.1 Pembagian Kelas (berdasarkan NBI)

| Kelas | Rentang NBI                | Jumlah |
|-------|-----------------------------|--------|
| A     | 1462400001 – 1462400030     | 30     |
| B     | 1462400031 – 1462400060     | 30     |
| C     | 1462400061 – 1462400090     | 30     |
| D     | 1462400091 – 1462400120     | 30     |

Kelas **tidak diinput manual** — kolom `kelas_id` pada tabel `mahasiswa` dihitung
otomatis dari NBI setiap kali data ditambah/diubah (lihat `lib/erp.php` fungsi
`kelas_id_dari_nbi()`), dan rentangnya sendiri disimpan di tabel `kelas` sehingga
bisa diubah dari menu **Kelas** tanpa mengubah kode. Ada tombol "Sinkronkan kelas
mahasiswa" untuk menata ulang seluruh data bila rentang kelas diubah.

### 11.2 Struktur Basis Data Baru

**Akademik**
- `kelas` — rentang NBI, kapasitas.
- `mahasiswa` — sama seperti V2, ditambah `kelas_id` (FK ke `kelas`).
- `users` — tetap sama (login admin).

**Master ERP**
- `satuan`, `supplier`
- `item` — RM/SFG/FG, parameter MRP (lead time, lot sizing L4L/FOQ/POQ, stok
  pengaman, stok berjalan, harga standar).
- `bom` — Bill of Materials multi-level (1 baris = 1 komponen untuk 1 induk).

**Transaksi**
- `kebutuhan_independen` — pesanan/forecast (input MPS).
- `stok_mutasi` — buku besar setiap perubahan stok (masuk/keluar/penyesuaian).
- `purchase_order` + `purchase_order_detail` — pembelian bahan baku.
- `work_order` — perintah produksi FG/SFG, terhubung ke `kelas` & `mahasiswa`
  (PIC) sehingga bisa dipetakan sebagai tugas praktikum per kelas/mahasiswa.

**MRP (snapshot tiap kali dijalankan)**
- `mrp_run` — header (tanggal mulai, horizon minggu, kelas/PIC penanggung jawab).
- `mrp_item` — parameter item saat run tsb dijalankan.
- `mrp_hasil` — tabel MRP per item per minggu (gross, scheduled receipt, proj. on
  hand, net requirement, planned receipt, planned release).
- `mrp_planned_order` — daftar rencana order (PO/WO) hasil MRP, bisa dikonversi
  menjadi PO/WO sungguhan.

**View**: `v_rekap_kelas`, `v_stok_item`, `v_bom_detail`.

### 11.3 Logika MRP

Engine ada di `lib/mrp.php` (`mrp_hitung()`), memakai bucket mingguan:

1. Item diurutkan berdasarkan **low-level code** (0 = barang jadi, makin ke bawah
   BOM nilainya makin besar) agar kebutuhan dependen (dari planned release induk)
   sudah tersedia sebelum item komponen dihitung.
2. Per minggu: `Net Requirement = Safety Stock − (On Hand + Scheduled Receipt − Gross)`
   jika hasilnya kurang dari safety stock.
3. Net requirement dibulatkan sesuai **lot sizing** item: L4L (persis kebutuhan),
   FOQ (kelipatan ukuran lot tetap), atau POQ (digabung untuk beberapa periode).
4. **Planned Release** = Planned Receipt digeser mundur sebesar *lead time*; bila
   jatuh sebelum minggu ke-1, ditandai **terlambat**.
5. Planned release item yang diproduksi (FG/SFG) otomatis meledak ke
   kebutuhan dependen komponennya via BOM (termasuk faktor scrap %).

Hasil MRP hanya berupa **rencana** (`mrp_planned_order`, status `planned`).
Rencana ini baru menjadi transaksi sungguhan setelah pengguna memilih baris yang
diinginkan dan menekan **Konversi**: item beli → `purchase_order` (status
`draft`, dikelompokkan per supplier), item produksi → `work_order` (status
`planned`).

### 11.4 Alur Operasional

1. **Kebutuhan (MPS)** — isi pesanan/forecast untuk barang jadi.
2. **MRP → Jalankan MRP** — pilih tanggal mulai (dibulatkan ke hari Senin),
   horizon minggu, opsional kelas & mahasiswa PIC.
3. Lihat tabel MRP per item (gross/SR/on hand/net/planned) dan tab **Rencana
   Order** → centang baris → **Konversi** menjadi PO/WO.
4. **Purchase Order** — terbitkan (draft → open) → terima barang (stok bahan
   baku bertambah otomatis, tercatat di `stok_mutasi`).
5. **Work Order** — release → tandai selesai (komponen BOM otomatis terpotong
   dari stok/backflush, hasil produksi masuk stok). Sistem menolak
   penyelesaian bila stok komponen tidak cukup.
6. **Stok** — lihat status tiap item (aman/kritis/habis), riwayat mutasi, dan
   penyesuaian manual (stok opname).

### 11.5 Validasi & Aturan Bisnis Penting

- Barang jadi (FG) tidak boleh menjadi komponen item lain; bahan baku (RM) tidak
  boleh punya komponen BOM; sistem mendeteksi & menolak siklus BOM.
- Item bertipe RM otomatis `pengadaan = beli`, FG/SFG otomatis `produksi`.
- Mahasiswa yang dipilih sebagai PIC pada MRP/Work Order harus anggota kelas
  yang dipilih (atau kelasnya otomatis mengikuti PIC).
- Semua endpoint tulis (POST/PUT/DELETE) tetap memakai CSRF token & rate limit
  seperti V2; error database (data duplikat, referensi terpakai) diterjemahkan
  ke pesan berbahasa Indonesia, bukan pesan SQL mentah.

### 11.6 Halaman Baru

`dashboard.php`, `kelas.php`, `item.php` (+ editor BOM), `supplier.php`,
`kebutuhan.php`, `mrp.php`, `po.php`, `wo.php`, `stok.php` — semua memakai
layout & navigasi bersama di `partials/layout.php` dan inti JS di
`js/erp-core.js` (client API, modal, toast, CRUD generik). Halaman mahasiswa
lama (`index.php`) tetap ada, ditambah kolom & filter **Kelas**.

**Login tetap sama**: `admin` / `admin123` (segera ganti setelah login pertama).

---

## 12. V4 — Desain Ulang Tampilan dengan Tailwind CSS

V4 adalah **murni pembaruan tampilan** di atas V3 — tidak ada perubahan pada struktur
database, logika MRP, maupun endpoint API. Semua yang sudah berjalan di V3 (CRUD,
perhitungan MRP, konversi PO/WO, backflush stok, dsb.) tetap sama persis.

### 12.1 Teknologi

- **Tailwind CSS via Play CDN** (`https://cdn.tailwindcss.com`) — tidak perlu Node.js,
  npm, atau proses build apa pun. Cukup unggah ke XAMPP/Laragon seperti biasa dan
  pastikan server punya akses internet (untuk memuat CDN Tailwind & font Google).
- Seluruh konfigurasi tema (warna `brand`, font `Inter`/`Plus Jakarta Sans`, mode
  gelap) dan "lapisan komponen" (`@layer components`) didefinisikan dalam satu file:
  **`partials/assets.php`** (fungsi `tailwind_assets()`). Di sinilah class seperti
  `.btn`, `.card`, `.badge-blue`, `.modal`, dsb. dipetakan ke utility Tailwind lewat
  `@apply`. Karena markup yang dihasilkan JavaScript (di `js/*.js`) tetap memakai
  nama class yang sama seperti V3, seluruh logika JS tidak perlu diubah — hanya
  definisi visualnya yang sekarang 100% Tailwind.
- **`partials/layout.php`** dirombak menjadi tata letak **sidebar** modern dengan
  ikon SVG per menu (fungsi `icon()`), bukan topnav horizontal seperti V3.
- **`login.php`** dirombak total menjadi tampilan split-screen (hero gradient +
  form), tanpa mengubah `js/login.js`.
- Mode gelap sekarang memakai konvensi standar Tailwind: kelas `dark` pada
  `<html>` (bukan `<body>` seperti V3), dengan inline script di `<head>` yang
  membaca `localStorage` *sebelum* halaman dirender supaya tidak "kedip".

### 12.2 Jika ingin menghilangkan ketergantungan pada CDN (opsional)

Pendekatan CDN dipilih supaya proyek tetap bisa langsung dijalankan tanpa build
step, cocok untuk tugas kuliah. Jika di kemudian hari ingin di-build secara lokal
(misalnya untuk produksi), konfigurasi warna & komponen yang dipakai saat
pengembangan V4 sudah disiapkan dan dapat dipakai ulang sebagai titik awal:

```js
// tailwind.config.js
darkMode: 'class',
theme: {
  extend: {
    colors: { brand: { /* lihat partials/assets.php untuk nilai lengkap 50-950 */ } },
    fontFamily: { sans: ['Inter', ...], display: ['"Plus Jakarta Sans"', ...] },
  },
}
```

dan isi `@layer components { ... }` dari blok `<style type="text/tailwindcss">`
di `partials/assets.php` bisa langsung dipindah ke file `input.css` untuk di-build
dengan `npx tailwindcss -i input.css -o output.css --minify`, lalu `<link>`-kan
hasilnya sebagai pengganti pemanggilan `tailwind_assets()`.

### 12.3 Berkas yang berubah dari V3 ke V4

| Berkas | Perubahan |
|---|---|
| `partials/assets.php` | **Baru.** CDN Tailwind, konfigurasi tema, seluruh `@layer components`. |
| `partials/layout.php` | Tata letak sidebar + ikon SVG, memanggil `tailwind_assets()`. |
| `login.php` | Desain ulang total (split-screen), tetap memakai `js/login.js` yang sama. |
| `js/erp-core.js`, `js/script.js` | Hanya toggle dark-mode dialihkan ke `document.documentElement` (dari `document.body`) agar sesuai konvensi `darkMode:'class'` Tailwind. |
| `css/style.css` | **Dihapus** — seluruh styling kini ada di `partials/assets.php`. |
| Semua file lain (`api/*`, `lib/*`, `database/*`, `js/dashboard.js`, `js/kelas.js`, dst.) | **Tidak berubah** — hanya mewarisi tampilan baru lewat class yang sudah dipetakan ulang. |
