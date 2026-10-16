# XZY AI - Android App v2.0.0

<div align="center">
  <img src="https://img.shields.io/badge/Kotlin-1.9+-7F52FF?style=for-the-badge&logo=kotlin&logoColor=white" />
  <img src="https://img.shields.io/badge/Android-8.0+-3DDC84?style=for-the-badge&logo=android&logoColor=white" />
  <img src="https://img.shields.io/badge/Jetpack_Compose-1.5+-4285F4?style=for-the-badge&logo=jetpackcompose&logoColor=white" />
  <img src="https://img.shields.io/badge/Version-2.0.0-EC4899?style=for-the-badge" />
</div>

## 🎨 Overview

XZY AI adalah aplikasi Android native premium yang menghubungkan Anda dengan berbagai model AI seperti Claude, GPT, Gemini, dan lainnya. Versi 2.0 hadir dengan UI/UX yang sepenuhnya dirombak dengan animasi modern, interaktif, dan pengalaman pengguna kelas dunia.

## ✨ Fitur Utama v2.0

### 🚀 UI/UX Modern & Interaktif
- **Gradient Animation System**: Background animasi gradient yang smooth dan eye-catching
- **Particle Effects**: Animasi partikel floating pada splash screen
- **Wave Effects**: Efek gelombang dinamis yang mengikuti gerakan
- **Micro-interactions**: Setiap tap, swipe, dan gesture memberikan feedback visual
- **Haptic Feedback**: Getaran halus untuk konfirmasi aksi
- **Shimmer Loading**: Loading states yang indah dengan shimmer effect
- **Typing Indicator**: Animated dots saat AI sedang mengetik
- **Message Bubbles**: Bubble chat dengan animasi scale, bounce, dan shadow

### 🎭 Animasi Kelas Dunia
- **Entrance Animations**: Fade in, slide, scale dengan spring physics
- **Exit Animations**: Smooth transitions antar screen
- **List Animations**: Setiap item muncul dengan staggered animation
- **Button Ripples**: Material ripple effect dengan color matching
- **Floating Action Buttons**: FAB dengan micro-animations
- **Pull to Refresh**: Custom pull-to-refresh dengan circular progress
- **Swipe Gestures**: Swipe to delete dengan animasi smooth

### 🤖 AI Features
- **Multi AI Models**: Claude, GPT-4, Gemini Pro, LLama
- **Real-time Chat**: Percakapan lancar dengan streaming response
- **Conversation History**: Simpan dan kelola riwayat chat
- **Model Switching**: Ganti model AI dengan mudah
- **Voice Input** (Coming Soon)

### 🎨 Design System
- **Material Design 3**: Mengikuti guideline terbaru Google
- **Dynamic Color**: Warna adaptif berdasarkan wallpaper (Android 12+)
- **Dark Mode**: Tema gelap otomatis dengan AMOLED black
- **Custom Typography**: Font scaling untuk aksesibilitas
- **Colorful Gradients**: Cyan, Pink, Purple, Orange gradients

### 🔐 Security & Performance
- **Secure Authentication**: JWT token dengan encrypted storage
- **Offline Support**: Room database untuk caching
- **Optimized Performance**: Lazy loading, pagination
- **Network Caching**: OkHttp cache untuk efisiensi

## 🏗️ Arsitektur

```
app/
├── data/              # Data layer
│   ├── api/          # Retrofit API services
│   ├── local/        # Room database & PreferencesManager
│   ├── model/        # Data models
│   ├── remote/       # Remote API DTOs
│   └── repository/   # Repository pattern
├── di/               # Dependency Injection (Hilt)
├── ui/               # Presentation layer
│   ├── components/   # Reusable animated components
│   ├── screens/      
│   │   ├── auth/     # Login & Register dengan micro-interactions
│   │   ├── chat/     # Chat interface dengan bubble animations
│   │   └── splash/   # Splash dengan particle & wave effects
│   └── theme/        # Material Design 3 theme system
└── utils/            # Utilities & helpers
```

## 🛠️ Tech Stack

- **Language**: Kotlin 1.9+
- **UI Framework**: Jetpack Compose with Material Design 3
- **Architecture**: MVVM + Clean Architecture
- **Dependency Injection**: Hilt
- **Networking**: Retrofit + OkHttp
- **Local Storage**: Room Database + EncryptedSharedPreferences
- **Async**: Kotlin Coroutines + Flow
- **Image Loading**: Coil
- **Animation**: Compose Animation APIs

## 🎯 What's New in v2.0

### Visual Improvements
✅ **Completely redesigned UI** with modern aesthetics  
✅ **Gradient animation system** across all screens  
✅ **Particle effects** on splash screen  
✅ **Wave animations** with dynamic colors  
✅ **Micro-interactions** on every touchpoint  
✅ **Animated message bubbles** with scale & bounce  
✅ **Typing indicator** with smooth dots animation  
✅ **Shimmer loading states** for better UX  
✅ **Pull-to-refresh** dengan custom indicators  
✅ **Floating action buttons** dengan animations  

### Technical Improvements
✅ **Optimized animations** dengan hardware acceleration  
✅ **Reduced overdraw** untuk performance  
✅ **Better state management** dengan MVI pattern  
✅ **Enhanced error handling** dengan user-friendly messages  
✅ **Improved accessibility** dengan content descriptions  
✅ **Dark mode** dengan AMOLED optimizations  

## 📱 Screenshots & Demo

