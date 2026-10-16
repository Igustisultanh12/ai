package com.xzy.ai.data.repository

import com.xzy.ai.data.api.ApiService
import com.xzy.ai.data.api.dto.*
import com.xzy.ai.data.local.ConversationDao
import com.xzy.ai.data.local.MessageDao
import com.xzy.ai.data.model.*
import com.xzy.ai.utils.Result
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.map
import java.text.SimpleDateFormat
import java.util.*
import javax.inject.Inject
import javax.inject.Singleton

@Singleton
class ConversationRepository @Inject constructor(
    private val apiService: ApiService,
    private val conversationDao: ConversationDao,
    private val messageDao: MessageDao
) {

    fun getConversations(): Flow<List<Conversation>> {
        return conversationDao.getAllConversations().map { entities ->
            entities.map { entity ->
                Conversation(
                    id = entity.id,
                    title = entity.title,
                    model = entity.model,
                    lastMessage = null,
                    lastMessageTime = entity.updatedAt,
                    messageCount = 0,
                    isFavorite = entity.isFavorite
                )
            }
        }
    }

    suspend fun syncConversations(): Result<Unit> {
        return try {
            val response = apiService.getConversations()
            if (response.isSuccessful && response.body() != null) {
                val conversations = response.body()!!.data.map { dto ->
                    ConversationEntity(
                        id = dto.id,
                        title = dto.title,
                        model = dto.model,
                        createdAt = parseDate(dto.createdAt),
                        updatedAt = parseDate(dto.updatedAt),
                        isFavorite = false
                    )
                }
                conversationDao.insertConversations(conversations)
                Result.Success(Unit)
            } else {
                Result.Error("Failed to sync conversations")
            }
        } catch (e: Exception) {
            Result.Error(e.message ?: "Unknown error")
        }
    }

    suspend fun createConversation(title: String, model: String): Result<String> {
        return try {
            val response = apiService.createConversation(
                CreateConversationRequest(title, model)
            )
            if (response.isSuccessful && response.body() != null) {
                val dto = response.body()!!.data
                val entity = ConversationEntity(
                    id = dto.id,
                    title = dto.title,
                    model = dto.model,
                    createdAt = parseDate(dto.createdAt),
                    updatedAt = parseDate(dto.updatedAt),
                    isFavorite = false
                )
                conversationDao.insertConversation(entity)
                Result.Success(dto.id)
            } else {
                Result.Error("Failed to create conversation")
            }
        } catch (e: Exception) {
            Result.Error(e.message ?: "Unknown error")
        }
    }

    suspend fun deleteConversation(id: String): Result<Unit> {
        return try {
            val response = apiService.deleteConversation(id)
            if (response.isSuccessful) {
                conversationDao.deleteConversationById(id)
                messageDao.deleteMessagesByConversation(id)
                Result.Success(Unit)
            } else {
                Result.Error("Failed to delete conversation")
            }
        } catch (e: Exception) {
            Result.Error(e.message ?: "Unknown error")
        }
    }

    fun getMessages(conversationId: String): Flow<List<Message>> {
        return messageDao.getMessagesByConversation(conversationId).map { entities ->
            entities.map { entity ->
                Message(
                    id = entity.id,
                    content = entity.content,
                    isUser = entity.role == "user",
                    timestamp = entity.createdAt,
                    formattedTime = formatTime(entity.createdAt),
                    model = entity.model
                )
            }
        }
    }

    suspend fun sendMessage(conversationId: String, content: String, model: String?): Result<Unit> {
        return try {
            // Insert user message locally first
            val userMessage = MessageEntity(
                id = UUID.randomUUID().toString(),
                conversationId = conversationId,
                content = content,
                role = "user",
                model = null,
                createdAt = System.currentTimeMillis()
            )
            messageDao.insertMessage(userMessage)

            // Send to API
            val response = apiService.sendMessage(
                conversationId,
                SendMessageRequest(content, model)
            )

            if (response.isSuccessful && response.body() != null) {
                val dto = response.body()!!.data
                val assistantMessage = MessageEntity(
                    id = dto.id,
                    conversationId = conversationId,
                    content = dto.content,
                    role = dto.role,
                    model = dto.model,
                    createdAt = parseDate(dto.createdAt),
                    tokens = dto.tokens
                )
                messageDao.insertMessage(assistantMessage)
                Result.Success(Unit)
            } else {
                Result.Error("Failed to send message")
            }
        } catch (e: Exception) {
            Result.Error(e.message ?: "Unknown error")
        }
    }

    private fun parseDate(dateStr: String): Long {
        return try {
            val format = SimpleDateFormat("yyyy-MM-dd'T'HH:mm:ss", Locale.getDefault())
            format.parse(dateStr)?.time ?: System.currentTimeMillis()
        } catch (e: Exception) {
            System.currentTimeMillis()
        }
    }

    private fun formatTime(timestamp: Long): String {
        val format = SimpleDateFormat("HH:mm", Locale.getDefault())
        return format.format(Date(timestamp))
    }
}