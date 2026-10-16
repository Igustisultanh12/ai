package com.xzy.ai.ui.screens

import android.content.Intent
import android.net.Uri
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.itemsIndexed
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.rounded.Chat
import androidx.compose.material.icons.rounded.Code
import androidx.compose.material.icons.rounded.DeleteOutline
import androidx.compose.material.icons.rounded.Description
import androidx.compose.material.icons.rounded.Image
import androidx.compose.material.icons.rounded.OpenInNew
import androidx.compose.material.icons.rounded.Search
import androidx.compose.material.icons.rounded.Settings
import androidx.compose.material.icons.rounded.Style
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontFamily
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import com.xzy.ai.data.Project
import com.xzy.ai.data.ProjectFile
import com.xzy.ai.data.Repo
import com.xzy.ai.data.Session
import com.xzy.ai.ui.components.BrutalBox
import com.xzy.ai.ui.components.Chip
import com.xzy.ai.ui.components.ConfirmDialog
import com.xzy.ai.ui.components.EmptyState
import com.xzy.ai.ui.components.ErrorBanner
import com.xzy.ai.ui.components.Field
import com.xzy.ai.ui.components.IconSquare
import com.xzy.ai.ui.components.IconTile
import com.xzy.ai.ui.components.LocalNav
import com.xzy.ai.ui.components.LocalToast
import com.xzy.ai.ui.components.PrimaryButton
import com.xzy.ai.ui.components.ScreenHeader
import com.xzy.ai.ui.components.SecondaryButton
import com.xzy.ai.ui.components.Shimmer
import com.xzy.ai.ui.components.enterAnim
import com.xzy.ai.ui.components.formatBytes
import com.xzy.ai.ui.components.relativeTime
import com.xzy.ai.ui.theme.Brand
import com.xzy.ai.ui.theme.X
import kotlinx.coroutines.launch

