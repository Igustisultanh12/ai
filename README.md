# XZY — Platform AI Chat & Vibecoding Mandiri

XZY adalah platform AI chat dan vibecoding modern berkinerja tinggi. Pengguna mendeskripsikan kebutuhan aplikasi melalui antarmuka percakapan natural, lalu AI menyusun rencana, mengusulkan patch kode, memverifikasi sintaks dan kelayakan pada runner sandbox terisolasi, menyajikan pratinjau instan (preview), menyimpan versi melalui checkpoint atomik, mengekspor proyek utuh (ZIP & genuine RAR), serta menyinkronkan kode ke GitHub.

---

## 1. Arsitektur & Teknologi

- **Backend**: Laravel 11/12 (PHP 8.3+) monolit modular tipis (Auth, Projects, Conversations, Agents, Usage, Sandbox, Exports, GitHub, Audit).
- **Frontend**: Vue 3, Composition API, TypeScript, Vite, Tailwind CSS v4, Monaco Editor (lazy loaded).
- **Database**: PostgreSQL 16 (kompatibel SQLite untuk lingkungan pengujian/dev cepat).
- **Queue, Cache & Distributed Locks**: Redis 7.
- **Private Object Storage**: Kompatibel S3 (MinIO / AWS S3) untuk artefak build dan berkas arsip ekspor.
- **Isolasi Sandbox**: Runner terpisah dengan argumen terstruktur (`proc_open` tanpa interpolasi shell), pembatasan resource, sanitasi variabel lingkungan (tanpa injeksi secret), dan validasi path kanonikal untuk menangkal path traversal (`../`) serta symlink escape.
- **Autentikasi**: Session-based authentication via cookie `HttpOnly` dan perlindungan CSRF. Autentikasi dua faktor (MFA/2FA) TOTP berbasis RFC 6238 dengan recovery codes ter-hash.

---

## 2. Fitur Utama

1. **Vibecoding Workspace Desktop & Mobile**:
   - Tiga area fleksibel (Desktop): File Explorer, Code Editor Monaco (tabs, syntax highlighting, pencarian, diff line-by-line), dan AI Chat / Live Preview / Build Diagnostic Logs.
   - Tab switcher adaptif pada layar mobile.
   - Mode tinjau usulan perubahan (diff inspector) sebelum diaplikasikan atau auto-apply instan.
   - Template awal lengkap **Laravel + Vue 3** yang langsung dapat dijalankan.
2. **Integrasi Gateway TokenomixAI**:
   - Provider resmi: TokenomixAI (`https://api.tokenomixai.com/v1`).
   - Sesuai spesifikasi dokumentasi: `GET /v1/models`, `POST /v1/chat/completions` (OpenAI format, `stream: true` SSE).
   - Penanganan error upstream bertingkat: 401 invalid key, 403 key disabled, 404 endpoint/model not found, 429 rate limit (membaca header `Retry-After`), 502 upstream unavailable (exponential backoff retry dengan jitter), 502 upstream auth.
   - Enkripsi API key di server (`Crypt::encryptString`), masking pada UI (`sk-tkx-••••••••`), tanpa kebocoran ke frontend atau log.
   - Sinkronisasi katalog model langsung dari gateway serta pengaturan multiplier per model.
3. **Akuntansi Token Presisi (Ledger & Kuota)**:
   - Rumus resmi: `raw_tokens = prompt_tokens + completion_tokens`, `weighted_tokens = raw_tokens × multiplier`.
   - Pemisahan data actual (resmi dari provider), estimated (karakter heuristik), dan unknown.
   - Ledger atomik dengan idempotency key guna mencegah double billing pada request paralel atau retry.
   - Kuota harian dan bulanan per pengguna, dengan dasbor admin global dan ekspor laporan CSV.
4. **Ekspor Proyek Asli (ZIP & RAR)**:
   - **ZIP**: Arsip asli yang dapat diekstrak langsung menggunakan `ZipArchive` PHP standar.
   - **RAR**: Dibuat menggunakan binary command-line RAR/WinRAR resmi berlisensi. Dilengkapi health check kesiapan (readiness probe) transparan jika binary belum dikonfigurasi.
   - Otomatis mengecualikan berkas rahasia `.env`, kredensial, `.git`, `node_modules`, dan `vendor`.
5. **Sinkronisasi GitHub**:
   - Integrasi Personal Access Token (PAT) atau GitHub App terenkripsi.
   - Pemindaian secret otomatis sebelum commit (memblokir AWS keys, token GitHub, atau berkas `.env`).
   - Deteksi konflik remote non-fast-forward tanpa merusak pekerjaan pengguna.
