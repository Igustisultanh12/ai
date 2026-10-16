package com.xzy.ai.ui.screens

import android.content.Intent
import android.net.Uri
import androidx.compose.animation.AnimatedVisibility
import androidx.compose.animation.animateContentSize
import androidx.compose.animation.core.Spring
import androidx.compose.animation.core.spring
import androidx.compose.animation.expandVertically
import androidx.compose.animation.fadeIn
import androidx.compose.animation.fadeOut
import androidx.compose.animation.scaleIn
import androidx.compose.animation.scaleOut
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
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.imePadding
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.layout.widthIn
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.LazyRow
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.lazy.rememberLazyListState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.BasicTextField
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.rounded.Send
import androidx.compose.material.icons.rounded.Check
import androidx.compose.material.icons.rounded.Close
import androidx.compose.material.icons.rounded.ContentCopy
import androidx.compose.material.icons.rounded.DeleteOutline
import androidx.compose.material.icons.rounded.Edit
import androidx.compose.material.icons.rounded.Folder
import androidx.compose.material.icons.rounded.KeyboardArrowDown
import androidx.compose.material.icons.rounded.Memory
import androidx.compose.material.icons.rounded.MoreVert
import androidx.compose.material.icons.rounded.OpenInNew
import androidx.compose.material.icons.rounded.Stop
import androidx.compose.material.icons.rounded.Tune
import androidx.compose.material.icons.rounded.Bolt
import androidx.compose.material.icons.rounded.Refresh
import androidx.compose.material3.DropdownMenu
import androidx.compose.material3.DropdownMenuItem
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.ModalBottomSheet
import androidx.compose.material3.Text
import androidx.compose.material3.rememberModalBottomSheetState
import androidx.compose.runtime.Composable
import androidx.compose.runtime.DisposableEffect
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.derivedStateOf
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.produceState
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.runtime.snapshotFlow
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.SolidColor
import androidx.compose.ui.platform.LocalClipboardManager
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.AnnotatedString
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.lifecycle.viewmodel.compose.viewModel
import com.xzy.ai.data.Agent
import com.xzy.ai.data.AgentRun
import com.xzy.ai.data.Message
import com.xzy.ai.data.ToolRun
import com.xzy.ai.ui.components.BrutalBox
import com.xzy.ai.ui.components.Chip
import com.xzy.ai.ui.components.ConfirmDialog
import com.xzy.ai.ui.components.ErrorBanner
import com.xzy.ai.ui.components.Field
import com.xzy.ai.ui.components.GradientSpinner
import com.xzy.ai.ui.components.IconSquare
import com.xzy.ai.ui.components.LocalNav
import com.xzy.ai.ui.components.LocalToast
import com.xzy.ai.ui.components.MarkdownText
import com.xzy.ai.ui.components.ScreenHeader
import com.xzy.ai.ui.components.SecondaryButton
import com.xzy.ai.ui.components.Shimmer
import com.xzy.ai.ui.components.TypingDots
import com.xzy.ai.ui.components.XzyDialog
import com.xzy.ai.ui.components.XzyLogo
import com.xzy.ai.ui.components.bounceClick
import com.xzy.ai.ui.components.enterAnim
import com.xzy.ai.ui.theme.Brand
import com.xzy.ai.ui.theme.X
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch

private val suggestions = listOf(
    "Buatkan landing page modern dengan Laravel + Vue",
    "Buat repo GitHub baru lalu push proyek ini",
    "Jelaskan struktur proyek yang terhubung",
    "Cek status GitHub Actions terakhir dan perbaiki jika gagal",
    "Tambahkan fitur login dan register",
)

/** One row in the chat: a saved message, a pending user message, or the live answer. */
private sealed class ChatItem {
    data class Saved(val m: Message) : ChatItem()
    data class PendingUser(val text: String) : ChatItem()
    object Live : ChatItem()
}

