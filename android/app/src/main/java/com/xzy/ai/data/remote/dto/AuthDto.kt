package com.xzy.ai.data.remote.dto

import com.google.gson.annotations.SerializedName

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
    val user: UserDto,
    val message: String?
)

data class UserDto(
    val id: String,
    val name: String,
    val email: String,
    @SerializedName("is_admin")
    val isAdmin: Boolean,
    @SerializedName("created_at")
    val createdAt: String
)
