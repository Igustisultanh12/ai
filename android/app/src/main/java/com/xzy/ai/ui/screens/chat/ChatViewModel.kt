package com.xzy.ai.ui.screens.chat

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.xzy.ai.data.model.Conversation
import com.xzy.ai.data.model.Message
import com.xzy.ai.data.repository.ConversationRepository
import com.xzy.ai.utils.onFailure
import com.xzy.ai.utils.onSuccess
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.Job
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch
import javax.inject.Inject

data class ChatUiState(
    val conversations: List<Conversation> = emptyList(),
    val selectedConversation: Conversation? = null,
    val messages: List<Message> = emptyList(),
    val currentMessage: String = "",
    val isLoading: Boolean = false,
    val isSending: Boolean = false,
    val error: String? = null,
    val showConversationDrawer: Boolean = false
)

@HiltViewModel
class ChatViewModel @Inject constructor(
    private val conversationRepository: ConversationRepository
) : ViewModel() {
    private val _uiState = MutableStateFlow(ChatUiState())
    val uiState: StateFlow<ChatUiState> = _uiState.asStateFlow()

    private var messagesJob: Job? = null

    init {
        loadConversations()
    }

    fun loadConversations() {
        viewModelScope.launch {
            _uiState.value = _uiState.value.copy(isLoading = true)

            // Sync with backend API in background
            try {
                conversationRepository.syncConversations()
            } catch (_: Exception) {
            }

            // Observe conversations from local Room database
            conversationRepository.getConversations().collect { conversations ->
                val currentSelected = _uiState.value.selectedConversation
                val updatedSelected = when {
                    currentSelected != null -> conversations.find { it.id == currentSelected.id } ?: conversations.firstOrNull()
                    conversations.isNotEmpty() -> conversations.first()
                    else -> null
                }

                _uiState.value = _uiState.value.copy(
                    conversations = conversations,
                    selectedConversation = updatedSelected,
                    isLoading = false,
                    error = null
                )

                if (updatedSelected != null && (_uiState.value.messages.isEmpty() || currentSelected?.id != updatedSelected.id)) {
                    observeMessages(updatedSelected.id)
                }
            }
        }
    }

    fun selectConversation(conversationId: String) {
        val conversation = _uiState.value.conversations.find { it.id == conversationId }
        _uiState.value = _uiState.value.copy(
            selectedConversation = conversation,
            showConversationDrawer = false
        )
        observeMessages(conversationId)
    }

    private fun observeMessages(conversationId: String) {
        messagesJob?.cancel()
        messagesJob = viewModelScope.launch {
            conversationRepository.getMessages(conversationId).collect { messages ->
                _uiState.value = _uiState.value.copy(messages = messages)
            }
        }
    }

    fun createNewConversation() {
        viewModelScope.launch {
            _uiState.value = _uiState.value.copy(isLoading = true)

            conversationRepository.createConversation("Percakapan Baru", "gpt-4")
                .onSuccess { newConversationId ->
                    _uiState.value = _uiState.value.copy(
                        isLoading = false,
                        showConversationDrawer = false
                    )
                    selectConversation(newConversationId)
                }
                .onFailure { error ->
                    _uiState.value = _uiState.value.copy(
                        isLoading = false,
                        error = error.message ?: "Gagal membuat percakapan"
                    )
                }
        }
    }

    fun onMessageChange(message: String) {
        _uiState.value = _uiState.value.copy(currentMessage = message)
    }

    fun sendMessage() {
        val conversationId = _uiState.value.selectedConversation?.id ?: return
        val message = _uiState.value.currentMessage.trim()

        if (message.isBlank()) return

        viewModelScope.launch {
            _uiState.value = _uiState.value.copy(
                isSending = true,
                currentMessage = ""
            )

            conversationRepository.sendMessage(conversationId, message, "gpt-4")
                .onSuccess {
                    _uiState.value = _uiState.value.copy(
                        isSending = false,
                        error = null
                    )
                }
                .onFailure { error ->
                    _uiState.value = _uiState.value.copy(
                        isSending = false,
                        error = error.message ?: "Gagal mengirim pesan"
                    )
                }
        }
    }

    fun deleteConversation(conversationId: String) {
        viewModelScope.launch {
            conversationRepository.deleteConversation(conversationId)
                .onSuccess {
                    val updatedConversations = _uiState.value.conversations.filter { it.id != conversationId }
                    val newSelected = if (_uiState.value.selectedConversation?.id == conversationId) {
                        updatedConversations.firstOrNull()
                    } else {
                        _uiState.value.selectedConversation
                    }

                    _uiState.value = _uiState.value.copy(
                        conversations = updatedConversations,
                        selectedConversation = newSelected,
                        messages = emptyList()
                    )

                    if (newSelected != null) {
                        observeMessages(newSelected.id)
                    }
                }
                .onFailure { error ->
                    _uiState.value = _uiState.value.copy(
                        error = error.message ?: "Gagal menghapus percakapan"
                    )
                }
        }
    }

    fun toggleConversationDrawer() {
        _uiState.value = _uiState.value.copy(
            showConversationDrawer = !_uiState.value.showConversationDrawer
        )
    }

    fun clearError() {
        _uiState.value = _uiState.value.copy(error = null)
    }
}
