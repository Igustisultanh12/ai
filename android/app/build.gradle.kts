plugins {
    alias(libs.plugins.android.application)
    alias(libs.plugins.jetbrains.kotlin.android)
    alias(libs.plugins.compose.compiler)
    alias(libs.plugins.hilt)
    alias(libs.plugins.ksp)
}

android {
    namespace = "com.xzy.ai"
    compileSdk = 35

    defaultConfig {
        applicationId = "com.xzy.ai"
        minSdk = 26
        targetSdk = 35
        versionCode = 3
        versionName = "2.1.0"

        testInstrumentationRunner = "androidx.test.runner.AndroidJUnitRunner"
        vectorDrawables {
            useSupportLibrary = true
        }

        buildConfigField("String", "BASE_URL", "\"https://xyzku.my.id/\"")
    }

    signingConfigs {
        create("release") {
            // Load from environment variables (for CI/CD)
            storeFile = System.getenv("KEYSTORE_FILE")?.let { file(it) }
                ?: project.findProperty("android.injected.signing.store.file")?.let { file(it) }
            storePassword = System.getenv("KEYSTORE_PASSWORD")
                ?: project.findProperty("android.injected.signing.store.password") as String?
            keyAlias = System.getenv("KEY_ALIAS")
                ?: project.findProperty("android.injected.signing.key.alias") as String?
            keyPassword = System.getenv("KEY_PASSWORD")
                ?: project.findProperty("android.injected.signing.key.password") as String?
        }
    }

    buildTypes {
        release {
            isMinifyEnabled = true
            isShrinkResources = true
            proguardFiles(
                getDefaultProguardFile("proguard-android-optimize.txt"),
                "proguard-rules.pro"
            )
            // Use release signing if available, otherwise use debug
            signingConfig = if (signingConfigs.getByName("release").storeFile != null) {
                signingConfigs.getByName("release")
            } else {
                signingConfigs.getByName("debug")
            }
        }
    }

    compileOptions {
        sourceCompatibility = JavaVersion.VERSION_17
        targetCompatibility = JavaVersion.VERSION_17
    }

    kotlinOptions {
        jvmTarget = "17"
    }

    buildFeatures {
        compose = true
        buildConfig = true
    }

    packaging {
        resources {
            excludes += "/META-INF/{AL2.0,LGPL2.1}"
        }
    }
}

dependencies {
    implementation(libs.androidx.core.ktx)
    implementation(libs.androidx.lifecycle.runtime.ktx)
    implementation(libs.androidx.lifecycle.viewmodel.compose)
    implementation(libs.androidx.lifecycle.runtime.compose)
    implementation(libs.androidx.activity.compose)
    implementation(libs.androidx.navigation.compose)
    implementation(libs.androidx.splashscreen)

    // Compose
    implementation(platform(libs.compose.bom))
    implementation(libs.compose.ui)
    implementation(libs.compose.ui.graphics)
    implementation(libs.compose.ui.tooling.preview)
    implementation(libs.compose.material3)
    implementation(libs.compose.material.icons.extended)
    implementation(libs.compose.animation)
    debugImplementation(libs.compose.ui.tooling)

    // Hilt
    implementation(libs.hilt.android)
    ksp(libs.hilt.compiler)
    implementation(libs.hilt.navigation.compose)

    // Security & EncryptedSharedPreferences
    implementation("androidx.security:security-crypto:1.1.0-alpha06")
    compileOnly("com.google.errorprone:error_prone_annotations:2.28.0")

    // Network
    implementation(libs.retrofit)
    implementation(libs.retrofit.gson)
    implementation(libs.okhttp)
    implementation(libs.okhttp.logging)
    implementation("com.squareup.okhttp3:okhttp-urlconnection:4.12.0")

    // Image loading
    implementation(libs.coil.compose)

    // Room
    implementation(libs.room.runtime)
    implementation(libs.room.ktx)
    ksp(libs.room.compiler)

    // DataStore
    implementation(libs.datastore.preferences)

    // Lottie animations
    implementation(libs.lottie.compose)

    // Unit Testing
    testImplementation("junit:junit:4.13.2")
}
