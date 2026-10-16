package com.xzy.ai.utils

import android.content.Context
import android.content.SharedPreferences
import androidx.security.crypto.EncryptedSharedPreferences
import androidx.security.crypto.MasterKey
import javax.inject.Inject
import javax.inject.Singleton

@Singleton
class TokenManager @Inject constructor(private val context: Context) {

    private val masterKey = MasterKey.Builder(context)
        .setKeyScheme(MasterKey.KeyScheme.AES256_GCM)
        .build()

    private val sharedPreferences: SharedPreferences = EncryptedSharedPreferences.create(
        context,
        "xzy_secure_prefs",
        masterKey,
        EncryptedSharedPreferences.PrefKeyEncryptionScheme.AES256_SIV,
        EncryptedSharedPreferences.PrefValueEncryptionScheme.AES256_GCM
    )

    fun saveToken(token: String) {
        sharedPreferences.edit().putString(KEY_TOKEN, token).apply()
    }

    fun getToken(): String? {
        return sharedPreferences.getString(KEY_TOKEN, null)
    }

    fun clearToken() {
        sharedPreferences.edit().remove(KEY_TOKEN).apply()
    }

    companion object {
        private const val KEY_TOKEN = "auth_token"
    }
}

class UserPreferences @Inject constructor(private val context: Context) {

    private val prefs: SharedPreferences = context.getSharedPreferences(
        "xzy_user_prefs",
        Context.MODE_PRIVATE
    )

    fun setDefaultModel(model: String) {
        prefs.edit().putString(KEY_DEFAULT_MODEL, model).apply()
    }

    fun getDefaultModel(): String {
        return prefs.getString(KEY_DEFAULT_MODEL, "gpt-4") ?: "gpt-4"
    }

    fun setTheme(isDark: Boolean) {
        prefs.edit().putBoolean(KEY_DARK_THEME, isDark).apply()
    }

    fun isDarkTheme(): Boolean {
        return prefs.getBoolean(KEY_DARK_THEME, true)
    }

    fun setAnimationsEnabled(enabled: Boolean) {
        prefs.edit().putBoolean(KEY_ANIMATIONS, enabled).apply()
    }

    fun areAnimationsEnabled(): Boolean {
        return prefs.getBoolean(KEY_ANIMATIONS, true)
    }

    companion object {
        private const val KEY_DEFAULT_MODEL = "default_model"
        private const val KEY_DARK_THEME = "dark_theme"
        private const val KEY_ANIMATIONS = "animations_enabled"
    }
}