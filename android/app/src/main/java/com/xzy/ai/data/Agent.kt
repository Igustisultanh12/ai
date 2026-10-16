package com.xzy.ai.data

import android.content.Context
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.mutableStateListOf
import androidx.compose.runtime.mutableStateMapOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.setValue
import com.xzy.ai.AgentService
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.Job
import kotlinx.coroutines.SupervisorJob
import kotlinx.coroutines.channels.Channel
import kotlinx.coroutines.coroutineScope
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch

data class ToolRun(val id: String, val status: String, val label: String, val link: String?, val error: String?)

/** Live state of one conversation's agent run. Lives outside the screen so it survives navigation. */
class AgentRun(val conversationId: String) {
    var streaming by mutableStateOf(false)
    var text by mutableStateOf("")
    val tools = mutableStateListOf<ToolRun>()
    var status by mutableStateOf<String?>(null)
    var error by mutableStateOf<String?>(null)
    var pendingUser by mutableStateOf<String?>(null)
    var startedAt by mutableStateOf(0L)

    /** Bumped whenever the server copy changed and the chat should reload its messages. */
    var revision by mutableIntStateOf(0)
    var continues by mutableIntStateOf(0)
    internal var stopRequested = false
    internal var job: Job? = null
    var title: String = ""

    fun clearLive() {
        text = ""
        tools.clear()
        status = null
        pendingUser = null
    }
}

object Agent {
    private const val MAX_AUTO_CONTINUE = 5
    private lateinit var app: Context
    private val scope = CoroutineScope(SupervisorJob() + Dispatchers.Main.immediate)
    private val runs = mutableStateMapOf<String, AgentRun>()

    /** Conversation currently on screen (no notification for it while the app is visible). */
    var visibleChat by mutableStateOf<String?>(null)
    var foreground by mutableStateOf(false)

    fun init(context: Context) { app = context.applicationContext }

    fun run(id: String): AgentRun = runs.getOrPut(id) { AgentRun(id) }

    fun activeCount(): Int = runs.values.count { it.streaming }

    fun activeRuns(): List<AgentRun> = runs.values.filter { it.streaming }

    fun send(id: String, text: String, model: String?, title: String) {
        val r = run(id)
        if (r.streaming) return
        r.title = title
        r.stopRequested = false
        r.error = null
        r.continues = 0
        r.clearLive()
        r.pendingUser = text
        r.streaming = true
        r.startedAt = System.currentTimeMillis()
        AgentService.start(app)
        r.job = scope.launch { loop(r, text, model) }
    }

    /** Follows a reply the server is still producing (e.g. after the app was closed mid-answer). */
    fun watch(id: String, title: String) {
        val r = run(id)
        if (r.streaming) return
        r.title = title
        r.error = null
        r.clearLive()
        r.streaming = true
        r.startedAt = System.currentTimeMillis()
        r.status = "Agen masih bekerja di server, menunggu hasilnya..."
        AgentService.start(app)
        r.job = scope.launch {
            val ok = poll(r)
            finish(r, if (ok) null else r.error)
        }
    }

    fun stop(id: String) {
        val r = run(id)
        if (!r.streaming) return
        r.stopRequested = true
        r.status = "Menghentikan agen..."
        scope.launch {
            try { Repo.stop(id) } catch (_: Exception) {}
            // The server ends the stream itself; if it does not within a few seconds, cut it here
            delay(8000)
            if (r.streaming && r.stopRequested) {
                r.job?.cancel()
                finish(r, null)
            }
        }
    }