### Splash Screen
- Animated logo dengan scale & bounce
- Floating particles dengan random colors
- Wave effects di background
- Smooth transition ke Login/Home

### Authentication
- Gradient animated background
- Input fields dengan focus animations
- Error states dengan shake animation
- Social login buttons dengan ripple effect

### Chat Interface
- Animated message bubbles
- Typing indicator saat AI berpikir
- Pull-to-refresh untuk conversation history
- FAB untuk new chat dengan scale animation
- Model selector dengan bottom sheet

## 🚀 Installation

### Prerequisites
- Android Studio Hedgehog | 2023.1.1 atau lebih baru
- JDK 17
- Android SDK 26-35
- Kotlin 1.9+

### Build Instructions

1. Clone repository
```bash
git clone https://github.com/Igustisultanh12/ai.git
cd ai/android
```

2. Open di Android Studio
- File > Open > Pilih folder `android`
- Tunggu Gradle sync selesai

3. Update `BASE_URL` di `app/build.gradle.kts` jika perlu
```kotlin
buildConfigField("String", "BASE_URL", "\"https://your-api-url.com/\"")
```

4. Build & Run
```bash
./gradlew assembleDebug
# atau
./gradlew assembleRelease
```

5. Install ke device
```bash
./gradlew installDebug
```

## 📦 Dependencies

```gradle
// Core
implementation "androidx.core:core-ktx:1.12.0"
implementation "androidx.lifecycle:lifecycle-runtime-ktx:2.7.0"
implementation "androidx.lifecycle:lifecycle-viewmodel-compose:2.7.0"

// Compose
implementation platform("androidx.compose:compose-bom:2024.02.00")
implementation "androidx.compose.ui:ui"
implementation "androidx.compose.material3:material3"
implementation "androidx.compose.animation:animation"
implementation "androidx.compose.material:material-icons-extended"

// Navigation
implementation "androidx.navigation:navigation-compose:2.7.6"

// Hilt
implementation "com.google.dagger:hilt-android:2.50"
ksp "com.google.dagger:hilt-compiler:2.50"
implementation "androidx.hilt:hilt-navigation-compose:1.1.0"

// Networking
implementation "com.squareup.retrofit2:retrofit:2.9.0"
implementation "com.squareup.retrofit2:converter-gson:2.9.0"
implementation "com.squareup.okhttp3:okhttp:4.12.0"
implementation "com.squareup.okhttp3:logging-interceptor:4.12.0"

// Room
implementation "androidx.room:room-runtime:2.6.1"
ksp "androidx.room:room-compiler:2.6.1"
implementation "androidx.room:room-ktx:2.6.1"

// Security
implementation "androidx.security:security-crypto:1.1.0-alpha06"

// Image Loading
implementation "io.coil-kt:coil-compose:2.5.0"
```

## 🎨 Color Palette v2.0

### Light Theme
- **Primary**: #3B6DFF (Electric Blue)
- **Secondary**: #38BDF8 (Cyan)
- **Tertiary**: #6EE7B7 (Emerald)
- **Accent**: #E9A568 (Warm Orange)
- **Error**: #EF4444 (Red)

### Dark Theme  
- **Surface**: #05070C → #0A0D12 → #0F131C (Stepped blacks)
- **Gradient**: Cyan → Pink → Purple → Orange
- **Message Bubbles**: Electric Blue for user, Dark surface for AI

### Gradients
- **Cyan Gradient**: #06B6D4 → #38BDF8
- **Pink Gradient**: #EC4899 → #F472B6
- **Purple Gradient**: #7C3AED → #A78BFA
- **Orange Gradient**: #FB923C → #FDBA74

## 🔧 Configuration

### API Endpoint
Edit `app/build.gradle.kts`:
```kotlin
buildConfigField("String", "BASE_URL", "\"YOUR_API_URL\"")
```

### Theme Customization
Edit `ui/theme/Color.kt` untuk custom colors  
Edit `ui/theme/Theme.kt` untuk theme configuration

### Animation Duration
Semua animasi dapat disesuaikan di masing-masing komponen:
- Splash: 2500ms
- Login transition: 800ms
- Message appear: 300ms dengan spring physics
- Typing indicator: 1400ms infinite

## 📄 License

MIT License - Feel free to use this project for learning and commercial purposes.

## 👨‍💻 Developer

**Igustisultanh12**  
- GitHub: [@Igustisultanh12](https://github.com/Igustisultanh12)
- Repository: [ai](https://github.com/Igustisultanh12/ai)

## 🙏 Credits

- Material Design 3 by Google
- Jetpack Compose by Google
- Icons by Material Icons
- Animations inspired by modern mobile apps

## 📝 Changelog

### v2.0.0 (2026-10-16)
- ✨ Complete UI/UX redesign dengan modern aesthetics
- 🎨 Gradient animation system
- ⚡ Particle & wave effects
- 🎭 Micro-interactions di semua komponen
- 💬 Animated message bubbles
- ⌨️ Typing indicator
- 🔄 Pull-to-refresh
- 🎯 FAB dengan animations
- 🌈 Enhanced color system dengan 7-step surfaces
- 🚀 Performance optimizations

### v1.0.0 (2026-10-15)
- 🎉 Initial release
- 🤖 Multi AI model support
- 💬 Basic chat interface
- 🔐 Authentication
- 💾 Offline support

---

Made with ❤️ using Jetpack Compose
