# XZY AI - Android App

<div align="center">
  <img src="https://img.shields.io/badge/Kotlin-1.9+-7F52FF?style=for-the-badge&logo=kotlin&logoColor=white" />
  <img src="https://img.shields.io/badge/Android-8.0+-3DDC84?style=for-the-badge&logo=android&logoColor=white" />
  <img src="https://img.shields.io/badge/Jetpack_Compose-1.5+-4285F4?style=for-the-badge&logo=jetpackcompose&logoColor=white" />
</div>

## 🎨 Overview

XZY AI adalah aplikasi Android native yang menghubungkan Anda dengan berbagai model AI seperti Claude, GPT, Gemini, dan lainnya. Aplikasi ini dibuat dengan Kotlin menggunakan arsitektur MVVM dan Material Design 3.

## ✨ Fitur Utama

- 🤖 **Multi AI Models**: Dukungan Claude, GPT-4, Gemini Pro, LLama, dan lainnya
- 💬 **Real-time Chat**: Percakapan lancar dengan animasi yang smooth
- 🎨 **Colorful UI**: Desain modern dengan gradient yang menarik dan animasi interaktif
- 🌙 **Dark Mode**: Tema gelap yang nyaman di mata
- 📱 **Material Design 3**: Mengikuti pedoman desain terbaru dari Google
- 💾 **Offline Support**: Simpan percakapan secara lokal
- 🔐 **Secure Authentication**: Login aman dengan JWT token
- 🎭 **Profile Customization**: Personalisasi profil Anda

## 🏗️ Arsitektur

```
app/
├── data/              # Data layer
│   ├── api/          # Retrofit API services
│   ├── local/        # Room database
│   ├── model/        # Data models
│   └── repository/   # Repository pattern
├── di/               # Dependency Injection (Hilt)
├── ui/               # Presentation layer
│   ├── auth/         # Login & Register
│   ├── main/         # Dashboard & Navigation
│   ├── chat/         # Chat interface
│   └── profile/      # User profile
└── utils/            # Utilities & helpers
```

## 🛠️ Tech Stack

- **Language**: Kotlin 1.9+
- **UI Framework**: Jetpack Compose
- **Architecture**: MVVM + Clean Architecture
- **Dependency Injection**: Hilt
- **Networking**: Retrofit + OkHttp
- **Local Storage**: Room Database + DataStore
- **Async**: Kotlin Coroutines + Flow
- **Image Loading**: Coil
- **Animation**: Lottie

## 📦 Dependencies

```gradle
// Core
implementation "androidx.core:core-ktx:1.12.0"
implementation "androidx.lifecycle:lifecycle-runtime-ktx:2.7.0"

// Compose
implementation "androidx.compose.ui:ui:1.6.0"
implementation "androidx.compose.material3:material3:1.2.0"
implementation "androidx.compose.animation:animation:1.6.0"

// Networking
implementation "com.squareup.retrofit2:retrofit:2.9.0"
implementation "com.squareup.okhttp3:okhttp:4.12.0"

// Dependency Injection
implementation "com.google.dagger:hilt-android:2.48"

// Database
implementation "androidx.room:room-runtime:2.6.1"

// Animation
implementation "com.airbnb.android:lottie-compose:6.3.0"
```

## 🚀 Build & Run

### Prerequisites
- Android Studio Hedgehog | 2023.1.1+
- JDK 17+
- Android SDK 34
- Gradle 8.2+

### Steps

1. **Clone repository**
   ```bash
   git clone https://github.com/Igustisultanh12/ai.git
   cd ai
   git checkout android-app
   ```

2. **Open di Android Studio**
   - Buka folder `android/` di Android Studio
   - Tunggu Gradle sync selesai

3. **Konfigurasi (Opsional)**
   - Base URL sudah di-set ke `https://xyzku.my.id/api/`
   - Jika perlu ubah, edit di `AppModule.kt`

4. **Build APK**
   ```bash
   ./gradlew assembleRelease
   ```
   APK akan tersimpan di: `app/build/outputs/apk/release/`

5. **Install & Run**
   ```bash
   ./gradlew installRelease
   ```

## 📱 Screenshots

_(Screenshots akan ditambahkan setelah build pertama)_

## 🎨 Design Highlights

### Color Palette
- **Primary**: Gradient dari Cyan (#00F5FF) ke Purple (#A855F7)
- **Secondary**: Pink (#EC4899) ke Orange (#F59E0B)
- **Background**: Dark surfaces dengan gradient subtle
- **Accent**: Vibrant colors untuk interaksi

### Animations
- ✅ Fade-in transitions pada screen baru
- ✅ Slide animations pada lists
- ✅ Bounce effect pada buttons
- ✅ Shimmer loading states
- ✅ Lottie animations untuk states kosong
- ✅ Ripple effects pada seluruh interaksi

## 🔐 API Integration

Aplikasi ini terhubung dengan backend Laravel di `https://xyzku.my.id/api/`

**Endpoints utama:**
- `POST /auth/login` - Login user
- `POST /auth/register` - Register user baru
- `GET /conversations` - Ambil daftar percakapan
- `POST /conversations` - Buat percakapan baru
- `POST /conversations/{id}/messages` - Kirim pesan
- `GET /user/profile` - Ambil profil user

## 📝 Development Notes

### Build Variants
- **debug**: Development build dengan logging enabled
- **release**: Production build dengan ProGuard dan optimasi

### Signing Config
Untuk build release, tambahkan keystore di `app/keystore.jks` dan konfigurasi:
```gradle
signingConfigs {
    release {
        storeFile file("keystore.jks")
        storePassword "your_password"
        keyAlias "your_alias"
        keyPassword "your_password"
    }
}
```

## 🐛 Troubleshooting

**Build Failed?**
- Pastikan JDK 17 terinstall
- Clean project: `./gradlew clean`
- Invalidate cache di Android Studio

**Network Error?**
- Cek koneksi internet
- Pastikan server `xyzku.my.id` berjalan
- Cek AndroidManifest.xml untuk permission INTERNET

**Room Database Error?**
- Uninstall dan reinstall aplikasi
- Atau increment database version di `AppDatabase.kt`

## 📄 License

MIT License - lihat file LICENSE untuk detail

## 👨‍💻 Developer

Dibuat dengan ❤️ oleh [@Igustisultanh12](https://github.com/Igustisultanh12)

## 🤝 Contributing

Contribusi sangat diterima! Silakan buat PR atau buka issue untuk saran dan bug report.

---

**Server**: [xyzku.my.id](https://xyzku.my.id)  
**Repository Web**: [ai](https://github.com/Igustisultanh12/ai)
