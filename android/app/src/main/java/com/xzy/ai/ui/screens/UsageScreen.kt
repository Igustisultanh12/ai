package com.xzy.ai.ui.screens

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
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

@Composable
fun UsageScreen() {
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
                    text = "Token Usage",
                    style = MaterialTheme.typography.headlineMedium,
                    fontWeight = FontWeight.Bold,
                    color = BrutalInk
                )
                Text(
                    text = "Pantau penggunaan token Anda",
                    style = MaterialTheme.typography.bodyMedium,
                    color = BrutalMuted
                )
            }
        }
        
        Column(
            modifier = Modifier.padding(16.dp),
            verticalArrangement = Arrangement.spacedBy(12.dp)
        ) {
            // Usage overview card
            Card(
                modifier = Modifier
                    .fillMaxWidth()
                    .shadow(
                        elevation = 4.dp,
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
                        text = "Token bulan ini",
                        style = MaterialTheme.typography.titleMedium,
                        fontWeight = FontWeight.Bold,
                        color = BrutalInk
                    )
                    
                    Spacer(modifier = Modifier.height(16.dp))
                    
                    Text(
                        text = "124,567",
                        style = MaterialTheme.typography.displaySmall,
                        fontWeight = FontWeight.Bold,
                        color = BrutalInk
                    )
                    
                    Text(
                        text = "dari 500,000 token",
                        style = MaterialTheme.typography.bodyMedium,
                        color = BrutalMuted
                    )
                    
                    Spacer(modifier = Modifier.height(16.dp))
                    
                    // Progress bar
                    Column {
                        LinearProgressIndicator(
                            progress = { 0.249f },
                            modifier = Modifier
                                .fillMaxWidth()
                                .height(12.dp),
                            color = BrutalLime,
                            trackColor = BrutalMuted.copy(alpha = 0.2f),
                            strokeCap = androidx.compose.ui.graphics.StrokeCap.Round,
                        )
                        
                        Spacer(modifier = Modifier.height(8.dp))
                        
                        Row(
                            modifier = Modifier.fillMaxWidth(),
                            horizontalArrangement = Arrangement.SpaceBetween
                        ) {
                            Text(
                                text = "25% terpakai",
                                style = MaterialTheme.typography.labelSmall,
                                color = BrutalMuted
                            )
                            Text(
                                text = "375,433 tersisa",
                                style = MaterialTheme.typography.labelSmall,
                                fontWeight = FontWeight.Bold,
                                color = BrutalInk
                            )
                        }
                    }
                }
            }
            
            // Stats grid
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.spacedBy(12.dp)
            ) {
                UsageStatCard(
                    title = "Hari ini",
                    value = "4,231",
                    icon = Icons.Filled.Today,
                    color = BrutalLime,
                    modifier = Modifier.weight(1f)
                )
                
                UsageStatCard(
                    title = "Rata-rata",
                    value = "5,902",
                    icon = Icons.Filled.TrendingUp,
                    color = BrutalViolet,
                    modifier = Modifier.weight(1f)
                )
            }
            
            // Recent activity
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
                        horizontalArrangement = Arrangement.SpaceBetween,
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Text(
                            text = "Aktivitas Terbaru",
                            style = MaterialTheme.typography.titleMedium,
                            fontWeight = FontWeight.Bold,
                            color = BrutalInk
                        )
                        
                        Surface(
                            shape = RoundedCornerShape(999.dp),
                            color = BrutalYellow
                        ) {
                            Row(
                                modifier = Modifier.padding(horizontal = 12.dp, vertical = 6.dp),
                                horizontalArrangement = Arrangement.spacedBy(6.dp),
                                verticalAlignment = Alignment.CenterVertically
                            ) {
                                Icon(
                                    Icons.Filled.Bolt,
                                    contentDescription = null,
                                    modifier = Modifier.size(14.dp),
                                    tint = BrutalInk
                                )
                                Text(
                                    text = "7 hari terakhir",
                                    style = MaterialTheme.typography.labelSmall,
                                    fontWeight = FontWeight.Bold,
                                    color = BrutalInk
                                )
                            }
                        }
                    }
                    
                    Spacer(modifier = Modifier.height(16.dp))
                    
                    // Activity items
                    UsageActivityItem(
                        date = "Hari ini",
                        tokens = 4231,
                        percentage = 85f
                    )
                    Spacer(modifier = Modifier.height(12.dp))
                    
                    UsageActivityItem(
                        date = "Kemarin",
                        tokens = 6892,
                        percentage = 65f
                    )
                    Spacer(modifier = Modifier.height(12.dp))
                    
                    UsageActivityItem(
                        date = "2 hari lalu",
                        tokens = 5341,
                        percentage = 55f
                    )
                    Spacer(modifier = Modifier.height(12.dp))
                    
                    UsageActivityItem(
                        date = "3 hari lalu",
                        tokens = 7123,
                        percentage = 70f
                    )
                }
            }
        }
    }
}

@Composable
private fun UsageStatCard(
    title: String,
    value: String,
    icon: ImageVector,
    color: androidx.compose.ui.graphics.Color,
    modifier: Modifier = Modifier
) {
    Card(
        modifier = modifier
            .shadow(
                elevation = 3.dp,
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
                .padding(16.dp)
        ) {
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.Top
            ) {
                Text(
                    text = title,
                    style = MaterialTheme.typography.labelMedium,
                    color = BrutalMuted
                )
                
                Surface(
                    shape = RoundedCornerShape(10.dp),
                    color = color,
                    border = androidx.compose.foundation.BorderStroke(2.dp, BrutalInk),
                    modifier = Modifier.size(32.dp)
                ) {
                    Box(
                        contentAlignment = Alignment.Center,
                        modifier = Modifier.fillMaxSize()
                    ) {
                        Icon(
                            icon,
                            contentDescription = null,
                            modifier = Modifier.size(18.dp),
                            tint = BrutalInk
                        )
                    }
                }
            }
            
            Spacer(modifier = Modifier.height(8.dp))
            
            Text(
                text = value,
                style = MaterialTheme.typography.headlineSmall,
                fontWeight = FontWeight.Bold,
                color = BrutalInk
            )
        }
    }
}

@Composable
private fun UsageActivityItem(
    date: String,
    tokens: Int,
    percentage: Float
) {
    Column {
        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.SpaceBetween,
            verticalAlignment = Alignment.CenterVertically
        ) {
            Text(
                text = date,
                style = MaterialTheme.typography.bodyMedium,
                color = BrutalInk
            )
            Text(
                text = "$tokens tokens",
                style = MaterialTheme.typography.bodyMedium,
                fontWeight = FontWeight.Bold,
                color = BrutalInk
            )
        }
        
        Spacer(modifier = Modifier.height(6.dp))
        
        LinearProgressIndicator(
            progress = { percentage / 100f },
            modifier = Modifier
                .fillMaxWidth()
                .height(8.dp),
            color = BrutalViolet,
            trackColor = BrutalMuted.copy(alpha = 0.2f),
            strokeCap = androidx.compose.ui.graphics.StrokeCap.Round,
        )
    }
}
