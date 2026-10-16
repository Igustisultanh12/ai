package com.xzy.ai.data

import android.content.Context
import android.content.SharedPreferences
import com.xzy.ai.BuildConfig
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import okhttp3.Cookie
import okhttp3.CookieJar
import okhttp3.HttpUrl
import okhttp3.HttpUrl.Companion.toHttpUrl
import okhttp3.MediaType.Companion.toMediaType
import okhttp3.OkHttpClient
import okhttp3.Request
import okhttp3.RequestBody.Companion.toRequestBody
import okhttp3.Response
import org.json.JSONArray
import org.json.JSONObject
import java.io.IOException
import java.net.URLDecoder
import java.util.concurrent.TimeUnit

class ApiException(message: String, val code: Int = 0) : Exception(message)

/**
 * Persistent cookie jar: the XZY backend uses Laravel session cookies (same as the web app),
 * so the login survives app restarts.
 */
class PersistentCookieJar(private val prefs: SharedPreferences) : CookieJar {
    private val cookies = mutableMapOf<String, Cookie>()

    init {
        val saved = prefs.getString("cookies", null)
        if (saved != null) {
            try {
                val arr = JSONArray(saved)
                val base = BuildConfig.BASE_URL.toHttpUrl()
                for (i in 0 until arr.length()) {
                    val c = Cookie.parse(base, arr.getString(i)) ?: continue
                    if (c.expiresAt > System.currentTimeMillis()) cookies[key(c)] = c
                }
            } catch (_: Exception) {
            }
        }
    }

    private fun key(c: Cookie) = "${c.domain}|${c.path}|${c.name}"

    @Synchronized
    override fun saveFromResponse(url: HttpUrl, cookies: List<Cookie>) {
        for (c in cookies) {
            if (c.expiresAt < System.currentTimeMillis()) this.cookies.remove(key(c)) else this.cookies[key(c)] = c
        }
        persist()
    }

    @Synchronized
    override fun loadForRequest(url: HttpUrl): List<Cookie> {
        val now = System.currentTimeMillis()
        return cookies.values.filter { it.expiresAt > now && it.matches(url) }
    }

    @Synchronized
    fun value(name: String): String? = cookies.values.firstOrNull { it.name == name && it.expiresAt > System.currentTimeMillis() }?.value

    @Synchronized
    fun clear() {
        cookies.clear()
        persist()
    }

    private fun persist() {
        val arr = JSONArray()
        // Session cookies have no expiry: keep them for 30 days so the login survives restarts
        for (c in cookies.values) {
            val fixed = if (!c.persistent) Cookie.Builder().name(c.name).value(c.value).domain(c.domain).path(c.path)
                .expiresAt(System.currentTimeMillis() + 30L * 24 * 3600 * 1000).apply { if (c.secure) secure(); if (c.httpOnly) httpOnly() }.build() else c
            arr.put(fixed.toString())
        }
        prefs.edit().putString("cookies", arr.toString()).apply()
    }
}

/**
 * Thin JSON client for https://xyzku.my.id/api. Sends the Laravel CSRF token
 * (X-XSRF-TOKEN from the XSRF-TOKEN cookie) on state-changing requests, like the web app.
 */
object Api {
    val baseUrl: String = BuildConfig.BASE_URL.trimEnd('/')
    private val JSON = "application/json; charset=utf-8".toMediaType()
    lateinit var prefs: SharedPreferences
        private set
    lateinit var cookieJar: PersistentCookieJar
        private set
    lateinit var http: OkHttpClient
        private set
    lateinit var streamHttp: OkHttpClient
        private set

    fun init(context: Context) {
        prefs = context.getSharedPreferences("xzy", Context.MODE_PRIVATE)
        cookieJar = PersistentCookieJar(prefs)
        http = OkHttpClient.Builder()
            .cookieJar(cookieJar)
            .connectTimeout(20, TimeUnit.SECONDS)
            .readTimeout(60, TimeUnit.SECONDS)
            .writeTimeout(60, TimeUnit.SECONDS)
            .build()
        // Agent answers can take many minutes; the server sends heartbeats
        streamHttp = http.newBuilder().readTimeout(10, TimeUnit.MINUTES).callTimeout(0, TimeUnit.SECONDS).build()
    }

    private fun xsrf(): String? = cookieJar.value("XSRF-TOKEN")?.let { URLDecoder.decode(it, "UTF-8") }

    fun request(method: String, path: String, body: JSONObject? = null): Request {
        val b = Request.Builder()
            .url(if (path.startsWith("http")) path else "$baseUrl$path")
            .header("Accept", "application/json")
            .header("X-Requested-With", "XMLHttpRequest")
            .header("Referer", "$baseUrl/")
        xsrf()?.let { b.header("X-XSRF-TOKEN", it) }
        val rb = body?.toString()?.toRequestBody(JSON)
        when (method) {
            "GET" -> b.get()
            "DELETE" -> if (rb != null) b.delete(rb) else b.delete()
            else -> b.method(method, rb ?: "{}".toRequestBody(JSON))
        }
        return b.build()
    }

    /** Make sure we have a CSRF cookie before the first POST (any API GET sets it). */
    suspend fun ensureCsrf() = withContext(Dispatchers.IO) {
        if (xsrf() == null) {
            try {
                http.newCall(request("GET", "/api/auth/me")).execute().close()
            } catch (_: IOException) {
            }
        }
    }

    suspend fun call(method: String, path: String, body: JSONObject? = null): JSONObject = withContext(Dispatchers.IO) {
        if (method != "GET") ensureCsrf()
        var response = execute(request(method, path, body))
        if (response.code == 419) {
            // CSRF token expired: refresh it once and retry
            response.close()
            try { http.newCall(request("GET", "/api/auth/me")).execute().close() } catch (_: IOException) {}
            response = execute(request(method, path, body))
        }
        response.use { parse(it) }
    }

    private fun execute(req: Request): Response = try {
        http.newCall(req).execute()
    } catch (e: IOException) {
        throw ApiException("Tidak bisa terhubung ke server. Periksa koneksi internet. (${e.message ?: "network"})")
    }

    fun parse(res: Response): JSONObject {
        val text = res.body?.string().orEmpty()
        val json = try {
            if (text.trim().startsWith("{")) JSONObject(text) else if (text.trim().startsWith("[")) JSONObject().put("data", JSONArray(text)) else JSONObject()
        } catch (_: Exception) {
            JSONObject()
        }
        if (!res.isSuccessful) {
            var msg = json.optString("message").ifBlank { "Permintaan gagal (HTTP ${res.code})" }
            val errors = json.optJSONObject("errors")
            if (errors != null) {
                val first = errors.keys().asSequence().firstOrNull()?.let { errors.optJSONArray(it)?.optString(0) }
                if (!first.isNullOrBlank()) msg = first
            }
            if (res.code == 401) msg = "Sesi berakhir. Silakan masuk lagi."
            if (res.code == 419) msg = "Sesi keamanan kedaluwarsa. Coba lagi."
            throw ApiException(msg, res.code)
        }
        return json
    }

    suspend fun get(path: String) = call("GET", path)
    suspend fun post(path: String, body: JSONObject = JSONObject()) = call("POST", path, body)
    suspend fun put(path: String, body: JSONObject) = call("PUT", path, body)
    suspend fun delete(path: String) = call("DELETE", path)

    fun clearSession() = cookieJar.clear()
}
