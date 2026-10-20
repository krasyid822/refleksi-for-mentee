# refleksi-for-mentee

Proyek ini digunakan untuk penggunaan dalam kalangan kecil, telah disesuaikan agar menjadi generik. Hanya bisa dihost pada server PHP (butuh ekstensi `mysqli` dan `curl`) dengan database MySQL/MariaDB.

## Konfigurasi

Semua pengaturan disimpan di file `.env` (tidak di-commit ke Git). Salin contohnya terlebih dahulu:

```bash
cp .env.example .env
```

Lalu isi nilai berikut di `.env`:

| Variabel         | Keterangan                                              |
| ---------------- | ------------------------------------------------------- |
| `APP_TIMEZONE`   | Zona waktu PHP & sesi MySQL, default `Asia/Jakarta`      |
| `DB_SERVER`      | Host database, mis. `localhost`                         |
| `DB_USERNAME`    | Username database                                       |
| `DB_PASSWORD`    | Password database                                       |
| `DB_NAME`        | Nama database                                           |
| `GEMINI_API_KEY` | API key Gemini dari https://aistudio.google.com         |
| `GEMINI_MODEL`   | Model Gemini, default `gemini-1.5-flash-latest`         |
| `OPENROUTER_API_KEY` | API key OpenRouter untuk fitur tes psikologi        |
| `FTP_URL`        | URL FTP tujuan deploy                                   |
| `FTP_USER`       | Username FTP                                            |
| `FTP_PASSWORD`   | Password FTP                                            |
| `FTP_SYNCROOT`   | Sub-folder tujuan FTP (opsional)                        |

Jika variabel environment sudah diatur di server (Apache/Nginx), nilai tersebut akan diprioritaskan dibanding isi `.env`.

`APP_TIMEZONE` diterapkan ke PHP dan sesi MySQL (`SET time_zone`), sehingga `NOW()`/`CURRENT_TIMESTAMP` konsisten dengan waktu PHP. Kartu "Waktu Sekarang" menampilkan rincian PHP/MySQL/pengguna hanya jika waktu atau zonanya belum sinkron.

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

Nilai di `.env.local` **menimpa** `.env`. Di server, `.env.local` tidak ada sehingga yang dipakai tetap `.env`. Siapkan database lokalnya:

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

## Push / deploy (GitHub & FTP)

Gunakan `push.sh` untuk mengirim perubahan ke GitHub dan/atau server FTP.

```bash
./push.sh                  # commit + push ke GitHub dan FTP
./push.sh github           # hanya ke GitHub
./push.sh ftp              # hanya ke server FTP
./push.sh ftp --init       # unggahan pertama ke server FTP kosong
./push.sh all -m "pesan"   # tentukan pesan commit
./push.sh all --no-commit  # push tanpa commit baru
```

Aturan file yang dikirim:

- **GitHub** — mengikuti `.gitignore`, ditambah pemeriksaan keamanan bawaan
  `push.sh` yang menolak file seperti `.env`, `*.pem`, `*.key`, `*.ppk`,
  `id_rsa*`, `*.p12`, dan `*.keystore`.
- **FTP** — mengikuti `.git-ftp-ignore` (file yang dilewati, mis. `run.sh`,
  `README.md`, `FORMAT_DATABASE.sql`) dan `.git-ftp-include` (file yang
  dipaksa ikut, yaitu `.env` agar konfigurasi server tetap tersedia).

Deploy FTP memerlukan [git-ftp](https://github.com/git-ftp/git-ftp) dan
kredensial FTP di `.env`.

## Pull (tarik dari server FTP)

Gunakan `pull.sh` untuk mengambil file terbaru dari server FTP.
**File yang hanya ada di lokal tidak akan dihapus** (mirror tanpa `--delete`).

```bash
./pull.sh              # unduh file terbaru dari server
./pull.sh --dry-run    # lihat rencana unduhan tanpa mengubah apa pun
./pull.sh --with-env   # sekaligus unduh .env (default: .env dilewati)
```

Secara default file berikut **tidak** ditarik: `.git-ftp.log` dan `.env`
(agar konfigurasi lokal tidak tertimpa). Fitur ini memerlukan `lftp`.

## Berkas utama

- `env.php` - bootstrap: memuat `.env`, mendefinisikan konstanta, dan membuat koneksi database.
- `clock_helper.php` - membaca waktu server PHP & MySQL untuk kartu "Waktu Sekarang".
- `time.php` - endpoint JSON sumber waktu server (dipakai jam analog di `index.php`).
- `run.sh` - menjalankan server PHP untuk pengembangan.
- `push.sh` - push ke GitHub dan/atau server FTP.
- `pull.sh` - tarik file terbaru dari server FTP tanpa menghapus file lokal.
- `.git-ftp-ignore` / `.git-ftp-include` - aturan file untuk deploy FTP.
- `FORMAT_DATABASE.sql` - struktur tabel database.

## Sourcecode
https://g.co/gemini/share/0a9d1d95003c

## Catatan
aktifakn pwa. buat sistem increment build yang otomatis terpicu saat jalankan push.sh.
buat script let-encrypt.sh untuk mengenkripsi semua key rahasia dan kuncinya di patch ke env.php.