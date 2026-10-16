package com.xzy.ai.data.repository

import com.xzy.ai.data.api.ApiService
import com.xzy.ai.data.api.dto.LoginRequest
import com.xzy.ai.data.api.dto.RegisterRequest
import com.xzy.ai.data.model.User
import com.xzy.ai.utils.Result
import com.xzy.ai.utils.TokenManager
import javax.inject.Inject
import javax.inject.Singleton

@Singleton
class AuthRepository @Inject constructor(
    private val apiService: ApiService,
    private val tokenManager: TokenManager
) {

    suspend fun login(email: String, password: String): Result<User> {
        return try {
            val response = apiService.login(LoginRequest(email, password))
            if (response.isSuccessful && response.body() != null) {
                val body = response.body()!!
                tokenManager.saveToken(body.token)
                val user = User(
                    id = body.user.id,
                    name = body.user.name,
                    email = body.user.email,
                    avatar = body.user.avatar
                )
                Result.Success(user)
            } else {
                Result.Error("Login failed: ${response.message()}")
            }
        } catch (e: Exception) {
            Result.Error(e.message ?: "Unknown error")
        }
    }

    suspend fun register(
        name: String,
        email: String,
        password: String,
        passwordConfirmation: String = password
    ): Result<User> {
        return try {
            val response = apiService.register(
                RegisterRequest(name, email, password, passwordConfirmation)
            )
            if (response.isSuccessful && response.body() != null) {
                val body = response.body()!!
                tokenManager.saveToken(body.token)
                val user = User(
                    id = body.user.id,
                    name = body.user.name,
                    email = body.user.email,
                    avatar = body.user.avatar
                )
                Result.Success(user)
            } else {
                Result.Error("Registration failed: ${response.message()}")
            }
        } catch (e: Exception) {
            Result.Error(e.message ?: "Unknown error")
        }
    }

    suspend fun logout(): Result<Unit> {
        return try {
            apiService.logout()
            tokenManager.clearToken()
            Result.Success(Unit)
        } catch (e: Exception) {
            tokenManager.clearToken()
            Result.Error(e.message ?: "Unknown error")
        }
    }

    fun isLoggedIn(): Boolean {
        return tokenManager.getToken() != null
    }

    suspend fun getCurrentUser(): Result<User> {
        return try {
            val response = apiService.getUser()
            if (response.isSuccessful && response.body() != null) {
                val dto = response.body()!!.data
                val user = User(
                    id = dto.id,
                    name = dto.name,
                    email = dto.email,
                    avatar = dto.avatar
                )
                Result.Success(user)
            } else {
                Result.Error("Failed to get user info")
            }
        } catch (e: Exception) {
            Result.Error(e.message ?: "Unknown error")
        }
    }
}