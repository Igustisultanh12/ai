package com.xzy.ai.data.repository

import com.xzy.ai.data.remote.RetrofitClient
import com.xzy.ai.data.remote.dto.*
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext

class ConversationRepository {
    
    private val apiService = RetrofitClient.apiService
    
    suspend fun getConversations(): Result<List<ConversationDto>> = withContext(Dispatchers.IO) {
        try {
            val response = apiService.getConversations()
            if (response.isSuccessful && response.body() != null) {
                Result.success(response.body()!!.data)
            } else {
                Result.failure(Exception("Failed to fetch conversations"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }
    
    suspend fun getConversationDetail(id: String): Result<ConversationDetailDto> = withContext(Dispatchers.IO) {
        try {
            val response = apiService.getConversation(id)
            if (response.isSuccessful && response.body() != null) {
                Result.success(response.body()!!.data)
            } else {
                Result.failure(Exception("Failed to fetch conversation detail"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }
    
    suspend fun createConversation(
        model: String = "claude-sonnet-4-20250514",
        title: String? = null
    ): Result<ConversationDto> = withContext(Dispatchers.IO) {
        try {
            val request = CreateConversationRequest(model, title)
            val response = apiService.createConversation(request)
            if (response.isSuccessful && response.body() != null) {
                Result.success(response.body()!!)
            } else {
                Result.failure(Exception("Failed to create conversation"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }
    
    suspend fun sendMessage(
        conversationId: String,
        content: String
    ): Result<MessageDto> = withContext(Dispatchers.IO) {
        try {
            val request = SendMessageRequest(content)
            val response = apiService.sendMessage(conversationId, request)
            if (response.isSuccessful && response.body() != null) {
                Result.success(response.body()!!)
            } else {
                Result.failure(Exception("Failed to send message"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }
    
    suspend fun deleteConversation(id: String): Result<Unit> = withContext(Dispatchers.IO) {
        try {
            val response = apiService.deleteConversation(id)
            if (response.isSuccessful) {
                Result.success(Unit)
            } else {
                Result.failure(Exception("Failed to delete conversation"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }
}
