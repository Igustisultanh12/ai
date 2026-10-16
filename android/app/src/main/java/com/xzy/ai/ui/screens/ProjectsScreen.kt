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
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import com.xzy.ai.ui.theme.*

data class ProjectDetail(
    val id: String,
    val name: String,
    val description: String,
    val lastActivity: String,
    val status: ProjectStatus,
    val commits: Int,
    val messages: Int,
    val language: String
)

enum class ProjectStatus {
    ACTIVE, IDLE, ARCHIVED
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun ProjectsScreen(
    onProjectClick: (String) -> Unit = {},
    onNewProjectClick: () -> Unit = {}
) {
    val projects = remember {
        listOf(
            ProjectDetail(
                id = "1",
                name = "xzy-ai-web",
                description = "Vue.js + Laravel application with AI chat",
                lastActivity = "2 jam lalu",
                status = ProjectStatus.ACTIVE,
                commits = 142,
                messages = 89,
                language = "Vue.js"
            ),
            ProjectDetail(
                id = "2",
                name = "mobile-app",
                description = "Android app with Jetpack Compose",
                lastActivity = "1 hari lalu",
                status = ProjectStatus.ACTIVE,
                commits = 67,
                messages = 34,
                language = "Kotlin"
            ),
            ProjectDetail(
                id = "3",
                name = "api-backend",
                description = "REST API with Express.js",
                lastActivity = "3 hari lalu",
                status = ProjectStatus.IDLE,
                commits = 92,
                messages = 51,
                language = "Node.js"
            ),
            ProjectDetail(
                id = "4",
                name = "data-pipeline",
                description = "ETL pipeline with Python",
                lastActivity = "1 minggu lalu",
                status = ProjectStatus.IDLE,
                commits = 45,
                messages = 23,
                language = "Python"
            )
        )
    }
    
    var selectedFilter by remember { mutableStateOf("All") }
    val filters = listOf("All", "Active", "Idle", "Archived")
    
    Column(
        modifier = Modifier
            .fillMaxSize()
            .background(BrutalSurface)
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
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Column {
                        Text(
                            text = "Projects",
                            style = MaterialTheme.typography.headlineMedium,
                            fontWeight = FontWeight.Bold,
                            color = BrutalInk
                        )
                        Text(
                            text = "${projects.size} workspace aktif",
                            style = MaterialTheme.typography.bodyMedium,
                            color = BrutalMuted
                        )
                    }
                    
                    Button(
                        onClick = onNewProjectClick,
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
                        Icon(
                            Icons.Filled.Add,
                            contentDescription = null,
                            modifier = Modifier.size(18.dp)
                        )
                        Spacer(Modifier.width(8.dp))
                        Text("Baru")
                    }
                }
                
                Spacer(modifier = Modifier.height(16.dp))
                
                // Filter chips
                Row(
                    horizontalArrangement = Arrangement.spacedBy(8.dp)
                ) {
                    filters.forEach { filter ->
                        FilterChip(
                            selected = selectedFilter == filter,
                            onClick = { selectedFilter = filter },
                            label = { Text(filter) },
                            colors = FilterChipDefaults.filterChipColors(
                                selectedContainerColor = BrutalLime,
                                selectedLabelColor = BrutalInk,
                                containerColor = BrutalCard,
                                labelColor = BrutalMuted
                            ),
                            border = FilterChipDefaults.filterChipBorder(
                                enabled = true,
                                selected = selectedFilter == filter,
                                borderColor = BrutalInk,
                                selectedBorderColor = BrutalInk,
                                borderWidth = 2.dp,
                                selectedBorderWidth = 2.dp
                            )
                        )
                    }
                }
            }
        }
        
        // Project list
        LazyColumn(
            modifier = Modifier
                .fillMaxSize()
                .padding(16.dp),
            verticalArrangement = Arrangement.spacedBy(12.dp)
        ) {
            items(projects.filter {
                selectedFilter == "All" || it.status.name.equals(selectedFilter, ignoreCase = true)
            }) { project ->
                ProjectItem(
                    project = project,
                    onClick = { onProjectClick(project.id) }
                )
            }
        }
    }
}