@Composable
fun ProjectScreen(id: String) {
    val nav = LocalNav.current
    val toast = LocalToast.current
    val context = LocalContext.current
    val scope = rememberCoroutineScope()
    val newChat = rememberNewChat()
    var project by remember { mutableStateOf<Project?>(null) }
    var files by remember { mutableStateOf<List<ProjectFile>?>(null) }
    var error by remember { mutableStateOf<String?>(null) }
    var filter by remember { mutableStateOf("") }
    var confirmDelete by remember { mutableStateOf(false) }
    var deleting by remember { mutableStateOf(false) }
    var reload by remember { mutableIntStateOf(0) }

    LaunchedEffect(reload) {
        error = null
        try {
            project = Repo.project(id)
            files = Repo.projectFiles(id).sortedBy { it.path.lowercase() }
        } catch (e: Exception) {
            error = Session.fail(e)
            if (files == null) files = emptyList()
        }
    }

    Column(Modifier.fillMaxSize()) {
        ScreenHeader(project?.name ?: "Proyek", project?.let { "${it.filesCount} file · diperbarui ${relativeTime(it.updatedAt)}" }, onBack = { nav.popBackStack() }) {
            IconSquare(Icons.Rounded.DeleteOutline, bg = Brand.Rose, tint = Color.White) { confirmDelete = true }
        }
        val shown = files?.filter { filter.isBlank() || it.path.contains(filter, ignoreCase = true) }
        LazyColumn(
            Modifier.fillMaxSize(),
            contentPadding = PaddingValues(start = 16.dp, end = 16.dp, top = 6.dp, bottom = 40.dp),
            verticalArrangement = Arrangement.spacedBy(10.dp),
        ) {
            item {
                val p = project
                if (p == null) Shimmer(height = 150.dp) else BrutalBox(Modifier.fillMaxWidth().enterAnim(0), brush = Brand.gradient, borderColor = Brand.Ink, contentPadding = 18.dp) {
                    Column {
                        Row(horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                            Chip(p.status.replaceFirstChar { it.uppercase() }, color = Brand.Lime)
                            if (p.githubRepo != null) Chip(p.githubRepo, color = Color.White)
                        }
                        Spacer(Modifier.height(10.dp))
                        Text(p.description.ifBlank { "Belum ada deskripsi." }, color = Color.White, style = MaterialTheme.typography.bodyMedium)
                        Spacer(Modifier.height(16.dp))
                        Row(horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                            SecondaryButton("Chat proyek", icon = Icons.AutoMirrored.Rounded.Chat, color = Brand.Yellow, textColor = Brand.Ink, modifier = Modifier.weight(1f)) { newChat.start(p.id, null) }
                            if (p.githubRepo != null) {
                                SecondaryButton("GitHub", icon = Icons.Rounded.OpenInNew, color = Color.White, textColor = Brand.Ink) {
                                    context.startActivity(Intent(Intent.ACTION_VIEW, Uri.parse("https://github.com/${p.githubRepo}")))
                                }
                            }
                        }
                    }
                }
            }
            error?.let { item { ErrorBanner(it) } }
            item {
                Spacer(Modifier.height(6.dp))
                Field(filter, { filter = it }, "Cari file", leading = Icons.Rounded.Search)
            }
            if (shown == null) {
                items(6) { Shimmer(height = 56.dp) }
            } else if (shown.isEmpty()) {
                item { EmptyState(Icons.Rounded.Description, "Tidak ada file", if (filter.isBlank()) "Minta agen membuat file di proyek ini." else "Tidak ada yang cocok dengan \"$filter\".") }
            } else {
                itemsIndexed(shown, key = { _, f -> f.path }) { i, f ->
                    FileRow(f, Modifier.enterAnim(i.coerceAtMost(10))) {
                        if (f.binary) toast("File biner tidak bisa dibuka di sini")
                        else nav.navigate("file/$id?path=${Uri.encode(f.path)}")
                    }
                }
            }
            item { Spacer(Modifier.navigationBarsPadding()) }
        }
    }

    if (confirmDelete) {
        ConfirmDialog("Hapus proyek?", "Proyek \"${project?.name ?: ""}\" beserta semua file akan dihapus permanen.", "Hapus", loading = deleting, onConfirm = {
            deleting = true
            scope.launch {
                try {
                    Repo.deleteProject(id)
                    toast("Proyek dihapus")
                    confirmDelete = false
                    nav.popBackStack()
                } catch (e: Exception) {
                    toast(Session.fail(e))
                }
                deleting = false
            }
        }, onDismiss = { confirmDelete = false })
    }
}

private fun fileIcon(path: String): Pair<ImageVector, Color> {
    val ext = path.substringAfterLast('.', "").lowercase()
    return when (ext) {
        "php", "js", "ts", "vue", "kt", "java", "py", "go", "rs", "jsx", "tsx", "sh" -> Icons.Rounded.Code to Brand.Cyan
        "css", "scss", "html", "blade" -> Icons.Rounded.Style to Color(0xFFFBCFE8)
        "png", "jpg", "jpeg", "gif", "svg", "webp", "ico" -> Icons.Rounded.Image to Brand.Lime
        "json", "yml", "yaml", "xml", "env", "toml", "lock" -> Icons.Rounded.Settings to Brand.Yellow
        else -> Icons.Rounded.Description to Color(0xFFE9E5FF)
    }
}

@Composable
private fun FileRow(f: ProjectFile, modifier: Modifier, onClick: () -> Unit) {
    val (icon, color) = fileIcon(f.path)
    BrutalBox(modifier.fillMaxWidth(), onClick = onClick, contentPadding = 10.dp, shadow = 3.dp, shape = androidx.compose.foundation.shape.RoundedCornerShape(14.dp)) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            IconTile(icon, color, size = 34.dp)
            Spacer(Modifier.width(10.dp))
            Column(Modifier.weight(1f)) {
                Text(f.path.substringAfterLast('/'), fontWeight = FontWeight.Bold, color = X.c.ink, maxLines = 1, overflow = TextOverflow.Ellipsis, fontFamily = FontFamily.Monospace, style = MaterialTheme.typography.bodyMedium)
                val dir = f.path.substringBeforeLast('/', "")
                if (dir.isNotEmpty()) Text(dir, style = MaterialTheme.typography.bodySmall, color = X.c.muted, maxLines = 1, overflow = TextOverflow.Ellipsis)
            }
            Text(formatBytes(f.size), style = MaterialTheme.typography.labelSmall, color = X.c.muted)
        }
    }
}