/** Saved messages + what is still in flight, newest first (the list is reversed). */
private fun chatItems(saved: List<Message>, r: AgentRun): List<ChatItem> {
    val live = r.streaming || r.text.isNotEmpty() || r.pendingUser != null
    var base: List<Message> = if (live) saved.filter { it.status != "streaming" } else saved.toList()
    val pending = r.pendingUser
    if (live && pending != null && base.lastOrNull()?.let { it.role == "user" && it.content.trim() == pending.trim() } == true) base = base.dropLast(1)
    val out = ArrayList<ChatItem>()
    if (live) {
        out.add(ChatItem.Live)
        if (pending != null) out.add(ChatItem.PendingUser(pending))
    }
    for (m in base.asReversed()) out.add(ChatItem.Saved(m))
    return out
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun ChatScreen(id: String) {
    val nav = LocalNav.current
    val toast = LocalToast.current
    val vm: ChatViewModel = viewModel(key = "chat-$id") { ChatViewModel(id) }
    val run = vm.run
    val scope = rememberCoroutineScope()
    var input by rememberSaveable { mutableStateOf(ChatDraft.pending.remove(id).orEmpty()) }
    var sheet by remember { mutableStateOf(false) }
    var menu by remember { mutableStateOf(false) }
    var renaming by remember { mutableStateOf(false) }
    var deleting by remember { mutableStateOf(false) }
    val listState = rememberLazyListState()

    DisposableEffect(id) {
        Agent.visibleChat = id
        onDispose { if (Agent.visibleChat == id) Agent.visibleChat = null }
    }
    LaunchedEffect(vm.notice) { vm.notice?.let { toast(it); vm.notice = null } }

    val rows: List<ChatItem> = chatItems(vm.messages, run)

    LaunchedEffect(rows.size) { if (listState.firstVisibleItemIndex <= 2) listState.animateScrollToItem(0) }
    val atBottom by remember { derivedStateOf { listState.firstVisibleItemIndex == 0 && listState.firstVisibleItemScrollOffset < 60 } }

    Box(Modifier.fillMaxSize()) {
        Column(Modifier.fillMaxSize().imePadding()) {
            val c = vm.conversation
            ScreenHeader(
                c?.title ?: "Percakapan",
                listOfNotNull(c?.model?.takeIf { it.isNotBlank() }, vm.projectName?.let { "proyek $it" }).joinToString(" · ").ifBlank { null },
                onBack = { nav.popBackStack() },
            ) {
                IconSquare(Icons.Rounded.Tune, bg = Brand.Yellow, tint = Brand.Ink) { vm.loadPickers(); sheet = true }
                Box {
                    IconSquare(Icons.Rounded.MoreVert) { menu = true }
                    DropdownMenu(menu, onDismissRequest = { menu = false }, containerColor = X.c.card) {
                        DropdownMenuItem(text = { Text("Ganti judul") }, leadingIcon = { Icon(Icons.Rounded.Edit, null) }, onClick = { menu = false; renaming = true })
                        DropdownMenuItem(text = { Text("Model & proyek") }, leadingIcon = { Icon(Icons.Rounded.Tune, null) }, onClick = { menu = false; vm.loadPickers(); sheet = true })
                        DropdownMenuItem(text = { Text("Muat ulang") }, leadingIcon = { Icon(Icons.Rounded.Refresh, null) }, onClick = { menu = false; vm.load() })
                        DropdownMenuItem(text = { Text("Hapus percakapan", color = Brand.Rose) }, leadingIcon = { Icon(Icons.Rounded.DeleteOutline, null, tint = Brand.Rose) }, onClick = { menu = false; deleting = true })
                    }
                }
            }

            Box(Modifier.weight(1f).fillMaxWidth()) {
                if (vm.loading) {
                    Column(Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(14.dp)) {
                        Shimmer(Modifier.fillMaxWidth(0.7f).align(Alignment.End), 54.dp)
                        Shimmer(height = 140.dp)
                        Shimmer(Modifier.fillMaxWidth(0.6f).align(Alignment.End), 54.dp)
                    }
                } else if (rows.isEmpty()) {
                    Welcome(Modifier.align(Alignment.Center)) { input = it }
                } else {
                    LazyColumn(
                        state = listState,
                        reverseLayout = true,
                        modifier = Modifier.fillMaxSize(),
                        contentPadding = PaddingValues(start = 14.dp, end = 14.dp, top = 10.dp, bottom = 16.dp),
                        verticalArrangement = Arrangement.spacedBy(14.dp, Alignment.Bottom),
                    ) {
                        items(rows, key = { r ->
                            when (r) {
                                is ChatItem.Saved -> "m" + r.m.id
                                is ChatItem.PendingUser -> "pending"
                                ChatItem.Live -> "live"
                            }
                        }) { r ->
                            Box(Modifier.animateItem()) {
                                when (r) {
                                    is ChatItem.Saved -> if (r.m.role == "user") UserBubble(r.m.content) else AssistantBubble(r.m, null)
                                    is ChatItem.PendingUser -> UserBubble(r.text)
                                    ChatItem.Live -> AssistantBubble(null, run)
                                }
                            }
                        }
                    }
                }
                AnimatedVisibility(!atBottom && rows.isNotEmpty(), modifier = Modifier.align(Alignment.BottomEnd).padding(16.dp), enter = scaleIn() + fadeIn(), exit = scaleOut() + fadeOut()) {
                    IconSquare(Icons.Rounded.KeyboardArrowDown, bg = Brand.Ink, tint = Color.White) { scope.launch { listState.animateScrollToItem(0) } }
                }
            }

            vm.error?.let { ErrorBanner(it, Modifier.padding(horizontal = 14.dp, vertical = 4.dp)) }
            run.error?.takeIf { !run.streaming }?.let { ErrorBanner("Agen berhenti: $it", Modifier.padding(horizontal = 14.dp, vertical = 4.dp)) }

            Composer(
                value = input,
                onChange = { input = it },
                streaming = run.streaming,
                onSend = {
                    if (input.isNotBlank()) {
                        vm.send(input)
                        input = ""
                        scope.launch { listState.animateScrollToItem(0) }
                    }
                },
                onStop = { vm.stop() },
            )
        }
    }

    if (sheet) {
        ModalBottomSheet(onDismissRequest = { sheet = false }, sheetState = rememberModalBottomSheetState(skipPartiallyExpanded = true), containerColor = X.c.surface) {
            PickerSheet(vm) { sheet = false }
        }
    }
    if (renaming) {
        var t by remember { mutableStateOf(vm.conversation?.title.orEmpty()) }
        XzyDialog("Ganti judul", "Simpan", confirmEnabled = t.isNotBlank(), onConfirm = { vm.rename(t.trim()); renaming = false }, onDismiss = { renaming = false }) {
            Field(t, { t = it }, "Judul percakapan")
        }
    }
    if (deleting) {
        ConfirmDialog("Hapus percakapan?", "Semua pesan di percakapan ini akan dihapus permanen.", "Hapus", onConfirm = {
            deleting = false
            vm.delete { toast("Percakapan dihapus"); nav.popBackStack() }
        }, onDismiss = { deleting = false })
    }
}

@Composable
private fun Welcome(modifier: Modifier, onPick: (String) -> Unit) {
    Column(modifier.fillMaxWidth().padding(24.dp), horizontalAlignment = Alignment.CenterHorizontally) {
        Box(Modifier.enterAnim(0)) { XzyLogo(72.dp, animated = true) }
        Spacer(Modifier.height(16.dp))
        Text("Halo! Mau dikerjakan apa?", style = MaterialTheme.typography.headlineSmall, color = X.c.ink, modifier = Modifier.enterAnim(1))
        Spacer(Modifier.height(6.dp))
        Text("Agen bisa menulis kode, membuat repo, push ke GitHub, dan memantau Actions.", style = MaterialTheme.typography.bodyMedium, color = X.c.muted, textAlign = androidx.compose.ui.text.style.TextAlign.Center, modifier = Modifier.enterAnim(2))
        Spacer(Modifier.height(20.dp))
        suggestions.forEachIndexed { i, s ->
            BrutalBox(Modifier.fillMaxWidth().padding(vertical = 5.dp).enterAnim(3 + i), contentPadding = 12.dp, shadow = 3.dp, shape = RoundedCornerShape(14.dp), onClick = { onPick(s) }) {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Icon(Icons.Rounded.Bolt, null, tint = Brand.Orange, modifier = Modifier.size(18.dp))
                    Spacer(Modifier.width(8.dp))
                    Text(s, style = MaterialTheme.typography.bodyMedium, color = X.c.ink, fontWeight = FontWeight.SemiBold)
                }
            }
        }
    }
}

