package com.xzy.ai.ui.screens

import androidx.compose.animation.AnimatedContent
import androidx.compose.animation.AnimatedVisibility
import androidx.compose.animation.core.Spring
import androidx.compose.animation.core.animateFloatAsState
import androidx.compose.animation.core.spring
import androidx.compose.animation.core.tween
import androidx.compose.animation.expandHorizontally
import androidx.compose.animation.fadeIn
import androidx.compose.animation.fadeOut
import androidx.compose.animation.shrinkHorizontally
import androidx.compose.animation.slideInHorizontally
import androidx.compose.animation.slideOutHorizontally
import androidx.compose.animation.togetherWith
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.interaction.MutableInteractionSource
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.rounded.Chat
import androidx.compose.material.icons.rounded.Folder
import androidx.compose.material.icons.rounded.Home
import androidx.compose.material.icons.rounded.Person
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.xzy.ai.ui.components.AuroraBackground
import com.xzy.ai.ui.components.BrutalBox
import com.xzy.ai.ui.components.DotGrid
import com.xzy.ai.ui.theme.Brand
import com.xzy.ai.ui.theme.X

private data class Tab(val label: String, val icon: ImageVector)

private val tabs = listOf(
    Tab("Beranda", Icons.Rounded.Home),
    Tab("Chat", Icons.AutoMirrored.Rounded.Chat),
    Tab("Proyek", Icons.Rounded.Folder),
    Tab("Akun", Icons.Rounded.Person),
)

@Composable
fun MainScreen() {
    var tab by rememberSaveable { mutableIntStateOf(0) }
    Box(Modifier.fillMaxSize()) {
        AuroraBackground(intensity = 0.6f)
        DotGrid()
        AnimatedContent(
            targetState = tab,
            transitionSpec = {
                val dir = if (targetState > initialState) 1 else -1
                (slideInHorizontally(tween(320)) { it / 6 * dir } + fadeIn(tween(320))) togetherWith
                    (slideOutHorizontally(tween(220)) { -it / 6 * dir } + fadeOut(tween(200)))
            },
            label = "tabs",
        ) { t ->
            when (t) {
                0 -> HomeTab(onTab = { tab = it })
                1 -> ChatsTab()
                2 -> ProjectsTab()
                else -> AccountTab()
            }
        }
        BottomBar(tab, Modifier.align(Alignment.BottomCenter)) { tab = it }
    }
}

@Composable
private fun BottomBar(selected: Int, modifier: Modifier, onSelect: (Int) -> Unit) {
    BrutalBox(
        modifier.navigationBarsPadding().padding(horizontal = 16.dp, vertical = 10.dp).fillMaxWidth(),
        shape = RoundedCornerShape(24.dp),
        color = if (X.c.isDark) Color(0xFF15131F) else Color.White,
        shadow = 5.dp,
        contentPadding = 7.dp,
    ) {
        Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically) {
            tabs.forEachIndexed { i, t ->
                val active = i == selected
                val scale by animateFloatAsState(if (active) 1f else 0.92f, spring(dampingRatio = Spring.DampingRatioMediumBouncy), label = "s$i")
                Row(
                    Modifier
                        .graphicsLayer { scaleX = scale; scaleY = scale }
                        .clip(RoundedCornerShape(18.dp))
                        .then(if (active) Modifier.background(Brand.gradient) else Modifier)
                        .clickable(remember { MutableInteractionSource() }, null) { onSelect(i) }
                        .padding(horizontal = 14.dp, vertical = 11.dp),
                    verticalAlignment = Alignment.CenterVertically,
                ) {
                    Icon(t.icon, t.label, tint = if (active) Color.White else X.c.muted, modifier = Modifier.size(22.dp))
                    AnimatedVisibility(active, enter = expandHorizontally(spring(dampingRatio = 0.7f)) + fadeIn(), exit = shrinkHorizontally() + fadeOut()) {
                        Row {
                            Spacer(Modifier.width(7.dp))
                            Text(t.label, color = Color.White, fontWeight = FontWeight.ExtraBold, fontSize = 13.sp, maxLines = 1)
                        }
                    }
                }
            }
        }
    }
}
