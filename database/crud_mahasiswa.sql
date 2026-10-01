-- =====================================================================
--  SISTEM AKADEMIK + MRP/ERP  (crud_mahasiswa V3)
--  Kompatibel: MySQL 5.7+/8.x dan MariaDB 10.3+ (XAMPP / Laragon)
--
--  PERHATIAN: file ini bersifat "rombak total". Semua tabel lama dihapus
--  lalu dibuat ulang beserta data contoh (120 mahasiswa + data ERP).
--  Jangan di-import ke database yang datanya masih ingin dipertahankan.
--
--  Struktur (urutan pembuatan):
--   A. AKADEMIK : kelas -> mahasiswa            (+ users untuk login)
--   B. MASTER   : satuan, supplier, item, bom
--   C. TRANSAKSI: kebutuhan_independen, stok_mutasi,
--                 purchase_order(+detail), work_order
--   D. MRP      : mrp_run, mrp_item, mrp_hasil, mrp_planned_order
--   E. VIEW     : v_rekap_kelas, v_stok_item, v_bom_detail
-- =====================================================================

CREATE DATABASE IF NOT EXISTS crud_mahasiswa
CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE crud_mahasiswa;

SET FOREIGN_KEY_CHECKS = 0;
DROP VIEW  IF EXISTS v_bom_detail;
DROP VIEW  IF EXISTS v_stok_item;
DROP VIEW  IF EXISTS v_rekap_kelas;
DROP TABLE IF EXISTS mrp_planned_order;
DROP TABLE IF EXISTS mrp_hasil;
DROP TABLE IF EXISTS mrp_item;
DROP TABLE IF EXISTS mrp_run;
DROP TABLE IF EXISTS work_order;
DROP TABLE IF EXISTS purchase_order_detail;
DROP TABLE IF EXISTS purchase_order;
DROP TABLE IF EXISTS stok_mutasi;
DROP TABLE IF EXISTS kebutuhan_independen;
DROP TABLE IF EXISTS bom;
DROP TABLE IF EXISTS item;
DROP TABLE IF EXISTS supplier;
DROP TABLE IF EXISTS satuan;
DROP TABLE IF EXISTS mahasiswa;
DROP TABLE IF EXISTS kelas;
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================================
-- A. AKADEMIK
-- =====================================================================

-- Kelas ditentukan dari RENTANG NBI (bukan diinput manual).
CREATE TABLE kelas (
    id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    kode      CHAR(1)      NOT NULL UNIQUE,
    nama      VARCHAR(50)  NOT NULL,
    nbi_awal  BIGINT UNSIGNED NOT NULL,
    nbi_akhir BIGINT UNSIGNED NOT NULL,
    kapasitas SMALLINT UNSIGNED NOT NULL DEFAULT 30,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_kelas_rentang (nbi_awal, nbi_akhir)
) ENGINE=InnoDB;

INSERT INTO kelas (id, kode, nama, nbi_awal, nbi_akhir, kapasitas) VALUES
(1, 'A', 'Kelas A', 1462400001, 1462400030, 30),
(2, 'B', 'Kelas B', 1462400031, 1462400060, 30),
(3, 'C', 'Kelas C', 1462400061, 1462400090, 30),
(4, 'D', 'Kelas D', 1462400091, 1462400120, 30);

