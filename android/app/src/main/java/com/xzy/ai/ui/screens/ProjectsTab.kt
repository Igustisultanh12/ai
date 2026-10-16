package com.xzy.ai.ui.screens

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
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.itemsIndexed
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.rounded.Add
import androidx.compose.material.icons.rounded.Folder
import androidx.compose.material.icons.rounded.Search
import androidx.compose.material.icons.rounded.Description
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.material3.pulltorefresh.PullToRefreshBox
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.mutableStateListOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import com.xzy.ai.data.Project
import com.xzy.ai.data.Repo
import com.xzy.ai.data.Session
import com.xzy.ai.ui.components.BrutalBox
import com.xzy.ai.ui.components.Chip
import com.xzy.ai.ui.components.EmptyState
import com.xzy.ai.ui.components.ErrorBanner
import com.xzy.ai.ui.components.Field
import com.xzy.ai.ui.components.GradientSpinner
import com.xzy.ai.ui.components.IconSquare
import com.xzy.ai.ui.components.IconTile
import com.xzy.ai.ui.components.LocalNav
import com.xzy.ai.ui.components.LocalToast
import com.xzy.ai.ui.components.PrimaryButton
import com.xzy.ai.ui.components.ScreenHeader
import com.xzy.ai.ui.components.SecondaryButton
import com.xzy.ai.ui.components.Shimmer
import com.xzy.ai.ui.components.XzyDialog
import com.xzy.ai.ui.components.enterAnim
import com.xzy.ai.ui.components.relativeTime
import com.xzy.ai.ui.theme.Brand
import com.xzy.ai.ui.theme.X
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch

