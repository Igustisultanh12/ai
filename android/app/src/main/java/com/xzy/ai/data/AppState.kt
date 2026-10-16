package com.xzy.ai.data

import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.setValue

/** Signed-in user and app-wide flags shared by all screens. */
object Session {
    var user by mutableStateOf<User?>(null)

    /** Set when the server answers 401; the root screen sends the user back to login. */
    var expired by mutableStateOf(false)

    /** Turns an exception into a message for the UI and flags expired sessions. */
    fun fail(e: Throwable): String {
        if (e is ApiException && e.code == 401) expired = true
        return e.message?.takeIf { it.isNotBlank() } ?: "Terjadi kesalahan. Coba lagi."
    }
}

object Prefs {
    private var darkState by mutableStateOf(true)
    private var loaded = false

    var dark: Boolean
        get() {
            if (!loaded) {
                loaded = true
                darkState = Api.prefs.getBoolean("dark", true)
            }
            return darkState
        }
        set(v) {
            darkState = v
            loaded = true
            Api.prefs.edit().putBoolean("dark", v).apply()
        }

    var askedNotifPermission: Boolean
        get() = Api.prefs.getBoolean("asked_notif", false)
        set(v) { Api.prefs.edit().putBoolean("asked_notif", v).apply() }
}

/** Where a notification tap should lead (set by MainActivity, consumed by the root screen). */
object DeepLink {
    var pending by mutableStateOf<String?>(null)
}
