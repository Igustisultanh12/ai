# Cara Setup GitHub Actions untuk Build APK

GitHub Actions sudah dikonfigurasi tapi tidak bisa dipush karena keterbatasan OAuth scope. Berikut cara manual setup:

## Langkah 1: Upload Workflow File ke GitHub

1. Buka repository GitHub Anda: https://github.com/Igustisultanh12/ai
2. Navigate ke folder `.github/workflows/`
3. Edit file `android-build.yml` atau buat baru
4. Copy paste isi dari file `android-build.yml` lokal Anda ke GitHub editor
5. Commit perubahan

Atau gunakan GitHub web interface:
- Buka https://github.com/Igustisultanh12/ai/new/main?filename=.github/workflows/android-build.yml
- Paste konten dari file lokal
- Commit

## Langkah 2: Setup GitHub Secrets (WAJIB untuk Release Build)

Buka Settings → Secrets and variables → Actions, tambahkan:

### Required Secrets:
```
KEYSTORE_BASE64: [isi dengan keystore yang diencode base64]
KEYSTORE_PASSWORD: [password keystore Anda]
KEY_ALIAS: [alias key Anda]
KEY_PASSWORD: [password key Anda]
```

### Cara Generate Keystore:
```bash
keytool -genkey -v -keystore xzy-release.keystore -alias xzy -keyalg RSA -keysize 2048 -validity 10000
```

### Cara Encode Keystore ke Base64:
```powershell
# Windows PowerShell
[Convert]::ToBase64String([IO.File]::ReadAllBytes("xzy-release.keystore")) | Out-File -Encoding ASCII keystore-base64.txt
```

## Langkah 3: Push Perubahan Lainnya

Setelah workflow file ada di GitHub (via web interface), push perubahan lainnya:

```bash
cd ai
git add android/app/build.gradle.kts android/app/proguard-rules.pro BUILD_INSTRUCTIONS.md build-apk.ps1
git commit -m "build: Configure Android build for GitHub Actions"
git push origin ci/github-actions-apk-build
```

## Langkah 4: Trigger Build

### Build Debug APK:
- Setiap push ke branch apapun akan build debug APK
- APK bisa didownload dari Actions → Workflow run → Artifacts

### Build Release APK:
```bash
git tag v1.0.0
git push origin v1.0.0
```

Release APK akan otomatis dipublish di GitHub Releases.

## Monitoring Build

1. Buka https://github.com/Igustisultanh12/ai/actions
2. Lihat workflow "Android CI/CD"
3. Klik pada run terbaru untuk lihat log detail
4. Jika error, baca log dan perbaiki

## Troubleshooting

### Build Gagal - Missing Secrets
Pastikan semua secrets sudah ditambahkan di repository settings.

### Build Gagal - Gradle Error
Check log di GitHub Actions, biasanya ada dependency issue atau syntax error.

### APK Not Uploaded
Check apakah build sukses dan artifact tersedia di workflow run page.

---

## File yang Sudah Diubah:

1. `.github/workflows/android-build.yml` - GitHub Actions workflow
2. `android/app/build.gradle.kts` - Build configuration dengan signing
3. `android/app/proguard-rules.pro` - ProGuard rules lengkap
4. `build-apk.ps1` - Script untuk build lokal
5. `BUILD_INSTRUCTIONS.md` - Dokumentasi ini

## Status Saat Ini:

✅ Workflow dikonfigurasi
✅ Build script siap
✅ ProGuard rules lengkap
⏳ Perlu upload workflow ke GitHub manual
⏳ Perlu setup secrets untuk release build
⏳ Perlu testing di GitHub Actions
