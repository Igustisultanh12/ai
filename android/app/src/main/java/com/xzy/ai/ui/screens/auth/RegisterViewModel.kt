package com.xzy.ai.ui.screens.auth

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.xzy.ai.data.repository.AuthRepository
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch

import dagger.hilt.android.lifecycle.HiltViewModel
import javax.inject.Inject

data class RegisterUiState(
    val name: String = "",
    val email: String = "",
    val password: String = "",
    val passwordConfirmation: String = "",
    val isLoading: Boolean = false,
    val error: String? = null,
    val isSuccess: Boolean = false
)

@HiltViewModel
class RegisterViewModel @Inject constructor(
    private val authRepository: AuthRepository
) : ViewModel() {
    private val _uiState = MutableStateFlow(RegisterUiState())
    val uiState: StateFlow<RegisterUiState> = _uiState.asStateFlow()
    
    fun onNameChange(name: String) {
        _uiState.value = _uiState.value.copy(name = name, error = null)
    }
    
    fun onEmailChange(email: String) {
        _uiState.value = _uiState.value.copy(email = email, error = null)
    }
    
    fun onPasswordChange(password: String) {
        _uiState.value = _uiState.value.copy(password = password, error = null)
    }
    
    fun onPasswordConfirmationChange(passwordConfirmation: String) {
        _uiState.value = _uiState.value.copy(passwordConfirmation = passwordConfirmation, error = null)
    }
    
    fun register() {
        val currentState = _uiState.value
        
        when {
            currentState.name.isBlank() -> {
                _uiState.value = currentState.copy(error = "Nama tidak boleh kosong")
                return
            }
            currentState.email.isBlank() -> {
                _uiState.value = currentState.copy(error = "Email tidak boleh kosong")
                return
            }
            currentState.password.isBlank() -> {
                _uiState.value = currentState.copy(error = "Password tidak boleh kosong")
                return
            }
            currentState.password.length < 8 -> {
                _uiState.value = currentState.copy(error = "Password minimal 8 karakter")
                return
            }
            currentState.password != currentState.passwordConfirmation -> {
                _uiState.value = currentState.copy(error = "Konfirmasi password tidak sesuai")
                return
            }
        }
        
        viewModelScope.launch {
            _uiState.value = currentState.copy(isLoading = true, error = null)
            
            authRepository.register(
                name = currentState.name,
                email = currentState.email,
                password = currentState.password,
                passwordConfirmation = currentState.passwordConfirmation
            )
                .onSuccess {
                    _uiState.value = currentState.copy(
                        isLoading = false,
                        isSuccess = true,
                        error = null
                    )
                }
                .onFailure { error ->
                    _uiState.value = currentState.copy(
                        isLoading = false,
                        error = error.message ?: "Registrasi gagal"
                    )
                }
        }
    }
}
