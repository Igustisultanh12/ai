package com.xzy.ai.ui.screens

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.shadow
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import com.xzy.ai.ui.theme.*

data class QuotaCard(
    val label: String,
    val value: Int,
    val total: Int? = null,
    val icon: ImageVector,
    val color: Color,
    val hint: String
)

data class Project(
    val id: String,
    val name: String,
    val lastActivity: String,
    val status: String
)

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun DashboardScreen(
    userName: String = "User",
    onNavigateToNewProject: () -> Unit = {},
    onNavigateToChat: () -> Unit = {},
    onProjectClick: (String) -> Unit = {}
) {
    val quotaCards = listOf(
        QuotaCard(
            label = "Token tersisa",
            value = 15240,
            total = 50000,
            icon = Icons.Filled.Token,
            color = BrutalLime,
            hint = "Reset tiap 1 bulan"
        ),
        QuotaCard(
            label = "Proyek aktif",
            value = 8,
            total = null,
            icon = Icons.Filled.FolderOpen,
            color = BrutalViolet,
            hint = "Total workspace"
        ),
        QuotaCard(
            label = "Chat hari ini",
            value = 42,
            total = null,
            icon = Icons.Filled.Chat,
            color = BrutalYellow,
            hint = "Pesan dikirim"
        )
    )
    
    val recentProjects = listOf(
        Project("1", "xzy-ai-web", "2 jam lalu", "Active"),
        Project("2", "mobile-app", "1 hari lalu", "Active"),
        Project("3", "api-backend", "3 hari lalu", "Idle")
    )
    
    LazyColumn(
        modifier = Modifier
            .fillMaxSize()
            .background(BrutalSurface)
            .padding(16.dp),
        verticalArrangement = Arrangement.spacedBy(16.dp)
    ) {
        // Welcome Banner
        item {
            Card(
                modifier = Modifier
                    .fillMaxWidth()
                    .shadow(
                        elevation = 0.dp,
                        shape = RoundedCornerShape(24.dp),
                        ambientColor = BrutalInk,
                        spotColor = BrutalInk
                    ),
                shape = RoundedCornerShape(24.dp),
                colors = CardDefaults.cardColors(
                    containerColor = Color.Transparent
                )
            ) {
                Box(
                    modifier = Modifier
                        .fillMaxWidth()
                        .background(
                            brush = Brush.horizontalGradient(
                                colors = listOf(BrutalBrandStart, BrutalBrandEnd)
                            )
                        )
                        .padding(24.dp)
                ) {
                    Column {
                        Surface(
                            shape = RoundedCornerShape(999.dp),
                            color = BrutalYellow,
                            modifier = Modifier.padding(bottom = 12.dp)
                        ) {
                            Row(
                                modifier = Modifier.padding(horizontal = 12.dp, vertical = 6.dp),
                                horizontalArrangement = Arrangement.spacedBy(6.dp),
                                verticalAlignment = Alignment.CenterVertically
                            ) {
                                Icon(
                                    imageVector = Icons.Filled.Bolt,
                                    contentDescription = null,
                                    modifier = Modifier.size(14.dp),
                                    tint = BrutalInk
                                )
                                Text(
                                    text = "WORKSPACE SIAP",
                                    style = MaterialTheme.typography.labelSmall,
                                    fontWeight = FontWeight.ExtraBold,
                                    color = BrutalInk
                                )
                            }
                        }
                        
                        Text(
                            text = "Halo, $userName.\nMau bikin apa hari ini?",
                            style = MaterialTheme.typography.headlineMedium,
                            fontWeight = FontWeight.Bold,
                            color = BrutalInk
                        )
                        
                        Spacer(modifier = Modifier.height(8.dp))
                        
                        Text(
                            text = "Bangun, perbaiki, dan kembangkan aplikasi cukup lewat percakapan. Agent membaca kode, menulis perubahan, lalu push ke GitHub.",
                            style = MaterialTheme.typography.bodyMedium,
                            color = BrutalInk.copy(alpha = 0.85f)
                        )
                        
                        Spacer(modifier = Modifier.height(20.dp))
                        
                        Row(
                            horizontalArrangement = Arrangement.spacedBy(12.dp)
                        ) {
                            Button(
                                onClick = onNavigateToNewProject,
                                colors = ButtonDefaults.buttonColors(
                                    containerColor = BrutalSurface,
                                    contentColor = BrutalInk
                                ),
                                shape = RoundedCornerShape(12.dp),
                                modifier = Modifier.shadow(
                                    elevation = 4.dp,
                                    shape = RoundedCornerShape(12.dp)
                                )
                            ) {
                                Icon(Icons.Filled.Add, contentDescription = null, modifier = Modifier.size(18.dp))
                                Spacer(Modifier.width(8.dp))
                                Text("Proyek baru")
                            }
                            
                            Button(
                                onClick = onNavigateToChat,
                                colors = ButtonDefaults.buttonColors(
                                    containerColor = BrutalLime,
                                    contentColor = BrutalInk
                                ),
                                shape = RoundedCornerShape(12.dp),
                                modifier = Modifier.shadow(
                                    elevation = 4.dp,
                                    shape = RoundedCornerShape(12.dp)
                                )
                            ) {
                                Icon(Icons.Filled.Chat, contentDescription = null, modifier = Modifier.size(18.dp))
                                Spacer(Modifier.width(8.dp))
                                Text("Mulai chat AI")
                            }
                        }
                    }
                }
            }
        }
        
        // Quota Cards
        item {
            Text(
                text = "Ringkasan",
                style = MaterialTheme.typography.titleMedium,
                fontWeight = FontWeight.Bold,
                color = BrutalInk,
                modifier = Modifier.padding(top = 8.dp)
            )
        }
        
        items(quotaCards) { card ->
            QuotaCardItem(card)
        }
        
        // Recent Projects
        item {
            Text(
                text = "Proyek terbaru",
                style = MaterialTheme.typography.titleMedium,
                fontWeight = FontWeight.Bold,
                color = BrutalInk,
                modifier = Modifier.padding(top = 8.dp)
            )
        }
        
        items(recentProjects) { project ->
            ProjectCardItem(
                project = project,
                onClick = { onProjectClick(project.id) }
            )
        }
    }
}

