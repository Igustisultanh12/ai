# 🚀 Quick Start - GitHub Actions APK Build

## Status
✅ **Pushed to GitHub** - Ready untuk setup GitHub Actions!

---

## 📋 Langkah Setup (3 Menit)

### 1️⃣ Upload Workflow File ke GitHub
```
1. Buka: https://github.com/Igustisultanh12/ai
2. Klik: Add file > Create new file
3. Nama file: .github/workflows/android-build.yml
4. Copy paste isi dari file: android-build.yml (di root repo)
5. Klik: Commit changes
```

### 2️⃣ Generate Keystore (Opsional - untuk signed release)
```bash
# Di terminal (Windows PowerShell atau Git Bash):
keytool -genkey -v -keystore ai-release.jks -keyalg RSA -keysize 2048 -validity 10000 -alias ai-key

# Simpan password yang Anda buat!
```

### 3️⃣ Setup GitHub Secrets (Opsional - untuk signed release)
```
1. Buka: https://github.com/Igustisultanh12/ai/settings/secrets/actions
2. Klik: New repository secret

Tambahkan 4 secrets:
- KEYSTORE_BASE64: (hasil base64 dari ai-release.jks)
- KEYSTORE_PASSWORD: (password keystore)
- KEY_ALIAS: ai-key
- KEY_PASSWORD: (password key)
```

**Cara encode keystore ke base64:**
```powershell
# Windows PowerShell:
[Convert]::ToBase64String([IO.File]::ReadAllBytes("ai-release.jks"))

# Linux/Mac:
base64 -i ai-release.jks
```

### 4️⃣ Trigger Build
```bash
# Push commit apapun:
git commit --allow-empty -m "test: trigger CI build"
git push

# ATAU create tag untuk release:
git tag -a v2.0.0 -m "Release v2.0.0"
git push origin v2.0.0
```

---

## 🎯 Hasil

### Debug Build (Push biasa)
- ✅ Build APK debug otomatis
- 📦 Upload artifact: `ai-debug-apk`
- 📥 Download dari Actions tab

### Release Build (Push tag `v*`)
- ✅ Build APK release (signed jika ada keystore)
- 📦 Upload artifact: `ai-release-apk`
- 🎉 Create GitHub Release otomatis
- 📱 APK tersedia di Releases

---

## 📖 Dokumentasi Lengkap

1. **CHECKLIST.md** - Checklist lengkap setup
2. **SETUP_GITHUB_ACTIONS.md** - Panduan detail GitHub Actions
3. **KEYSTORE_SETUP.md** - Panduan generate keystore
4. **build-apk.ps1** - Script build lokal (Windows)
5. **build-apk.sh** - Script build lokal (Linux/Mac)

---

## 🔍 Monitor Build

**Lihat progress build:**
```
https://github.com/Igustisultanh12/ai/actions
```

**Jika build gagal:**
- Klik run yang failed
- Baca error message
- Fix dan push lagi

**Common issues:**
- ✅ Missing workflow file → Upload android-build.yml
- ✅ Gradlew permission denied → Workflow otomatis fix
- ✅ Build failed → Check stacktrace di workflow log

---

## 🎉 Quick Test

**Test sekarang tanpa keystore (debug build):**
1. Upload workflow file (langkah 1)
2. Push commit kosong: `git commit --allow-empty -m "test" && git push`
3. Lihat di: https://github.com/Igustisultanh12/ai/actions
4. Download APK dari Artifacts setelah selesai

**Nanti untuk production:**
1. Generate keystore (langkah 2)
2. Setup secrets (langkah 3)
3. Create tag: `git tag v2.0.0 && git push origin v2.0.0`
4. APK signed tersedia di Releases!

---

## ⚡ Next Steps

Setelah workflow berjalan:
1. ✅ Setiap push ke `main` → Build debug APK
2. ✅ Setiap push tag `v*` → Build release APK + GitHub Release
3. ✅ Pull request → Build dan test otomatis
4. ✅ Manual trigger dari Actions tab (workflow_dispatch)

---

**Need help?** Baca CHECKLIST.md untuk panduan lengkap!