CREATE TABLE mahasiswa (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nbi        VARCHAR(30)  NOT NULL UNIQUE,
    nama       VARCHAR(100) NOT NULL,
    jurusan    VARCHAR(100) NOT NULL,
    angkatan   SMALLINT UNSIGNED NULL,
    email      VARCHAR(100) NOT NULL UNIQUE,
    no_hp      VARCHAR(20)  NULL,
    alamat     VARCHAR(255) NULL,
    foto       VARCHAR(255) NULL,
    kelas_id   INT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_jurusan (jurusan),
    INDEX idx_angkatan (angkatan),
    INDEX idx_mhs_kelas (kelas_id),
    CONSTRAINT fk_mhs_kelas FOREIGN KEY (kelas_id) REFERENCES kelas(id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB;

-- 120 mahasiswa: NBI 1462400001 - 1462400120 (angkatan 2024, Teknik Informatika)
-- Nama, email, no. HP, dan alamat adalah DATA CONTOH (fiktif) - silakan diedit.
INSERT INTO mahasiswa (nbi, nama, jurusan, angkatan, email, no_hp, alamat, kelas_id) VALUES
('1462400001', 'Rina Khoirunnisa', 'Teknik Informatika', 2024, 'rina.khoirunnisa.001@student.example.com', '081287994887', 'Jl. Tenggilis Mejoyo No. 20, Sidoarjo', 1),
('1462400002', 'Andi Utomo', 'Teknik Informatika', 2024, 'andi.utomo.002@student.example.com', '081253866652', 'Jl. Kertajaya No. 54, Mojokerto', 1),
('1462400003', 'Tiara Larasati', 'Teknik Informatika', 2024, 'tiara.larasati.003@student.example.com', '081206434320', 'Jl. Menur Pumpungan No. 20, Surabaya', 1),
('1462400004', 'Bagus Hakim', 'Teknik Informatika', 2024, 'bagus.hakim.004@student.example.com', '081205630747', 'Jl. Kertajaya No. 29, Surabaya', 1),
('1462400005', 'Reza Purnama', 'Teknik Informatika', 2024, 'reza.purnama.005@student.example.com', '081233330687', 'Jl. Klampis Aji No. 48, Surabaya', 1),
('1462400006', 'Arif Firmansyah', 'Teknik Informatika', 2024, 'arif.firmansyah.006@student.example.com', '081273845901', 'Jl. Dr. Soetomo No. 12, Mojokerto', 1),
('1462400007', 'Nabila Larasati', 'Teknik Informatika', 2024, 'nabila.larasati.007@student.example.com', '081272301163', 'Jl. Klampis Aji No. 68, Sidoarjo', 1),
('1462400008', 'Gilang Syahputra', 'Teknik Informatika', 2024, 'gilang.syahputra.008@student.example.com', '081228459973', 'Jl. Kertajaya No. 37, Surabaya', 1),
('1462400009', 'Wahyu Aziz', 'Teknik Informatika', 2024, 'wahyu.aziz.009@student.example.com', '081203484923', 'Jl. Raya Wiyung No. 107, Mojokerto', 1),
('1462400010', 'Yoga Susanto', 'Teknik Informatika', 2024, 'yoga.susanto.010@student.example.com', '081241645163', 'Jl. Tenggilis Mejoyo No. 21, Surabaya', 1),
('1462400011', 'Citra Isnaini', 'Teknik Informatika', 2024, 'citra.isnaini.011@student.example.com', '081242333058', 'Jl. Ngagel Jaya No. 104, Surabaya', 1),
('1462400012', 'Naufal Saputra', 'Teknik Informatika', 2024, 'naufal.saputra.012@student.example.com', '081248008290', 'Jl. Ngagel Jaya No. 82, Surabaya', 1),
('1462400013', 'Hendra Syahputra', 'Teknik Informatika', 2024, 'hendra.syahputra.013@student.example.com', '081217119108', 'Jl. Raya Wiyung No. 87, Gresik', 1),
('1462400014', 'Muhammad Effendi', 'Teknik Informatika', 2024, 'muhammad.effendi.014@student.example.com', '081275020443', 'Jl. Nginden Semolo No. 27, Surabaya', 1),
('1462400015', 'Rahma Larasati', 'Teknik Informatika', 2024, 'rahma.larasati.015@student.example.com', '081280145234', 'Jl. Kertajaya No. 68, Surabaya', 1),
('1462400016', 'Danu Cahyono', 'Teknik Informatika', 2024, 'danu.cahyono.016@student.example.com', '081257448681', 'Jl. Gubeng Kertajaya No. 51, Surabaya', 1),
('1462400017', 'Rahma Anggraini', 'Teknik Informatika', 2024, 'rahma.anggraini.017@student.example.com', '081217512575', 'Jl. Klampis Aji No. 56, Sidoarjo', 1),
('1462400018', 'Naufal Yulianto', 'Teknik Informatika', 2024, 'naufal.yulianto.018@student.example.com', '081225863742', 'Jl. Nginden Semolo No. 3, Surabaya', 1),
('1462400019', 'Galang Santoso', 'Teknik Informatika', 2024, 'galang.santoso.019@student.example.com', '081274134155', 'Jl. Tenggilis Mejoyo No. 88, Gresik', 1),
('1462400020', 'Novita Ningsih', 'Teknik Informatika', 2024, 'novita.ningsih.020@student.example.com', '081297106363', 'Jl. Pucang Anom No. 45, Surabaya', 1),
('1462400021', 'Ratna Permata', 'Teknik Informatika', 2024, 'ratna.permata.021@student.example.com', '081245813261', 'Jl. Menur Pumpungan No. 44, Surabaya', 1),
('1462400022', 'Aditya Effendi', 'Teknik Informatika', 2024, 'aditya.effendi.022@student.example.com', '081266990575', 'Jl. Manyar Kertoarjo No. 16, Surabaya', 1),
('1462400023', 'Zahra Isnaini', 'Teknik Informatika', 2024, 'zahra.isnaini.023@student.example.com', '081262716764', 'Jl. Kertajaya No. 107, Surabaya', 1),
('1462400024', 'Bella Junita', 'Teknik Informatika', 2024, 'bella.junita.024@student.example.com', '081217740145', 'Jl. Kertajaya No. 33, Surabaya', 1),
('1462400025', 'Oka Hartono', 'Teknik Informatika', 2024, 'oka.hartono.025@student.example.com', '081268225146', 'Jl. Klampis Aji No. 23, Sidoarjo', 1),
('1462400026', 'Fajar Hidayat', 'Teknik Informatika', 2024, 'fajar.hidayat.026@student.example.com', '081209153128', 'Jl. Kertajaya No. 37, Surabaya', 1),
('1462400027', 'Rendra Prasetyo', 'Teknik Informatika', 2024, 'rendra.prasetyo.027@student.example.com', '081265225681', 'Jl. Menur Pumpungan No. 120, Gresik', 1),
('1462400028', 'Hana Yuliana', 'Teknik Informatika', 2024, 'hana.yuliana.028@student.example.com', '081251846751', 'Jl. Manyar Kertoarjo No. 112, Surabaya', 1),
('1462400029', 'Nadia Cahyani', 'Teknik Informatika', 2024, 'nadia.cahyani.029@student.example.com', '081271306428', 'Jl. Kertajaya No. 9, Surabaya', 1),
('1462400030', 'Sekar Febrianti', 'Teknik Informatika', 2024, 'sekar.febrianti.030@student.example.com', '081210256219', 'Jl. Nginden Semolo No. 86, Surabaya', 1),
('1462400031', 'Rendra Ghozali', 'Teknik Informatika', 2024, 'rendra.ghozali.031@student.example.com', '081239813358', 'Jl. Mulyosari No. 93, Surabaya', 2),
('1462400032', 'Aditya Nugroho', 'Teknik Informatika', 2024, 'aditya.nugroho.032@student.example.com', '081226766660', 'Jl. Tenggilis Mejoyo No. 94, Surabaya', 2),
('1462400033', 'Hana Anggraini', 'Teknik Informatika', 2024, 'hana.anggraini.033@student.example.com', '081247896040', 'Jl. Nginden Semolo No. 3, Surabaya', 2),
('1462400034', 'Galang Effendi', 'Teknik Informatika', 2024, 'galang.effendi.034@student.example.com', '081252542627', 'Jl. Klampis Aji No. 108, Surabaya', 2),
('1462400035', 'Putri Isnaini', 'Teknik Informatika', 2024, 'putri.isnaini.035@student.example.com', '081204613053', 'Jl. Manyar Kertoarjo No. 87, Surabaya', 2),
('1462400036', 'Indah Rahayu', 'Teknik Informatika', 2024, 'indah.rahayu.036@student.example.com', '081270463012', 'Jl. Klampis Aji No. 29, Surabaya', 2),
('1462400037', 'Ratna Kusumawati', 'Teknik Informatika', 2024, 'ratna.kusumawati.037@student.example.com', '081256730909', 'Jl. Menur Pumpungan No. 33, Gresik', 2),
('1462400038', 'Ikhsan Ramadhan', 'Teknik Informatika', 2024, 'ikhsan.ramadhan.038@student.example.com', '081233190043', 'Jl. Kenjeran No. 103, Sidoarjo', 2),
('1462400039', 'Oka Effendi', 'Teknik Informatika', 2024, 'oka.effendi.039@student.example.com', '081247194587', 'Jl. Dr. Soetomo No. 37, Sidoarjo', 2),
('1462400040', 'Andi Hakim', 'Teknik Informatika', 2024, 'andi.hakim.040@student.example.com', '081251632033', 'Jl. Darmo Permai No. 77, Surabaya', 2),
('1462400041', 'Ratna Larasati', 'Teknik Informatika', 2024, 'ratna.larasati.041@student.example.com', '081270537243', 'Jl. Gubeng Kertajaya No. 85, Surabaya', 2),
('1462400042', 'Eko Wibowo', 'Teknik Informatika', 2024, 'eko.wibowo.042@student.example.com', '081208234180', 'Jl. Dr. Soetomo No. 58, Mojokerto', 2),
('1462400043', 'Wulan Permata', 'Teknik Informatika', 2024, 'wulan.permata.043@student.example.com', '081286850678', 'Jl. Nginden Semolo No. 47, Surabaya', 2),
('1462400044', 'Rahma Junita', 'Teknik Informatika', 2024, 'rahma.junita.044@student.example.com', '081243315084', 'Jl. Ngagel Jaya No. 45, Surabaya', 2),
('1462400045', 'Nadia Damayanti', 'Teknik Informatika', 2024, 'nadia.damayanti.045@student.example.com', '081232561285', 'Jl. Kertajaya No. 57, Surabaya', 2),
('1462400046', 'Lukman Pratama', 'Teknik Informatika', 2024, 'lukman.pratama.046@student.example.com', '081292777074', 'Jl. Kertajaya No. 96, Surabaya', 2),
('1462400047', 'Muhammad Wijaya', 'Teknik Informatika', 2024, 'muhammad.wijaya.047@student.example.com', '081287503101', 'Jl. Manyar Kertoarjo No. 1, Mojokerto', 2),
('1462400048', 'Bayu Susanto', 'Teknik Informatika', 2024, 'bayu.susanto.048@student.example.com', '081223213709', 'Jl. Nginden Semolo No. 72, Surabaya', 2),
('1462400049', 'Budi Prasetyo', 'Teknik Informatika', 2024, 'budi.prasetyo.049@student.example.com', '081228816131', 'Jl. Manyar Kertoarjo No. 70, Surabaya', 2),
('1462400050', 'Bayu Hartono', 'Teknik Informatika', 2024, 'bayu.hartono.050@student.example.com', '081267457028', 'Jl. Raya Wiyung No. 57, Surabaya', 2),
('1462400051', 'Krisna Prasetyo', 'Teknik Informatika', 2024, 'krisna.prasetyo.051@student.example.com', '081214086774', 'Jl. Dr. Soetomo No. 63, Surabaya', 2),
('1462400052', 'Wahyu Maulana', 'Teknik Informatika', 2024, 'wahyu.maulana.052@student.example.com', '081245518803', 'Jl. Kertajaya No. 15, Surabaya', 2),
('1462400053', 'Farhan Darmawan', 'Teknik Informatika', 2024, 'farhan.darmawan.053@student.example.com', '081232169733', 'Jl. Darmo Permai No. 41, Surabaya', 2),
('1462400054', 'Wahyu Wibowo', 'Teknik Informatika', 2024, 'wahyu.wibowo.054@student.example.com', '081241290967', 'Jl. Rungkut Madya No. 12, Sidoarjo', 2),
('1462400055', 'Bagus Wibowo', 'Teknik Informatika', 2024, 'bagus.wibowo.055@student.example.com', '081275413531', 'Jl. Manyar Kertoarjo No. 44, Sidoarjo', 2),
('1462400056', 'Prima Pratama', 'Teknik Informatika', 2024, 'prima.pratama.056@student.example.com', '081263303864', 'Jl. Nginden Semolo No. 12, Surabaya', 2),
('1462400057', 'Bella Sari', 'Teknik Informatika', 2024, 'bella.sari.057@student.example.com', '081267088805', 'Jl. Kenjeran No. 72, Gresik', 2),
('1462400058', 'Galang Ghozali', 'Teknik Informatika', 2024, 'galang.ghozali.058@student.example.com', '081287698148', 'Jl. Gubeng Kertajaya No. 20, Surabaya', 2),
('1462400059', 'Aditya Utomo', 'Teknik Informatika', 2024, 'aditya.utomo.059@student.example.com', '081214057519', 'Jl. Tenggilis Mejoyo No. 34, Surabaya', 2),
('1462400060', 'Bayu Saputra', 'Teknik Informatika', 2024, 'bayu.saputra.060@student.example.com', '081291250738', 'Jl. Ngagel Jaya No. 50, Surabaya', 2),
('1462400061', 'Elsa Rosalina', 'Teknik Informatika', 2024, 'elsa.rosalina.061@student.example.com', '081297404826', 'Jl. Mulyosari No. 48, Mojokerto', 3),
('1462400062', 'Budi Permana', 'Teknik Informatika', 2024, 'budi.permana.062@student.example.com', '081273710664', 'Jl. Rungkut Madya No. 70, Surabaya', 3),
('1462400063', 'Mario Maulana', 'Teknik Informatika', 2024, 'mario.maulana.063@student.example.com', '081236603569', 'Jl. Rungkut Madya No. 68, Surabaya', 3),
('1462400064', 'Budi Utomo', 'Teknik Informatika', 2024, 'budi.utomo.064@student.example.com', '081220149962', 'Jl. Kertajaya No. 15, Surabaya', 3),
('1462400065', 'Wahyu Kurniawan', 'Teknik Informatika', 2024, 'wahyu.kurniawan.065@student.example.com', '081208092140', 'Jl. Tenggilis Mejoyo No. 99, Gresik', 3),
('1462400066', 'Bayu Aziz', 'Teknik Informatika', 2024, 'bayu.aziz.066@student.example.com', '081239685695', 'Jl. Dr. Soetomo No. 91, Surabaya', 3),
('1462400067', 'Fajar Pratama', 'Teknik Informatika', 2024, 'fajar.pratama.067@student.example.com', '081259669159', 'Jl. Menur Pumpungan No. 5, Surabaya', 3),
('1462400068', 'Sekar Amalia', 'Teknik Informatika', 2024, 'sekar.amalia.068@student.example.com', '081277969408', 'Jl. Nginden Semolo No. 50, Surabaya', 3),
('1462400069', 'Danu Saputra', 'Teknik Informatika', 2024, 'danu.saputra.069@student.example.com', '081220292193', 'Jl. Raya Wiyung No. 96, Surabaya', 3),
('1462400070', 'Yoga Cahyono', 'Teknik Informatika', 2024, 'yoga.cahyono.070@student.example.com', '081271270037', 'Jl. Nginden Semolo No. 52, Surabaya', 3),
('1462400071', 'Ahmad Effendi', 'Teknik Informatika', 2024, 'ahmad.effendi.071@student.example.com', '081243505106', 'Jl. Ngagel Jaya No. 91, Surabaya', 3),
('1462400072', 'Dewi Wulandari', 'Teknik Informatika', 2024, 'dewi.wulandari.072@student.example.com', '081277790899', 'Jl. Gubeng Kertajaya No. 56, Surabaya', 3),
('1462400073', 'Wulan Novitasari', 'Teknik Informatika', 2024, 'wulan.novitasari.073@student.example.com', '081209995815', 'Jl. Dr. Soetomo No. 2, Surabaya', 3),
('1462400074', 'Yoga Firmansyah', 'Teknik Informatika', 2024, 'yoga.firmansyah.074@student.example.com', '081210135181', 'Jl. Klampis Aji No. 97, Surabaya', 3),
('1462400075', 'Wahyu Utomo', 'Teknik Informatika', 2024, 'wahyu.utomo.075@student.example.com', '081200620043', 'Jl. Manyar Kertoarjo No. 47, Surabaya', 3),
('1462400076', 'Rina Ningsih', 'Teknik Informatika', 2024, 'rina.ningsih.076@student.example.com', '081269889994', 'Jl. Manyar Kertoarjo No. 80, Surabaya', 3),
('1462400077', 'Rahma Maharani', 'Teknik Informatika', 2024, 'rahma.maharani.077@student.example.com', '081275714519', 'Jl. Mulyosari No. 63, Gresik', 3),
('1462400078', 'Budi Wibowo', 'Teknik Informatika', 2024, 'budi.wibowo.078@student.example.com', '081222068175', 'Jl. Manyar Kertoarjo No. 78, Surabaya', 3),
('1462400079', 'Raka Nugroho', 'Teknik Informatika', 2024, 'raka.nugroho.079@student.example.com', '081234294891', 'Jl. Tenggilis Mejoyo No. 20, Surabaya', 3),
('1462400080', 'Gita Maharani', 'Teknik Informatika', 2024, 'gita.maharani.080@student.example.com', '081288852606', 'Jl. Pucang Anom No. 51, Mojokerto', 3),
('1462400081', 'Hendra Yulianto', 'Teknik Informatika', 2024, 'hendra.yulianto.081@student.example.com', '081228962409', 'Jl. Ngagel Jaya No. 35, Surabaya', 3),
('1462400082', 'Mega Marlina', 'Teknik Informatika', 2024, 'mega.marlina.082@student.example.com', '081213679227', 'Jl. Manyar Kertoarjo No. 32, Surabaya', 3),
('1462400083', 'Andi Wibowo', 'Teknik Informatika', 2024, 'andi.wibowo.083@student.example.com', '081219707910', 'Jl. Pucang Anom No. 46, Surabaya', 3),
('1462400084', 'Nabila Novitasari', 'Teknik Informatika', 2024, 'nabila.novitasari.084@student.example.com', '081272724460', 'Jl. Kenjeran No. 60, Sidoarjo', 3),
('1462400085', 'Gita Isnaini', 'Teknik Informatika', 2024, 'gita.isnaini.085@student.example.com', '081287693760', 'Jl. Raya Wiyung No. 23, Surabaya', 3),
('1462400086', 'Mario Aziz', 'Teknik Informatika', 2024, 'mario.aziz.086@student.example.com', '081238943630', 'Jl. Raya Wiyung No. 4, Gresik', 3),
('1462400087', 'Satria Wibowo', 'Teknik Informatika', 2024, 'satria.wibowo.087@student.example.com', '081204023002', 'Jl. Menur Pumpungan No. 65, Surabaya', 3),
('1462400088', 'Nanda Utomo', 'Teknik Informatika', 2024, 'nanda.utomo.088@student.example.com', '081299407924', 'Jl. Tenggilis Mejoyo No. 111, Surabaya', 3),
('1462400089', 'Krisna Kusuma', 'Teknik Informatika', 2024, 'krisna.kusuma.089@student.example.com', '081244237254', 'Jl. Dr. Soetomo No. 82, Mojokerto', 3),
('1462400090', 'Gilang Maulana', 'Teknik Informatika', 2024, 'gilang.maulana.090@student.example.com', '081260807968', 'Jl. Gubeng Kertajaya No. 117, Surabaya', 3),
('1462400091', 'Gita Permata', 'Teknik Informatika', 2024, 'gita.permata.091@student.example.com', '081289785726', 'Jl. Menur Pumpungan No. 19, Sidoarjo', 4),
('1462400092', 'Andi Ardiansyah', 'Teknik Informatika', 2024, 'andi.ardiansyah.092@student.example.com', '081211077015', 'Jl. Manyar Kertoarjo No. 59, Sidoarjo', 4),
('1462400093', 'Tiara Cahyani', 'Teknik Informatika', 2024, 'tiara.cahyani.093@student.example.com', '081241840877', 'Jl. Nginden Semolo No. 6, Surabaya', 4),
('1462400094', 'Wahyu Ardiansyah', 'Teknik Informatika', 2024, 'wahyu.ardiansyah.094@student.example.com', '081246207252', 'Jl. Rungkut Madya No. 19, Surabaya', 4),
('1462400095', 'Yoga Effendi', 'Teknik Informatika', 2024, 'yoga.effendi.095@student.example.com', '081250052937', 'Jl. Mulyosari No. 61, Gresik', 4),
('1462400096', 'Aditya Yulianto', 'Teknik Informatika', 2024, 'aditya.yulianto.096@student.example.com', '081270382490', 'Jl. Raya Wiyung No. 30, Surabaya', 4),
('1462400097', 'Oka Saputra', 'Teknik Informatika', 2024, 'oka.saputra.097@student.example.com', '081272058003', 'Jl. Klampis Aji No. 43, Surabaya', 4),
('1462400098', 'Raka Susanto', 'Teknik Informatika', 2024, 'raka.susanto.098@student.example.com', '081271810536', 'Jl. Gubeng Kertajaya No. 79, Surabaya', 4),
('1462400099', 'Bagus Prasetyo', 'Teknik Informatika', 2024, 'bagus.prasetyo.099@student.example.com', '081213247584', 'Jl. Darmo Permai No. 19, Surabaya', 4),
('1462400100', 'Gita Damayanti', 'Teknik Informatika', 2024, 'gita.damayanti.100@student.example.com', '081231682435', 'Jl. Mulyosari No. 61, Surabaya', 4),
('1462400101', 'Indah Cahyani', 'Teknik Informatika', 2024, 'indah.cahyani.101@student.example.com', '081213477683', 'Jl. Klampis Aji No. 86, Mojokerto', 4),
('1462400102', 'Andi Susanto', 'Teknik Informatika', 2024, 'andi.susanto.102@student.example.com', '081293158212', 'Jl. Manyar Kertoarjo No. 95, Surabaya', 4),
('1462400103', 'Wulan Rahayu', 'Teknik Informatika', 2024, 'wulan.rahayu.103@student.example.com', '081225296354', 'Jl. Klampis Aji No. 45, Surabaya', 4),
('1462400104', 'Rina Safitri', 'Teknik Informatika', 2024, 'rina.safitri.104@student.example.com', '081250470513', 'Jl. Raya Wiyung No. 100, Surabaya', 4),
('1462400105', 'Galang Syahputra', 'Teknik Informatika', 2024, 'galang.syahputra.105@student.example.com', '081275608260', 'Jl. Klampis Aji No. 25, Gresik', 4),
('1462400106', 'Muhammad Ramadhan', 'Teknik Informatika', 2024, 'muhammad.ramadhan.106@student.example.com', '081253637712', 'Jl. Menur Pumpungan No. 98, Surabaya', 4),
('1462400107', 'Wahyu Kusuma', 'Teknik Informatika', 2024, 'wahyu.kusuma.107@student.example.com', '081258224860', 'Jl. Nginden Semolo No. 64, Surabaya', 4),
('1462400108', 'Elsa Larasati', 'Teknik Informatika', 2024, 'elsa.larasati.108@student.example.com', '081283252980', 'Jl. Klampis Aji No. 33, Surabaya', 4),
('1462400109', 'Zahra Permata', 'Teknik Informatika', 2024, 'zahra.permata.109@student.example.com', '081239350170', 'Jl. Kenjeran No. 24, Surabaya', 4),
('1462400110', 'Hendra Purnama', 'Teknik Informatika', 2024, 'hendra.purnama.110@student.example.com', '081207628452', 'Jl. Kertajaya No. 86, Surabaya', 4),
('1462400111', 'Ayu Safitri', 'Teknik Informatika', 2024, 'ayu.safitri.111@student.example.com', '081215561683', 'Jl. Manyar Kertoarjo No. 91, Surabaya', 4),
('1462400112', 'Galang Darmawan', 'Teknik Informatika', 2024, 'galang.darmawan.112@student.example.com', '081242881488', 'Jl. Dr. Soetomo No. 22, Surabaya', 4),
('1462400113', 'Rizky Syahputra', 'Teknik Informatika', 2024, 'rizky.syahputra.113@student.example.com', '081219551318', 'Jl. Dr. Soetomo No. 44, Surabaya', 4),
('1462400114', 'Eko Purnama', 'Teknik Informatika', 2024, 'eko.purnama.114@student.example.com', '081266781956', 'Jl. Klampis Aji No. 76, Gresik', 4),
('1462400115', 'Dinda Damayanti', 'Teknik Informatika', 2024, 'dinda.damayanti.115@student.example.com', '081281942529', 'Jl. Dr. Soetomo No. 41, Surabaya', 4),
('1462400116', 'Sekar Wardani', 'Teknik Informatika', 2024, 'sekar.wardani.116@student.example.com', '081296919165', 'Jl. Gubeng Kertajaya No. 111, Surabaya', 4),
('1462400117', 'Krisna Utomo', 'Teknik Informatika', 2024, 'krisna.utomo.117@student.example.com', '081266189507', 'Jl. Kertajaya No. 63, Surabaya', 4),
('1462400118', 'Eko Syahputra', 'Teknik Informatika', 2024, 'eko.syahputra.118@student.example.com', '081246699195', 'Jl. Tenggilis Mejoyo No. 72, Sidoarjo', 4),
('1462400119', 'Oka Wibowo', 'Teknik Informatika', 2024, 'oka.wibowo.119@student.example.com', '081211386739', 'Jl. Raya Wiyung No. 35, Gresik', 4),
('1462400120', 'Hendra Pratama', 'Teknik Informatika', 2024, 'hendra.pratama.120@student.example.com', '081238943601', 'Jl. Nginden Semolo No. 52, Sidoarjo', 4);

-- Pengaman konsistensi: paksa kelas_id sesuai rentang NBI.
UPDATE mahasiswa m
JOIN kelas k ON CAST(m.nbi AS UNSIGNED) BETWEEN k.nbi_awal AND k.nbi_akhir
SET m.kelas_id = k.id;

CREATE TABLE users (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username   VARCHAR(50)  NOT NULL UNIQUE,
    password   VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Akun default: username "admin", password "admin123" (bcrypt, cocok dengan password_verify PHP).
-- SEGERA GANTI setelah login pertama.
INSERT INTO users (username, password) VALUES
('admin', '$2b$10$Fp.e5HbN1VDQVtoIbp/pd.dCKjaiC8tjDmxa8hXvpcHACEnPU3NNy');

-- =====================================================================
-- B. MASTER ERP
-- =====================================================================

CREATE TABLE satuan (
    id   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    kode VARCHAR(10) NOT NULL UNIQUE,
    nama VARCHAR(50) NOT NULL
) ENGINE=InnoDB;

CREATE TABLE supplier (
    id      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    kode    VARCHAR(20)  NOT NULL UNIQUE,
    nama    VARCHAR(100) NOT NULL,
    kontak  VARCHAR(100) NULL,
    telepon VARCHAR(20)  NULL,
    email   VARCHAR(100) NULL,
    alamat  VARCHAR(255) NULL,
    aktif   TINYINT(1)   NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Item = bahan baku (RM), barang setengah jadi (SFG), barang jadi (FG).
-- Parameter MRP (lead time, lot sizing, safety stock) melekat di item.
CREATE TABLE item (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    kode            VARCHAR(30)  NOT NULL UNIQUE,
    nama            VARCHAR(120) NOT NULL,
    tipe            ENUM('FG','SFG','RM') NOT NULL,
    satuan_id       INT UNSIGNED NOT NULL,
    pengadaan       ENUM('beli','produksi') NOT NULL,
    supplier_id     INT UNSIGNED NULL,
    lead_time_minggu TINYINT UNSIGNED NOT NULL DEFAULT 1,
    lot_sizing      ENUM('L4L','FOQ','POQ') NOT NULL DEFAULT 'L4L',
    ukuran_lot      DECIMAL(14,2) NOT NULL DEFAULT 0,      -- dipakai bila FOQ
    periode_poq     TINYINT UNSIGNED NOT NULL DEFAULT 1,   -- dipakai bila POQ
    stok_pengaman   DECIMAL(14,2) NOT NULL DEFAULT 0,
    stok            DECIMAL(14,2) NOT NULL DEFAULT 0,      -- saldo on-hand (diubah lewat stok_mutasi)
    harga_standar   DECIMAL(14,2) NOT NULL DEFAULT 0,
    aktif           TINYINT(1)   NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_item_tipe (tipe),
    CONSTRAINT fk_item_satuan   FOREIGN KEY (satuan_id)   REFERENCES satuan(id),
    CONSTRAINT fk_item_supplier FOREIGN KEY (supplier_id) REFERENCES supplier(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Bill of Materials: 1 baris = 1 komponen (child) untuk 1 induk (parent).
CREATE TABLE bom (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    parent_item_id INT UNSIGNED NOT NULL,
    child_item_id  INT UNSIGNED NOT NULL,
    qty_per        DECIMAL(14,4) NOT NULL,                 -- kebutuhan komponen per 1 unit induk
    scrap_persen   DECIMAL(5,2)  NOT NULL DEFAULT 0,
    catatan        VARCHAR(255)  NULL,
    UNIQUE KEY uq_bom (parent_item_id, child_item_id),
    INDEX idx_bom_child (child_item_id),
    CONSTRAINT fk_bom_parent FOREIGN KEY (parent_item_id) REFERENCES item(id) ON DELETE CASCADE,
    CONSTRAINT fk_bom_child  FOREIGN KEY (child_item_id)  REFERENCES item(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================================================================
-- C. TRANSAKSI
-- =====================================================================

-- Kebutuhan independen (pesanan pelanggan / forecast) = input MPS.
CREATE TABLE kebutuhan_independen (
    id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    item_id            INT UNSIGNED NOT NULL,
    tanggal_dibutuhkan DATE NOT NULL,
    qty                DECIMAL(14,2) NOT NULL,
    jenis              ENUM('pesanan','forecast') NOT NULL DEFAULT 'pesanan',
    no_referensi       VARCHAR(50)  NULL,
    keterangan         VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_keb_tanggal (tanggal_dibutuhkan),
    CONSTRAINT fk_keb_item FOREIGN KEY (item_id) REFERENCES item(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Buku besar stok: setiap perubahan saldo item tercatat di sini.
CREATE TABLE stok_mutasi (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    item_id     INT UNSIGNED NOT NULL,
    tipe        ENUM('masuk','keluar','penyesuaian') NOT NULL,
    qty         DECIMAL(14,2) NOT NULL,                    -- bertanda: + menambah, - mengurangi
    stok_setelah DECIMAL(14,2) NOT NULL,
    ref_tipe    VARCHAR(20)  NULL,                         -- 'PO' / 'WO' / 'MANUAL'
    ref_id      INT UNSIGNED NULL,
    keterangan  VARCHAR(255) NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_mutasi_item (item_id, created_at),
    CONSTRAINT fk_mutasi_item FOREIGN KEY (item_id) REFERENCES item(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE purchase_order (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    no_po       VARCHAR(30) NOT NULL UNIQUE,
    supplier_id INT UNSIGNED NOT NULL,
    tanggal_po  DATE NOT NULL,
    status      ENUM('draft','open','diterima','batal') NOT NULL DEFAULT 'draft',
    catatan     VARCHAR(255) NULL,
    kelas_id    INT UNSIGNED NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_po_status (status),
    CONSTRAINT fk_po_supplier FOREIGN KEY (supplier_id) REFERENCES supplier(id),
    CONSTRAINT fk_po_kelas    FOREIGN KEY (kelas_id)    REFERENCES kelas(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE purchase_order_detail (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    po_id         INT UNSIGNED NOT NULL,
    item_id       INT UNSIGNED NOT NULL,
    qty           DECIMAL(14,2) NOT NULL,
    harga         DECIMAL(14,2) NOT NULL DEFAULT 0,
    tanggal_terima DATE NOT NULL,                          -- estimasi barang datang
    qty_diterima  DECIMAL(14,2) NOT NULL DEFAULT 0,
    CONSTRAINT fk_pod_po   FOREIGN KEY (po_id)   REFERENCES purchase_order(id) ON DELETE CASCADE,
    CONSTRAINT fk_pod_item FOREIGN KEY (item_id) REFERENCES item(id)
) ENGINE=InnoDB;

-- Work order = perintah produksi item FG/SFG. PIC & kelas menghubungkan ERP dengan data mahasiswa.
CREATE TABLE work_order (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    no_wo           VARCHAR(30) NOT NULL UNIQUE,
    item_id         INT UNSIGNED NOT NULL,
    qty             DECIMAL(14,2) NOT NULL,
    tanggal_mulai   DATE NOT NULL,
    tanggal_selesai DATE NOT NULL,
    status          ENUM('planned','released','selesai','batal') NOT NULL DEFAULT 'planned',
    kelas_id        INT UNSIGNED NULL,
    pic_mahasiswa_id INT UNSIGNED NULL,
    catatan         VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_wo_status (status),
    CONSTRAINT fk_wo_item  FOREIGN KEY (item_id)          REFERENCES item(id),
    CONSTRAINT fk_wo_kelas FOREIGN KEY (kelas_id)         REFERENCES kelas(id)     ON DELETE SET NULL,
    CONSTRAINT fk_wo_pic   FOREIGN KEY (pic_mahasiswa_id) REFERENCES mahasiswa(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- =====================================================================
-- D. MRP  (setiap eksekusi MRP disimpan sebagai snapshot)
-- =====================================================================

CREATE TABLE mrp_run (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    kode             VARCHAR(30) NOT NULL UNIQUE,
    tanggal_mulai    DATE NOT NULL,                        -- selalu hari Senin (awal minggu ke-1)
    horizon_minggu   TINYINT UNSIGNED NOT NULL,
    kelas_id         INT UNSIGNED NULL,                    -- kelas yang menjalankan
    pic_mahasiswa_id INT UNSIGNED NULL,                    -- mahasiswa penanggung jawab
    catatan          VARCHAR(255) NULL,
    total_planned_order INT UNSIGNED NOT NULL DEFAULT 0,
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_run_kelas FOREIGN KEY (kelas_id)         REFERENCES kelas(id)     ON DELETE SET NULL,
    CONSTRAINT fk_run_pic   FOREIGN KEY (pic_mahasiswa_id) REFERENCES mahasiswa(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Snapshot parameter item saat MRP dijalankan (agar hasil bisa dijelaskan ulang).
CREATE TABLE mrp_item (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    run_id        INT UNSIGNED NOT NULL,
    item_id       INT UNSIGNED NOT NULL,
    level_bom     TINYINT UNSIGNED NOT NULL,               -- low-level code
    stok_awal     DECIMAL(14,2) NOT NULL,
    stok_pengaman DECIMAL(14,2) NOT NULL,
    lead_time_minggu TINYINT UNSIGNED NOT NULL,
    lot_sizing    ENUM('L4L','FOQ','POQ') NOT NULL,
    ukuran_lot    DECIMAL(14,2) NOT NULL,
    periode_poq   TINYINT UNSIGNED NOT NULL,
    UNIQUE KEY uq_mrp_item (run_id, item_id),
    CONSTRAINT fk_mi_run  FOREIGN KEY (run_id)  REFERENCES mrp_run(id) ON DELETE CASCADE,
    CONSTRAINT fk_mi_item FOREIGN KEY (item_id) REFERENCES item(id)    ON DELETE CASCADE
) ENGINE=InnoDB;

-- Tabel MRP berperiode (time-phased): 1 baris = 1 item x 1 minggu.
CREATE TABLE mrp_hasil (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    run_id           INT UNSIGNED NOT NULL,
    item_id          INT UNSIGNED NOT NULL,
    minggu           TINYINT UNSIGNED NOT NULL,
    tanggal_awal     DATE NOT NULL,
    gross            DECIMAL(14,2) NOT NULL DEFAULT 0,     -- gross requirements
    scheduled_receipt DECIMAL(14,2) NOT NULL DEFAULT 0,
    proj_on_hand     DECIMAL(14,2) NOT NULL DEFAULT 0,
    net_req          DECIMAL(14,2) NOT NULL DEFAULT 0,
    planned_receipt  DECIMAL(14,2) NOT NULL DEFAULT 0,
    planned_release  DECIMAL(14,2) NOT NULL DEFAULT 0,
    UNIQUE KEY uq_mrp_hasil (run_id, item_id, minggu),
    CONSTRAINT fk_mh_run  FOREIGN KEY (run_id)  REFERENCES mrp_run(id) ON DELETE CASCADE,
    CONSTRAINT fk_mh_item FOREIGN KEY (item_id) REFERENCES item(id)    ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE mrp_planned_order (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    run_id         INT UNSIGNED NOT NULL,
    item_id        INT UNSIGNED NOT NULL,
    jenis          ENUM('PO','WO') NOT NULL,
    qty            DECIMAL(14,2) NOT NULL,
    minggu_release TINYINT UNSIGNED NOT NULL,
    tanggal_release DATE NOT NULL,
    tanggal_butuh  DATE NOT NULL,
    terlambat      TINYINT(1) NOT NULL DEFAULT 0,          -- 1 = seharusnya sudah dirilis sebelum minggu ke-1
    status         ENUM('planned','dikonversi') NOT NULL DEFAULT 'planned',
    po_id          INT UNSIGNED NULL,
    wo_id          INT UNSIGNED NULL,
    INDEX idx_mpo_run (run_id, status),
    CONSTRAINT fk_mpo_run  FOREIGN KEY (run_id)  REFERENCES mrp_run(id) ON DELETE CASCADE,
    CONSTRAINT fk_mpo_item FOREIGN KEY (item_id) REFERENCES item(id)    ON DELETE CASCADE,
    CONSTRAINT fk_mpo_po   FOREIGN KEY (po_id)   REFERENCES purchase_order(id) ON DELETE SET NULL,
    CONSTRAINT fk_mpo_wo   FOREIGN KEY (wo_id)   REFERENCES work_order(id)     ON DELETE SET NULL
) ENGINE=InnoDB;


-- =====================================================================
-- DATA CONTOH ERP : pabrik mebel "Meja Belajar" & "Kursi Belajar"
-- Semua tanggal relatif terhadap hari import (minggu ke-1 = minggu berjalan)
-- =====================================================================
SET @senin = DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY);

INSERT INTO satuan (id, kode, nama) VALUES
(1,'pcs','Pieces'),(2,'lbr','Lembar'),(3,'btg','Batang'),
(4,'mtr','Meter'),(5,'ltr','Liter'),(6,'set','Set');

INSERT INTO supplier (id, kode, nama, kontak, telepon, email, alamat) VALUES
(1,'SUP-001','CV Kayu Makmur Sentosa','Pak Hadi','081311110001','sales@kayumakmur.example.com','Sidoarjo'),
(2,'SUP-002','PT Multi Hardware Jaya','Bu Lina','081311110002','order@multihardware.example.com','Surabaya'),
(3,'SUP-003','UD Cat & Finishing Prima','Pak Anton','081311110003','prima@finishing.example.com','Surabaya'),
(4,'SUP-004','CV Busa & Tekstil Nusantara','Bu Rani','081311110004','cs@busatekstil.example.com','Gresik');

-- id : kode, nama, tipe, satuan, pengadaan, supplier, LT, lot, ukuran_lot, POQ, safety, stok, harga
INSERT INTO item (id, kode, nama, tipe, satuan_id, pengadaan, supplier_id, lead_time_minggu, lot_sizing, ukuran_lot, periode_poq, stok_pengaman, stok, harga_standar) VALUES
(1 ,'FG-001' ,'Meja Belajar'          ,'FG' ,1,'produksi',NULL,1,'L4L', 0,1, 0,   5, 650000),
(2 ,'FG-002' ,'Kursi Belajar'         ,'FG' ,1,'produksi',NULL,1,'FOQ',10,1, 0,   8, 320000),
(3 ,'SFG-001','Papan Meja'            ,'SFG',1,'produksi',NULL,1,'L4L', 0,1, 0,   4, 250000),
(4 ,'SFG-002','Rangka Kaki Meja'      ,'SFG',1,'produksi',NULL,1,'L4L', 0,1, 0,   3, 180000),
(5 ,'SFG-003','Rangka Kursi'          ,'SFG',1,'produksi',NULL,1,'L4L', 0,1, 0,   6, 150000),
(6 ,'RM-001' ,'Triplek 18 mm'         ,'RM' ,2,'beli',1,2,'FOQ',20,1,10,  30, 185000),
(7 ,'RM-002' ,'Kayu Balok 5x5'        ,'RM' ,3,'beli',1,2,'POQ', 0,2,20, 120,  45000),
(8 ,'RM-003' ,'Sekrup Kayu'           ,'RM' ,1,'beli',2,1,'FOQ',500,1,200,1500,    300),
(9 ,'RM-004' ,'Edging Tape'           ,'RM' ,4,'beli',2,1,'L4L', 0,1,20, 100,   2500),
(10,'RM-005' ,'Vernis / Cat Finishing','RM' ,5,'beli',3,1,'FOQ',10,1, 5,  12,  65000),
(11,'RM-006' ,'Busa Dudukan'          ,'RM' ,1,'beli',4,2,'L4L', 0,1, 5,  20,  35000),
(12,'RM-007' ,'Kain Pelapis'          ,'RM' ,4,'beli',4,2,'L4L', 0,1, 0,  25,  28000);

-- BOM multi-level (RM-003 & RM-005 dipakai di dua level -> contoh low-level coding)
INSERT INTO bom (parent_item_id, child_item_id, qty_per, scrap_persen, catatan) VALUES
(1 ,3 , 1   ,0,'Papan meja'),
(1 ,4 , 1   ,0,'Rangka kaki'),
(1 ,8 ,16   ,0,'Sekrup perakitan akhir'),
(3 ,6 , 0.5 ,5,'1 lembar triplek untuk 2 papan'),
(3 ,9 , 4   ,0,'Edging keliling papan'),
(3 ,10, 0.2 ,0,'Finishing papan'),
(4 ,7 , 4   ,3,'4 batang kaki'),
(4 ,8 , 8   ,0,'Sekrup rangka'),
(4 ,10, 0.3 ,0,'Finishing rangka'),
(2 ,5 , 1   ,0,'Rangka kursi'),
(2 ,11, 1   ,0,'Busa dudukan'),
(2 ,12, 0.8 ,0,'Kain pelapis'),
(2 ,8 ,12   ,0,'Sekrup perakitan akhir'),
(5 ,7 , 3   ,3,'Kaki & rangka kursi'),
(5 ,6 , 0.25,5,'Sandaran dari triplek'),
(5 ,10, 0.15,0,'Finishing rangka kursi');

-- Kebutuhan independen (input MPS) - tanggal = hari Kamis pada minggu ke-n
INSERT INTO kebutuhan_independen (item_id, tanggal_dibutuhkan, qty, jenis, no_referensi, keterangan) VALUES
(1, DATE_ADD(@senin, INTERVAL 7*1+3 DAY), 20, 'pesanan','SO-0001','Pesanan sekolah'),
(1, DATE_ADD(@senin, INTERVAL 7*3+3 DAY), 30, 'pesanan','SO-0002','Pesanan kampus'),
(1, DATE_ADD(@senin, INTERVAL 7*5+3 DAY), 25, 'forecast',NULL,'Perkiraan penjualan'),
(2, DATE_ADD(@senin, INTERVAL 7*2+3 DAY), 40, 'pesanan','SO-0003','Pesanan sekolah'),
(2, DATE_ADD(@senin, INTERVAL 7*4+3 DAY), 30, 'pesanan','SO-0004','Pesanan kampus'),
(2, DATE_ADD(@senin, INTERVAL 7*6+3 DAY), 35, 'forecast',NULL,'Perkiraan penjualan');

-- Satu PO yang sudah terbit (menjadi scheduled receipt di MRP)
INSERT INTO purchase_order (id, no_po, supplier_id, tanggal_po, status, catatan) VALUES
(1,'PO-SEED-0001',1,CURDATE(),'open','PO contoh (data awal)');
INSERT INTO purchase_order_detail (po_id, item_id, qty, harga, tanggal_terima) VALUES
(1,6,20,185000,DATE_ADD(@senin, INTERVAL 7 DAY));

-- Riwayat stok awal
INSERT INTO stok_mutasi (item_id, tipe, qty, stok_setelah, ref_tipe, keterangan)
SELECT id, 'penyesuaian', stok, stok, 'MANUAL', 'Stok awal (seed)' FROM item WHERE stok > 0;

-- =====================================================================
-- E. VIEW
-- =====================================================================
CREATE VIEW v_rekap_kelas AS
SELECT k.id, k.kode, k.nama, k.nbi_awal, k.nbi_akhir, k.kapasitas,
       COUNT(m.id) AS jumlah_mahasiswa
FROM kelas k
LEFT JOIN mahasiswa m ON m.kelas_id = k.id
GROUP BY k.id, k.kode, k.nama, k.nbi_awal, k.nbi_akhir, k.kapasitas;

CREATE VIEW v_stok_item AS
SELECT i.id, i.kode, i.nama, i.tipe, s.kode AS satuan, i.stok, i.stok_pengaman,
       CASE WHEN i.stok <= 0 THEN 'habis'
            WHEN i.stok < i.stok_pengaman THEN 'kritis'
            ELSE 'aman' END AS status_stok
FROM item i JOIN satuan s ON s.id = i.satuan_id;

CREATE VIEW v_bom_detail AS
SELECT b.id, p.kode AS kode_induk, p.nama AS nama_induk,
       c.kode AS kode_komponen, c.nama AS nama_komponen,
       b.qty_per, b.scrap_persen,
       ROUND(b.qty_per * (1 + b.scrap_persen/100), 4) AS qty_efektif
FROM bom b
JOIN item p ON p.id = b.parent_item_id
JOIN item c ON c.id = b.child_item_id;