6. **Keamanan & Audit**:
   - TOTP MFA wajib untuk administrator pada lingkungan production.
   - Rotasi sesi login dan pencabutan sesi di perangkat lain.
   - Command bootstrap administrator aman tanpa password bawaan: `php artisan xzy:admin:bootstrap`.
   - Pencatatan jejak audit dengan correlation ID dan penyaringan otomatis kata kunci sensitif (redacted).

---

## 3. Menjalankan Aplikasi

### Opsi A: Menggunakan Docker Compose (Rekomendasi Produksi)

Lingkungan Docker Compose sudah mencakup App (Nginx + PHP-FPM), Background Worker, PostgreSQL 16, Redis 7, MinIO (S3 Object Storage), dan Sandbox Runner terisolasi.

1. Salin konfigurasi environment:
   ```bash
   cp .env.example .env
   ```
2. Jalankan container:
   ```bash
   docker compose up -d --build
   ```
3. Generate application key dan jalankan migrasi database:
   ```bash
   docker compose exec app php artisan key:generate
   docker compose exec app php artisan migrate --seed
   ```
4. Buat akun administrator pertama secara aman:
   ```bash
   docker compose exec app php artisan xzy:admin:bootstrap
   ```
5. Akses platform XZY melalui peramban: `http://localhost:8000`.

---

### Opsi B: Menjalankan Secara Lokal (Host Development)

**Prasyarat**: PHP 8.2+ (disarankan 8.3), Composer 2.x, Node.js 20+, npm.

1. Salin berkas lingkungan dan instal dependensi backend:
   ```bash
   cp .env.example .env
   composer install
   php artisan key:generate
   ```
2. Untuk pengujian lokal cepat menggunakan SQLite:
   ```bash
   touch database/database.sqlite
   # Sesuaikan DB_CONNECTION=sqlite pada .env jika diperlukan
   php artisan migrate --seed
   ```
3. Buat akun administrator:
   ```bash
   php artisan xzy:admin:bootstrap --email=admin@xzy.test --name="Admin XZY" --password="PasswordAman123!"
   ```
4. Instal dependensi frontend dan bangun bundle:
   ```bash
   npm install
   npm run build
   ```
5. Jalankan web server Laravel dan queue worker:
   ```bash
   # Terminal 1 (Web Server)
   php artisan serve --port=8000

   # Terminal 2 (Background Queue Worker)
   php artisan queue:work --queue=default,ai,build,export,github
   ```
6. Buka peramban di `http://localhost:8000`.

---

## 4. Panduan Administrator

### 1. Menyiapkan Gateway TokenomixAI
1. Masuk sebagai administrator, buka menu **🛡️ Dasbor Admin** → **🤖 Konfigurasi Agent**.
2. Masukkan URL: `https://api.tokenomixai.com/v1`.
3. Masukkan API key TokenomixAI Anda (`sk-tkx-...`).
4. Klik tombol **⚡ Uji Koneksi** untuk memvalidasi gateway secara live.
5. Klik **↻ Sinkronkan Katalog Model** untuk menarik seluruh model aktif (`GET /v1/models`).
6. Atur batas konkurensi, batas token, dan pastikan toggle **Aktifkan Gateway** menyala.

### 2. Mengaktifkan Fitur Ekspor RAR
Fitur ekspor RAR memanfaatkan binary command-line resmi RAR.
- **Linux**: Pasang paket `rar` (misal: `apt-get install rar` atau unduh binary resmi dari RARLAB ke `/usr/bin/rar`).
- **Windows**: Pasang WinRAR dan tentukan path `Rar.exe` pada `.env`:
  ```ini
  RAR_BINARY_PATH="C:\Program Files\WinRAR\Rar.exe"
  ```
- Jika binary belum terpasang, sistem tetap berfungsi penuh dengan format ZIP, dan antarmuka akan memberikan panduan readiness transparan kepada pengguna.

### 3. Mengatur Kuota & Role Pengguna
Pada menu **👥 Pengguna & Kuota**, administrator dapat:
- Menyesuaikan jatah token harian (`daily_token_quota`) dan bulanan (`monthly_token_quota`).
- Mengubah batas konkurensi request per pengguna.
- Menangguhkan (suspend) akun yang menyalahgunakan platform.
- *Aturan Keamanan*: Sistem secara ketat menolak penonaktifan atau penurunan role administrator aktif terakhir.

---

## 5. Prosedur Backup & Restore

### Backup Database PostgreSQL:
```bash
docker compose exec postgres pg_dump -U xzy_user -d xzy_db > backup_xzy_$(date +%Y%m%d).sql
```