val projectColors = listOf(Brand.Yellow, Brand.Lime, Brand.Cyan, Color(0xFFFBCFE8), Color(0xFFFDBA74))
fun projectColor(name: String) = projectColors[(name.hashCode() and 0x7fffffff) % projectColors.size]

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun ProjectsTab() {
    val nav = LocalNav.current
    val toast = LocalToast.current
    val scope = rememberCoroutineScope()
    val items = remember { mutableStateListOf<Project>() }
    var loaded by remember { mutableStateOf(false) }
    var page by remember { mutableIntStateOf(1) }
    var lastPage by remember { mutableIntStateOf(1) }
    var search by remember { mutableStateOf("") }
    var error by remember { mutableStateOf<String?>(null) }
    var refreshing by remember { mutableStateOf(false) }
    var loadingMore by remember { mutableStateOf(false) }
    var reload by remember { mutableIntStateOf(0) }
    var creating by remember { mutableStateOf(false) }

    LaunchedEffect(search, reload) {
        if (search.isNotEmpty()) delay(350)
        error = null
        try {
            val p = Repo.projects(1, search)
            items.clear(); items.addAll(p.items)
            page = 1; lastPage = p.lastPage
        } catch (e: Exception) {
            error = Session.fail(e)
        }
        loaded = true
        refreshing = false
    }

    Column(Modifier.fillMaxSize()) {
        ScreenHeader("Proyek", "Workspace kode yang dikerjakan agen") {
            IconSquare(Icons.Rounded.Add, bg = Brand.Lime, tint = Brand.Ink) { creating = true }
        }
        Field(search, { search = it }, "Cari proyek", leading = Icons.Rounded.Search, modifier = Modifier.padding(horizontal = 16.dp))
        PullToRefreshBox(isRefreshing = refreshing, onRefresh = { refreshing = true; reload++ }, modifier = Modifier.fillMaxSize()) {
            LazyColumn(
                Modifier.fillMaxSize(),
                contentPadding = PaddingValues(start = 16.dp, end = 16.dp, top = 14.dp, bottom = BOTTOM_SPACE.dp),
                verticalArrangement = Arrangement.spacedBy(14.dp),
            ) {
                error?.let { item { ErrorBanner(it) } }
                if (!loaded) {
                    items(5) { Shimmer(height = 96.dp) }
                } else if (items.isEmpty()) {
                    item {
                        EmptyState(Icons.Rounded.Folder, if (search.isBlank()) "Belum ada proyek" else "Tidak ditemukan", if (search.isBlank()) "Buat proyek, lalu minta agen mengisinya." else "Coba kata kunci lain.") {
                            if (search.isBlank()) PrimaryButton("Buat proyek", icon = Icons.Rounded.Add) { creating = true }
                        }
                    }
                } else {
                    itemsIndexed(items, key = { _, p -> p.id }) { i, p ->
                        ProjectCard(p, Modifier.animateItem().enterAnim(i)) { nav.navigate("project/${p.id}") }
                    }
                    if (page < lastPage) item {
                        Box(Modifier.fillMaxWidth(), contentAlignment = Alignment.Center) {
                            if (loadingMore) GradientSpinner(24.dp) else SecondaryButton("Muat lebih banyak") {
                                loadingMore = true
                                scope.launch {
                                    try {
                                        val p = Repo.projects(page + 1, search)
                                        items.addAll(p.items.filter { n -> items.none { it.id == n.id } })
                                        page = p.page; lastPage = p.lastPage
                                    } catch (e: Exception) { toast(Session.fail(e)) }
                                    loadingMore = false
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    if (creating) {
        CreateProjectDialog(onDismiss = { creating = false }) { p ->
            creating = false
            items.add(0, p)
            toast("Proyek \"${p.name}\" dibuat")
            nav.navigate("project/${p.id}")
        }
    }
}

@Composable
fun ProjectCard(p: Project, modifier: Modifier = Modifier, onClick: () -> Unit) {
    BrutalBox(modifier.fillMaxWidth(), onClick = onClick, contentPadding = 14.dp) {
        Row(verticalAlignment = Alignment.Top) {
            IconTile(Icons.Rounded.Folder, projectColor(p.name), size = 46.dp)
            Spacer(Modifier.width(12.dp))
            Column(Modifier.weight(1f)) {
                Text(p.name, style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.Black, color = X.c.ink, maxLines = 1, overflow = TextOverflow.Ellipsis)
                if (p.description.isNotBlank()) {
                    Text(p.description, style = MaterialTheme.typography.bodySmall, color = X.c.muted, maxLines = 2, overflow = TextOverflow.Ellipsis)
                }
                Spacer(Modifier.height(8.dp))
                Row(horizontalArrangement = Arrangement.spacedBy(6.dp), verticalAlignment = Alignment.CenterVertically) {
                    Chip("${p.filesCount} file", color = X.c.card2, textColor = X.c.ink, icon = Icons.Rounded.Description)
                    if (p.githubRepo != null) Chip(p.githubRepo.substringAfter('/'), color = Brand.Ink, textColor = Color.White)
                }
            }
            Text(relativeTime(p.updatedAt), style = MaterialTheme.typography.labelSmall, color = X.c.muted)
        }
    }
}

@Composable
fun CreateProjectDialog(onDismiss: () -> Unit, onCreated: (Project) -> Unit) {
    val scope = rememberCoroutineScope()
    var name by remember { mutableStateOf("") }
    var desc by remember { mutableStateOf("") }
    var loading by remember { mutableStateOf(false) }
    var error by remember { mutableStateOf<String?>(null) }
    XzyDialog("Proyek baru", "Buat", confirmEnabled = name.isNotBlank(), loading = loading, onDismiss = onDismiss, onConfirm = {
        loading = true
        error = null
        scope.launch {
            try {
                onCreated(Repo.createProject(name.trim(), desc.trim()))
            } catch (e: Exception) {
                error = Session.fail(e)
            }
            loading = false
        }
    }) {
        Text("Berisi template Laravel + Vue 3. Agen bisa mengubahnya sesuai permintaanmu.", style = MaterialTheme.typography.bodySmall, color = X.c.muted)
        Spacer(Modifier.height(12.dp))
        Field(name, { name = it }, "Nama proyek")
        Spacer(Modifier.height(10.dp))
        Field(desc, { desc = it }, "Deskripsi (opsional)", singleLine = false, minLines = 2)
        error?.let { Spacer(Modifier.height(10.dp)); ErrorBanner(it) }
    }
}
