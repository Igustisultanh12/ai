package com.xzy.ai.ui.screens

import android.content.Intent
import android.net.Uri
import androidx.compose.animation.AnimatedVisibility
import androidx.compose.animation.core.Spring
import androidx.compose.animation.core.animateDpAsState
import androidx.compose.animation.core.spring
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
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.offset
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.rounded.ArrowForward
import androidx.compose.material.icons.automirrored.rounded.Logout
import androidx.compose.material.icons.rounded.AdminPanelSettings
import androidx.compose.material.icons.rounded.AlternateEmail
import androidx.compose.material.icons.rounded.Badge
import androidx.compose.material.icons.rounded.DarkMode
import androidx.compose.material.icons.rounded.Hub
import androidx.compose.material.icons.rounded.Insights
import androidx.compose.material.icons.rounded.Key
import androidx.compose.material.icons.rounded.Language
import androidx.compose.material.icons.rounded.LightMode
import androidx.compose.material.icons.rounded.Lock
import androidx.compose.material.icons.rounded.Notifications
import androidx.compose.material.icons.rounded.Person
import androidx.compose.material.icons.rounded.SystemUpdate
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import com.xzy.ai.data.Api
import com.xzy.ai.data.GitHubStatus
import com.xzy.ai.data.Notifier
import com.xzy.ai.data.Prefs
import com.xzy.ai.data.Repo
import com.xzy.ai.data.Session
import com.xzy.ai.data.Updater
import com.xzy.ai.ui.components.Avatar
import com.xzy.ai.ui.components.BrutalBox
import com.xzy.ai.ui.components.Chip
import com.xzy.ai.ui.components.ConfirmDialog
import com.xzy.ai.ui.components.ErrorBanner
import com.xzy.ai.ui.components.Field
import com.xzy.ai.ui.components.GradientSpinner
import com.xzy.ai.ui.components.IconTile
import com.xzy.ai.ui.components.LocalNav
import com.xzy.ai.ui.components.LocalToast
import com.xzy.ai.ui.components.PrimaryButton
import com.xzy.ai.ui.components.SecondaryButton
import com.xzy.ai.ui.components.enterAnim
import com.xzy.ai.ui.theme.Brand
import com.xzy.ai.ui.theme.X
import kotlinx.coroutines.launch

