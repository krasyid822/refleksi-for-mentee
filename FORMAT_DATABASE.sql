--
-- Struktur dari tabel `refleksi_mingguan`
--

CREATE TABLE `refleksi_mingguan` (
  `id` int(11) NOT NULL,
  `nama_mentee` varchar(255) NOT NULL,
  `nama_mentor` varchar(255) NOT NULL,
  `nama_disamarkan` tinyint(1) NOT NULL DEFAULT 0,
  `sandi_nama` varchar(255) DEFAULT NULL,
  `periode_minggu` int(3) NOT NULL,
  `periode_tanggal` varchar(50) NOT NULL,
  `pencapaian` text NOT NULL,
  `kemajuan_tujuan` text NOT NULL,
  `tantangan` text NOT NULL,
  `pembelajaran_baru` text NOT NULL,
  `skor_energi` int(2) NOT NULL,
  `penjelasan_energi` text NOT NULL,
  `momen_berkesan` text NOT NULL,
  `prioritas_mingguan` text NOT NULL,
  `tujuan_spesifik` text NOT NULL,
  `topik_diskusi` text NOT NULL,
  `bantuan_diharapkan` text NOT NULL,
  `catatan_lain` text DEFAULT NULL,
  `kalimat_epik` text DEFAULT NULL,
  `tanggal_submit` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indeks untuk tabel `refleksi_mingguan`
--
ALTER TABLE `refleksi_mingguan`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT untuk tabel `refleksi_mingguan`
--
ALTER TABLE `refleksi_mingguan`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;
COMMIT;