    private suspend fun loop(r: AgentRun, first: String, model: String?) {
        var next = first
        var failure: String? = null
        while (true) {
            var stepLimit = false
            try {
                coroutineScope {
                val ch = Channel<StreamEvent>(Channel.UNLIMITED)
                launch(Dispatchers.IO) {
                    try {
                        ChatStream.send(r.conversationId, next, model) { ch.trySend(it) }
                        ch.close()
                    } catch (e: Throwable) {
                        ch.close(e)
                    }
                }
                for (ev in ch) {
                    when (ev) {
                        is StreamEvent.Text -> { r.text += ev.delta; if (r.status?.startsWith("Mencoba") == true) r.status = null }
                        is StreamEvent.Tool -> {
                            val t = ToolRun(ev.id, ev.status, ev.label, ev.link, ev.error)
                            val i = r.tools.indexOfFirst { it.id == ev.id }
                            if (i >= 0) r.tools[i] = t else r.tools.add(t)
                            r.status = null
                            if (ev.status == "running") Notifier.notifyRunning(activeCount(), ev.label)
                        }
                        is StreamEvent.Retry -> {
                            if (ev.content != null) r.text = ev.content
                            r.status = "Mencoba lagi: ${ev.message}"
                        }
                        is StreamEvent.Progress -> r.status = "Menulis ${ev.tool ?: "file"} (${formatChars(ev.chars)})"
                        StreamEvent.Started -> {}
                        StreamEvent.StepLimit -> stepLimit = true
                        is StreamEvent.Stopped -> r.status = ev.message
                        is StreamEvent.Error -> failure = ev.message
                        StreamEvent.Done -> {}
                    }
                }
                }
            } catch (e: StreamDropped) {
                r.status = "Koneksi terputus. Agen tetap bekerja di server, menunggu hasilnya..."
                if (!poll(r)) failure = r.error ?: "Tidak bisa memantau agen."
            } catch (e: kotlinx.coroutines.CancellationException) {
                throw e
            } catch (e: Exception) {
                failure = Session.fail(e)
            }
            if (stepLimit && failure == null && !r.stopRequested && r.continues < MAX_AUTO_CONTINUE) {
                r.continues++
                r.revision++ // show the finished part from the server
                r.clearLive()
                next = "lanjutkan"
                r.pendingUser = next
                r.status = "Melanjutkan otomatis (${r.continues}/$MAX_AUTO_CONTINUE)..."
                continue
            }
            break
        }
        finish(r, failure)
    }

    /** Polls the conversation until the last assistant reply is no longer streaming. */
    private suspend fun poll(r: AgentRun): Boolean {
        val deadline = System.currentTimeMillis() + 45 * 60 * 1000L
        var misses = 0
        while (System.currentTimeMillis() < deadline) {
            delay(3000)
            try {
                val (conv, messages) = Repo.conversation(r.conversationId)
                r.title = conv.title
                misses = 0
                val last = messages.lastOrNull { it.role == "assistant" }
                if (last == null) continue
                if (last.status == "streaming") {
                    r.text = last.content
                } else {
                    return true
                }
            } catch (e: Exception) {
                if (e is ApiException && e.code == 401) { r.error = Session.fail(e); return false }
                if (++misses > 20) { r.error = "Koneksi ke server hilang."; return false }
            }
        }
        r.error = "Agen terlalu lama, buka percakapan untuk melihat hasilnya."
        return false
    }

    private fun finish(r: AgentRun, failure: String?) {
        if (!r.streaming) return
        r.streaming = false
        r.error = failure
        if (failure == null) r.status = null
        r.revision++
        val visible = foreground && visibleChat == r.conversationId
        if (!visible && !r.stopRequested) {
            val snippet = r.text.replace(Regex("<[^>]+>"), "").replace(Regex("[*#`_>]"), "").trim().takeLast(160)
            if (failure != null) {
                Notifier.push("error", "Agen gagal: ${r.title.ifBlank { "Percakapan" }}", failure, "chat:${r.conversationId}")
            } else {
                val done = r.tools.count { it.status == "done" }
                val body = buildString {
                    if (done > 0) append("$done aksi berhasil. ")
                    append(snippet.ifBlank { "Jawaban sudah siap." })
                }
                Notifier.push("agent", "Agen selesai: ${r.title.ifBlank { "Percakapan" }}", body, "chat:${r.conversationId}")
            }
        }
        r.stopRequested = false
    }

    private fun formatChars(n: Int): String = if (n >= 1000) "%.1fk karakter".format(n / 1000f) else "$n karakter"
}
