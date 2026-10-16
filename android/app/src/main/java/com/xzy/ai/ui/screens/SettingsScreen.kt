package com.xzy.ai.ui.screens

import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material.icons.outlined.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.shadow
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import com.xzy.ai.ui.theme.*

@Composable
fun SettingsScreen(
    onLogout: () -> Unit = {}
) {
    Column(
        modifier = Modifier
            .fillMaxSize()
            .background(BrutalSurface)
            .verticalScroll(rememberScrollState())
    ) {
        // Header
        Surface(
            color = BrutalCard,
            shadowElevation = 2.dp,
            modifier = Modifier.fillMaxWidth()
        ) {
            Column(
                modifier = Modifier.padding(16.dp)
            ) {
                Text(
                    text = "Settings",
                    style = MaterialTheme.typography.headlineMedium,
                    fontWeight = FontWeight.Bold,
                    color = BrutalInk
                )
                Text(
                    text = "Kelola profil dan preferensi Anda",
                    style = MaterialTheme.typography.bodyMedium,
                    color = BrutalMuted
                )
            }
        }
        
        Column(
            modifier = Modifier.padding(16.dp),
            verticalArrangement = Arrangement.spacedBy(12.dp)
        ) {
            // Profile section
            Card(
                modifier = Modifier
                    .fillMaxWidth()
                    .shadow(
                        elevation = 3.dp,
                        shape = RoundedCornerShape(20.dp),
                        ambientColor = BrutalInk,
                        spotColor = BrutalInk
                    ),
                shape = RoundedCornerShape(20.dp),
                colors = CardDefaults.cardColors(
                    containerColor = BrutalCard
                ),
                border = androidx.compose.foundation.BorderStroke(2.dp, BrutalInk)
            ) {
                Column(
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(20.dp)
                ) {
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        verticalAlignment = Alignment.CenterVertically,
                        horizontalArrangement = Arrangement.spacedBy(16.dp)
                    ) {
                        Surface(
                            shape = RoundedCornerShape(50),
                            color = BrutalLime,
                            border = androidx.compose.foundation.BorderStroke(3.dp, BrutalInk),
                            modifier = Modifier.size(64.dp)
                        ) {
                            Box(
                                contentAlignment = Alignment.Center,
                                modifier = Modifier.fillMaxSize()
                            ) {
                                Icon(
                                    Icons.Filled.Person,
                                    contentDescription = null,
                                    modifier = Modifier.size(32.dp),
                                    tint = BrutalInk
                                )
                            }
                        }
                        
                        Column(
                            modifier = Modifier.weight(1f)
                        ) {
                            Text(
                                text = "User Name",
                                style = MaterialTheme.typography.titleLarge,
                                fontWeight = FontWeight.Bold,
                                color = BrutalInk
                            )
                            Text(
                                text = "user@example.com",
                                style = MaterialTheme.typography.bodyMedium,
                                color = BrutalMuted
                            )
                        }
                        
                        IconButton(onClick = { /* Edit profile */ }) {
                            Icon(
                                Icons.Outlined.Edit,
                                contentDescription = "Edit profile",
                                tint = BrutalInk
                            )
                        }
                    }
                }
            }
            
            // Account settings
            SettingsSection(
                title = "Account",
                items = listOf(
                    SettingsItem(
                        icon = Icons.Outlined.Badge,
                        title = "Profile",
                        subtitle = "Edit your name and email"
                    ),
                    SettingsItem(
                        icon = Icons.Outlined.Lock,
                        title = "Password",
                        subtitle = "Change your password"
                    ),
                    SettingsItem(
                        icon = Icons.Outlined.VpnKey,
                        title = "API Keys",
                        subtitle = "Manage your API keys"
                    )
                )
            )
            
            // Preferences
            SettingsSection(
                title = "Preferences",
                items = listOf(
                    SettingsItem(
                        icon = Icons.Outlined.Palette,
                        title = "Theme",
                        subtitle = "Customize app appearance"
                    ),
                    SettingsItem(
                        icon = Icons.Outlined.Notifications,
                        title = "Notifications",
                        subtitle = "Manage notification settings"
                    ),
                    SettingsItem(
                        icon = Icons.Outlined.Language,
                        title = "Language",
                        subtitle = "Change app language"
                    )
                )
            )
            
            // About
            SettingsSection(
                title = "About",
                items = listOf(
                    SettingsItem(
                        icon = Icons.Outlined.Info,
                        title = "About XZY AI",
                        subtitle = "Version 2.1.0"
                    ),
                    SettingsItem(
                        icon = Icons.Outlined.Description,
                        title = "Terms & Privacy",
                        subtitle = "Read our terms and privacy policy"
                    ),
                    SettingsItem(
                        icon = Icons.Outlined.Help,
                        title = "Help & Support",
                        subtitle = "Get help with XZY AI"
                    )
                )
            )
            
            // Logout button
            Button(
                onClick = onLogout,
                modifier = Modifier
                    .fillMaxWidth()
                    .height(56.dp)
                    .shadow(
                        elevation = 4.dp,
                        shape = RoundedCornerShape(999.dp),
                        ambientColor = BrutalInk,
                        spotColor = BrutalInk
                    ),
                colors = ButtonDefaults.buttonColors(
                    containerColor = BrutalCard,
                    contentColor = BrutalInk
                ),
                border = androidx.compose.foundation.BorderStroke(2.dp, BrutalInk),
                shape = RoundedCornerShape(999.dp)
            ) {
                Icon(
                    Icons.Outlined.Logout,
                    contentDescription = null,
                    modifier = Modifier.size(20.dp)
                )
                Spacer(modifier = Modifier.width(8.dp))
                Text(
                    text = "Logout",
                    style = MaterialTheme.typography.labelLarge,
                    fontWeight = FontWeight.Bold
                )
            }
            
            Spacer(modifier = Modifier.height(16.dp))
        }
    }
}

