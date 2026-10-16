package com.xzy.ai.ui.chat

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.xzy.ai.data.model.Message
import com.xzy.ai.data.repository.ChatRepository
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch
import java.text.SimpleDateFormat
import java.util.*
import javax.inject.Inject

@HiltViewModel
class ChatViewModel @Inject constructor(
    private val chatRepository: ChatRepository
) : ViewModel() {

    private val _messages = MutableStateFlow<List<Message>>(emptyList())
    val messages: StateFlow<List<Message>> = _messages.asStateFlow()

    private val _isLoading = MutableStateFlow(false)
    val isLoading: StateFlow<Boolean> = _isLoading.asStateFlow()

    private val _error = MutableStateFlow<String?>(null)
    val error: StateFlow<String?> = _error.asStateFlow()

    private var currentConversationId: String? = null
    private val timeFormat = SimpleDateFormat("HH:mm", Locale.getDefault())

    fun loadConversation(conversationId: String) {
        currentConversationId = conversationId
        viewModelScope.launch {
            _isLoading.value = true
            try {
                chatRepository.getConversationMessages(conversationId).collect { messages ->
                    _messages.value = messages.map { it.toUIMessage() }
                }
            } catch (e: Exception) {
                _error.value = "Failed to load conversation: ${e.message}"
            } finally {
                _isLoading.value = false
            }
        }
    }

    fun createNewConversation() {
        viewModelScope.launch {
            try {
                val conversation = chatRepository.createConversation("New Chat")
                currentConversationId = conversation.id
            } catch (e: Exception) {
                _error.value = "Failed to create conversation: ${e.message}"
            }
        }
    }

    fun sendMessage(content: String) {
        val conversationId = currentConversationId ?: run {
            _error.value = "No active conversation"
            return
        }

        viewModelScope.launch {
            // Add user message immediately
            val userMessage = Message(
                id = UUID.randomUUID().toString(),
                content = content,
                isUser = true,
                timestamp = System.currentTimeMillis(),
                formattedTime = timeFormat.format(Date())
            )
            _messages.value = _messages.value + userMessage

            // Add placeholder AI message with streaming indicator
            val aiMessageId = UUID.randomUUID().toString()
            val aiMessage = Message(
                id = aiMessageId,
                content = "",
                isUser = false,
                timestamp = System.currentTimeMillis(),
                formattedTime = timeFormat.format(Date()),
                isStreaming = true
            )
            _messages.value = _messages.value + aiMessage

            _isLoading.value = true
            try {
                // Stream AI response
                chatRepository.sendMessage(conversationId, content).collect { chunk ->
                    val currentMessages = _messages.value.toMutableList()
                    val index = currentMessages.indexOfFirst { it.id == aiMessageId }
                    if (index != -1) {
                        val updatedMessage = currentMessages[index].copy(
                            content = currentMessages[index].content + chunk,
                            isStreaming = false
                        )
                        currentMessages[index] = updatedMessage
                        _messages.value = currentMessages
                    }
                }
            } catch (e: Exception) {
                _error.value = "Failed to send message: ${e.message}"
                // Remove failed AI message
                _messages.value = _messages.value.filter { it.id != aiMessageId }
            } finally {
                _isLoading.value = false
            }
        }
    }

    private fun com.xzy.ai.data.model.MessageEntity.toUIMessage(): Message {
        return Message(
            id = this.id,
            content = this.content,
            isUser = this.role == "user",
            timestamp = this.createdAt,
            formattedTime = timeFormat.format(Date(this.createdAt)),
            model = this.model
        )
    }
}