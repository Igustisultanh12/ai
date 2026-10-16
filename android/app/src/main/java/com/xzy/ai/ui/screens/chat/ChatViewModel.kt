package com.xzy.ai.ui.screens.chat

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.xzy.ai.data.remote.dto.ConversationDto
import com.xzy.ai.data.remote.dto.MessageDto
import com.xzy.ai.data.repository.ConversationRepository
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch

import dagger.hilt.android.lifecycle.HiltViewModel
import javax.inject.Inject

data class ChatUiState(
    val conversations: List<ConversationDto> = emptyList(),
    val selectedConversation: ConversationDto? = null,
    val messages: List<MessageDto> = emptyList(),
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
    
    init {
        loadConversations()
    }
    
    fun loadConversations() {
        viewModelScope.launch {
            _uiState.value = _uiState.value.copy(isLoading = true)
            
            conversationRepository.getConversations()
                .onSuccess { conversations ->
                    _uiState.value = _uiState.value.copy(
                        conversations = conversations,
                        isLoading = false,
                        error = null
                    )
                    
                    // Auto select first conversation
                    if (conversations.isNotEmpty() && _uiState.value.selectedConversation == null) {
                        selectConversation(conversations.first().id)
                    }
                }
                .onFailure { error ->
                    _uiState.value = _uiState.value.copy(
                        isLoading = false,
                        error = error.message ?: "Gagal memuat percakapan"
                    )
                }
        }
    }
    
    fun selectConversation(conversationId: String) {
        val conversation = _uiState.value.conversations.find { it.id == conversationId }
        _uiState.value = _uiState.value.copy(
            selectedConversation = conversation,
            messages = conversation?.messages ?: emptyList(),
            showConversationDrawer = false
        )
    }
    
    fun createNewConversation() {
        viewModelScope.launch {
            _uiState.value = _uiState.value.copy(isLoading = true)
            
            conversationRepository.createConversation("", null)
                .onSuccess { conversation ->
                    val updatedConversations = listOf(conversation) + _uiState.value.conversations
                    _uiState.value = _uiState.value.copy(
                        conversations = updatedConversations,
                        selectedConversation = conversation,
                        messages = emptyList(),
                        isLoading = false,
                        showConversationDrawer = false
                    )
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
            
            conversationRepository.sendMessage(conversationId, message)
                .onSuccess { updatedConversation ->
                    // Update conversation in list
                    val updatedConversations = _uiState.value.conversations.map {
                        if (it.id == conversationId) updatedConversation else it
                    }
                    
                    _uiState.value = _uiState.value.copy(
                        conversations = updatedConversations,
                        selectedConversation = updatedConversation,
                        messages = updatedConversation.messages,
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
                    val newSelectedConversation = if (_uiState.value.selectedConversation?.id == conversationId) {
                        updatedConversations.firstOrNull()
                    } else {
                        _uiState.value.selectedConversation
                    }
                    
                    _uiState.value = _uiState.value.copy(
                        conversations = updatedConversations,
                        selectedConversation = newSelectedConversation,
                        messages = newSelectedConversation?.messages ?: emptyList()
                    )
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
