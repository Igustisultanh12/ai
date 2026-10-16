package com.xzy.ai.data

import android.Manifest
import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.content.Context
import android.content.Intent
import android.content.pm.PackageManager
import android.os.Build
import androidx.compose.runtime.mutableStateListOf
import androidx.core.app.NotificationCompat
import androidx.core.app.NotificationManagerCompat
import androidx.core.content.ContextCompat
import com.xzy.ai.MainActivity
import com.xzy.ai.R
import org.json.JSONArray
import org.json.JSONObject

data class Notif(
    val id: Long,
    val kind: String, // agent | error | update | info
    val title: String,
    val body: String,
    val target: String?, // chat:<id> | update | project:<id>
    val time: Long,
    val read: Boolean,
)

/** System notifications plus an in-app inbox (kept in SharedPreferences). */
object Notifier {
    const val CH_AGENT = "agent"
    const val CH_UPDATE = "update"
    const val CH_RUNNING = "running"
    const val RUNNING_ID = 4001
    const val UPDATE_ID = 4002

    private lateinit var app: Context
    val inbox = mutableStateListOf<Notif>()

    val unread: Int get() = inbox.count { !it.read }

    fun init(context: Context) {
        app = context.applicationContext
        if (Build.VERSION.SDK_INT >= 26) {
            val nm = app.getSystemService(NotificationManager::class.java)
            nm.createNotificationChannel(NotificationChannel(CH_AGENT, "Hasil agen", NotificationManager.IMPORTANCE_HIGH).apply { description = "Kabar saat agen selesai atau gagal mengerjakan chat" })
            nm.createNotificationChannel(NotificationChannel(CH_UPDATE, "Pembaruan aplikasi", NotificationManager.IMPORTANCE_DEFAULT).apply { description = "Versi baru XZY AI tersedia" })
            nm.createNotificationChannel(NotificationChannel(CH_RUNNING, "Agen sedang bekerja", NotificationManager.IMPORTANCE_LOW).apply { description = "Tetap aktif selama agen bekerja di latar belakang"; setShowBadge(false) })
        }
        load()
    }

    fun canPost(): Boolean =
        (Build.VERSION.SDK_INT < 33 || ContextCompat.checkSelfPermission(app, Manifest.permission.POST_NOTIFICATIONS) == PackageManager.PERMISSION_GRANTED) &&
            NotificationManagerCompat.from(app).areNotificationsEnabled()

    private fun contentIntent(target: String?, code: Int): PendingIntent {
        val i = Intent(app, MainActivity::class.java).apply {
            flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_SINGLE_TOP or Intent.FLAG_ACTIVITY_CLEAR_TOP
            if (target != null) putExtra("target", target)
        }
        return PendingIntent.getActivity(app, code, i, PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE)
    }

    /** Adds an entry to the inbox and, when [system] is true, shows a system notification. */
    fun push(kind: String, title: String, body: String, target: String? = null, system: Boolean = true) {
        val now = System.currentTimeMillis()
        val id = maxOf(now, (inbox.maxOfOrNull { it.id } ?: 0L) + 1)
        val n = Notif(id, kind, title, body, target, now, false)
        inbox.add(0, n)
        while (inbox.size > 100) inbox.removeAt(inbox.size - 1)
        save()
        if (!system || !canPost()) return
        val channel = if (kind == "update") CH_UPDATE else CH_AGENT
        val notification = NotificationCompat.Builder(app, channel)
            .setSmallIcon(R.drawable.ic_notif)
            .setColor(0xFF7C3AED.toInt())
            .setContentTitle(title)
            .setContentText(body)
            .setStyle(NotificationCompat.BigTextStyle().bigText(body))
            .setAutoCancel(true)
            .setPriority(if (kind == "update") NotificationCompat.PRIORITY_DEFAULT else NotificationCompat.PRIORITY_HIGH)
            .setContentIntent(contentIntent(target, (n.id % Int.MAX_VALUE).toInt()))
            .build()
        val notifId = if (kind == "update") UPDATE_ID else (n.id % 100000).toInt() + 5000
        try { NotificationManagerCompat.from(app).notify(notifId, notification) } catch (_: SecurityException) {}
    }

    fun running(count: Int, text: String?): android.app.Notification =
        NotificationCompat.Builder(app, CH_RUNNING)
            .setSmallIcon(R.drawable.ic_notif)
            .setColor(0xFF7C3AED.toInt())
            .setContentTitle(if (count > 1) "$count agen sedang bekerja" else "Agen sedang bekerja")
            .setContentText(text ?: "Hasilnya akan dikabari lewat notifikasi.")
            .setOngoing(true)
            .setOnlyAlertOnce(true)
            .setProgress(0, 0, true)
            .setContentIntent(contentIntent(null, 1))
            .build()

    fun notifyRunning(count: Int, text: String?) {
        if (!canPost()) return
        try { NotificationManagerCompat.from(app).notify(RUNNING_ID, running(count, text)) } catch (_: SecurityException) {}
    }

    fun markRead(id: Long) {
        val i = inbox.indexOfFirst { it.id == id }
        if (i >= 0 && !inbox[i].read) { inbox[i] = inbox[i].copy(read = true); save() }
    }

    fun markAllRead() {
        for (i in inbox.indices) if (!inbox[i].read) inbox[i] = inbox[i].copy(read = true)
        save()
    }

    fun remove(id: Long) { inbox.removeAll { it.id == id }; save() }

    fun clear() { inbox.clear(); save() }

    private fun save() {
        val arr = JSONArray()
        inbox.forEach {
            arr.put(JSONObject().put("id", it.id).put("kind", it.kind).put("title", it.title).put("body", it.body).put("target", it.target ?: JSONObject.NULL).put("time", it.time).put("read", it.read))
        }
        Api.prefs.edit().putString("inbox", arr.toString()).apply()
    }

    private fun load() {
        inbox.clear()
        try {
            val arr = JSONArray(Api.prefs.getString("inbox", "[]"))
            for (i in 0 until arr.length()) {
                val o = arr.getJSONObject(i)
                inbox.add(Notif(o.optLong("id"), o.optString("kind"), o.optString("title"), o.optString("body"), o.strOrNull("target"), o.optLong("time"), o.optBoolean("read")))
            }
        } catch (_: Exception) {
        }
    }
}
