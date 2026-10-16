package com.xzy.ai

import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.activity.enableEdgeToEdge
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.padding
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Menu
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Modifier
import androidx.navigation.NavType
import androidx.navigation.compose.NavHost
import androidx.navigation.compose.composable
import androidx.navigation.compose.currentBackStackEntryAsState
import androidx.navigation.compose.rememberNavController
import androidx.navigation.navArgument
import com.xzy.ai.ui.components.NavigationDrawer
import com.xzy.ai.ui.navigation.Screen
import com.xzy.ai.ui.screens.DashboardScreen
import com.xzy.ai.ui.screens.ProjectsScreen
import com.xzy.ai.ui.screens.UsageScreen
import com.xzy.ai.ui.screens.SettingsScreen
import com.xzy.ai.ui.screens.WorkspaceScreen
import com.xzy.ai.ui.screens.auth.LoginScreen
import com.xzy.ai.ui.screens.auth.RegisterScreen
import com.xzy.ai.ui.screens.chat.ChatScreen
import com.xzy.ai.ui.screens.splash.SplashScreen
import com.xzy.ai.ui.theme.XzyAITheme
import com.xzy.ai.utils.TokenManager
import dagger.hilt.android.AndroidEntryPoint
import kotlinx.coroutines.launch
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
                    XzyAppNavigation(
                        isLoggedIn = isLoggedIn,
                        onLogout = { tokenManager.clearToken() }
                    )
                }
            }
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun XzyAppNavigation(
    isLoggedIn: Boolean,
    onLogout: () -> Unit
) {
    val navController = rememberNavController()
    val currentBackStackEntry by navController.currentBackStackEntryAsState()
    val currentRoute = currentBackStackEntry?.destination?.route
    
    val drawerState = rememberDrawerState(DrawerValue.Closed)
    val scope = rememberCoroutineScope()

    // Check if current screen should show drawer
    val showDrawer = when {
        currentRoute == null -> false
        currentRoute.startsWith("splash") -> false
        currentRoute.startsWith("login") -> false
        currentRoute.startsWith("register") -> false
        else -> true
    }

    if (showDrawer) {
        ModalNavigationDrawer(
            drawerState = drawerState,
            drawerContent = {
                NavigationDrawer(
                    navController = navController,
                    currentRoute = currentRoute,
                    onCloseDrawer = {
                        scope.launch { drawerState.close() }
                    },
                    onLogout = {
                        onLogout()
                        navController.navigate("login") {
                            popUpTo(0) { inclusive = true }
                        }
                    }
                )
            }
        ) {
            Scaffold(
                topBar = {
                    if (showDrawer) {
                        TopAppBar(
                            title = {
                                Text(
                                    text = when {
                                        currentRoute == Screen.Dashboard.route -> "Dashboard"
                                        currentRoute == Screen.Projects.route -> "Projects"
                                        currentRoute?.startsWith("chat") == true -> "Chat"
                                        currentRoute == Screen.Usage.route -> "Usage"
                                        currentRoute == Screen.Settings.route -> "Settings"
                                        currentRoute?.startsWith("workspace") == true -> "Workspace"
                                        else -> "XZY AI"
                                    }
                                )
                            },
                            navigationIcon = {
                                IconButton(onClick = {
                                    scope.launch { drawerState.open() }
                                }) {
                                    Icon(
                                        imageVector = Icons.Default.Menu,
                                        contentDescription = "Menu"
                                    )
                                }
                            }
                        )
                    }
                }
            ) { padding ->
                NavHost(
                    navController = navController,
                    startDestination = "splash",
                    modifier = Modifier.padding(padding)
                ) {
                    // Auth screens
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
                        LoginScreen(navController = navController)
                    }
                    composable("register") {
                        RegisterScreen(
                            onNavigateToLogin = {
                                navController.popBackStack()
                            },
                            onRegisterSuccess = {
                                navController.navigate(Screen.Dashboard.route) {
                                    popUpTo("register") { inclusive = true }
                                }
                            }
                        )
                    }

                    // Main screens
                    composable(Screen.Dashboard.route) {
                        DashboardScreen()
                    }
                    composable(Screen.Projects.route) {
                        ProjectsScreen()
                    }
                    composable(
                        route = Screen.Chat.route,
                        arguments = listOf(
                            navArgument("id") {
                                type = NavType.StringType
                                defaultValue = "new"
                            }
                        )
                    ) { backStackEntry ->
                        val chatId = backStackEntry.arguments?.getString("id") ?: "new"
                        ChatScreen(
                            chatId = chatId,
                            onNavigateBack = {
                                navController.popBackStack()
                            }
                        )
                    }
                    composable(Screen.Usage.route) {
                        UsageScreen()
                    }
                    composable(Screen.Settings.route) {
                        SettingsScreen(navController = navController)
                    }
                    composable(
                        route = Screen.Workspace.route,
                        arguments = listOf(
                            navArgument("id") {
                                type = NavType.StringType
                            }
                        )
                    ) { backStackEntry ->
                        val projectId = backStackEntry.arguments?.getString("id") ?: ""
                        WorkspaceScreen(
                            projectId = projectId,
                            onNavigateBack = {
                                navController.popBackStack()
                            }
                        )
                    }
                }
            }
        }
    } else {
        // Auth flow without drawer
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
                LoginScreen(navController = navController)
            }
            composable("register") {
                RegisterScreen(
                    onNavigateToLogin = {
                        navController.popBackStack()
                    },
                    onRegisterSuccess = {
                        navController.navigate(Screen.Dashboard.route) {
                            popUpTo("register") { inclusive = true }
                        }
                    }
                )
            }
        }
    }
}
