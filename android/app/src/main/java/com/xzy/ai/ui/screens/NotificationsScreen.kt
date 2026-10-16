package com.xzy.ai.ui.screens

import android.Manifest
import android.content.Intent
import android.net.Uri
import android.os.Build
import android.provider.Settings
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.itemsIndexed
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.rounded.CheckCircle
import androidx.compose.material.icons.rounded.DeleteSweep
import androidx.compose.material.icons.rounded.DoneAll
import androidx.compose.material.icons.rounded.ErrorOutline
import androidx.compose.material.icons.rounded.Info
import androidx.compose.material.icons.rounded.Notifications
import androidx.compose.material.icons.rounded.NotificationsOff
import androidx.compose.material.icons.rounded.SystemUpdate
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import com.xzy.ai.data.Notif
import com.xzy.ai.data.Notifier
import com.xzy.ai.data.Updater
import com.xzy.ai.ui.components.BrutalBox
import com.xzy.ai.ui.components.ConfirmDialog
import com.xzy.ai.ui.components.EmptyState
import com.xzy.ai.ui.components.IconSquare
import com.xzy.ai.ui.components.IconTile
import com.xzy.ai.ui.components.LocalNav
import com.xzy.ai.ui.components.ScreenHeader
import com.xzy.ai.ui.components.SecondaryButton
import com.xzy.ai.ui.components.enterAnim
import com.xzy.ai.ui.components.relativeMillis
import com.xzy.ai.ui.theme.Brand
import com.xzy.ai.ui.theme.X

@Composable
fun NotificationsScreen() {
    val nav = LocalNav.current
    val context = LocalContext.current
    var confirmClear by remember { mutableStateOf(false) }
    var permTick by remember { mutableIntStateOf(0) }
    val launcher = rememberLauncherForActivityResult(ActivityResultContracts.RequestPermission()) { permTick++ }
    val allowed = remember(permTick) { Notifier.canPost() }

    Column(Modifier.fillMaxSize()) {
        ScreenHeader("Notifikasi", if (Notifier.unread > 0) "${Notifier.unread} belum dibaca" else "Semua sudah dibaca", onBack = { nav.popBackStack() }) {
            if (Notifier.unread > 0) IconSquare(Icons.Rounded.DoneAll, bg = Brand.Lime, tint = Brand.Ink) { Notifier.markAllRead() }
            if (Notifier.inbox.isNotEmpty()) IconSquare(Icons.Rounded.DeleteSweep) { confirmClear = true }
        }
        LazyColumn(
            Modifier.fillMaxSize(),
            contentPadding = PaddingValues(start = 16.dp, end = 16.dp, top = 6.dp, bottom = 40.dp),
            verticalArrangement = Arrangement.spacedBy(10.dp),
        ) {
            if (!allowed) item {
                BrutalBox(Modifier.fillMaxWidth().enterAnim(0), color = Brand.Yellow, borderColor = Brand.Ink, contentPadding = 14.dp) {
                    Column {
                        Row(verticalAlignment = Alignment.CenterVertically) {
                            Icon(Icons.Rounded.NotificationsOff)
                            Spacer(Modifier.width(10.dp))
                            Text("Notifikasi sistem belum diizinkan", fontWeight = FontWeight.Black, color = Brand.Ink)
                        }
                        Spacer(Modifier.height(6.dp))
                        Text("Izinkan supaya kamu dikabari saat agen selesai bekerja atau ada versi baru.", style = MaterialTheme.typography.bodySmall, color = Brand.Ink)
                        Spacer(Modifier.height(10.dp))
                        SecondaryButton("Izinkan notifikasi", color = Color.White, textColor = Brand.Ink) {
                            if (Build.VERSION.SDK_INT >= 33 && context.checkSelfPermission(Manifest.permission.POST_NOTIFICATIONS) != android.content.pm.PackageManager.PERMISSION_GRANTED) {
                                launcher.launch(Manifest.permission.POST_NOTIFICATIONS)
                            } else {
                                context.startActivity(
                                    Intent(Settings.ACTION_APP_NOTIFICATION_SETTINGS).putExtra(Settings.EXTRA_APP_PACKAGE, context.packageName)
                                        .addFlags(Intent.FLAG_ACTIVITY_NEW_TASK),
                                )
                            }
                        }
                    }
                }
            }
            if (Notifier.inbox.isEmpty()) {
                item { EmptyState(Icons.Rounded.Notifications, "Belum ada notifikasi", "Kabar dari agen dan info pembaruan akan muncul di sini.") }
            } else {
                itemsIndexed(Notifier.inbox, key = { _, n -> n.id }) { i, n ->
                    NotifRow(n, Modifier.animateItem().enterAnim(i.coerceAtMost(10))) {
                        Notifier.markRead(n.id)
                        val t = n.target
                        when {
                            t == null -> {}
                            t.startsWith("chat:") -> nav.navigate("chat/${t.removePrefix("chat:")}")
                            t.startsWith("project:") -> nav.navigate("project/${t.removePrefix("project:")}")
                            t == "update" -> Updater.sheetOpen = true
                        }
                    }
                }
            }
            item { Spacer(Modifier.navigationBarsPadding()) }
        }
    }

    if (confirmClear) {
        ConfirmDialog("Hapus semua notifikasi?", "Riwayat notifikasi di aplikasi ini akan dikosongkan.", "Hapus", onConfirm = { Notifier.clear(); confirmClear = false }, onDismiss = { confirmClear = false })
    }
}

@Composable
private fun Icon(v: androidx.compose.ui.graphics.vector.ImageVector) =
    androidx.compose.material3.Icon(v, null, tint = Brand.Ink, modifier = Modifier.size(22.dp))

@Composable
private fun NotifRow(n: Notif, modifier: Modifier, onClick: () -> Unit) {
    val (icon, color) = when (n.kind) {
        "agent" -> Icons.Rounded.CheckCircle to Brand.Lime
        "error" -> Icons.Rounded.ErrorOutline to Color(0xFFFDA4AF)
        "update" -> Icons.Rounded.SystemUpdate to Brand.Cyan
        else -> Icons.Rounded.Info to Brand.Yellow
    }
    BrutalBox(modifier.fillMaxWidth(), color = if (n.read) X.c.card else X.c.card2, shadow = if (n.read) 2.dp else 4.dp, contentPadding = 12.dp, onClick = onClick) {
        Row(verticalAlignment = Alignment.Top) {
            IconTile(icon, color, size = 38.dp)
            Spacer(Modifier.width(12.dp))
            Column(Modifier.weight(1f)) {
                Text(n.title, fontWeight = if (n.read) FontWeight.Bold else FontWeight.Black, color = X.c.ink, maxLines = 2)
                Spacer(Modifier.height(2.dp))
                Text(n.body, style = MaterialTheme.typography.bodySmall, color = X.c.muted, maxLines = 3)
                Spacer(Modifier.height(4.dp))
                Text(relativeMillis(n.time), style = MaterialTheme.typography.labelSmall, color = X.c.muted)
            }
            if (!n.read) Box(Modifier.size(10.dp).clip(CircleShape).background(Brand.Rose))
        }
    }
}