@Composable
private fun SettingsSection(
    title: String,
    items: List<SettingsItem>
) {
    Card(
        modifier = Modifier
            .fillMaxWidth()
            .shadow(
                elevation = 3.dp,
                shape = RoundedCornerShape(20.dp),
                ambientColor = BrutalInk,
                spotColor = BrutalInk
            ),
        shape = RoundedCornerShape(20.dp),
        colors = CardDefaults.cardColors(
            containerColor = BrutalCard
        ),
        border = androidx.compose.foundation.BorderStroke(2.dp, BrutalInk)
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(20.dp)
        ) {
            Text(
                text = title,
                style = MaterialTheme.typography.titleMedium,
                fontWeight = FontWeight.Bold,
                color = BrutalInk
            )
            
            Spacer(modifier = Modifier.height(12.dp))
            
            items.forEachIndexed { index, item ->
                if (index > 0) {
                    HorizontalDivider(
                        modifier = Modifier.padding(vertical = 12.dp),
                        color = BrutalMuted.copy(alpha = 0.2f)
                    )
                }
                
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .clickable { /* Handle click */ }
                        .padding(vertical = 8.dp),
                    horizontalArrangement = Arrangement.spacedBy(16.dp),
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Surface(
                        shape = RoundedCornerShape(12.dp),
                        color = BrutalLime.copy(alpha = 0.2f),
                        border = androidx.compose.foundation.BorderStroke(2.dp, BrutalInk),
                        modifier = Modifier.size(44.dp)
                    ) {
                        Box(
                            contentAlignment = Alignment.Center,
                            modifier = Modifier.fillMaxSize()
                        ) {
                            Icon(
                                item.icon,
                                contentDescription = null,
                                modifier = Modifier.size(22.dp),
                                tint = BrutalInk
                            )
                        }
                    }
                    
                    Column(
                        modifier = Modifier.weight(1f)
                    ) {
                        Text(
                            text = item.title,
                            style = MaterialTheme.typography.bodyLarge,
                            fontWeight = FontWeight.SemiBold,
                            color = BrutalInk
                        )
                        Text(
                            text = item.subtitle,
                            style = MaterialTheme.typography.bodySmall,
                            color = BrutalMuted
                        )
                    }
                    
                    Icon(
                        Icons.Filled.ChevronRight,
                        contentDescription = null,
                        tint = BrutalMuted
                    )
                }
            }
        }
    }
}

private data class SettingsItem(
    val icon: ImageVector,
    val title: String,
    val subtitle: String
)
