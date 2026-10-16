package com.xzy.ai.ui.components

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Logout
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.shadow
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.navigation.NavController
import androidx.navigation.compose.currentBackStackEntryAsState
import com.xzy.ai.ui.navigation.Screen
import com.xzy.ai.ui.theme.*
import kotlinx.coroutines.launch

@Composable
fun AppNavigationDrawer(
    navController: NavController,
    userName: String = "User",
    userEmail: String = "user@example.com",
    onLogout: () -> Unit,
    content: @Composable () -> Unit
) {
    val drawerState = rememberDrawerState(initialValue = DrawerValue.Closed)
    val scope = rememberCoroutineScope()
    val navBackStackEntry by navController.currentBackStackEntryAsState()
    val currentRoute = navBackStackEntry?.destination?.route
    
    ModalNavigationDrawer(
        drawerState = drawerState,
        drawerContent = {
            ModalDrawerSheet(
                modifier = Modifier.width(300.dp),
                drawerContainerColor = BrutalSurface,
                drawerContentColor = BrutalInk
            ) {
                DrawerContent(
                    currentRoute = currentRoute,
                    userName = userName,
                    userEmail = userEmail,
                    onNavigate = { route ->
                        navController.navigate(route) {
                            popUpTo(Screen.Dashboard.route) {
                                saveState = true
                            }
                            launchSingleTop = true
                            restoreState = true
                        }
                        scope.launch { drawerState.close() }
                    },
                    onLogout = {
                        onLogout()
                        scope.launch { drawerState.close() }
                    }
                )
            }
        }
    ) {
        content()
    }
}

@Composable
private fun DrawerContent(
    currentRoute: String?,
    userName: String,
    userEmail: String,
    onNavigate: (String) -> Unit,
    onLogout: () -> Unit
) {
    Column(
        modifier = Modifier
            .fillMaxSize()
            .padding(vertical = 24.dp)
    ) {
        // Header with gradient
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .background(
                    brush = Brush.horizontalGradient(
                        colors = listOf(BrutalBrandStart, BrutalBrandEnd)
                    ),
                    shape = RoundedCornerShape(16.dp)
                )
                .padding(horizontal = 20.dp, vertical = 16.dp)
        ) {
            Text(
                text = userName,
                style = MaterialTheme.typography.titleMedium,
                fontWeight = FontWeight.Bold,
                color = BrutalInk
            )
            Text(
                text = userEmail,
                style = MaterialTheme.typography.bodySmall,
                color = BrutalInk.copy(alpha = 0.7f)
            )
        }
        
        Spacer(modifier = Modifier.height(24.dp))
        
        // Navigation items
        Screen.mainScreens.forEach { screen ->
            val isSelected = currentRoute?.startsWith(screen.route.substringBefore("/")) == true
            
            NavigationDrawerItem(
                icon = {
                    Icon(
                        imageVector = if (isSelected) screen.iconSelected else screen.icon,
                        contentDescription = screen.title
                    )
                },
                label = { 
                    Text(
                        text = screen.title,
                        fontWeight = if (isSelected) FontWeight.Bold else FontWeight.Normal
                    ) 
                },
                selected = isSelected,
                onClick = { onNavigate(screen.route.substringBefore("/")) },
                modifier = Modifier.padding(horizontal = 12.dp, vertical = 4.dp),
                colors = NavigationDrawerItemDefaults.colors(
                    selectedContainerColor = BrutalLime.copy(alpha = 0.2f),
                    selectedIconColor = BrutalInk,
                    selectedTextColor = BrutalInk,
                    unselectedContainerColor = BrutalSurface,
                    unselectedIconColor = BrutalMuted,
                    unselectedTextColor = BrutalMuted
                ),
                shape = RoundedCornerShape(12.dp)
            )
        }
        
        Spacer(modifier = Modifier.weight(1f))
        
        // Logout button
        NavigationDrawerItem(
            icon = { Icon(Icons.Filled.Logout, "Logout") },
            label = { Text("Logout") },
            selected = false,
            onClick = onLogout,
            modifier = Modifier.padding(horizontal = 12.dp, vertical = 4.dp),
            colors = NavigationDrawerItemDefaults.colors(
                selectedContainerColor = BrutalSurface,
                selectedIconColor = BrutalMuted,
                selectedTextColor = BrutalMuted,
                unselectedContainerColor = BrutalSurface,
                unselectedIconColor = BrutalMuted,
                unselectedTextColor = BrutalMuted
            ),
            shape = RoundedCornerShape(12.dp)
        )
    }
}