@Composable
fun AccountTab() {
    val nav = LocalNav.current
    val toast = LocalToast.current
    val context = LocalContext.current
    val scope = rememberCoroutineScope()
    val user = Session.user
    var open by remember { mutableStateOf<String?>(null) }
    var github by remember { mutableStateOf<GitHubStatus?>(null) }
    var confirmLogout by remember { mutableStateOf(false) }

    LaunchedEffect(Unit) {
        try { github = Repo.githubStatus() } catch (_: Exception) {}
    }

    Column(
        Modifier
            .fillMaxSize()
            .verticalScroll(rememberScrollState())
            .statusBarsPadding()
            .padding(start = 16.dp, end = 16.dp, top = 12.dp, bottom = BOTTOM_SPACE.dp),
        verticalArrangement = Arrangement.spacedBy(14.dp),
    ) {
        BrutalBox(Modifier.fillMaxWidth().enterAnim(0), brush = Brand.gradient, borderColor = Brand.Ink, contentPadding = 18.dp, shadow = 6.dp) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Avatar(user?.initials ?: "?", 62.dp)
                Spacer(Modifier.width(14.dp))
                Column(Modifier.weight(1f)) {
                    Text(user?.name ?: "-", style = MaterialTheme.typography.headlineSmall, color = Color.White, maxLines = 1, overflow = TextOverflow.Ellipsis)
                    Text(user?.email ?: "", style = MaterialTheme.typography.bodySmall, color = Color.White.copy(alpha = 0.85f), maxLines = 1, overflow = TextOverflow.Ellipsis)
                    Spacer(Modifier.height(8.dp))
                    Row(horizontalArrangement = Arrangement.spacedBy(6.dp)) {
                        Chip(if (user?.isAdmin == true) "Admin" else "Pengguna", color = Brand.Yellow)
                        if (user?.mfaEnabled == true) Chip("2FA aktif", color = Brand.Lime)
                    }
                }
            }
        }

        Group("Akun", Modifier.enterAnim(1)) {
            Expandable("profile", open, { open = it }, Icons.Rounded.Person, Brand.Yellow, "Edit profil", "Nama dan email") { ProfileForm { open = null } }
            Expandable("password", open, { open = it }, Icons.Rounded.Lock, Color(0xFFFBCFE8), "Ganti password", "Minimal 8 karakter") { PasswordForm { open = null } }
            Expandable("github", open, { open = it }, Icons.Rounded.Hub, Brand.Cyan, "GitHub", github?.let { if (it.connected) "Terhubung sebagai @${it.username}" else "Belum terhubung" } ?: "Memuat...") {
                GitHubForm(github) { github = it }
            }
        }

        Group("Aplikasi", Modifier.enterAnim(2)) {
            SettingRow(if (Prefs.dark) Icons.Rounded.DarkMode else Icons.Rounded.LightMode, Brand.Lime, "Tema gelap", if (Prefs.dark) "Aktif" else "Nonaktif", trailing = { Toggle(Prefs.dark) }) { Prefs.dark = !Prefs.dark }
            SettingRow(Icons.Rounded.Notifications, Brand.Yellow, "Notifikasi", if (Notifier.unread > 0) "${Notifier.unread} belum dibaca" else "Riwayat notifikasi") { nav.navigate("notifications") }
            SettingRow(Icons.Rounded.Insights, Brand.Cyan, "Pemakaian token", "Kuota dan riwayat") { nav.navigate("usage") }
            SettingRow(
                Icons.Rounded.SystemUpdate, Color(0xFFFDBA74), "Cek pembaruan",
                when {
                    Updater.checking -> "Mengecek..."
                    Updater.available != null -> "Versi ${Updater.available!!.tag.removePrefix("android-v")} tersedia"
                    else -> "Versi ${Updater.currentVersion}"
                },
                trailing = { if (Updater.checking) GradientSpinner(20.dp) else if (Updater.available != null) Chip("baru", color = Brand.Rose, textColor = Color.White) },
            ) {
                scope.launch {
                    val r = Updater.check(notify = false)
                    when {
                        r != null -> Updater.sheetOpen = true
                        Updater.error != null -> toast(Updater.error!!)
                        else -> toast("Kamu sudah memakai versi terbaru")
                    }
                }
            }
            SettingRow(Icons.Rounded.Language, Color(0xFFE9E5FF), "Buka versi web", Api.baseUrl.removePrefix("https://")) {
                context.startActivity(Intent(Intent.ACTION_VIEW, Uri.parse(Api.baseUrl)))
            }
            if (user?.isAdmin == true) {
                SettingRow(Icons.Rounded.AdminPanelSettings, Brand.Rose, "Panel admin", "Kelola pengguna, model, dan agen di web") {
                    context.startActivity(Intent(Intent.ACTION_VIEW, Uri.parse("${Api.baseUrl}/admin")))
                }
            }
        }

        PrimaryButton("Keluar", icon = Icons.AutoMirrored.Rounded.Logout, modifier = Modifier.fillMaxWidth().enterAnim(3), brush = androidx.compose.ui.graphics.SolidColor(Brand.Rose)) { confirmLogout = true }
        Text("XZY AI ${Updater.currentVersion}", style = MaterialTheme.typography.labelSmall, color = X.c.muted, modifier = Modifier.align(Alignment.CenterHorizontally))
    }

    if (confirmLogout) {
        ConfirmDialog("Keluar dari akun?", "Kamu perlu masuk lagi untuk memakai aplikasi.", "Keluar", onConfirm = {
            confirmLogout = false
            scope.launch {
                Repo.logout()
                Session.user = null
                nav.navigate("auth") { popUpTo(nav.graph.id) { inclusive = true } }
            }
        }, onDismiss = { confirmLogout = false })
    }
}

