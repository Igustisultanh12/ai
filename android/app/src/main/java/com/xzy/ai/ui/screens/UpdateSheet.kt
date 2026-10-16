package com.xzy.ai.ui.screens

import androidx.compose.animation.AnimatedContent
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.rounded.Download
import androidx.compose.material.icons.rounded.InstallMobile
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.ModalBottomSheet
import androidx.compose.material3.Text
import androidx.compose.material3.rememberModalBottomSheetState
import androidx.compose.runtime.Composable
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.unit.dp
import com.xzy.ai.data.Api
import com.xzy.ai.data.Updater
import com.xzy.ai.ui.components.AnimatedBar
import com.xzy.ai.ui.components.BrutalBox
import com.xzy.ai.ui.components.Chip
import com.xzy.ai.ui.components.ErrorBanner
import com.xzy.ai.ui.components.MarkdownText
import com.xzy.ai.ui.components.PrimaryButton
import com.xzy.ai.ui.components.SecondaryButton
import com.xzy.ai.ui.components.XzyLogo
import com.xzy.ai.ui.components.formatBytes
import com.xzy.ai.ui.components.relativeTime
import com.xzy.ai.ui.theme.Brand
import com.xzy.ai.ui.theme.X
import kotlinx.coroutines.launch

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun UpdateSheet() {
    val r = Updater.available ?: return
    if (!Updater.sheetOpen) return
    val context = LocalContext.current
    val scope = rememberCoroutineScope()
    fun close() {
        Updater.sheetOpen = false
        Updater.dismissedBuild = r.build
        Api.prefs.edit().putInt("update_prompted", r.build).apply()
    }
    ModalBottomSheet(onDismissRequest = { close() }, sheetState = rememberModalBottomSheetState(skipPartiallyExpanded = true), containerColor = X.c.surface) {
        Column(Modifier.fillMaxWidth().navigationBarsPadding().padding(start = 20.dp, end = 20.dp, bottom = 24.dp)) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                XzyLogo(54.dp, animated = true)
                Spacer(Modifier.width(14.dp))
                Column(Modifier.weight(1f)) {
                    Text("Versi baru tersedia", style = MaterialTheme.typography.headlineSmall, color = X.c.ink)
                    Row(horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                        Chip(Updater.currentVersion, color = X.c.card2, textColor = X.c.muted)
                        Text("->", color = X.c.muted)
                        Chip(r.tag.removePrefix("android-v"), color = Brand.Lime)
                    }
                }
            }
            Spacer(Modifier.height(14.dp))
            Text(
                listOfNotNull(relativeTime(r.publishedAt).ifBlank { null }, if (r.size > 0) formatBytes(r.size) else null).joinToString(" · "),
                style = MaterialTheme.typography.bodySmall, color = X.c.muted,
            )
            Spacer(Modifier.height(10.dp))
            BrutalBox(Modifier.fillMaxWidth(), contentPadding = 14.dp, shadow = 3.dp) {
                Box(Modifier.heightIn(max = 240.dp).verticalScroll(rememberScrollState())) {
                    MarkdownText(r.notes.ifBlank { "Perbaikan dan peningkatan." })
                }
            }
            Spacer(Modifier.height(16.dp))
            Updater.error?.let { ErrorBanner(it); Spacer(Modifier.height(12.dp)) }
            val p = Updater.progress
            val file = Updater.downloaded?.takeIf { it.name.contains(r.build.toString()) && it.exists() }
            AnimatedContent(targetState = when { file != null -> 2; p >= 0f -> 1; else -> 0 }, label = "upd") { state ->
                Column {
                    when (state) {
                        1 -> {
                            Text("Mengunduh ${(p * 100).toInt()}%", style = MaterialTheme.typography.labelLarge, color = X.c.ink)
                            Spacer(Modifier.height(8.dp))
                            AnimatedBar(p, height = 14.dp)
                        }
                        2 -> PrimaryButton("Pasang sekarang", icon = Icons.Rounded.InstallMobile, modifier = Modifier.fillMaxWidth()) {
                            if (file != null && !Updater.install(context, file)) {
                                Updater.error = "Izinkan \"Instal aplikasi tak dikenal\" untuk XZY AI, lalu ketuk Pasang lagi."
                            }
                        }
                        else -> PrimaryButton("Unduh & pasang", icon = Icons.Rounded.Download, modifier = Modifier.fillMaxWidth()) {
                            scope.launch {
                                val f = Updater.download(context, r)
                                if (f != null && !Updater.install(context, f)) {
                                    Updater.error = "Izinkan \"Instal aplikasi tak dikenal\" untuk XZY AI, lalu ketuk Pasang lagi."
                                }
                            }
                        }
                    }
                }
            }
            Spacer(Modifier.height(10.dp))
            SecondaryButton("Nanti saja", modifier = Modifier.fillMaxWidth()) { close() }
        }
    }
}