@Composable
private fun UserBubble(text: String) {
    Box(Modifier.fillMaxWidth(), contentAlignment = Alignment.CenterEnd) {
        BrutalBox(
            Modifier.widthIn(max = 320.dp),
            shape = RoundedCornerShape(topStart = 20.dp, topEnd = 6.dp, bottomStart = 20.dp, bottomEnd = 20.dp),
            brush = Brand.gradient,
            borderColor = Brand.Ink,
            shadow = 3.dp,
            contentPadding = 13.dp,
        ) {
            Text(text, color = Color.White, style = MaterialTheme.typography.bodyLarge)
        }
    }
}

@Composable
private fun AssistantBubble(m: Message?, run: AgentRun?) {
    val clipboard = LocalClipboardManager.current
    val toast = LocalToast.current
    val liveText by produceState(run?.text.orEmpty(), run) {
        if (run != null) snapshotFlow { run.text }.collect { value = it; delay(90) }
    }
    val content = m?.content ?: liveText
    Column(Modifier.fillMaxWidth()) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            XzyLogo(26.dp, animated = run?.streaming == true)
            Spacer(Modifier.width(8.dp))
            Text("XZY Agent", fontWeight = FontWeight.Black, color = X.c.ink, fontSize = 13.sp)
            Spacer(Modifier.width(8.dp))
            when {
                run != null && run.streaming -> {
                    val started = run.startedAt
                    val elapsed by produceState(0L, started) {
                        while (true) { value = (System.currentTimeMillis() - started) / 1000; delay(1000) }
                    }
                    Chip("bekerja ${elapsed / 60}:${(elapsed % 60).toString().padStart(2, '0')}", color = Brand.Lime)
                }
                m?.status == "failed" -> Chip("gagal", color = Brand.Rose, textColor = Color.White)
                m?.status == "cancelled" -> Chip("dihentikan", color = X.c.card2, textColor = X.c.ink)
            }
        }
        Spacer(Modifier.height(8.dp))
        BrutalBox(
            Modifier.fillMaxWidth(),
            shape = RoundedCornerShape(topStart = 6.dp, topEnd = 20.dp, bottomStart = 20.dp, bottomEnd = 20.dp),
            shadow = 4.dp,
            contentPadding = 14.dp,
        ) {
            Column(Modifier.animateContentSize(spring(stiffness = Spring.StiffnessMediumLow))) {
                if (run != null && run.tools.isNotEmpty()) {
                    ActivityPanel(run.tools)
                    Spacer(Modifier.height(12.dp))
                }
                if (content.isNotBlank()) {
                    MarkdownText(content)
                }
                if (run != null && run.streaming) {
                    if (content.isNotBlank()) Spacer(Modifier.height(10.dp))
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        TypingDots()
                        val st = run.status
                        if (st != null) {
                            Spacer(Modifier.width(10.dp))
                            Text(st, style = MaterialTheme.typography.bodySmall, color = X.c.muted, maxLines = 2, overflow = TextOverflow.Ellipsis)
                        } else if (content.isBlank() && run.tools.isEmpty()) {
                            Spacer(Modifier.width(10.dp))
                            Text("Agen sedang berpikir...", style = MaterialTheme.typography.bodySmall, color = X.c.muted)
                        }
                    }
                }
            }
        }
        if (m != null && content.isNotBlank()) {
            Row(
                Modifier.padding(top = 6.dp, start = 4.dp).clip(RoundedCornerShape(8.dp)).clickable {
                    clipboard.setText(AnnotatedString(content.replace(Regex("<details>[\\s\\S]*?</details>\\s*"), "")))
                    toast("Jawaban disalin")
                }.padding(horizontal = 6.dp, vertical = 4.dp),
                verticalAlignment = Alignment.CenterVertically,
            ) {
                Icon(Icons.Rounded.ContentCopy, null, tint = X.c.muted, modifier = Modifier.size(14.dp))
                Spacer(Modifier.width(4.dp))
                Text("Salin", style = MaterialTheme.typography.labelSmall, color = X.c.muted)
            }
        }
    }
}

