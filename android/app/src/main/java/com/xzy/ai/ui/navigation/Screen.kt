package com.xzy.ai.ui.navigation

import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material.icons.outlined.*
import androidx.compose.ui.graphics.vector.ImageVector

sealed class Screen(
    val route: String,
    val title: String,
    val icon: ImageVector,
    val iconSelected: ImageVector = icon
) {
    data object Dashboard : Screen(
        route = "dashboard",
        title = "Dashboard",
        icon = Icons.Outlined.Dashboard,
        iconSelected = Icons.Filled.Dashboard
    )
    
    data object Projects : Screen(
        route = "projects",
        title = "Projects",
        icon = Icons.Outlined.FolderOpen,
        iconSelected = Icons.Filled.FolderOpen
    )
    
    data object Chat : Screen(
        route = "chat/{id}",
        title = "Chat",
        icon = Icons.Outlined.Chat,
        iconSelected = Icons.Filled.Chat
    ) {
        fun createRoute(id: String? = null) = if (id != null) "chat/$id" else "chat/new"
    }
    
    data object Usage : Screen(
        route = "usage",
        title = "Usage",
        icon = Icons.Outlined.BarChart,
        iconSelected = Icons.Filled.BarChart
    )
    
    data object Settings : Screen(
        route = "settings",
        title = "Settings",
        icon = Icons.Outlined.Settings,
        iconSelected = Icons.Filled.Settings
    )
    
    data object Workspace : Screen(
        route = "workspace/{id}",
        title = "Workspace",
        icon = Icons.Outlined.Workspaces,
        iconSelected = Icons.Filled.Workspaces
    ) {
        fun createRoute(id: String) = "workspace/$id"
    }

    companion object {
        val mainScreens = listOf(Dashboard, Projects, Chat, Usage, Settings)
    }
}
