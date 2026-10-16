package com.xzy.ai.data

import android.content.Context
import android.content.Intent
import android.net.Uri
import android.os.Build
import android.provider.Settings
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.setValue
import androidx.core.content.FileProvider
import com.xzy.ai.BuildConfig
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import okhttp3.OkHttpClient
import okhttp3.Request
import org.json.JSONArray
import java.io.File
import java.util.concurrent.TimeUnit

data class Release(
    val tag: String,
    val build: Int,
    val name: String,
    val notes: String,
    val apkUrl: String,
    val size: Long,
    val pageUrl: String,
    val publishedAt: String,
)

/** In-app updates from the APKs that CI publishes as GitHub releases (tag android-v3.0.<build>). */
object Updater {
    const val REPO = "Igustisultanh12/ai"
    val currentVersion: String = BuildConfig.VERSION_NAME
    val currentBuild: Int = BuildConfig.VERSION_NAME.substringAfterLast('.').toIntOrNull() ?: 0

    var available by mutableStateOf<Release?>(null)
    var checking by mutableStateOf(false)
    var progress by mutableStateOf(-1f) // -1 = not downloading
    var error by mutableStateOf<String?>(null)
    var downloaded by mutableStateOf<File?>(null)
    var dismissedBuild by mutableStateOf(0)
    var sheetOpen by mutableStateOf(false)

    private val http by lazy {
        OkHttpClient.Builder().connectTimeout(20, TimeUnit.SECONDS).readTimeout(60, TimeUnit.SECONDS).followRedirects(true).build()
    }

    /** Looks for a newer release. Returns it, or null when the app is up to date. */
    suspend fun check(notify: Boolean = true): Release? = withContext(Dispatchers.IO) {
        checking = true
        error = null
        try {
            val req = Request.Builder()
                .url("https://api.github.com/repos/$REPO/releases?per_page=20")
                .header("Accept", "application/vnd.github+json")
                .header("User-Agent", "XZY-AI-Android/$currentVersion")
                .build()
            val text = http.newCall(req).execute().use { res ->
                if (!res.isSuccessful) throw ApiException("Gagal mengecek pembaruan (HTTP ${res.code})")
                res.body?.string().orEmpty()
            }
            val arr = JSONArray(text)
            var best: Release? = null
            for (i in 0 until arr.length()) {
                val o = arr.getJSONObject(i)
                if (o.optBoolean("draft")) continue
                val tag = o.optString("tag_name")
                if (!tag.startsWith("android-v")) continue
                val build = tag.substringAfterLast('.').toIntOrNull() ?: continue
                val assets = o.optJSONArray("assets")
                var url: String? = null
                var size = 0L
                if (assets != null) for (j in 0 until assets.length()) {
                    val a = assets.getJSONObject(j)
                    if (a.optString("name").endsWith(".apk")) { url = a.optString("browser_download_url"); size = a.optLong("size"); break }
                }
                if (url == null) continue
                if (best == null || build > best.build) {
                    best = Release(tag, build, o.optString("name").ifBlank { tag }, o.optString("body"), url, size, o.optString("html_url"), o.optString("published_at"))
                }
            }
            val newer = best?.takeIf { it.build > currentBuild }
            available = newer
            Api.prefs.edit().putLong("update_checked", System.currentTimeMillis()).apply()
            if (newer != null && notify && Api.prefs.getInt("update_notified", 0) < newer.build) {
                Api.prefs.edit().putInt("update_notified", newer.build).apply()
                withContext(Dispatchers.Main) {
                    Notifier.push("update", "Versi baru tersedia: ${newer.tag.removePrefix("android-")}", "Ketuk untuk melihat catatan rilis dan memasang pembaruan.", "update")
                }
            }
            newer
        } catch (e: Exception) {
            error = e.message ?: "Gagal mengecek pembaruan"
            null
        } finally {
            checking = false
        }
    }

    /** Check at most every few hours (called on app start and resume). */
    suspend fun checkIfDue() {
        val last = Api.prefs.getLong("update_checked", 0)
        if (System.currentTimeMillis() - last > 3 * 60 * 60 * 1000L || available == null && last == 0L) check()
    }

    suspend fun download(context: Context, r: Release): File? = withContext(Dispatchers.IO) {
        error = null
        progress = 0f
        try {
            val dir = File(context.cacheDir, "updates").apply { mkdirs() }
            dir.listFiles()?.forEach { it.delete() }
            val file = File(dir, "xzy-ai-${r.build}.apk")
            val req = Request.Builder().url(r.apkUrl).header("User-Agent", "XZY-AI-Android/$currentVersion").build()
            http.newCall(req).execute().use { res ->
                if (!res.isSuccessful) throw ApiException("Unduhan gagal (HTTP ${res.code})")
                val body = res.body ?: throw ApiException("Unduhan kosong")
                val total = body.contentLength().takeIf { it > 0 } ?: r.size
                body.byteStream().use { input ->
                    file.outputStream().use { out ->
                        val buf = ByteArray(64 * 1024)
                        var read = 0L
                        while (true) {
                            val n = input.read(buf)
                            if (n < 0) break
                            out.write(buf, 0, n)
                            read += n
                            if (total > 0) progress = (read.toFloat() / total).coerceIn(0f, 1f)
                        }
                    }
                }
            }
            downloaded = file
            progress = 1f
            file
        } catch (e: Exception) {
            error = e.message ?: "Unduhan gagal"
            progress = -1f
            null
        }
    }

    /** Opens the system installer. Returns false when the user must first allow installs from this app. */
    fun install(context: Context, file: File): Boolean {
        if (Build.VERSION.SDK_INT >= 26 && !context.packageManager.canRequestPackageInstalls()) {
            context.startActivity(
                Intent(Settings.ACTION_MANAGE_UNKNOWN_APP_SOURCES, Uri.parse("package:${context.packageName}")).addFlags(Intent.FLAG_ACTIVITY_NEW_TASK),
            )
            return false
        }
        val uri = FileProvider.getUriForFile(context, "${context.packageName}.files", file)
        val i = Intent(Intent.ACTION_VIEW)
            .setDataAndType(uri, "application/vnd.android.package-archive")
            .addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION or Intent.FLAG_ACTIVITY_NEW_TASK)
        context.startActivity(i)
        return true
    }
}
