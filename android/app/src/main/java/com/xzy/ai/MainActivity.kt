package com.xzy.ai

import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.activity.enableEdgeToEdge
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Surface
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import androidx.navigation.compose.NavHost
import androidx.navigation.compose.composable
import androidx.navigation.compose.rememberNavController
import com.xzy.ai.ui.screens.auth.LoginScreen
import com.xzy.ai.ui.screens.auth.RegisterScreen
import com.xzy.ai.ui.screens.chat.ChatScreen
import com.xzy.ai.ui.screens.splash.SplashScreen
import com.xzy.ai.ui.theme.XzyAITheme
import com.xzy.ai.utils.TokenManager
import dagger.hilt.android.AndroidEntryPoint
import javax.inject.Inject

@AndroidEntryPoint
class MainActivity : ComponentActivity() {

    @Inject
    lateinit var tokenManager: TokenManager

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        enableEdgeToEdge()
        setContent {
            XzyAITheme {
                Surface(
                    modifier = Modifier.fillMaxSize(),
                    color = MaterialTheme.colorScheme.background
                ) {
                    val isLoggedIn = !tokenManager.getToken().isNullOrBlank()
                    XzyAppNavigation(isLoggedIn = isLoggedIn, onLogout = { tokenManager.clearToken() })
                }
            }
        }
    }
}

@Composable
fun XzyAppNavigation(
    isLoggedIn: Boolean,
    onLogout: () -> Unit
) {
    val navController = rememberNavController()

    NavHost(
        navController = navController,
        startDestination = "splash"
    ) {
        composable("splash") {
            SplashScreen(
                onNavigateToLogin = {
                    navController.navigate("login") {
                        popUpTo("splash") { inclusive = true }
                    }
                }
            )
        }
        composable("login") {
            LoginScreen(
                navController = navController
            )
        }
        composable("register") {
            RegisterScreen(
                onNavigateToLogin = {
                    navController.popBackStack()
                },
                onRegisterSuccess = {
                    navController.navigate("chat") {
                        popUpTo("register") { inclusive = true }
                    }
                }
            )
        }
        composable("chat") {
            ChatScreen(
                onNavigateBack = {
                    onLogout()
                    navController.navigate("login") {
                        popUpTo("chat") { inclusive = true }
                    }
                }
            )
        }
    }
}