@Composable
private fun Group(title: String, modifier: Modifier = Modifier, content: @Composable () -> Unit) {
    Column(modifier) {
        Text(title.uppercase(), style = MaterialTheme.typography.labelSmall, color = X.c.muted, modifier = Modifier.padding(start = 4.dp, bottom = 8.dp))
        BrutalBox(Modifier.fillMaxWidth(), contentPadding = 6.dp) {
            Column { content() }
        }
    }
}

@Composable
private fun SettingRow(icon: ImageVector, color: Color, title: String, subtitle: String, trailing: (@Composable () -> Unit)? = null, onClick: () -> Unit) {
    Row(
        Modifier.fillMaxWidth().clip(RoundedCornerShape(14.dp)).clickable(onClick = onClick).padding(10.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        IconTile(icon, color, size = 38.dp)
        Spacer(Modifier.width(12.dp))
        Column(Modifier.weight(1f)) {
            Text(title, fontWeight = FontWeight.ExtraBold, color = X.c.ink)
            Text(subtitle, style = MaterialTheme.typography.bodySmall, color = X.c.muted, maxLines = 1, overflow = TextOverflow.Ellipsis)
        }
        if (trailing != null) trailing() else Icon(Icons.AutoMirrored.Rounded.ArrowForward, null, tint = X.c.muted, modifier = Modifier.size(18.dp))
    }
}

@Composable
private fun Expandable(
    key: String, open: String?, setOpen: (String?) -> Unit, icon: ImageVector, color: Color, title: String, subtitle: String,
    content: @Composable () -> Unit,
) {
    val isOpen = open == key
    Column {
        SettingRow(icon, color, title, subtitle, trailing = {
            val rot by androidx.compose.animation.core.animateFloatAsState(if (isOpen) 90f else 0f, label = "rot")
            Icon(Icons.AutoMirrored.Rounded.ArrowForward, null, tint = X.c.muted, modifier = Modifier.size(18.dp).graphicsLayer { rotationZ = rot })
        }) { setOpen(if (isOpen) null else key) }
        AnimatedVisibility(isOpen, enter = expandVertically(spring(dampingRatio = 0.8f)) + fadeIn(), exit = shrinkVertically() + fadeOut()) {
            Box(Modifier.padding(start = 10.dp, end = 10.dp, bottom = 12.dp)) { content() }
        }
    }
}


@Composable
private fun Toggle(on: Boolean) {
    val x by animateDpAsState(if (on) 22.dp else 0.dp, spring(dampingRatio = Spring.DampingRatioMediumBouncy), label = "toggle")
    Box(
        Modifier
            .width(50.dp)
            .height(28.dp)
            .clip(RoundedCornerShape(50))
            .then(if (on) Modifier.background(Brand.gradient) else Modifier.background(X.c.card2))
            .border(2.dp, Brand.Ink, RoundedCornerShape(50))
            .padding(3.dp),
    ) {
        Box(Modifier.offset(x = x).size(20.dp).clip(CircleShape).background(Color.White).border(1.5.dp, Brand.Ink, CircleShape))
    }
}

@Composable
private fun ProfileForm(onDone: () -> Unit) {
    val toast = LocalToast.current
    val scope = rememberCoroutineScope()
    var name by remember { mutableStateOf(Session.user?.name.orEmpty()) }
    var email by remember { mutableStateOf(Session.user?.email.orEmpty()) }
    var loading by remember { mutableStateOf(false) }
    var error by remember { mutableStateOf<String?>(null) }
    Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
        Field(name, { name = it }, "Nama", leading = Icons.Rounded.Badge)
        Field(email, { email = it }, "Email", leading = Icons.Rounded.AlternateEmail)
        error?.let { ErrorBanner(it) }
        PrimaryButton("Simpan profil", loading = loading, enabled = name.isNotBlank() && email.contains("@"), modifier = Modifier.fillMaxWidth()) {
            loading = true; error = null
            scope.launch {
                try {
                    Session.user = Repo.updateProfile(name.trim(), email.trim())
                    toast("Profil diperbarui")
                    onDone()
                } catch (e: Exception) { error = Session.fail(e) }
                loading = false
            }
        }
    }
}