@Composable
private fun ActivityPanel(tools: List<ToolRun>) {
    val context = LocalContext.current
    var all by remember { mutableStateOf(false) }
    val shown = if (all || tools.size <= 6) tools else tools.takeLast(6)
    Column(
        Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(14.dp))
            .background(X.c.card2)
            .border(1.5.dp, X.c.line, RoundedCornerShape(14.dp))
            .padding(10.dp),
        verticalArrangement = Arrangement.spacedBy(8.dp),
    ) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            Icon(Icons.Rounded.Memory, null, tint = Brand.Violet2, modifier = Modifier.size(16.dp))
            Spacer(Modifier.width(6.dp))
            Text("Aktivitas agen", style = MaterialTheme.typography.labelLarge, color = X.c.ink, modifier = Modifier.weight(1f))
            val done = tools.count { it.status == "done" }
            val failed = tools.count { it.status == "error" }
            Text("$done berhasil" + if (failed > 0) " · $failed gagal" else "", style = MaterialTheme.typography.labelSmall, color = X.c.muted)
        }
        if (tools.size > 6) {
            Text(
                if (all) "Sembunyikan" else "Tampilkan semua (${tools.size})",
                style = MaterialTheme.typography.labelSmall, color = Brand.Violet2,
                modifier = Modifier.clip(RoundedCornerShape(6.dp)).clickable { all = !all }.padding(2.dp),
            )
        }
        shown.forEach { t ->
            androidx.compose.runtime.key(t.id) {
                Row(verticalAlignment = Alignment.Top) {
                    Box(Modifier.padding(top = 1.dp).size(20.dp), contentAlignment = Alignment.Center) {
                        when (t.status) {
                            "running" -> GradientSpinner(16.dp, 2.dp)
                            "done" -> Box(Modifier.size(18.dp).clip(CircleShape).background(Brand.Green), contentAlignment = Alignment.Center) {
                                Icon(Icons.Rounded.Check, null, tint = Brand.Ink, modifier = Modifier.size(12.dp))
                            }
                            else -> Box(Modifier.size(18.dp).clip(CircleShape).background(Brand.Rose), contentAlignment = Alignment.Center) {
                                Icon(Icons.Rounded.Close, null, tint = Color.White, modifier = Modifier.size(12.dp))
                            }
                        }
                    }
                    Spacer(Modifier.width(8.dp))
                    Column(Modifier.weight(1f)) {
                        Text(t.label, style = MaterialTheme.typography.bodySmall, color = X.c.text, fontWeight = FontWeight.SemiBold)
                        t.error?.let { Text(it, style = MaterialTheme.typography.bodySmall, color = Brand.Rose, maxLines = 3, overflow = TextOverflow.Ellipsis) }
                    }
                    val link = t.link
                    if (link != null) {
                        Icon(
                            Icons.Rounded.OpenInNew, "Buka", tint = Brand.Violet2,
                            modifier = Modifier.size(26.dp).clip(RoundedCornerShape(6.dp)).clickable {
                                try { context.startActivity(Intent(Intent.ACTION_VIEW, Uri.parse(link))) } catch (_: Exception) {}
                            }.padding(4.dp),
                        )
                    }
                }
            }
        }
    }
}

