package com.xzy.ai.ui.screens

import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateListOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.setValue
import androidx.compose.runtime.snapshotFlow
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.xzy.ai.data.Agent
import com.xzy.ai.data.ChatModel
import com.xzy.ai.data.Conversation
import com.xzy.ai.data.Message
import com.xzy.ai.data.Project
import com.xzy.ai.data.Repo
import com.xzy.ai.data.Session
import kotlinx.coroutines.flow.drop
import kotlinx.coroutines.launch

class ChatViewModel(val id: String) : ViewModel() {
    val run = Agent.run(id)
    var conversation by mutableStateOf<Conversation?>(null)
    val messages = mutableStateListOf<Message>()
    var loading by mutableStateOf(true)
    var error by mutableStateOf<String?>(null)
    var models by mutableStateOf<List<ChatModel>>(emptyList())
    var projects by mutableStateOf<List<Project>>(emptyList())
    var projectName by mutableStateOf<String?>(null)
    var notice by mutableStateOf<String?>(null)

    init {
        load()
        viewModelScope.launch { snapshotFlow { run.revision }.drop(1).collect { reload() } }
    }

    fun load() {
        viewModelScope.launch {
            loading = messages.isEmpty()
            error = null
            reload(first = true)
            loading = false
        }
    }

    private suspend fun reload(first: Boolean = false) {
        try {
            val (c, m) = Repo.conversation(id)
            conversation = c
            messages.clear()
            messages.addAll(m)
            if (!run.streaming) {
                run.clearLive()
                val last = m.lastOrNull()
                if (first && last != null && last.role == "assistant" && last.status == "streaming") Agent.watch(id, c.title)
            }
            run.title = c.title
            if (first) loadProjectName(c.projectId)
        } catch (e: Exception) {
            error = Session.fail(e)
        }
    }

    private suspend fun loadProjectName(pid: String?) {
        projectName = if (pid == null) null else try { Repo.project(pid).name } catch (_: Exception) { null }
    }

    fun loadPickers() {
        viewModelScope.launch {
            try { models = Repo.models().first } catch (e: Exception) { notice = Session.fail(e) }
            try { projects = Repo.projects(1).items } catch (_: Exception) {}
        }
    }

    fun send(text: String) {
        val c = conversation
        Agent.send(id, text.trim(), c?.model, c?.title ?: "")
    }

    fun stop() = Agent.stop(id)

    fun setModel(m: ChatModel) {
        if (!m.available) { notice = "Model ${m.id} sedang habis stok"; return }
        viewModelScope.launch {
            try {
                Repo.updateConversation(id, model = m.id)
                conversation = conversation?.copy(model = m.id)
                notice = "Model diganti ke ${m.id}"
            } catch (e: Exception) { notice = Session.fail(e) }
        }
    }

    fun setProject(p: Project?) {
        viewModelScope.launch {
            try {
                if (p == null) Repo.updateConversation(id, clearProject = true) else Repo.updateConversation(id, projectId = p.id)
                conversation = conversation?.copy(projectId = p?.id)
                projectName = p?.name
                notice = if (p == null) "Proyek dilepas" else "Terhubung ke proyek ${p.name}"
            } catch (e: Exception) { notice = Session.fail(e) }
        }
    }

    fun rename(title: String) {
        viewModelScope.launch {
            try {
                Repo.updateConversation(id, title = title)
                conversation = conversation?.copy(title = title)
                run.title = title
            } catch (e: Exception) { notice = Session.fail(e) }
        }
    }

    fun delete(onDone: () -> Unit) {
        viewModelScope.launch {
            try {
                if (run.streaming) Agent.stop(id)
                Repo.deleteConversation(id)
                onDone()
            } catch (e: Exception) { notice = Session.fail(e) }
        }
    }
}
