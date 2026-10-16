package com.xzy.ai.data

import org.json.JSONArray
import org.json.JSONObject

fun JSONObject.str(key: String, def: String = ""): String = if (has(key) && !isNull(key)) optString(key, def) else def
fun JSONObject.strOrNull(key: String): String? = if (has(key) && !isNull(key)) optString(key) else null
fun JSONObject.long(key: String): Long = if (has(key) && !isNull(key)) optDouble(key, 0.0).toLong() else 0L
fun JSONObject.dbl(key: String): Double = if (has(key) && !isNull(key)) optDouble(key, 0.0) else 0.0
inline fun <T> JSONArray?.mapObjects(f: (JSONObject) -> T): List<T> {
    if (this == null) return emptyList()
    val out = ArrayList<T>(length())
    for (i in 0 until length()) optJSONObject(i)?.let { out.add(f(it)) }
    return out
}

data class User(
    val id: String,
    val name: String,
    val email: String,
    val role: String,
    val mfaEnabled: Boolean,
) {
    val isAdmin get() = role == "admin"
    val initials: String
        get() = name.split(" ").filter { it.isNotBlank() }.take(2).joinToString("") { it.take(1).uppercase() }.ifBlank { "?" }

    companion object {
        fun from(o: JSONObject) = User(
            id = o.str("id"),
            name = o.str("name"),
            email = o.str("email"),
            role = o.str("role", "user"),
            mfaEnabled = o.strOrNull("two_factor_confirmed_at") != null,
        )
    }
}

data class Usage(
    val dailyQuota: Long,
    val monthlyQuota: Long,
    val remainingDaily: Long,
    val remainingMonthly: Long,
    val dailyPercent: Double,
    val monthlyPercent: Double,
    val todayWeighted: Long,
    val totalWeighted: Long,
) {
    companion object {
        fun from(o: JSONObject) = Usage(
            dailyQuota = o.long("daily_quota"),
            monthlyQuota = o.long("monthly_quota"),
            remainingDaily = o.long("remaining_daily"),
            remainingMonthly = o.long("remaining_monthly"),
            dailyPercent = o.dbl("daily_percent"),
            monthlyPercent = o.dbl("monthly_percent"),
            todayWeighted = o.long("today_weighted"),
            totalWeighted = o.long("total_weighted"),
        )
    }
}

data class Project(
    val id: String,
    val name: String,
    val description: String,
    val status: String,
    val filesCount: Int,
    val githubRepo: String?,
    val updatedAt: String,
) {
    companion object {
        fun from(o: JSONObject) = Project(
            id = o.str("id"),
            name = o.str("name"),
            description = o.str("description"),
            status = o.str("status", "active"),
            filesCount = o.optInt("files_count", 0),
            githubRepo = o.strOrNull("github_repo"),
            updatedAt = o.str("updated_at"),
        )
    }
}

data class ProjectFile(val path: String, val size: Long, val binary: Boolean) {
    companion object {
        fun from(o: JSONObject) = ProjectFile(o.str("path"), o.long("size_bytes"), o.optBoolean("is_binary", false))
    }
}

data class Conversation(
    val id: String,
    val title: String,
    val model: String,
    val projectId: String?,
    val updatedAt: String,
    val messagesCount: Int,
) {
    companion object {
        fun from(o: JSONObject) = Conversation(
            id = o.str("id"),
            title = o.str("title", "Percakapan Baru"),
            model = o.str("model"),
            projectId = o.strOrNull("project_id"),
            updatedAt = o.str("updated_at"),
            messagesCount = o.optInt("messages_count", 0),
        )
    }
}

data class Message(
    val id: String,
    val role: String,
    val content: String,
    val status: String,
) {
    companion object {
        fun from(o: JSONObject) = Message(o.str("id"), o.str("role"), o.str("content"), o.str("status", "completed"))
    }
}

data class ChatModel(val id: String, val available: Boolean, val multiplier: Double?, val description: String?) {
    val label: String
        get() = buildString {
            append(id)
            if (multiplier != null && multiplier > 0) append(" ×").append(if (multiplier % 1.0 == 0.0) multiplier.toLong().toString() else "%.2f".format(multiplier).trimEnd('0'))
            if (!available) append(" (stok habis)")
        }

    companion object {
        fun from(o: JSONObject) = ChatModel(
            id = o.str("id"),
            available = o.optBoolean("available", true),
            multiplier = if (o.has("multiplier") && !o.isNull("multiplier")) o.optDouble("multiplier") else null,
            description = o.strOrNull("description"),
        )
    }
}

data class LedgerEntry(
    val id: String,
    val model: String,
    val promptTokens: Long,
    val completionTokens: Long,
    val weighted: Long,
    val multiplier: Double,
    val status: String,
    val source: String,
    val createdAt: String,
    val projectName: String?,
) {
    companion object {
        fun from(o: JSONObject) = LedgerEntry(
            id = o.str("id"),
            model = o.str("model"),
            promptTokens = o.long("prompt_tokens"),
            completionTokens = o.long("completion_tokens"),
            weighted = o.long("weighted_tokens"),
            multiplier = o.dbl("multiplier_snapshot"),
            status = o.str("status"),
            source = o.str("data_source"),
            createdAt = o.str("created_at"),
            projectName = o.optJSONObject("project")?.str("name"),
        )
    }
}

data class GitHubStatus(val connected: Boolean, val username: String?, val scopes: List<String>) {
    companion object {
        fun from(o: JSONObject): GitHubStatus {
            val arr = o.optJSONArray("scopes")
            val scopes = if (arr == null) emptyList() else (0 until arr.length()).map { arr.optString(it) }
            return GitHubStatus(o.optBoolean("is_connected", false), o.strOrNull("username"), scopes)
        }
    }
}

data class Page<T>(val items: List<T>, val page: Int, val lastPage: Int, val total: Int)

fun <T> JSONObject.page(f: (JSONObject) -> T) = Page(
    items = optJSONArray("data").mapObjects(f),
    page = optInt("current_page", 1),
    lastPage = optInt("last_page", 1),
    total = optInt("total", 0),
)
