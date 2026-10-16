# XZY AI untuk Android

Aplikasi Android (Jetpack Compose) untuk platform XZY di https://xyzku.my.id. Memakai akun dan API yang sama dengan versi web.

## Fitur

- Masuk, daftar, dan verifikasi 2 langkah (2FA).
- Beranda: sapaan, kuota token harian/bulanan dengan animasi, chat dan proyek terbaru, status agen yang sedang bekerja.
- Chat dengan agen secara streaming: aktivitas tool (buat repo, push, pantau Actions) tampil langsung dengan tautan, lanjut otomatis saat batas langkah tercapai, tombol berhenti, ganti model, dan hubungkan proyek.
- Agen tetap bekerja saat aplikasi ditutup (layanan latar depan), lalu mengirim notifikasi saat selesai atau gagal.
- Proyek: daftar, buat, hapus, lihat file, dan edit file.
- Pemakaian token: cincin kuota dan riwayat pemakaian.
- Akun: edit profil, ganti password, hubungkan GitHub (token dengan izin repo + workflow), tema gelap/terang.
- Notifikasi: riwayat di dalam aplikasi beserta lencana belum dibaca.
- Pembaruan di dalam aplikasi: mengecek rilis `android-v*` di GitHub, mengunduh APK dengan progres, lalu membuka pemasang.

## Build

CI (`.github/workflows/android-build.yml`) membangun APK release setiap ada perubahan di `android/` pada `main` dan menerbitkannya sebagai rilis `android-v3.0.<nomor run>`.

APK ditandatangani dengan kunci yang disimpan di cache Actions, jadi versi baru bisa terpasang di atas versi lama. Untuk kunci permanen, tambahkan secret `KEYSTORE_FILE`, `KEYSTORE_PASSWORD`, `KEY_ALIAS`, dan `KEY_PASSWORD`.

Build lokal:

```sh
cd android
./gradlew assembleRelease
```
