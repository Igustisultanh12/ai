package com.xzy.ai.ui

import android.Manifest
import android.os.Build
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.animation.core.tween
import androidx.compose.animation.fadeIn
import androidx.compose.animation.fadeOut
import androidx.compose.animation.scaleIn
import androidx.compose.animation.scaleOut
import androidx.compose.animation.slideInHorizontally
import androidx.compose.animation.slideOutHorizontally
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.runtime.Composable
import androidx.compose.runtime.CompositionLocalProvider
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.remember
import androidx.compose.ui.Modifier
import androidx.navigation.NavType
import androidx.navigation.compose.NavHost
import androidx.navigation.compose.composable
import androidx.navigation.compose.rememberNavController
import androidx.navigation.navArgument
import com.xzy.ai.data.Api
import com.xzy.ai.data.DeepLink
import com.xzy.ai.data.Notifier
import com.xzy.ai.data.Prefs
import com.xzy.ai.data.Session
import com.xzy.ai.data.Updater
import com.xzy.ai.ui.components.LocalNav
import com.xzy.ai.ui.components.LocalToast
import com.xzy.ai.ui.components.ToastHost
import com.xzy.ai.ui.components.ToastState
import com.xzy.ai.ui.screens.AuthScreen
import com.xzy.ai.ui.screens.ChatScreen
import com.xzy.ai.ui.screens.FileScreen
import com.xzy.ai.ui.screens.MainScreen
import com.xzy.ai.ui.screens.NotificationsScreen
import com.xzy.ai.ui.screens.ProjectScreen
import com.xzy.ai.ui.screens.SplashScreen
import com.xzy.ai.ui.screens.UpdateSheet
import com.xzy.ai.ui.screens.UsageScreen
import com.xzy.ai.ui.theme.X
import com.xzy.ai.ui.theme.XzyTheme

@Composable
fun XzyRoot() {
    XzyTheme(dark = Prefs.dark) {
        val nav = rememberNavController()
        val toast = remember { ToastState() }
        val permission = rememberLauncherForActivityResult(ActivityResultContracts.RequestPermission()) {}

        CompositionLocalProvider(LocalNav provides nav, LocalToast provides { msg: String -> toast.show(msg) }) {
            Box(Modifier.fillMaxSize().background(X.c.bg)) {
                NavHost(
                    navController = nav,
                    startDestination = "splash",
                    enterTransition = { slideInHorizontally(tween(340)) { it / 3 } + fadeIn(tween(340)) },
                    exitTransition = { slideOutHorizontally(tween(300)) { -it / 5 } + fadeOut(tween(260)) },
                    popEnterTransition = { slideInHorizontally(tween(340)) { -it / 5 } + fadeIn(tween(340)) },
                    popExitTransition = { slideOutHorizontally(tween(300)) { it / 3 } + fadeOut(tween(260)) },
                ) {
                    composable("splash", exitTransition = { fadeOut(tween(400)) + scaleOut(tween(400), targetScale = 1.1f) }) { SplashScreen() }
                    composable("auth", enterTransition = { fadeIn(tween(450)) + scaleIn(tween(450), initialScale = 0.94f) }) { AuthScreen() }
                    composable("main", enterTransition = { fadeIn(tween(450)) + scaleIn(tween(450), initialScale = 0.94f) }) { MainScreen() }
                    composable("chat/{id}") { ChatScreen(it.arguments?.getString("id").orEmpty()) }
                    composable("project/{id}") { ProjectScreen(it.arguments?.getString("id").orEmpty()) }
                    composable(
                        "file/{pid}?path={path}",
                        arguments = listOf(navArgument("pid") { type = NavType.StringType }, navArgument("path") { type = NavType.StringType; defaultValue = "" }),
                    ) {
                        FileScreen(it.arguments?.getString("pid").orEmpty(), it.arguments?.getString("path").orEmpty())
                    }
                    composable("usage") { UsageScreen() }
                    composable("notifications") { NotificationsScreen() }
                }
                ToastHost(toast)
                UpdateSheet()
            }
        }

        val user = Session.user

        // Session expired somewhere: back to login
        LaunchedEffect(Session.expired) {
            if (Session.expired) {
                Session.expired = false
                Api.clearSession()
                Session.user = null
                toast.show("Sesi berakhir. Silakan masuk lagi.")
                nav.navigate("auth") { popUpTo(nav.graph.id) { inclusive = true } }
            }
        }

        // Once signed in: ask for notification permission, check for updates
        LaunchedEffect(user?.id) {
            if (user == null) return@LaunchedEffect
            if (Build.VERSION.SDK_INT >= 33 && !Notifier.canPost() && !Prefs.askedNotifPermission) {
                Prefs.askedNotifPermission = true
                permission.launch(Manifest.permission.POST_NOTIFICATIONS)
            }
            Updater.checkIfDue()
        }

        // Show the update sheet once per new version
        val available = Updater.available
        LaunchedEffect(available?.build, user?.id) {
            if (available != null && user != null && Api.prefs.getInt("update_prompted", 0) < available.build) {
                Updater.sheetOpen = true
            }
        }

        // Taps on notifications
        val link = DeepLink.pending
        LaunchedEffect(link, user?.id) {
            if (link == null || user == null) return@LaunchedEffect
            DeepLink.pending = null
            when {
                link.startsWith("chat:") -> nav.navigate("chat/${link.removePrefix("chat:")}") { launchSingleTop = true }
                link.startsWith("project:") -> nav.navigate("project/${link.removePrefix("project:")}") { launchSingleTop = true }
                link == "update" -> Updater.sheetOpen = true
            }
        }
    }
}
