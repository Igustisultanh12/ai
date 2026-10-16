package com.xzy.ai.ui.screens

import androidx.compose.animation.AnimatedVisibility
import androidx.compose.animation.core.LinearEasing
import androidx.compose.animation.core.RepeatMode
import androidx.compose.animation.core.animateFloat
import androidx.compose.animation.core.infiniteRepeatable
import androidx.compose.animation.core.rememberInfiniteTransition
import androidx.compose.animation.core.tween
import androidx.compose.animation.expandVertically
import androidx.compose.animation.fadeIn
import androidx.compose.animation.fadeOut
import androidx.compose.animation.shrinkVertically
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.LazyRow
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.rounded.ArrowForward
import androidx.compose.material.icons.automirrored.rounded.Chat
import androidx.compose.material.icons.rounded.AutoAwesome
import androidx.compose.material.icons.rounded.CreateNewFolder
import androidx.compose.material.icons.rounded.Insights
import androidx.compose.material.icons.rounded.Notifications
import androidx.compose.material.icons.rounded.SystemUpdate
import androidx.compose.material.icons.rounded.Code
import androidx.compose.material.icons.rounded.Folder
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.material3.pulltorefresh.PullToRefreshBox
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.xzy.ai.data.Agent
import com.xzy.ai.data.Conversation
import com.xzy.ai.data.Notifier
import com.xzy.ai.data.Project
import com.xzy.ai.data.Repo
import com.xzy.ai.data.Session
import com.xzy.ai.data.Updater
import com.xzy.ai.data.Usage
import com.xzy.ai.ui.components.AnimatedBar
import com.xzy.ai.ui.components.Avatar
import com.xzy.ai.ui.components.BrutalBox
import com.xzy.ai.ui.components.Chip
import com.xzy.ai.ui.components.CountUp
import com.xzy.ai.ui.components.ErrorBanner
import com.xzy.ai.ui.components.GradientSpinner
import com.xzy.ai.ui.components.IconSquare
import com.xzy.ai.ui.components.IconTile
import com.xzy.ai.ui.components.LocalNav
import com.xzy.ai.ui.components.SectionTitle
import com.xzy.ai.ui.components.Shimmer
import com.xzy.ai.ui.components.enterAnim
import com.xzy.ai.ui.components.relativeTime
import com.xzy.ai.ui.theme.Brand
import com.xzy.ai.ui.theme.X
import java.util.Calendar

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun HomeTab(onTab: (Int) -> Unit) {
    val nav = LocalNav.current
    val newChat = rememberNewChat()
    var usage by remember { mutableStateOf<Usage?>(null) }
    var chats by remember { mutableStateOf<List<Conversation>?>(null) }
    var projects by remember { mutableStateOf<List<Project>?>(null) }
    var error by remember { mutableStateOf<String?>(null) }
    var refreshing by remember { mutableStateOf(false) }
    var reload by remember { mutableIntStateOf(0) }
    val user = Session.user

    LaunchedEffect(reload) {
        error = null
        try { usage = Repo.usage() } catch (e: Exception) { error = Session.fail(e) }
        try { chats = Repo.conversations().items.take(4) } catch (e: Exception) { chats = chats ?: emptyList(); error = Session.fail(e) }
        try { projects = Repo.projects().items.take(8) } catch (e: Exception) { projects = projects ?: emptyList() }
        refreshing = false
    }

    PullToRefreshBox(isRefreshing = refreshing, onRefresh = { refreshing = true; reload++ }, modifier = Modifier.fillMaxSize()) {
        LazyColumn(
            Modifier.fillMaxSize(),
            contentPadding = PaddingValues(start = 18.dp, end = 18.dp, bottom = BOTTOM_SPACE.dp),
            verticalArrangement = Arrangement.spacedBy(18.dp),
        ) {
            item("head") {
                Row(Modifier.fillMaxWidth().statusBarsPadding().padding(top = 12.dp).enterAnim(0), verticalAlignment = Alignment.CenterVertically) {
                    Avatar(user?.initials ?: "?", 46.dp)
                    Spacer(Modifier.width(12.dp))
                    Column(Modifier.weight(1f)) {
                        Text(greeting(), style = MaterialTheme.typography.bodySmall, color = X.c.muted)
                        Text(user?.name?.substringBefore(" ") ?: "Kamu", style = MaterialTheme.typography.headlineSmall, color = X.c.ink, maxLines = 1, overflow = TextOverflow.Ellipsis)
                    }
                    IconSquare(Icons.Rounded.Notifications, badge = Notifier.unread) { nav.navigate("notifications") }
                }
            }

            val update = Updater.available
            if (update != null) item("update") {
                BrutalBox(Modifier.fillMaxWidth().enterAnim(1), color = Brand.Lime, onClick = { Updater.sheetOpen = true }, contentPadding = 14.dp) {
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        IconTile(Icons.Rounded.SystemUpdate, Color.White)
                        Spacer(Modifier.width(12.dp))
                        Column(Modifier.weight(1f)) {
                            Text("Pembaruan tersedia", fontWeight = FontWeight.Black, color = Brand.Ink)
                            Text("Versi ${update.tag.removePrefix("android-v")} siap dipasang", style = MaterialTheme.typography.bodySmall, color = Brand.Ink.copy(alpha = 0.75f))
                        }
                        Icon(Icons.AutoMirrored.Rounded.ArrowForward, null, tint = Brand.Ink)
                    }
                }
            }

            item("running") {
                val running = Agent.activeRuns()
                AnimatedVisibility(running.isNotEmpty(), enter = expandVertically() + fadeIn(), exit = shrinkVertically() + fadeOut()) {
                    Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
                        running.forEach { r ->
                            BrutalBox(Modifier.fillMaxWidth(), color = X.c.card, onClick = { nav.navigate("chat/${r.conversationId}") }, contentPadding = 14.dp) {
                                Row(verticalAlignment = Alignment.CenterVertically) {
                                    GradientSpinner(26.dp, 3.dp)
                                    Spacer(Modifier.width(12.dp))
                                    Column(Modifier.weight(1f)) {
                                        Text("Agen sedang bekerja", fontWeight = FontWeight.Black, color = X.c.ink)
                                        Text(r.tools.lastOrNull()?.label ?: r.status ?: r.title.ifBlank { "Menyiapkan jawaban..." }, style = MaterialTheme.typography.bodySmall, color = X.c.muted, maxLines = 1, overflow = TextOverflow.Ellipsis)
                                    }
                                    Chip("${r.tools.count { it.status == "done" }} aksi", color = Brand.Cyan)
                                }
                            }
                        }
                    }
                }
            }

            item("hero") { Hero(Modifier.enterAnim(2), busy = newChat.busy()) { newChat.start(null, null) } }

            item("quota") {
                Column(Modifier.enterAnim(3)) {
                    SectionTitle("Kuota token") {
                        Text("Detail", color = Brand.Violet2, fontWeight = FontWeight.Bold, modifier = Modifier.clip(RoundedCornerShape(8.dp)).clickable { nav.navigate("usage") }.padding(4.dp))
                    }
                    Spacer(Modifier.height(10.dp))
                    val u = usage
                    if (u == null) {
                        Row(horizontalArrangement = Arrangement.spacedBy(12.dp)) {
                            Shimmer(Modifier.weight(1f), 130.dp)
                            Shimmer(Modifier.weight(1f), 130.dp)
                        }
                    } else {
                        Row(horizontalArrangement = Arrangement.spacedBy(12.dp)) {
                            QuotaCard("Sisa hari ini", u.remainingDaily, u.dailyQuota, u.dailyPercent, Brand.Yellow, Modifier.weight(1f)) { nav.navigate("usage") }
                            QuotaCard("Sisa bulan ini", u.remainingMonthly, u.monthlyQuota, u.monthlyPercent, Brand.Cyan, Modifier.weight(1f)) { nav.navigate("usage") }
                        }
                    }
                }
            }

            item("quick") {
                Row(Modifier.fillMaxWidth().enterAnim(4), horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                    QuickAction("Chat baru", Icons.Rounded.AutoAwesome, Brand.Yellow, Modifier.weight(1f)) { newChat.start(null, null) }
                    QuickAction("Proyek", Icons.Rounded.CreateNewFolder, Brand.Lime, Modifier.weight(1f)) { onTab(2) }
                    QuickAction("Pemakaian", Icons.Rounded.Insights, Brand.Cyan, Modifier.weight(1f)) { nav.navigate("usage") }
                }
            }

            error?.let { item("err") { ErrorBanner(it) } }

            item("chatsTitle") {
                SectionTitle("Chat terakhir", Modifier.enterAnim(5)) {
                    Text("Semua", color = Brand.Violet2, fontWeight = FontWeight.Bold, modifier = Modifier.clip(RoundedCornerShape(8.dp)).clickable { onTab(1) }.padding(4.dp))
                }
            }
            val cs = chats
            if (cs == null) {
                items(3) { Shimmer(height = 68.dp) }
            } else if (cs.isEmpty()) {
                item("noChats") {
                    Text("Belum ada percakapan. Mulai dari tombol di atas.", color = X.c.muted, style = MaterialTheme.typography.bodyMedium)
                }
            } else {
                items(cs, key = { "c" + it.id }) { c ->
                    ChatRow(c, Modifier.enterAnim(6 + cs.indexOf(c))) { nav.navigate("chat/${c.id}") }
                }
                item("allChats") {
                    Text(
                        "Lihat semua percakapan",
                        color = Brand.Violet2, fontWeight = FontWeight.Bold,
                        modifier = Modifier.clip(RoundedCornerShape(8.dp)).clickable { onTab(1) }.padding(vertical = 4.dp, horizontal = 2.dp),
                    )
                }
            }

            val ps = projects
            if (!ps.isNullOrEmpty()) {
                item("projTitle") { SectionTitle("Proyek kamu") }
                item("projRow") {
                    LazyRow(horizontalArrangement = Arrangement.spacedBy(12.dp), contentPadding = PaddingValues(end = 8.dp)) {
                        items(ps, key = { it.id }) { p ->
                            BrutalBox(Modifier.width(200.dp), onClick = { nav.navigate("project/${p.id}") }, contentPadding = 14.dp) {
                                Column {
                                    IconTile(Icons.Rounded.Folder, listOf(Brand.Yellow, Brand.Lime, Brand.Cyan, Color(0xFFFBCFE8))[(p.name.hashCode() and 3)], size = 38.dp)
                                    Spacer(Modifier.height(10.dp))
                                    Text(p.name, fontWeight = FontWeight.Black, color = X.c.ink, maxLines = 1, overflow = TextOverflow.Ellipsis)
                                    Text("${p.filesCount} file · ${relativeTime(p.updatedAt)}", style = MaterialTheme.typography.bodySmall, color = X.c.muted, maxLines = 1)
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}

private fun greeting(): String = when (Calendar.getInstance().get(Calendar.HOUR_OF_DAY)) {
    in 4..10 -> "Selamat pagi"
    in 11..14 -> "Selamat siang"
    in 15..17 -> "Selamat sore"
    else -> "Selamat malam"
}

@Composable
private fun Hero(modifier: Modifier, busy: Boolean, onStart: () -> Unit) {
    val t = rememberInfiniteTransition(label = "hero")
    val shift by t.animateFloat(0f, 1f, infiniteRepeatable(tween(5000, easing = LinearEasing), RepeatMode.Reverse), label = "h")
    val float by t.animateFloat(0f, 1f, infiniteRepeatable(tween(2200), RepeatMode.Reverse), label = "f")
    val brush = Brush.linearGradient(
        listOf(Brand.Violet, Brand.Fuchsia, Brand.Orange),
        start = androidx.compose.ui.geometry.Offset(-300f + 600f * shift, 0f),
        end = androidx.compose.ui.geometry.Offset(700f + 600f * shift, 700f),
    )
    BrutalBox(modifier.fillMaxWidth(), brush = brush, shadow = 6.dp, contentPadding = 20.dp, borderColor = Brand.Ink, onClick = onStart) {
        Box(Modifier.align(Alignment.TopEnd).graphicsLayer { translationY = -8f * float; rotationZ = 8f - 16f * float }) {
            Box(
                Modifier.size(54.dp).clip(RoundedCornerShape(16.dp)).background(Brand.Yellow).border(2.dp, Brand.Ink, RoundedCornerShape(16.dp)),
                contentAlignment = Alignment.Center,
            ) { Icon(Icons.Rounded.Code, null, tint = Brand.Ink, modifier = Modifier.size(28.dp)) }
        }
        Column {
            Chip("Agen XZY", color = Color.White, icon = Icons.Rounded.AutoAwesome)
            Spacer(Modifier.height(14.dp))
            Text("Mau bikin apa\nhari ini?", color = Color.White, style = MaterialTheme.typography.headlineMedium)
            Spacer(Modifier.height(8.dp))
            Text("Agen bisa menulis kode, membuat repo GitHub, push, dan memantau Actions sampai beres.", color = Color.White.copy(alpha = 0.88f), style = MaterialTheme.typography.bodyMedium)
            Spacer(Modifier.height(16.dp))
            Row(
                Modifier.fillMaxWidth().clip(RoundedCornerShape(16.dp)).background(Color.White).border(2.dp, Brand.Ink, RoundedCornerShape(16.dp)).padding(horizontal = 14.dp, vertical = 12.dp),
                verticalAlignment = Alignment.CenterVertically,
            ) {
                Icon(Icons.AutoMirrored.Rounded.Chat, null, tint = Brand.Violet, modifier = Modifier.size(20.dp))
                Spacer(Modifier.width(10.dp))
                Text(if (busy) "Menyiapkan percakapan..." else "Mulai chat baru", color = Brand.Ink, fontWeight = FontWeight.Bold, modifier = Modifier.weight(1f))
                if (busy) GradientSpinner(18.dp) else Icon(Icons.AutoMirrored.Rounded.ArrowForward, null, tint = Brand.Ink)
            }
        }
    }
}

@Composable
private fun QuotaCard(title: String, remaining: Long, quota: Long, usedPercent: Double, accent: Color, modifier: Modifier, onClick: () -> Unit) {
    BrutalBox(modifier, onClick = onClick, contentPadding = 14.dp) {
        Column {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Box(Modifier.size(10.dp).clip(RoundedCornerShape(3.dp)).background(accent).border(1.5.dp, Brand.Ink, RoundedCornerShape(3.dp)))
                Spacer(Modifier.width(6.dp))
                Text(title, style = MaterialTheme.typography.labelMedium, color = X.c.muted)
            }
            Spacer(Modifier.height(8.dp))
            if (quota <= 0) {
                Text("Tanpa batas", style = MaterialTheme.typography.titleLarge, color = X.c.ink)
            } else {
                CountUp(remaining, MaterialTheme.typography.titleLarge.copy(fontSize = 20.sp, fontWeight = FontWeight.Black))
            }
            Spacer(Modifier.height(10.dp))
            AnimatedBar((usedPercent / 100.0).toFloat())
            Spacer(Modifier.height(6.dp))
            Text("${"%.1f".format(usedPercent)}% terpakai", style = MaterialTheme.typography.bodySmall, color = X.c.muted)
        }
    }
}

@Composable
private fun QuickAction(label: String, icon: ImageVector, color: Color, modifier: Modifier, onClick: () -> Unit) {
    BrutalBox(modifier, color = color, onClick = onClick, contentPadding = 12.dp, shadow = 4.dp, borderColor = Brand.Ink) {
        Column(Modifier.fillMaxWidth(), horizontalAlignment = Alignment.CenterHorizontally) {
            Icon(icon, null, tint = Brand.Ink, modifier = Modifier.size(24.dp))
            Spacer(Modifier.height(6.dp))
            Text(label, color = Brand.Ink, fontWeight = FontWeight.ExtraBold, fontSize = 12.sp, maxLines = 1)
        }
    }
}

@Composable
fun ChatRow(c: Conversation, modifier: Modifier = Modifier, trailing: (@Composable () -> Unit)? = null, onClick: () -> Unit) {
    val run = Agent.run(c.id)
    BrutalBox(modifier.fillMaxWidth(), onClick = onClick, contentPadding = 12.dp, shadow = 4.dp) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            Box {
                IconTile(Icons.AutoMirrored.Rounded.Chat, if (run.streaming) Brand.Lime else Brand.Yellow, size = 40.dp)
            }
            Spacer(Modifier.width(12.dp))
            Column(Modifier.weight(1f)) {
                Text(c.title, fontWeight = FontWeight.ExtraBold, color = X.c.ink, maxLines = 1, overflow = TextOverflow.Ellipsis)
                Spacer(Modifier.height(2.dp))
                Row(verticalAlignment = Alignment.CenterVertically) {
                    if (run.streaming) {
                        GradientSpinner(12.dp, 2.dp)
                        Spacer(Modifier.width(6.dp))
                        Text("agen bekerja", style = MaterialTheme.typography.bodySmall, color = Brand.Fuchsia, fontWeight = FontWeight.Bold)
                    } else {
                        Text(listOf(c.model, relativeTime(c.updatedAt)).filter { it.isNotBlank() }.joinToString(" · "), style = MaterialTheme.typography.bodySmall, color = X.c.muted, maxLines = 1, overflow = TextOverflow.Ellipsis)
                    }
                }
            }
            if (trailing != null) trailing()
        }
    }
}