@Composable
private fun Composer(value: String, onChange: (String) -> Unit, streaming: Boolean, onSend: () -> Unit, onStop: () -> Unit) {
    Row(
        Modifier
            .fillMaxWidth()
            .navigationBarsPadding()
            .padding(start = 12.dp, end = 12.dp, top = 6.dp, bottom = 10.dp),
        verticalAlignment = Alignment.Bottom,
    ) {
        BrutalBox(Modifier.weight(1f), shape = RoundedCornerShape(22.dp), shadow = 3.dp, contentPadding = 0.dp) {
            Box(Modifier.padding(horizontal = 16.dp, vertical = 13.dp)) {
                if (value.isEmpty()) Text(if (streaming) "Agen sedang bekerja..." else "Tulis pesan ke agen...", color = X.c.muted, style = MaterialTheme.typography.bodyLarge)
                BasicTextField(
                    value = value,
                    onValueChange = onChange,
                    textStyle = MaterialTheme.typography.bodyLarge.copy(color = X.c.ink),
                    cursorBrush = SolidColor(Brand.Fuchsia),
                    maxLines = 6,
                    modifier = Modifier.fillMaxWidth().heightIn(min = 24.dp),
                )
            }
        }
        Spacer(Modifier.width(10.dp))
        val canSend = value.isNotBlank() && !streaming
        Box(
            Modifier
                .size(52.dp)
                .bounceClick { if (streaming) onStop() else if (canSend) onSend() }
                .clip(RoundedCornerShape(18.dp))
                .then(
                    when {
                        streaming -> Modifier.background(Brand.Rose)
                        canSend -> Modifier.background(Brand.gradient)
                        else -> Modifier.background(X.c.card2)
                    },
                )
                .border(2.dp, Brand.Ink, RoundedCornerShape(18.dp)),
            contentAlignment = Alignment.Center,
        ) {
            androidx.compose.animation.AnimatedContent(streaming, label = "send") { s ->
                if (s) Icon(Icons.Rounded.Stop, "Hentikan", tint = Color.White, modifier = Modifier.size(24.dp))
                else Icon(Icons.AutoMirrored.Rounded.Send, "Kirim", tint = if (canSend) Color.White else X.c.muted, modifier = Modifier.size(22.dp))
            }
        }
    }
}

