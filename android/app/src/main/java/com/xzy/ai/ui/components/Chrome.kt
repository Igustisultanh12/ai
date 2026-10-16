package com.xzy.ai.ui.components

import androidx.compose.animation.AnimatedVisibility
import androidx.compose.animation.core.Spring
import androidx.compose.animation.core.spring
import androidx.compose.animation.fadeIn
import androidx.compose.animation.fadeOut
import androidx.compose.animation.scaleIn
import androidx.compose.animation.slideInVertically
import androidx.compose.animation.slideOutVertically
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.BoxScope
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.RowScope
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.rounded.ArrowBack
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.compositionLocalOf
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.setValue
import androidx.compose.runtime.staticCompositionLocalOf
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.navigation.NavHostController
import com.xzy.ai.ui.theme.Brand
import com.xzy.ai.ui.theme.X
import kotlinx.coroutines.delay

val LocalNav = staticCompositionLocalOf<NavHostController> { error("no nav") }
val LocalToast = compositionLocalOf<(String) -> Unit> { {} }

class ToastState {
    var message by mutableStateOf<String?>(null)
    var key by mutableIntStateOf(0)
    fun show(text: String) {
        message = text
        key++
    }
}

/** Short message that pops up above the bottom bar and hides itself. */
@Composable
fun BoxScope.ToastHost(state: ToastState) {
    var visible by androidx.compose.runtime.remember { mutableStateOf(false) }
    LaunchedEffect(state.key) {
        if (state.message != null) {
            visible = true
            delay(2800)
            visible = false
        }
    }
    AnimatedVisibility(
        visible,
        modifier = Modifier.align(Alignment.BottomCenter).navigationBarsPadding().padding(bottom = 96.dp, start = 20.dp, end = 20.dp),
        enter = slideInVertically(spring(dampingRatio = Spring.DampingRatioMediumBouncy)) { it * 2 } + fadeIn(),
        exit = slideOutVertically { it * 2 } + fadeOut(),
    ) {
        Row(
            Modifier
                .clip(RoundedCornerShape(16.dp))
                .background(Brand.Ink)
                .border(2.dp, Brand.Yellow, RoundedCornerShape(16.dp))
                .padding(horizontal = 16.dp, vertical = 12.dp),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            Box(Modifier.size(8.dp).clip(CircleShape).background(Brand.gradient))
            Spacer(Modifier.width(10.dp))
            Text(state.message.orEmpty(), color = Color.White, style = MaterialTheme.typography.bodyMedium, fontWeight = FontWeight.SemiBold)
        }
    }
}

/** Small square icon button with the brutal outline. */
@Composable
fun IconSquare(icon: ImageVector, modifier: Modifier = Modifier, bg: Color = X.c.card, tint: Color = X.c.ink, size: Dp = 42.dp, badge: Int = 0, onClick: () -> Unit) {
    Box(modifier) {
        BrutalBox(shape = RoundedCornerShape(13.dp), color = bg, shadow = 3.dp, contentPadding = 0.dp, onClick = onClick) {
            Box(Modifier.size(size), contentAlignment = Alignment.Center) {
                Icon(icon, null, tint = tint, modifier = Modifier.size(size * 0.5f))
            }
        }
        AnimatedVisibility(badge > 0, modifier = Modifier.align(Alignment.TopEnd), enter = scaleIn(spring(dampingRatio = 0.4f)) + fadeIn(), exit = fadeOut()) {
            Box(
                Modifier
                    .padding(top = 0.dp)
                    .size(19.dp)
                    .clip(CircleShape)
                    .background(Brand.Rose)
                    .border(2.dp, Brand.Ink, CircleShape),
                contentAlignment = Alignment.Center,
            ) {
                Text(if (badge > 9) "9+" else badge.toString(), color = Color.White, fontSize = 9.sp, fontWeight = FontWeight.Black)
            }
        }
    }
}

@Composable
fun ScreenHeader(
    title: String,
    subtitle: String? = null,
    onBack: (() -> Unit)? = null,
    actions: @Composable RowScope.() -> Unit = {},
) {
    Row(
        Modifier.fillMaxWidth().statusBarsPadding().padding(start = 16.dp, end = 16.dp, top = 10.dp, bottom = 8.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        if (onBack != null) {
            IconSquare(Icons.AutoMirrored.Rounded.ArrowBack, onClick = onBack)
            Spacer(Modifier.width(12.dp))
        }
        Column(Modifier.weight(1f)) {
            Text(title, style = MaterialTheme.typography.headlineSmall, color = X.c.ink, maxLines = 1, overflow = TextOverflow.Ellipsis)
            if (subtitle != null) Text(subtitle, style = MaterialTheme.typography.bodySmall, color = X.c.muted, maxLines = 1, overflow = TextOverflow.Ellipsis)
        }
        Row(horizontalArrangement = Arrangement.spacedBy(8.dp), verticalAlignment = Alignment.CenterVertically, content = actions)
    }
}

/** "2 menit lalu" style labels from ISO timestamps. */
fun relativeTime(iso: String): String {
    val t = parseIso(iso) ?: return ""
    return relativeMillis(t)
}

fun relativeMillis(t: Long): String {
    val diff = (System.currentTimeMillis() - t) / 1000
    return when {
        diff < 45 -> "baru saja"
        diff < 3600 -> "${diff / 60} menit lalu"
        diff < 86400 -> "${diff / 3600} jam lalu"
        diff < 7 * 86400 -> "${diff / 86400} hari lalu"
        else -> java.text.SimpleDateFormat("d MMM yyyy", java.util.Locale("id", "ID")).format(java.util.Date(t))
    }
}

fun parseIso(iso: String): Long? {
    if (iso.isBlank()) return null
    return try {
        java.time.OffsetDateTime.parse(iso).toInstant().toEpochMilli()
    } catch (_: Exception) {
        try {
            java.time.LocalDateTime.parse(iso.replace(" ", "T").substringBefore(".")).atZone(java.time.ZoneId.systemDefault()).toInstant().toEpochMilli()
        } catch (_: Exception) {
            null
        }
    }
}

fun formatBytes(b: Long): String = when {
    b < 1024 -> "$b B"
    b < 1024 * 1024 -> "%.1f KB".format(b / 1024f)
    else -> "%.1f MB".format(b / 1024f / 1024f)
}