@Composable
private fun PasswordForm(onDone: () -> Unit) {
    val toast = LocalToast.current
    val scope = rememberCoroutineScope()
    var current by remember { mutableStateOf("") }
    var pass by remember { mutableStateOf("") }
    var confirm by remember { mutableStateOf("") }
    var loading by remember { mutableStateOf(false) }
    var error by remember { mutableStateOf<String?>(null) }
    Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
        Field(current, { current = it }, "Password sekarang", leading = Icons.Rounded.Lock, password = true)
        Field(pass, { pass = it }, "Password baru", leading = Icons.Rounded.Key, password = true)
        Field(confirm, { confirm = it }, "Ulangi password baru", leading = Icons.Rounded.Key, password = true)
        error?.let { ErrorBanner(it) }
        PrimaryButton("Ganti password", loading = loading, enabled = current.isNotBlank() && pass.length >= 8 && confirm == pass, modifier = Modifier.fillMaxWidth()) {
            loading = true; error = null
            scope.launch {
                try {
                    toast(Repo.updatePassword(current, pass, confirm))
                    onDone()
                } catch (e: Exception) { error = Session.fail(e) }
                loading = false
            }
        }
    }
}

@Composable
private fun GitHubForm(status: GitHubStatus?, onChange: (GitHubStatus) -> Unit) {
    val toast = LocalToast.current
    val context = LocalContext.current
    val scope = rememberCoroutineScope()
    var token by remember { mutableStateOf("") }
    var loading by remember { mutableStateOf(false) }
    var error by remember { mutableStateOf<String?>(null) }
    Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
        if (status?.connected == true) {
            Text("Akun @${status.username} terhubung. Agen bisa membuat repo, push, dan memantau Actions.", style = MaterialTheme.typography.bodySmall, color = X.c.text)
            if (status.scopes.isNotEmpty()) {
                Text("Izin token: ${status.scopes.joinToString(", ")}", style = MaterialTheme.typography.bodySmall, color = X.c.muted)
                if ("workflow" !in status.scopes) {
                    ErrorBanner("Token belum punya izin \"workflow\", jadi file .github/workflows tidak bisa di-push. Buat token baru dengan centang repo + workflow.")
                }
            }
            SecondaryButton(if (loading) "Memutus..." else "Putuskan GitHub", color = Brand.Rose, textColor = Color.White, modifier = Modifier.fillMaxWidth()) {
                if (loading) return@SecondaryButton
                loading = true
                scope.launch {
                    try {
                        Repo.githubDisconnect()
                        onChange(GitHubStatus(false, null, emptyList()))
                        toast("GitHub diputus")
                    } catch (e: Exception) { error = Session.fail(e) }
                    loading = false
                }
            }
        } else {
            Text("Tempel Personal Access Token (classic) dengan izin repo dan workflow.", style = MaterialTheme.typography.bodySmall, color = X.c.text)
            Text(
                "Buat token di github.com",
                color = Brand.Violet2, fontWeight = FontWeight.Bold, style = MaterialTheme.typography.bodySmall,
                modifier = Modifier.clip(RoundedCornerShape(6.dp)).clickable {
                    context.startActivity(Intent(Intent.ACTION_VIEW, Uri.parse("https://github.com/settings/tokens/new?scopes=repo,workflow&description=XZY%20AI")))
                }.padding(vertical = 2.dp),
            )
            Field(token, { token = it.trim() }, "ghp_...", leading = Icons.Rounded.Key, password = true)
            PrimaryButton("Hubungkan", loading = loading, enabled = token.length > 10, modifier = Modifier.fillMaxWidth()) {
                loading = true; error = null
                scope.launch {
                    try {
                        Repo.githubConnect(token)
                        onChange(Repo.githubStatus())
                        token = ""
                        toast("GitHub terhubung")
                    } catch (e: Exception) { error = Session.fail(e) }
                    loading = false
                }
            }
        }
        error?.let { ErrorBanner(it) }
    }
}