@Composable
private fun QuotaCardItem(card: QuotaCard) {
    Card(
        modifier = Modifier
            .fillMaxWidth()
            .shadow(
                elevation = 4.dp,
                shape = RoundedCornerShape(16.dp),
                ambientColor = BrutalInk,
                spotColor = BrutalInk
            ),
        shape = RoundedCornerShape(16.dp),
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
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Text(
                    text = card.label,
                    style = MaterialTheme.typography.labelMedium,
                    fontWeight = FontWeight.SemiBold,
                    color = BrutalMuted
                )
                
                Surface(
                    shape = RoundedCornerShape(12.dp),
                    color = card.color,
                    border = androidx.compose.foundation.BorderStroke(2.dp, BrutalInk),
                    modifier = Modifier.size(40.dp)
                ) {
                    Box(
                        contentAlignment = Alignment.Center,
                        modifier = Modifier.fillMaxSize()
                    ) {
                        Icon(
                            imageVector = card.icon,
                            contentDescription = null,
                            tint = BrutalInk,
                            modifier = Modifier.size(20.dp)
                        )
                    }
                }
            }
            
            Spacer(modifier = Modifier.height(12.dp))
            
            Text(
                text = card.value.toString(),
                style = MaterialTheme.typography.displaySmall,
                fontWeight = FontWeight.Bold,
                color = BrutalInk
            )
            
            Text(
                text = card.hint,
                style = MaterialTheme.typography.bodySmall,
                color = BrutalMuted
            )
            
            if (card.total != null) {
                Spacer(modifier = Modifier.height(12.dp))
                LinearProgressIndicator(
                    progress = { card.value.toFloat() / card.total.toFloat() },
                    modifier = Modifier
                        .fillMaxWidth()
                        .height(8.dp),
                    color = card.color,
                    trackColor = BrutalInk.copy(alpha = 0.1f),
                    strokeCap = androidx.compose.ui.graphics.StrokeCap.Round
                )
            }
        }
    }
}

@Composable
private fun ProjectCardItem(
    project: Project,
    onClick: () -> Unit
) {
    Card(
        onClick = onClick,
        modifier = Modifier
            .fillMaxWidth()
            .shadow(
                elevation = 2.dp,
                shape = RoundedCornerShape(16.dp),
                ambientColor = BrutalInk,
                spotColor = BrutalInk
            ),
        shape = RoundedCornerShape(16.dp),
        colors = CardDefaults.cardColors(
            containerColor = BrutalCard
        ),
        border = androidx.compose.foundation.BorderStroke(2.dp, BrutalInk)
    ) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(16.dp),
            verticalAlignment = Alignment.CenterVertically
        ) {
            Surface(
                shape = RoundedCornerShape(12.dp),
                color = BrutalViolet,
                border = androidx.compose.foundation.BorderStroke(2.dp, BrutalInk),
                modifier = Modifier.size(48.dp)
            ) {
                Box(
                    contentAlignment = Alignment.Center,
                    modifier = Modifier.fillMaxSize()
                ) {
                    Icon(
                        imageVector = Icons.Filled.Folder,
                        contentDescription = null,
                        tint = BrutalInk,
                        modifier = Modifier.size(24.dp)
                    )
                }
            }
            
            Spacer(modifier = Modifier.width(16.dp))
            
            Column(modifier = Modifier.weight(1f)) {
                Text(
                    text = project.name,
                    style = MaterialTheme.typography.titleSmall,
                    fontWeight = FontWeight.Bold,
                    color = BrutalInk
                )
                Text(
                    text = project.lastActivity,
                    style = MaterialTheme.typography.bodySmall,
                    color = BrutalMuted
                )
            }
            
            Surface(
                shape = RoundedCornerShape(999.dp),
                color = if (project.status == "Active") BrutalLime else BrutalMuted.copy(alpha = 0.2f),
                modifier = Modifier.padding(start = 8.dp)
            ) {
                Text(
                    text = project.status,
                    style = MaterialTheme.typography.labelSmall,
                    fontWeight = FontWeight.Bold,
                    color = BrutalInk,
                    modifier = Modifier.padding(horizontal = 12.dp, vertical = 4.dp)
                )
            }
        }
    }
}