### Restore Database PostgreSQL:
```bash
cat backup_xzy_20261015.sql | docker compose exec -T postgres psql -U xzy_user -d xzy_db
```

### Backup Workspace & Artefak Proyek:
Seluruh berkas workspace proyek disimpan dalam direktori `storage/app/workspaces/` dan arsip ekspor pada `storage/app/exports/`. Cadangkan folder `storage/app/` atau bucket S3/MinIO `xzy-artifacts`.

---

## 6. Pengujian & Bukti Kualitas (Test Suite)

Platform XZY dilengkapi pengujian otomatis backend lengkap mencakup fungsionalitas inti, batas otorisasi, keamanan sandbox, akuntansi token, dan ekspor.

Jalankan test suite menggunakan:
```bash
php artisan test
```

### Ringkasan Status Pengujian:
- **Auth & Keamanan Akun**: Registrasi, login, session rotation, TOTP 2FA setup, challenge OTP, recovery codes, pencabutan sesi di perangkat lain (`AuthTest`) — **LULUS (Passed)**.
- **Otorisasi Multi-Tenant**: Isolasi antar pengguna; pencegahan akses proyek, berkas, obrolan, ekspor, dan koneksi GitHub milik pengguna lain (`AuthorizationAndIsolationTest`) — **LULUS (Passed)**.
- **Keamanan Sandbox**: Pencegahan directory traversal (`../`), null-byte injection, absolute paths, validasi CSP pada preview, pemblokiran SSRF localhost/metadata cloud, pemindaian secret sebelum push (`SecuritySandboxTest`) — **LULUS (Passed)**.
- **TokenomixAI & Ledger**: Rumus `raw_tokens * multiplier`, snaphot multiplier, idempotency key pencegah double billing, batas kuota harian/bulanan, masking API key pada JSON response (`TokenomixAgentAndLedgerTest`) — **LULUS (Passed)**.
- **Ekspor Proyek**: Pembuatan arsip ZIP asli yang dapat diekstrak utuh dengan checksum SHA-256 dan pengecualian secret; pemeriksaan readiness RAR (`ExportsTest`) — **LULUS (Passed)**.
- **Siklus Vibecoding**: Inisialisasi template Laravel + Vue 3, manipulasi berkas workspace, checkpoint atomik, deteksi sintaks PHP/JSON di sandbox, dan pemulihan checkpoint (`VibecodingCycleTest`) — **LULUS (Passed)**.
- **Pemeriksaan Kesehatan**: Endpoint `/health` dan `/health/ready` memverifikasi database, cache, storage, status provider, dan binary RAR (`HealthCheckTest`) — **LULUS (Passed)**.
- **Type Checking Frontend**: `npx tsc --noEmit` — **0 Error (Passed)**.
- **Bundle Produksi Frontend**: `npm run build` — **Sukses (Built in 1.8s)**.
- **Audit Kerentanan Dependensi**: `composer audit` & `npm audit` — **0 Vulnerabilities Found**.
- **CI/CD Automation Pipeline**: Template GitHub Actions CI lengkap disediakan di berkas [`deploy/ci.yml`](file:///deploy/ci.yml). Salin berkas ini ke `.github/workflows/ci.yml` pada repositori Anda setelah memastikan token GitHub memiliki scope `workflow`.

---

## 7. Status Kesiapan Layanan Eksternal

| Layanan / Fitur | Status Kesiapan | Catatan Operasional |
| :--- | :--- | :--- |
| **Workspace & Editor** | **Lulus & Siap** | Monaco Editor lazy loaded, tree explorer, live diff viewer |
| **Sandbox Runner** | **Lulus & Siap** | Isolasi path, pemeriksaan sintaksis PHP & JSON in-memory |
| **Pratinjau (Preview)** | **Lulus & Siap** | Iframe sandbox dengan CSP strict tanpa cookie platform |
| **Ekspor ZIP** | **Lulus & Siap** | Pustaka ZipArchive terverifikasi, SHA-256 checksum aktif |
| **Ekspor RAR** | **Siap Dikonfigurasi** | Health check aktif; memerlukan `RAR_BINARY_PATH` resmi |
| **Gateway TokenomixAI**| **Lulus & Siap** | Klien HTTP, streaming SSE, retry 502 backoff, masking key |
| **Integrasi GitHub** | **Lulus & Siap** | Git Data API atomik, deteksi non-fast-forward, secret scanner |
| **MFA TOTP 2FA** | **Lulus & Siap** | Standar RFC 6238, QR code inline SVG, 8 recovery codes |
