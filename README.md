# refleksi-for-mentee

Proyek ini digunakan untuk penggunaan dalam kalangan kecil, telah disesuaikan agar menjadi generik. Hanya bisa dihost pada server PHP (butuh ekstensi `mysqli`, `curl`, dan `openssl`) dengan database MySQL/MariaDB.

## Konfigurasi

Semua pengaturan disimpan di file `.env` (tidak di-commit ke Git). Salin contohnya terlebih dahulu:

```bash
cp .env.example .env
```

Lalu isi nilai berikut di `.env`:

| Variabel | Keterangan |
| --- | --- |
| `APP_TIMEZONE` | Zona waktu PHP & sesi MySQL, default `Asia/Jakarta` |
| `DB_SERVER` | Host database, mis. `localhost` |
| `DB_USERNAME` | Username database |
| `DB_PASSWORD` | Password database |
| `DB_NAME` | Nama database |
| `GEMINI_API_KEY` | API key Gemini dari https://aistudio.google.com |
| `GEMINI_MODEL` | Model Gemini, default `gemini-1.5-flash-latest` |
| `OPENROUTER_API_KEY` | API key OpenRouter (server-side, tidak pernah dikirim ke browser) |
| `OPENROUTER_MODEL` | Opsional: model gratis yang diprioritaskan |
| `GITHUB_TOKEN` | Token GitHub dengan izin **Issues: Write** untuk laporan skema baru (nama lama `GITHUB_FINEGRAINED_TOKEN` juga didukung) |
| `GITHUB_REPO` | Repo tujuan issue, default `krasyid822/refleksi-for-mentee` |
| `KATA_KATA_REFRESH_TOKEN` | Opsional: token untuk memaksa refresh cache kata-kata |
| `FTP_URL` | URL FTP tujuan deploy |
| `FTP_USER` | Username FTP |
| `FTP_PASSWORD` | Password FTP |
| `FTP_SYNCROOT` | Sub-folder tujuan FTP (opsional) |

Jika variabel environment sudah diatur di server (Apache/Nginx), nilai tersebut akan diprioritaskan dibanding isi `.env`.

`APP_TIMEZONE` diterapkan ke PHP dan sesi MySQL (`SET time_zone`), sehingga `NOW()`/`CURRENT_TIMESTAMP` konsisten dengan waktu PHP. Kartu "Waktu Sekarang" menampilkan rincian PHP/MySQL/pengguna hanya jika waktu atau zonanya belum sinkron.

### Enkripsi konfigurasi server

Sebelum deploy FTP, jalankan:

```bash
./let-encrypt.sh
```

Skrip ini:

- Mengenkripsi seluruh isi `.env` menjadi `.env.enc` memakai AES-256-GCM.
- Memasang kunci 32 byte ke `env.php` dalam bentuk deretan angka (bukan string yang terlihat seperti API key).
- Memakai ulang kunci yang sudah ada; gunakan `--rotate` hanya bila ingin mengganti kunci.
- Bisa diverifikasi dengan `./let-encrypt.sh --check`.

`env.php` memuat `.env` lebih dulu (untuk pengembangan lokal), lalu `.env.enc` bila `.env` tidak ada (server). `.env` tidak diunggah ke FTP; yang diunggah hanya `.env.enc`.

> Catatan: ini adalah lapisan pertahanan tambahan, bukan jaminan absolut. Jika penyerang bisa membaca `env.php` **dan** `.env.enc`, kunci tetap bisa direkonstruksi. Karena itu `.htaccess` juga menolak akses langsung ke file konfigurasi, dan `env.php` menolak akses langsung dari browser.

### Database lokal (`.env.local`)

Database InfinityFree hanya bisa diakses dari hostingnya, sehingga di komputer sendiri gunakan database lokal. Buat file `.env.local` (tidak di-commit & tidak diunggah ke FTP):

```bash
cp .env.local.example .env.local
```

Isi kredensial database lokal:

