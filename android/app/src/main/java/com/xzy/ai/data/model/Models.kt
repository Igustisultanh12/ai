package com.xzy.ai.data.model

import androidx.room.Entity
import androidx.room.PrimaryKey

@Entity(tableName = "conversations")
data class ConversationEntity(
    @PrimaryKey
    val id: String,
    val title: String,
    val model: String,
    val createdAt: Long,
    val updatedAt: Long,
    val isFavorite: Boolean = false
)

@Entity(tableName = "messages")
data class MessageEntity(
    @PrimaryKey
    val id: String,
    val conversationId: String,
    val content: String,
    val role: String, // "user" or "assistant"
    val model: String?,
    val createdAt: Long,
    val tokens: Int? = null
)

data class User(
    val id: String,
    val name: String,
    val email: String,
    val avatar: String?,
    val plan: String? = null,
    val credits: Int = 0
)

data class Message(
    val id: String,
    val content: String,
    val isUser: Boolean,
    val timestamp: Long,
    val formattedTime: String,
    val model: String? = null,
    val isStreaming: Boolean = false
)

data class Conversation(
    val id: String,
    val title: String,
    val model: String,
    val lastMessage: String?,
    val lastMessageTime: Long,
    val messageCount: Int,
    val isFavorite: Boolean = false
)

data class AIModel(
    val id: String,
    val name: String,
    val description: String,
    val icon: String,
    val color: String,
    val isPremium: Boolean = false
)