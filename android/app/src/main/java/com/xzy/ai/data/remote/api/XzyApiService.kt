package com.xzy.ai.data.remote.api

import com.xzy.ai.data.remote.dto.*
import retrofit2.Response
import retrofit2.http.*

interface XzyApiService {
    
    // Auth endpoints
    @POST("auth/register")
    suspend fun register(@Body request: RegisterRequest): Response<AuthResponse>
    
    @POST("auth/login")
    suspend fun login(@Body request: LoginRequest): Response<AuthResponse>
    
    @POST("auth/logout")
    suspend fun logout(): Response<Unit>
    
    @GET("auth/user")
    suspend fun getCurrentUser(): Response<UserDto>
    
    // Conversation endpoints
    @GET("conversations")
    suspend fun getConversations(): Response<ConversationsResponse>
    
    @GET("conversations/{id}")
    suspend fun getConversation(@Path("id") id: String): Response<ConversationDetailResponse>
    
    @POST("conversations")
    suspend fun createConversation(@Body request: CreateConversationRequest): Response<ConversationDto>
    
    @POST("conversations/{id}/messages")
    suspend fun sendMessage(
        @Path("id") conversationId: String,
        @Body request: SendMessageRequest
    ): Response<MessageDto>
    
    @DELETE("conversations/{id}")
    suspend fun deleteConversation(@Path("id") id: String): Response<Unit>
    
    // Models endpoint
    @GET("models")
    suspend fun getModels(): Response<ModelsResponse>
    
    // Projects endpoint
    @GET("projects")
    suspend fun getProjects(): Response<ProjectsResponse>
    
    @POST("projects")
    suspend fun createProject(@Body request: CreateProjectRequest): Response<ProjectDto>
    
    @DELETE("projects/{id}")
    suspend fun deleteProject(@Path("id") id: String): Response<Unit>
}