@Composable
private fun PickerSheet(vm: ChatViewModel, onClose: () -> Unit) {
    val current = vm.conversation
    LazyColumn(Modifier.fillMaxWidth().navigationBarsPadding(), contentPadding = PaddingValues(start = 18.dp, end = 18.dp, bottom = 24.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
        item { Text("Model AI", style = MaterialTheme.typography.titleLarge, color = X.c.ink) }
        if (vm.models.isEmpty()) item { Shimmer(height = 60.dp) }
        items(vm.models, key = { "m" + it.id }) { m ->
            val selected = current?.model == m.id
            BrutalBox(
                Modifier.fillMaxWidth(),
                color = if (selected) Brand.Yellow else X.c.card,
                borderColor = if (selected) Brand.Ink else if (X.c.isDark) X.c.line else Brand.Ink,
                shadow = if (selected) 4.dp else 2.dp,
                contentPadding = 12.dp,
                shape = RoundedCornerShape(14.dp),
                onClick = { vm.setModel(m); if (m.available) onClose() },
            ) {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Column(Modifier.weight(1f)) {
                        Text(m.id, fontWeight = FontWeight.ExtraBold, color = if (selected) Brand.Ink else X.c.ink)
                        m.description?.takeIf { it.isNotBlank() }?.let { Text(it, style = MaterialTheme.typography.bodySmall, color = if (selected) Brand.Ink.copy(alpha = 0.7f) else X.c.muted, maxLines = 2, overflow = TextOverflow.Ellipsis) }
                    }
                    if (m.multiplier != null) Chip(m.label.substringAfter(m.id).trim().substringBefore(" (").ifBlank { "x1" }, color = Brand.Cyan)
                    if (!m.available) { Spacer(Modifier.width(6.dp)); Chip("habis", color = Brand.Rose, textColor = Color.White) }
                }
            }
        }
        item {
            Spacer(Modifier.height(8.dp))
            Text("Proyek terhubung", style = MaterialTheme.typography.titleLarge, color = X.c.ink)
            Text("Agen membaca dan menulis file di proyek yang dipilih.", style = MaterialTheme.typography.bodySmall, color = X.c.muted)
        }
        item {
            ProjectOption("Tanpa proyek", current?.projectId == null) { vm.setProject(null); onClose() }
        }
        items(vm.projects, key = { "p" + it.id }) { p ->
            ProjectOption(p.name, current?.projectId == p.id) { vm.setProject(p); onClose() }
        }
    }
}

@Composable
private fun ProjectOption(name: String, selected: Boolean, onClick: () -> Unit) {
    Row(
        Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(14.dp))
            .background(if (selected) Brand.Lime else X.c.card)
            .border(2.dp, if (selected) Brand.Ink else X.c.line, RoundedCornerShape(14.dp))
            .clickable(onClick = onClick)
            .padding(12.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Icon(Icons.Rounded.Folder, null, tint = if (selected) Brand.Ink else X.c.muted, modifier = Modifier.size(20.dp))
        Spacer(Modifier.width(10.dp))
        Text(name, fontWeight = FontWeight.Bold, color = if (selected) Brand.Ink else X.c.ink, modifier = Modifier.weight(1f), maxLines = 1, overflow = TextOverflow.Ellipsis)
        if (selected) Icon(Icons.Rounded.Check, null, tint = Brand.Ink)
    }
}
