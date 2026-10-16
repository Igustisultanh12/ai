package com.xzy.ai.data

import org.json.JSONObject
import java.net.URLEncoder

/** All calls to the XZY backend used by the app. */
object Repo {
    private fun enc(s: String) = URLEncoder.encode(s, "UTF-8")

    // ---- auth
    suspend fun me(): User = User.from(Api.get("/api/auth/me").getJSONObject("user"))

    /** Returns the user, or null when a 2FA code is required next. */
    suspend fun login(email: String, password: String): User? {
        val r = Api.post("/api/auth/login", JSONObject().put("email", email).put("password", password))
        return if (r.optBoolean("mfa_required", false)) null else User.from(r.getJSONObject("user"))
    }

    suspend fun verifyMfa(code: String): User = User.from(Api.post("/api/auth/mfa/verify", JSONObject().put("code", code)).getJSONObject("user"))

    suspend fun register(name: String, email: String, password: String, confirm: String): User =
        User.from(
            Api.post(
                "/api/auth/register",
                JSONObject().put("name", name).put("email", email).put("password", password).put("password_confirmation", confirm),
            ).getJSONObject("user"),
        )

    suspend fun logout() {
        try { Api.post("/api/auth/logout") } catch (_: Exception) {}
        Api.clearSession()
    }

    suspend fun updateProfile(name: String, email: String): User =
        User.from(Api.put("/api/auth/profile", JSONObject().put("name", name).put("email", email)).getJSONObject("user"))

    suspend fun updatePassword(current: String, password: String, confirm: String): String =
        Api.put("/api/auth/password", JSONObject().put("current_password", current).put("password", password).put("password_confirmation", confirm)).str("message", "Password diubah.")

    // ---- usage
    suspend fun usage(): Usage = Usage.from(Api.get("/api/usage/summary"))
    suspend fun ledger(page: Int = 1): Page<LedgerEntry> = Api.get("/api/usage/ledger?page=$page").page { LedgerEntry.from(it) }

    // ---- projects
    suspend fun projects(page: Int = 1, search: String = ""): Page<Project> =
        Api.get("/api/projects?page=$page&search=${enc(search)}").page { Project.from(it) }

    suspend fun project(id: String): Project = Project.from(Api.get("/api/projects/$id").getJSONObject("project"))

    suspend fun createProject(name: String, description: String): Project =
        Project.from(Api.post("/api/projects", JSONObject().put("name", name).put("description", description).put("template", "laravel-vue3")).getJSONObject("project"))

    suspend fun deleteProject(id: String) { Api.delete("/api/projects/$id") }

    suspend fun projectFiles(id: String): List<ProjectFile> = Api.get("/api/projects/$id/files").optJSONArray("files").mapObjects { ProjectFile.from(it) }

    suspend fun projectFile(id: String, path: String): String = Api.get("/api/projects/$id/file?path=${enc(path)}").str("content")

    suspend fun saveProjectFile(id: String, path: String, content: String) {
        Api.post("/api/projects/$id/file", JSONObject().put("path", path).put("content", content))
    }

    // ---- conversations
    suspend fun conversations(page: Int = 1, search: String = ""): Page<Conversation> =
        Api.get("/api/conversations?page=$page&search=${enc(search)}").page { Conversation.from(it) }

    suspend fun conversation(id: String): Pair<Conversation, List<Message>> {
        val c = Api.get("/api/conversations/$id").getJSONObject("conversation")
        return Conversation.from(c) to c.optJSONArray("messages").mapObjects { Message.from(it) }
    }

    suspend fun createConversation(model: String?, projectId: String? = null, title: String? = null): Conversation {
        val body = JSONObject()
        if (!model.isNullOrBlank()) body.put("model", model)
        if (!projectId.isNullOrBlank()) body.put("project_id", projectId)
        if (!title.isNullOrBlank()) body.put("title", title)
        return Conversation.from(Api.post("/api/conversations", body).getJSONObject("conversation"))
    }

    suspend fun updateConversation(id: String, model: String? = null, projectId: String? = null, clearProject: Boolean = false, title: String? = null) {
        val body = JSONObject()
        if (model != null) body.put("model", model)
        if (title != null) body.put("title", title)
        if (clearProject) body.put("project_id", JSONObject.NULL) else if (projectId != null) body.put("project_id", projectId)
        Api.put("/api/conversations/$id", body)
    }

    suspend fun deleteConversation(id: String) { Api.delete("/api/conversations/$id") }
    suspend fun stop(id: String) { Api.post("/api/conversations/$id/stop") }

    // ---- models
    suspend fun models(): Pair<List<ChatModel>, String> {
        val r = Api.get("/api/models")
        val list = r.optJSONArray("models").mapObjects { ChatModel.from(it) }
        val def = r.str("default").ifBlank { list.firstOrNull { it.available }?.id.orEmpty() }
        return list to def
    }

    // ---- github
    suspend fun githubStatus(): GitHubStatus = GitHubStatus.from(Api.get("/api/github/status"))
    suspend fun githubConnect(token: String): String = Api.post("/api/github/connect", JSONObject().put("token", token)).str("username")
    suspend fun githubDisconnect() { Api.delete("/api/github/disconnect") }
}
