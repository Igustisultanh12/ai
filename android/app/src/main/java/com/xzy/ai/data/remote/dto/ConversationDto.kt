package com.xzy.ai.data.remote.dto

import com.google.gson.annotations.SerializedName

data class ConversationDto(
    val id: String,
    val title: String,
    val model: String?,
    @SerializedName("project_id")
    val projectId: String?,
    @SerializedName("updated_at")
    val updatedAt: String,
    val messages: List<MessageDto>?
)

data class MessageDto(
    val id: String?,
    val role: String, // "user" or "assistant"
    val content: String,
    val status: String?, // "streaming", "complete"
    @SerializedName("created_at")
    val createdAt: String?
)

data class ConversationsResponse(
    val data: List<ConversationDto>
)

data class ConversationDetailResponse(
    val conversation: ConversationDto
)

data class SendMessageRequest(
    val content: String,
    val model: String?
)

data class CreateConversationRequest(
    val title: String,
    val model: String?,
    @SerializedName("project_id")
    val projectId: String?
)
