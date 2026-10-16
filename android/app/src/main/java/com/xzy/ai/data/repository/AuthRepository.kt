package com.xzy.ai.data.repository

import com.xzy.ai.data.local.PreferencesManager
import com.xzy.ai.data.remote.RetrofitClient
import com.xzy.ai.data.remote.dto.*
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext

class AuthRepository(private val prefsManager: PreferencesManager) {
    
    private val apiService = RetrofitClient.apiService
    
    suspend fun register(
        name: String,
        email: String,
        password: String,
        passwordConfirmation: String
    ): Result<AuthResponse> = withContext(Dispatchers.IO) {
        try {
            val request = RegisterRequest(name, email, password, passwordConfirmation)
            val response = apiService.register(request)
            
            if (response.isSuccessful && response.body() != null) {
                val authResponse = response.body()!!
                saveUserData(authResponse.user)
                Result.success(authResponse)
            } else {
                Result.failure(Exception(response.message() ?: "Registration failed"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }
    
    suspend fun login(
        email: String,
        password: String
    ): Result<AuthResponse> = withContext(Dispatchers.IO) {
        try {
            val request = LoginRequest(email, password)
            val response = apiService.login(request)
            
            if (response.isSuccessful && response.body() != null) {
                val authResponse = response.body()!!
                saveUserData(authResponse.user)
                Result.success(authResponse)
            } else {
                Result.failure(Exception(response.message() ?: "Login failed"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }
    
    suspend fun logout(): Result<Unit> = withContext(Dispatchers.IO) {
        try {
            apiService.logout()
            clearUserData()
            Result.success(Unit)
        } catch (e: Exception) {
            // Clear local data even if API call fails
            clearUserData()
            Result.success(Unit)
        }
    }
    
    suspend fun getCurrentUser(): Result<UserDto> = withContext(Dispatchers.IO) {
        try {
            val response = apiService.getCurrentUser()
            if (response.isSuccessful && response.body() != null) {
                val user = response.body()!!
                saveUserData(user)
                Result.success(user)
            } else {
                Result.failure(Exception("Failed to fetch user"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }
    
    private fun saveUserData(user: UserDto) {
        prefsManager.userId = user.id
        prefsManager.userName = user.name
        prefsManager.userEmail = user.email
        prefsManager.isLoggedIn = true
    }
    
    private fun clearUserData() {
        prefsManager.clearAll()
        RetrofitClient.clearCookies()
    }
    
    fun isLoggedIn(): Boolean = prefsManager.isLoggedIn
    
    fun getUserName(): String? = prefsManager.userName
}
