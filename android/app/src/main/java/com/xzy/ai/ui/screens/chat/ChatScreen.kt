package com.xzy.ai.ui.screens.chat

import androidx.compose.animation.*
import androidx.compose.animation.core.*
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.lazy.rememberLazyListState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.rotate
import androidx.compose.ui.draw.scale
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.xzy.ai.data.remote.dto.MessageDto
import com.xzy.ai.ui.theme.*
import kotlinx.coroutines.launch

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun ChatScreen(
    onLogout: () -> Unit,
    viewModel: ChatViewModel = androidx.hilt.navigation.compose.hiltViewModel()
) {
    val uiState by viewModel.uiState.collectAsState()
    val scrollState = rememberLazyListState()
    val scope = rememberCoroutineScope()
    
    // Auto scroll to bottom when new message
    LaunchedEffect(uiState.messages.size) {
        if (uiState.messages.isNotEmpty()) {
            scope.launch {
                scrollState.animateScrollToItem(uiState.messages.size - 1)
            }
        }
    }
    
    // Gradient background animation
    val infiniteTransition = rememberInfiniteTransition(label = "gradient")
    val gradientOffset by infiniteTransition.animateFloat(
        initialValue = 0f,
        targetValue = 1000f,
        animationSpec = infiniteRepeatable(
            animation = tween(20000, easing = LinearEasing),
            repeatMode = RepeatMode.Reverse
        ),
        label = "gradientOffset"
    )
    
    Box(
        modifier = Modifier
            .fillMaxSize()
            .background(
                Brush.linearGradient(
                    colors = listOf(
                        DeepPurple.copy(alpha = 0.05f),
                        ElectricBlue.copy(alpha = 0.05f),
                        VibrantPink.copy(alpha = 0.05f)
                    ),
                    start = androidx.compose.ui.geometry.Offset(gradientOffset, gradientOffset),
                    end = androidx.compose.ui.geometry.Offset(
                        gradientOffset + 500f,
                        gradientOffset + 500f
                    )
                )
            )
    ) {
        Scaffold(
            topBar = {
                ChatTopBar(
                    conversationTitle = uiState.selectedConversation?.title ?: "XZY AI Chat",
                    onMenuClick = { viewModel.toggleConversationDrawer() },
                    onLogout = onLogout
                )
            },
            bottomBar = {
                ChatInputBar(
                    message = uiState.currentMessage,
                    onMessageChange = viewModel::onMessageChange,
                    onSend = viewModel::sendMessage,
                    isSending = uiState.isSending,
                    enabled = uiState.selectedConversation != null
                )
            },
            containerColor = Color.Transparent
        ) { paddingValues ->
            Box(
                modifier = Modifier
                    .fillMaxSize()
                    .padding(paddingValues)
            ) {
                // Messages List
                if (uiState.messages.isEmpty()) {
                    EmptyStateView(onNewChat = viewModel::createNewConversation)
                } else {
                    MessagesList(
                        messages = uiState.messages,
                        scrollState = scrollState,
                        modifier = Modifier.fillMaxSize()
                    )
                }
                
                // Conversation Drawer
                AnimatedVisibility(
                    visible = uiState.showConversationDrawer,
                    enter = slideInHorizontally(initialOffsetX = { -it }) + fadeIn(),
                    exit = slideOutHorizontally(targetOffsetX = { -it }) + fadeOut()
                ) {
                    ConversationDrawer(
                        conversations = uiState.conversations,
                        selectedConversationId = uiState.selectedConversation?.id,
                        onConversationSelect = viewModel::selectConversation,
                        onNewConversation = viewModel::createNewConversation,
                        onDeleteConversation = viewModel::deleteConversation,
                        onClose = { viewModel.toggleConversationDrawer() }
                    )
                }
                
                // Loading Overlay
                if (uiState.isLoading) {
                    LoadingOverlay()
                }
                
                // Error Snackbar
                uiState.error?.let { error ->
                    LaunchedEffect(error) {
                        // Show error for 3 seconds then clear
                        kotlinx.coroutines.delay(3000)
                        viewModel.clearError()
                    }
                    
                    Box(
                        modifier = Modifier
                            .fillMaxSize()
                            .padding(16.dp),
                        contentAlignment = Alignment.BottomCenter
                    ) {
                        ErrorCard(error)
                    }
                }
            }
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun ChatTopBar(
    conversationTitle: String,
    onMenuClick: () -> Unit,
    onLogout: () -> Unit
) {
    var showMenu by remember { mutableStateOf(false) }
    
    TopAppBar(
        title = {
            Row(
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.spacedBy(12.dp)
            ) {
                // Animated Logo
                val infiniteTransition = rememberInfiniteTransition(label = "logo")
                val rotation by infiniteTransition.animateFloat(
                    initialValue = 0f,
                    targetValue = 360f,
                    animationSpec = infiniteRepeatable(
                        animation = tween(3000, easing = LinearEasing),
                        repeatMode = RepeatMode.Restart
                    ),
                    label = "rotation"
                )
                
                Box(
                    modifier = Modifier
                        .size(40.dp)
                        .clip(CircleShape)
                        .background(
                            Brush.linearGradient(
                                colors = listOf(ElectricBlue, VibrantPink)
                            )
                        )
                        .rotate(rotation),
                    contentAlignment = Alignment.Center
                ) {
                    Icon(
                        imageVector = Icons.Default.Star,
                        contentDescription = null,
                        tint = Color.White,
                        modifier = Modifier.size(24.dp)
                    )
                }
                
                Column {
                    Text(
                        text = "XZY AI",
                        fontSize = 18.sp,
                        fontWeight = FontWeight.Bold,
                        color = MaterialTheme.colorScheme.onSurface
                    )
                    Text(
                        text = conversationTitle,
                        fontSize = 12.sp,
                        color = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.6f),
                        maxLines = 1
                    )
                }
            }
        },
        navigationIcon = {
            IconButton(onClick = onMenuClick) {
                Icon(
                    imageVector = Icons.Default.Menu,
                    contentDescription = "Menu",
                    tint = ElectricBlue
                )
            }
        },
        actions = {
            Box {
                IconButton(onClick = { showMenu = true }) {
                    Icon(
                        imageVector = Icons.Default.MoreVert,
                        contentDescription = "More",
                        tint = ElectricBlue
                    )
                }
                
                DropdownMenu(
                    expanded = showMenu,
                    onDismissRequest = { showMenu = false }
                ) {
                    DropdownMenuItem(
                        text = { Text("Logout") },
                        onClick = {
                            showMenu = false
                            onLogout()
                        },
                        leadingIcon = {
                            Icon(Icons.Default.ExitToApp, contentDescription = null)
                        }
                    )
                }
            }
        },
        colors = TopAppBarDefaults.topAppBarColors(
            containerColor = MaterialTheme.colorScheme.surface.copy(alpha = 0.95f)
        )
    )
}
