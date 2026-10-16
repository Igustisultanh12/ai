package com.xzy.ai.data.api.dto

import com.google.gson.annotations.SerializedName

// Auth DTOs
data class LoginRequest(
    val email: String,
    val password: String
)

data class RegisterRequest(
    val name: String,
    val email: String,
    val password: String,
    @SerializedName("password_confirmation")
    val passwordConfirmation: String
)

data class AuthResponse(
    val token: String,
    val user: UserDto
)

data class UserDto(
    val id: String,
    val name: String,
    val email: String,
    val avatar: String?,
    val plan: String?,
    val credits: Int?
)

data class UserResponse(
    val data: UserDto
)

// Conversation DTOs
data class CreateConversationRequest(
    val title: String,
    val model: String = "gpt-4"
)

data class ConversationDto(
    val id: String,
    val title: String,
    val model: String,
    @SerializedName("created_at")
    val createdAt: String,
    @SerializedName("updated_at")
    val updatedAt: String,
    val messages: List<MessageDto>?
)

data class ConversationResponse(
    val data: ConversationDto
)

data class ConversationsResponse(
    val data: List<ConversationDto>
)

// Message DTOs
data class SendMessageRequest(
    val content: String,
    val model: String? = null
)

data class MessageDto(
    val id: String,
    val content: String,
    val role: String,
    val model: String?,
    @SerializedName("created_at")
    val createdAt: String,
    val tokens: Int?
)

data class MessageResponse(
    val data: MessageDto
)

// Model DTOs
data class ModelDto(
    val id: String,
    val name: String,
    val description: String,
    val icon: String,
    val color: String,
    @SerializedName("is_premium")
    val isPremium: Boolean
)

data class ModelsResponse(
    val data: List<ModelDto>
)

// Vibecoding DTOs
data class CreateProjectRequest(
    val name: String,
    val description: String?
)

data class ProjectDto(
    val id: String,
    val name: String,
    val description: String?,
    @SerializedName("created_at")
    val createdAt: String,
    val files: List<FileDto>?
)

data class ProjectResponse(
    val data: ProjectDto
)

data class ProjectsResponse(
    val data: List<ProjectDto>
)

data class UploadFileRequest(
    val path: String,
    val content: String
)

data class FileDto(
    val id: String,
    val path: String,
    val content: String?,
    val size: Int
)

data class FileResponse(
    val data: FileDto
)