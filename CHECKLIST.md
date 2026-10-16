# ✅ Checklist Setup GitHub Actions APK Build

## 🎯 Yang Sudah Saya Siapkan
- ✅ Workflow file: `android-build.yml`
- ✅ Build configuration dengan signing support
- ✅ ProGuard rules lengkap
- ✅ Build scripts (PowerShell & Bash)
- ✅ Dokumentasi lengkap

## 📋 Yang Perlu Anda Lakukan

### 1. Setup Keystore (Optional tapi Direkomendasikan)

Jika ingin APK signed (production ready), buka `KEYSTORE_SETUP.md` dan ikuti langkahnya:

```bash
# Generate keystore
keytool -genkey -v -keystore android/app/keystore.jks \
  -alias xzy-ai-release \
  -keyalg RSA -keysize 2048 \
  -validity 10000

# Encode ke base64
certutil -encode android/app/keystore.jks keystore_base64.txt  # Windows
# atau
base64 android/app/keystore.jks > keystore_base64.txt  # Linux/Mac
```

### 2. Upload Workflow ke GitHub

1. Buka repository di GitHub: https://github.com/Igustisultanh12/ai
2. Klik **Actions** tab
3. Klik **set up a workflow yourself** atau **New workflow**
4. Paste isi file `android-build.yml` yang sudah saya buat
5. Path file harus: `.github/workflows/android-build.yml`
6. Commit file tersebut

### 3. Setup GitHub Secrets (Jika Pakai Keystore)

Di repository GitHub:
1. Settings → Secrets and variables → Actions
2. Klik **New repository secret** untuk setiap secret:

**Wajib untuk Release Signed APK:**
- `KEYSTORE_BASE64`: Isi file `keystore_base64.txt` (tanpa header/footer)
- `KEYSTORE_PASSWORD`: Password keystore yang Anda masukkan saat generate
- `KEY_ALIAS`: Alias key (contoh: `xzy-ai-release`)
- `KEY_PASSWORD`: Password key (bisa sama dengan keystore password)

### 4. Trigger Build

**Cara 1: Push biasa (build debug APK)**
```bash
git push origin main
```

**Cara 2: Create release tag (build signed APK + GitHub Release)**
```bash
git tag -a v2.0.0 -m "Release version 2.0.0"
git push origin v2.0.0
```

**Cara 3: Manual trigger**
- Buka Actions tab di GitHub
- Klik workflow "Android CI/CD - Build & Release APK"
- Klik "Run workflow"

### 5. Monitor Build

1. Buka **Actions** tab di GitHub
2. Klik workflow yang berjalan
3. Lihat progress tiap step
4. Download APK dari **Artifacts** section (untuk debug/PR)
5. Atau download dari **Releases** (untuk tagged version)

## 🔧 Troubleshooting

### Error: "Gradle build failed"
- Cek log lengkap di Actions
- Pastikan semua dependencies bisa diakses
- Coba jalankan `./gradlew clean` dulu

### Error: "Signing failed"
- Pastikan semua 4 secrets sudah diset dengan benar
- Cek KEYSTORE_BASE64 tidak mengandung header BEGIN/END
- Pastikan KEY_ALIAS cocok dengan yang di keystore

### Error: "No workflow file found"
- Pastikan path PERSIS: `.github/workflows/android-build.yml`
- Bukan `android-build.yml` di root
- Bukan `.github/android-build.yml`
- Harus ada folder `workflows`

### Error: "Permission denied for GitHub release"
- Di Settings → Actions → General
- Scroll ke "Workflow permissions"
- Pilih "Read and write permissions"
- Save

## 📦 Hasil Akhir

Setelah setup selesai:

1. **Push biasa** → Debug APK di Artifacts
2. **Push tag v**** → Signed APK di GitHub Releases
3. **Pull Request** → APK preview di Artifacts

APK location:
- Debug: `android/app/build/outputs/apk/debug/app-debug.apk`
- Release: `android/app/build/outputs/apk/release/app-release.apk`

## 🚀 Quick Start

Kalau mau cepat tanpa signed APK:

1. Upload `android-build.yml` ke `.github/workflows/android-build.yml`
2. Push commit apapun
3. GitHub Actions akan auto build APK debug
4. Download dari Artifacts

Kalau mau production ready (signed):

1. Generate keystore (lihat KEYSTORE_SETUP.md)
2. Setup 4 secrets di GitHub
3. Create tag `v2.0.0` dan push
4. APK signed otomatis di Releases

## 📝 Notes

- Build time: sekitar 5-10 menit
- APK size: sekitar 8-15 MB (tergantung ProGuard optimization)
- Retention: Artifacts disimpan 90 hari
- Releases: permanent sampai dihapus manual

---

**Butuh bantuan?** Buka `SETUP_GITHUB_ACTIONS.md` atau `KEYSTORE_SETUP.md` untuk panduan lengkap.