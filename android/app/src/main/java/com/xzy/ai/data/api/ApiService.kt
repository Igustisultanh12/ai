package com.xzy.ai.data.api

import com.xzy.ai.data.api.dto.*
import retrofit2.Response
import retrofit2.http.*

interface ApiService {

    @POST("api/login")
    suspend fun login(@Body request: LoginRequest): Response<AuthResponse>

    @POST("api/register")
    suspend fun register(@Body request: RegisterRequest): Response<AuthResponse>

    @POST("api/logout")
    suspend fun logout(): Response<Unit>

    @GET("api/user")
    suspend fun getUser(): Response<UserResponse>

    @GET("api/conversations")
    suspend fun getConversations(): Response<ConversationsResponse>

    @POST("api/conversations")
    suspend fun createConversation(@Body request: CreateConversationRequest): Response<ConversationResponse>

    @GET("api/conversations/{id}")
    suspend fun getConversation(@Path("id") id: String): Response<ConversationResponse>

    @DELETE("api/conversations/{id}")
    suspend fun deleteConversation(@Path("id") id: String): Response<Unit>

    @POST("api/conversations/{id}/messages")
    suspend fun sendMessage(
        @Path("id") conversationId: String,
        @Body request: SendMessageRequest
    ): Response<MessageResponse>

    @GET("api/models")
    suspend fun getModels(): Response<ModelsResponse>

    @GET("api/vibecoding/projects")
    suspend fun getProjects(): Response<ProjectsResponse>

    @POST("api/vibecoding/projects")
    suspend fun createProject(@Body request: CreateProjectRequest): Response<ProjectResponse>

    @GET("api/vibecoding/projects/{id}")
    suspend fun getProject(@Path("id") id: String): Response<ProjectResponse>

    @POST("api/vibecoding/projects/{id}/files")
    suspend fun uploadFile(
        @Path("id") projectId: String,
        @Body request: UploadFileRequest
    ): Response<FileResponse>
}