@Composable
fun FileScreen(projectId: String, path: String) {
    val nav = LocalNav.current
    val toast = LocalToast.current
    val scope = rememberCoroutineScope()
    var content by remember { mutableStateOf<String?>(null) }
    var draft by remember { mutableStateOf("") }
    var editing by remember { mutableStateOf(false) }
    var saving by remember { mutableStateOf(false) }
    var error by remember { mutableStateOf<String?>(null) }

    LaunchedEffect(path) {
        try {
            content = Repo.projectFile(projectId, path)
            draft = content.orEmpty()
        } catch (e: Exception) {
            error = Session.fail(e)
        }
    }

    Column(Modifier.fillMaxSize()) {
        ScreenHeader(path.substringAfterLast('/'), path, onBack = { nav.popBackStack() }) {
            if (content != null) {
                SecondaryButton(if (editing) "Batal" else "Edit", color = if (editing) X.c.card else Brand.Yellow, textColor = Brand.Ink.takeIf { !editing } ?: X.c.ink) {
                    if (editing) draft = content.orEmpty()
                    editing = !editing
                }
            }
        }
        error?.let { ErrorBanner(it, Modifier.padding(16.dp)) }
        val c = content
        if (c == null && error == null) {
            Column(Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) { repeat(8) { Shimmer(height = 22.dp) } }
        } else if (c != null) {
            if (editing) {
                Column(Modifier.fillMaxSize().padding(16.dp).navigationBarsPadding()) {
                    androidx.compose.material3.OutlinedTextField(
                        value = draft,
                        onValueChange = { draft = it },
                        modifier = Modifier.fillMaxWidth().weight(1f),
                        textStyle = MaterialTheme.typography.bodySmall.copy(fontFamily = FontFamily.Monospace, color = X.c.ink),
                        shape = androidx.compose.foundation.shape.RoundedCornerShape(16.dp),
                    )
                    Spacer(Modifier.height(12.dp))
                    PrimaryButton("Simpan perubahan", loading = saving, enabled = draft != c, modifier = Modifier.fillMaxWidth()) {
                        saving = true
                        scope.launch {
                            try {
                                Repo.saveProjectFile(projectId, path, draft)
                                content = draft
                                editing = false
                                toast("File disimpan")
                            } catch (e: Exception) {
                                toast(Session.fail(e))
                            }
                            saving = false
                        }
                    }
                }
            } else {
                CodeViewer(c, Modifier.fillMaxSize().padding(horizontal = 16.dp).padding(bottom = 16.dp).navigationBarsPadding())
            }
        }
    }
}

@Composable
private fun CodeViewer(code: String, modifier: Modifier) {
    val lines = remember(code) { code.split("\n") }
    BrutalBox(modifier, color = Color(0xFF0F0D1A), contentPadding = 0.dp, shadow = 4.dp, shape = androidx.compose.foundation.shape.RoundedCornerShape(16.dp)) {
        LazyColumn(Modifier.fillMaxSize().padding(vertical = 10.dp)) {
            itemsIndexed(lines) { i, line ->
                Row {
                    Text(
                        (i + 1).toString().padStart(4),
                        fontFamily = FontFamily.Monospace,
                        color = Color(0xFF5E5880),
                        style = MaterialTheme.typography.bodySmall,
                        modifier = Modifier.padding(start = 8.dp, end = 12.dp),
                    )
                    Text(line.ifEmpty { " " }, fontFamily = FontFamily.Monospace, color = Color(0xFFE9E5FF), style = MaterialTheme.typography.bodySmall, modifier = Modifier.padding(end = 12.dp))
                }
            }
        }
    }
}