@Composable
private fun ProjectItem(
    project: ProjectDetail,
    onClick: () -> Unit
) {
    Card(
        onClick = onClick,
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
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.Top
            ) {
                Row(
                    verticalAlignment = Alignment.CenterVertically,
                    horizontalArrangement = Arrangement.spacedBy(12.dp)
                ) {
                    Surface(
                        shape = RoundedCornerShape(14.dp),
                        color = when (project.status) {
                            ProjectStatus.ACTIVE -> BrutalLime
                            ProjectStatus.IDLE -> BrutalViolet
                            ProjectStatus.ARCHIVED -> BrutalMuted.copy(alpha = 0.3f)
                        },
                        border = androidx.compose.foundation.BorderStroke(2.dp, BrutalInk),
                        modifier = Modifier.size(56.dp)
                    ) {
                        Box(
                            contentAlignment = Alignment.Center,
                            modifier = Modifier.fillMaxSize()
                        ) {
                            Icon(
                                imageVector = Icons.Filled.Folder,
                                contentDescription = null,
                                tint = BrutalInk,
                                modifier = Modifier.size(28.dp)
                            )
                        }
                    }
                    
                    Column {
                        Text(
                            text = project.name,
                            style = MaterialTheme.typography.titleMedium,
                            fontWeight = FontWeight.Bold,
                            color = BrutalInk
                        )
                        Text(
                            text = project.language,
                            style = MaterialTheme.typography.bodySmall,
                            color = BrutalMuted
                        )
                    }
                }
                
                Surface(
                    shape = RoundedCornerShape(999.dp),
                    color = when (project.status) {
                        ProjectStatus.ACTIVE -> BrutalLime
                        ProjectStatus.IDLE -> BrutalYellow
                        ProjectStatus.ARCHIVED -> BrutalMuted.copy(alpha = 0.2f)
                    }
                ) {
                    Text(
                        text = project.status.name,
                        style = MaterialTheme.typography.labelSmall,
                        fontWeight = FontWeight.Bold,
                        color = BrutalInk,
                        modifier = Modifier.padding(horizontal = 12.dp, vertical = 6.dp)
                    )
                }
            }
            
            Spacer(modifier = Modifier.height(12.dp))
            
            Text(
                text = project.description,
                style = MaterialTheme.typography.bodyMedium,
                color = BrutalMuted
            )
            
            Spacer(modifier = Modifier.height(16.dp))
            
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.spacedBy(16.dp)
            ) {
                ProjectStat(
                    icon = Icons.Filled.Commit,
                    value = project.commits.toString(),
                    label = "commits"
                )
                ProjectStat(
                    icon = Icons.Filled.Chat,
                    value = project.messages.toString(),
                    label = "messages"
                )
            }
            
            Spacer(modifier = Modifier.height(12.dp))
            
            Row(
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.spacedBy(8.dp)
            ) {
                Icon(
                    Icons.Filled.AccessTime,
                    contentDescription = null,
                    modifier = Modifier.size(14.dp),
                    tint = BrutalMuted
                )
                Text(
                    text = "Terakhir aktif ${project.lastActivity}",
                    style = MaterialTheme.typography.bodySmall,
                    color = BrutalMuted
                )
            }
        }
    }
}

@Composable
private fun ProjectStat(
    icon: ImageVector,
    value: String,
    label: String
) {
    Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(6.dp)
    ) {
        Icon(
            icon,
            contentDescription = null,
            modifier = Modifier.size(16.dp),
            tint = BrutalMuted
        )
        Text(
            text = value,
            style = MaterialTheme.typography.labelMedium,
            fontWeight = FontWeight.Bold,
            color = BrutalInk
        )
        Text(
            text = label,
            style = MaterialTheme.typography.labelSmall,
            color = BrutalMuted
        )
    }
}
