package com.xzy.ai.data

import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.currentCoroutineContext
import kotlinx.coroutines.isActive
import kotlinx.coroutines.job
import kotlinx.coroutines.withContext
import org.json.JSONObject
import java.io.IOException

/** Events from POST /api/conversations/{id}/stream (Server-Sent Events). */
sealed class StreamEvent {
    data class Text(val delta: String) : StreamEvent()
    data class Tool(val id: String, val status: String, val label: String, val link: String?, val error: String?) : StreamEvent()
    data class Retry(val content: String?, val message: String) : StreamEvent()
    data class Progress(val tool: String?, val chars: Int) : StreamEvent()
    object Started : StreamEvent()
    object StepLimit : StreamEvent()
    data class Stopped(val message: String) : StreamEvent()
    data class Error(val message: String) : StreamEvent()
    object Done : StreamEvent()
}

class StreamDropped(message: String) : IOException(message)

object ChatStream {
    /**
     * Sends a message and reads the agent's answer as it streams. Blocking on IO; call from a coroutine.
     * Throws StreamDropped when the connection drops (the server keeps working in that case).
     */
    suspend fun send(conversationId: String, content: String, model: String?, onEvent: (StreamEvent) -> Unit) = withContext(Dispatchers.IO) {
        Api.ensureCsrf()
        val body = JSONObject().put("content", content)
        if (!model.isNullOrBlank()) body.put("model", model)
        val req = Api.request("POST", "/api/conversations/$conversationId/stream", body).newBuilder()
            .header("Accept", "text/event-stream, application/json")
            .build()
        val call = Api.streamHttp.newCall(req)
        // Leaving the chat cancels the coroutine; abort the blocking read too
        val cancelHook = currentCoroutineContext().job.invokeOnCompletion { if (it != null) call.cancel() }
        val res = try { call.execute() } catch (e: IOException) { throw StreamDropped(e.message ?: "koneksi terputus") }
        if (!res.isSuccessful) {
            res.use { Api.parse(it) } // throws ApiException with the server message
            return@withContext
        }
        val source = res.body?.source() ?: throw StreamDropped("respons kosong")
        var finished = false
        try {
            while (currentCoroutineContext().isActive) {
                val line = source.readUtf8Line() ?: break
                val t = line.trim()
                if (!t.startsWith("data:")) continue
                val data = t.substring(5).trim()
                if (data == "[DONE]") { finished = true; onEvent(StreamEvent.Done); break }
                val o = try { JSONObject(data) } catch (_: Exception) { continue }
                val ev = o.str("event")
                when {
                    o.has("content") && ev.isEmpty() -> onEvent(StreamEvent.Text(o.str("content")))
                    ev == "tool" -> onEvent(StreamEvent.Tool(o.str("id"), o.str("status"), o.str("label"), o.strOrNull("link"), o.strOrNull("error")))
                    ev == "retry" -> onEvent(StreamEvent.Retry(o.strOrNull("content"), o.str("message")))
                    ev == "progress" -> onEvent(StreamEvent.Progress(o.strOrNull("tool"), o.optInt("chars", 0)))
                    ev == "start" -> onEvent(StreamEvent.Started)
                    ev == "step_limit" -> onEvent(StreamEvent.StepLimit)
                    ev == "stopped" -> onEvent(StreamEvent.Stopped(o.str("message", "Dihentikan.")))
                    o.has("error") -> onEvent(StreamEvent.Error(o.str("error")))
                }
            }
        } catch (e: IOException) {
            if (!finished) throw StreamDropped(e.message ?: "koneksi terputus")
        } finally {
            cancelHook.dispose()
            res.close()
        }
        if (!finished && currentCoroutineContext().isActive) throw StreamDropped("koneksi terputus sebelum selesai")
    }
}
