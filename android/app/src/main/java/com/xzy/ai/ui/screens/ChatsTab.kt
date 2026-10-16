package com.xzy.ai.ui.screens

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.itemsIndexed
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.rounded.Chat
import androidx.compose.material.icons.rounded.Add
import androidx.compose.material.icons.rounded.DeleteOutline
import androidx.compose.material.icons.rounded.Search
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
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
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
import com.xzy.ai.data.Conversation
import com.xzy.ai.data.Repo
import com.xzy.ai.data.Session
import com.xzy.ai.ui.components.ConfirmDialog
import com.xzy.ai.ui.components.EmptyState
import com.xzy.ai.ui.components.ErrorBanner
import com.xzy.ai.ui.components.Field
import com.xzy.ai.ui.components.GradientSpinner
import com.xzy.ai.ui.components.IconSquare
import com.xzy.ai.ui.components.LocalNav
import com.xzy.ai.ui.components.LocalToast
import com.xzy.ai.ui.components.PrimaryButton
import com.xzy.ai.ui.components.ScreenHeader
import com.xzy.ai.ui.components.SecondaryButton
import com.xzy.ai.ui.components.Shimmer
import com.xzy.ai.ui.components.enterAnim
import com.xzy.ai.ui.theme.Brand
import com.xzy.ai.ui.theme.X
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun ChatsTab() {
    val nav = LocalNav.current
    val toast = LocalToast.current
    val scope = rememberCoroutineScope()
    val newChat = rememberNewChat()
    val items = remember { mutableStateListOf<Conversation>() }
    var loaded by remember { mutableStateOf(false) }
    var page by remember { mutableIntStateOf(1) }
    var lastPage by remember { mutableIntStateOf(1) }
    var search by remember { mutableStateOf("") }
    var error by remember { mutableStateOf<String?>(null) }
    var refreshing by remember { mutableStateOf(false) }
    var loadingMore by remember { mutableStateOf(false) }
    var reload by remember { mutableIntStateOf(0) }
    var toDelete by remember { mutableStateOf<Conversation?>(null) }
    var deleting by remember { mutableStateOf(false) }

    LaunchedEffect(search, reload) {
        if (search.isNotEmpty()) delay(350)
        error = null
        try {
            val p = Repo.conversations(1, search)
            items.clear(); items.addAll(p.items)
            page = 1; lastPage = p.lastPage
        } catch (e: Exception) {
            error = Session.fail(e)
        }
        loaded = true
        refreshing = false
    }

    Column(Modifier.fillMaxSize()) {
        ScreenHeader("Percakapan", "Semua chat kamu dengan agen") {
            IconSquare(Icons.Rounded.Add, bg = Brand.Yellow, tint = Brand.Ink) { newChat.start(null, null) }
        }
        Field(search, { search = it }, "Cari percakapan", leading = Icons.Rounded.Search, modifier = Modifier.padding(horizontal = 16.dp))
        PullToRefreshBox(isRefreshing = refreshing, onRefresh = { refreshing = true; reload++ }, modifier = Modifier.fillMaxSize()) {
            LazyColumn(
                Modifier.fillMaxSize(),
                contentPadding = PaddingValues(start = 16.dp, end = 16.dp, top = 14.dp, bottom = BOTTOM_SPACE.dp),
                verticalArrangement = Arrangement.spacedBy(12.dp),
            ) {
                error?.let { item { ErrorBanner(it) } }
                if (!loaded) {
                    items(6) { Shimmer(height = 66.dp) }
                } else if (items.isEmpty()) {
                    item {
                        EmptyState(Icons.AutoMirrored.Rounded.Chat, if (search.isBlank()) "Belum ada percakapan" else "Tidak ditemukan", if (search.isBlank()) "Mulai chat pertamamu dan biarkan agen bekerja." else "Coba kata kunci lain.") {
                            if (search.isBlank()) PrimaryButton("Mulai chat", icon = Icons.Rounded.Add, loading = newChat.busy()) { newChat.start(null, null) }
                        }
                    }
                } else {
                    itemsIndexed(items, key = { _, c -> c.id }) { i, c ->
                        ChatRow(
                            c,
                            Modifier.animateItem().enterAnim(i),
                            trailing = {
                                IconButton(onClick = { toDelete = c }) { Icon(Icons.Rounded.DeleteOutline, "Hapus", tint = X.c.muted) }
                            },
                        ) { nav.navigate("chat/${c.id}") }
                    }
                    if (page < lastPage) item {
                        Box(Modifier.fillMaxWidth(), contentAlignment = androidx.compose.ui.Alignment.Center) {
                            if (loadingMore) GradientSpinner(24.dp) else SecondaryButton("Muat lebih banyak") {
                                loadingMore = true
                                scope.launch {
                                    try {
                                        val p = Repo.conversations(page + 1, search)
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

    toDelete?.let { c ->
        ConfirmDialog("Hapus percakapan?", "\"${c.title}\" dan semua pesannya akan dihapus permanen.", "Hapus", loading = deleting, onConfirm = {
            deleting = true
            scope.launch {
                try {
                    Repo.deleteConversation(c.id)
                    items.removeAll { it.id == c.id }
                    toast("Percakapan dihapus")
                } catch (e: Exception) { toast(Session.fail(e)) }
                deleting = false
                toDelete = null
            }
        }, onDismiss = { toDelete = null })
    }
}