```ini
DB_SERVER=127.0.0.1
DB_USERNAME=root
DB_PASSWORD=
DB_NAME=refleksi_local
```

Nilai di `.env.local` **menimpa** `.env`. Di server, `.env.local` tidak ada sehingga yang dipakai tetap `.env.enc`. Siapkan database lokalnya:

```bash
mysql -u root -p -e "CREATE DATABASE refleksi_local CHARACTER SET utf8mb4;"
mysql -u root -p refleksi_local < FORMAT_DATABASE.sql
```

## Menjalankan secara lokal

```bash
./run.sh                  # http://localhost:8000
PORT=9000 ./run.sh        # ganti port
HOST=0.0.0.0 ./run.sh     # dapat diakses dari jaringan lokal
```

## Impor database

```bash
mysql -u <user> -p <nama_database> < FORMAT_DATABASE.sql
```

## PWA, build, dan cache

PWA sudah aktif melalui `manifest.json`, `service-worker.js`, dan `pwa-register.js`.

- `build.sh` menaikkan nomor build di `build.json` dan mengganti `BUILD_ID` di service worker.
- `push.sh` memanggil `build.sh` otomatis sebelum commit.
- Setiap build baru memakai nama cache baru (`refleksi-cache-<build_id>`), sehingga cache PWA lama dihapus saat service worker aktif.
- `pwa-register.js` memeriksa `build.json` secara berkala dan memuat ulang halaman saat build baru terdeteksi.
- Halaman offline sederhana tersedia di `offline.html`.

Build juga bisa dijalankan manual:

```bash
./build.sh
```

## Form Bebas berbantuan AI

`form_bebas.php` hanya punya satu kolom teks bebas. Saat dikirim:

1. AI membaca cerita dan memilih varian tabel yang paling cocok dari database.
2. AI memecah cerita menjadi kolom-kolom terstruktur sesuai skema tabel tersebut.
3. Data divalidasi, dinormalisasi, lalu disimpan dengan prepared statement.
4. Jika tidak ada tabel yang cocok, hasil ekstraksi dan usulan skema dikirim ke GitHub issue (atau diantrikan di `cache/` bila token belum diatur).

Struktur tabel ditemukan otomatis lewat `schema_helper.php` (bukan daftar hard-code), sehingga varian tabel baru ikut terdeteksi.

### Cache kata-kata

Pertanyaan pemicu refleksi disimpan di `kata-kata.json`:

- Cache dipakai selama masih segar (7 hari) dan jumlahnya cukup.
- Saat kedaluwarsa, halaman memakai cache lama lalu memicu `kata-kata-refresh.php` di belakang layar.
- AI hanya dipanggil saat refresh diperlukan; jika gagal, percobaan ditunda 24 jam.
- `kata-kata.json` tidak diunggah lewat FTP agar cache di server tidak tertimpa; server membuatnya otomatis.

### OpenRouter

Semua panggilan OpenRouter dilakukan di server melalui `openrouter_helper.php` dan `ai_proxy.php`. Browser tidak pernah menerima API key. `OPENROUTER_MODEL` (bila diisi dan berakhiran `:free`) dicoba lebih dulu; jika gagal, helper otomatis mencoba model gratis lain, mendukung fallback `response_format`, dan mengekstrak JSON dari teks model bila perlu.

## Push / deploy (GitHub & FTP)

Gunakan `push.sh` untuk mengirim perubahan ke GitHub dan/atau server FTP.

```bash
./push.sh                  # build + commit + push ke GitHub dan FTP
./push.sh github           # hanya ke GitHub
./push.sh ftp              # hanya ke server FTP
./push.sh ftp --init       # unggahan pertama ke server FTP kosong
./push.sh all -m "pesan"   # tentukan pesan commit
./push.sh all --no-commit  # push tanpa commit baru
```

Saat commit baru dibuat, `push.sh` otomatis:

