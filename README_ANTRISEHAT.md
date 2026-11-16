# AntriSehat — Backend (Laravel + MySQL) — K05 Komputasi Awan

Sistem antrean & janji temu klinik. File di paket ini adalah lapisan aplikasi yang
disalin ke proyek Laravel baru (struktur folder sama dengan Laravel).

## 1. Pasang ke proyek Laravel baru

```bash
composer create-project laravel/laravel antrisehat
cd antrisehat
# salin isi zip ini ke root proyek (timpa bootstrap/app.php, routes/*.php, phpunit.xml)
```

Isi `.env`:

```
APP_NAME=AntriSehat
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=antrisehat
DB_USERNAME=root
DB_PASSWORD=
CLINIC_TIMEZONE=Asia/Jakarta
STAFF_API_TOKEN=ganti-dengan-token-rahasia
# opsional: hapus antrean lama saat reset harian
# QUEUE_RETENTION_DAYS=30
```

```bash
php artisan migrate --seed      # di APP_ENV=production hanya data master dokter/jadwal
php artisan serve
curl http://127.0.0.1:8000/health
```

Catatan: bila memakai MariaDB (bukan MySQL 8), tambahkan `DB_COLLATION=utf8mb4_unicode_ci`.

## 2. Endpoint

`GET /health` -> 200 `{"status":"ok","database":"up",...}`; 503 `{"database":"down"}` bila DB mati.

Publik (pasien / layar antrean):

| Method | Path | Fungsi |
|---|---|---|
| GET | /api/doctors, /api/doctors/{id} | daftar / detail dokter (+jadwal), filter `?poly=&active=` |
| GET | /api/doctors/{id}/schedules, /api/schedules/{id} | jadwal praktik |
| GET | /api/schedules/{id}/availability?date=YYYY-MM-DD | sisa kuota sesi |
| POST | /api/queues `{poly, patient_name}` | ambil nomor antrean poli (auto-increment per hari) |
| GET | /api/display[?poly=] | live display antrean hari ini |
| POST | /api/appointments `{patient_name, doctor_id, schedule_id, appointment_date}` | buat janji temu |

Staf (header `X-Staff-Token: <STAFF_API_TOKEN>` atau `Authorization: Bearer ...`):

| Method | Path | Fungsi |
|---|---|---|
| POST/PUT/PATCH/DELETE | /api/doctors[/{id}] | CRUD dokter |
| POST | /api/doctors/{id}/schedules | tambah jadwal |
| PUT/PATCH/DELETE | /api/schedules/{id} | ubah / hapus jadwal |
| GET | /api/queues?poly=&date=&status= | daftar antrean |
| POST | /api/queues/call-next `{poly}` | panggil antrean berikutnya (waiting -> called) |
| PATCH | /api/queues/{id}/finish, /cancel | selesai / batal |
| GET | /api/appointments, /api/appointments/{id} | daftar / detail janji temu |
| PATCH | /api/appointments/{id}/cancel | batalkan janji temu |

`day_of_week` memakai ISO: 1 = Senin ... 7 = Minggu. Error bisnis berbentuk
`{"message": "...", "code": "QUOTA_FULL"}` dengan status 409/422/404.

## 3. Aturan bisnis

- Nomor antrean = nomor terbesar poli tsb pada hari ini + 1 (zona waktu klinik), jadi otomatis mulai dari 1 tiap hari dan terpisah per poli. Pengambilan nomor diserialkan per poli + unique index `(poly, date, queue_number)`.
- Janji temu: jadwal harus milik dokter & dokter aktif; tanggal harus jatuh pada hari praktik, belum lewat, dan sesi hari ini belum berakhir; kuota sesi (`max_quota`, default 30) per tanggal; baris jadwal dikunci saat booking agar kuota tidak jebol saat request serentak.
- Anti-bentrok: pasien (nama, tanpa beda huruf besar/kecil & spasi ganda) tidak boleh punya janji aktif lain yang jamnya bertumpuk pada tanggal yang sama. Jam yang hanya menempel (12:00 dan 12:00) tidak dianggap bentrok. Janji yang dibatalkan tidak menghalangi / tidak memakai slot.
- Memanggil antrean berikutnya otomatis menyelesaikan tiket `called` sebelumnya di poli yang sama.
- Jadwal dokter tidak boleh bertumpuk; kuota tidak boleh diturunkan di bawah jumlah janji yang sudah ada; hari/jam jadwal yang sudah punya janji mendatang tidak bisa diubah; dokter/jadwal yang punya janji tidak bisa dihapus (nonaktifkan dokter).

## 4. Scheduler (tantangan DevOps K05)

`php artisan antrian:reset-harian` — tiap 00:00 waktu klinik menutup tiket hari sebelumnya yang
menggantung (waiting -> cancelled, called -> finished). Opsi: `--dry-run`, `--date=YYYY-MM-DD`, `--purge-days=N`.
Idempotent. Penomoran TIDAK bergantung pada job ini, jadi hari baru tetap mulai dari 1 walau scheduler mati.

Terdaftar di `routes/console.php`. Server cukup satu cron:

```
* * * * * cd /path/ke/app && php artisan schedule:run >> /dev/null 2>&1
```

(`onOneServer()` dan `withoutOverlapping()` memerlukan cache store yang bisa dijangkau, mis. `database`/`redis`.)

## 5. Test

```bash
php artisan test            # atau: vendor/bin/phpunit   (default SQLite in-memory)
# melawan MySQL:
DB_CONNECTION=mysql DB_DATABASE=antrisehat_test DB_USERNAME=root DB_PASSWORD= php artisan test
```

63 test: health, penomoran & reset harian, kuota 30, anti-bentrok, validasi slot, CRUD, command scheduler, akses staf.
Catatan: `phpunit.xml` memakai SQLite memori sebagai default; variabel `DB_*` yang diset di shell menimpanya (cara di atas).

## 6. Hasil verifikasi (di lingkungan penyusun)

- Laravel 11.57.1, PHP 8.3, PHPUnit 10.5: 63 test / 300 assertion lulus di SQLite dan di MariaDB 10.11.
- `migrate:fresh --seed` di MariaDB berhasil; seeder dijalankan dua kali tanpa duplikasi.
- Uji paralel ke server nyata: 60 booking serentak ke sesi berkuota 30 -> tepat 30 berhasil, 30 ditolak 409; 3x80 pengambilan nomor serentak -> semua 201, nomor unik tanpa lubang; 24 call-next serentak -> berurutan, hanya satu tiket `called`.
- Belum diuji: MySQL 8 asli, Laravel 12 ke atas, pipeline Jenkins sungguhan.