1. Menjalankan `build.sh` (nomor build naik, cache PWA direset).
2. Untuk tujuan FTP, menjalankan `let-encrypt.sh` (membuat `.env.enc`).
3. Melakukan pemeriksaan keamanan, commit, lalu push.

Aturan file yang dikirim:

- **GitHub** — mengikuti `.gitignore`, ditambah pemeriksaan keamanan bawaan `push.sh` yang menolak file seperti `.env`, `*.pem`, `*.key`, `*.ppk`, `id_rsa*`, `*.p12`, dan `*.keystore`.
- **FTP** — mengikuti `.git-ftp-ignore` (file yang dilewati, mis. `run.sh`, `README.md`, `FORMAT_DATABASE.sql`, `kata-kata.json`) dan `.git-ftp-include` (file yang dipaksa ikut, yaitu `.env.enc`).

> Setelah beralih dari `.env` ke `.env.enc`, hapus `.env` lama di server sekali secara manual. `git-ftp` tidak menghapus file yang cocok dengan `.git-ftp-ignore`.

Deploy FTP memerlukan [git-ftp](https://github.com/git-ftp/git-ftp) dan kredensial FTP di `.env`.

## Pull (tarik dari server FTP)

Gunakan `pull.sh` untuk mengambil file terbaru dari server FTP.
**File yang hanya ada di lokal tidak akan dihapus** (mirror tanpa `--delete`).

```bash
./pull.sh              # unduh file terbaru dari server
./pull.sh --dry-run    # lihat rencana unduhan tanpa mengubah apa pun
./pull.sh --with-env   # sekaligus unduh .env dan .env.enc
```

Secara default file berikut **tidak** ditarik: `.git-ftp.log`, `.env`, dan `.env.enc`
(agar konfigurasi lokal tidak tertimpa). Fitur ini memerlukan `lftp`.

## Berkas utama

- `env.php` - bootstrap: memuat `.env`/`.env.enc`, mendefinisikan konstanta, dan membuat koneksi database.
- `app_helper.php` - folder cache, baca/tulis JSON, dan penyamaran teks.
- `openrouter_helper.php` - klien OpenRouter server-side dengan fallback model.
- `ai_proxy.php` - endpoint aman untuk model & kalimat epik form psikologi.
- `schema_helper.php` - deteksi varian tabel, validasi, dan INSERT dinamis.
- `github_helper.php` - pembuatan issue untuk skema baru.
- `kata_kata_helper.php` / `kata-kata-refresh.php` - cache pertanyaan refleksi.
- `form_bebas.php` - form satu kolom berbantuan AI.
- `build.sh` / `build.json` - versi build dan reset cache PWA.
- `let-encrypt.sh` - enkripsi `.env` dan pemasangan kunci di `env.php`.
- `clock_helper.php` - membaca waktu server PHP & MySQL untuk kartu "Waktu Sekarang".
- `time.php` - endpoint JSON sumber waktu server (dipakai jam analog dan overlay audio).
- `run.sh` - menjalankan server PHP untuk pengembangan.
- `push.sh` - push ke GitHub dan/atau server FTP.
- `pull.sh` - tarik file terbaru dari server FTP tanpa menghapus file lokal.
- `.git-ftp-ignore` / `.git-ftp-include` - aturan file untuk deploy FTP.
- `FORMAT_DATABASE.sql` - struktur tabel database.

## Sourcecode
https://g.co/gemini/share/0a9d1d95003c

## Changelogs
- Form Bebas dengan ekstraksi AI dan pemetaan skema dinamis.
- Cache kata-kata AI (`kata-kata.json`) dengan siklus refresh.
- OpenRouter dipindahkan sepenuhnya ke server (API key tidak lagi bocor ke browser).
- Enkripsi `.env.enc` + kunci terpatri di `env.php`.
- Build PWA otomatis dan reset cache saat push.
- Overlay audio memuat `div.clock` dan memberi pemicu saat autoplay diblokir browser.
- Menggunakan teknologi View Transitions API.

## Catatan
satukan semua varian form sebelumnya menjadi Form Klasik.