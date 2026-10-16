package com.xzy.ai.ui.screens

import androidx.compose.animation.AnimatedContent
import androidx.compose.animation.AnimatedVisibility
import androidx.compose.animation.animateContentSize
import androidx.compose.animation.core.Spring
import androidx.compose.animation.core.animateDpAsState
import androidx.compose.animation.core.spring
import androidx.compose.animation.expandVertically
import androidx.compose.animation.fadeIn
import androidx.compose.animation.fadeOut
import androidx.compose.animation.shrinkVertically
import androidx.compose.animation.slideInHorizontally
import androidx.compose.animation.slideOutHorizontally
import androidx.compose.animation.togetherWith
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.interaction.MutableInteractionSource
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.BoxWithConstraints
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxHeight
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.imePadding
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.offset
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.rounded.ArrowForward
import androidx.compose.material.icons.automirrored.rounded.Login
import androidx.compose.material.icons.rounded.AlternateEmail
import androidx.compose.material.icons.rounded.Badge
import androidx.compose.material.icons.rounded.Key
import androidx.compose.material.icons.rounded.Lock
import androidx.compose.material.icons.rounded.PersonAdd
import androidx.compose.material.icons.rounded.Shield
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import com.xzy.ai.data.Repo
import com.xzy.ai.data.Session
import com.xzy.ai.ui.components.AuroraBackground
import com.xzy.ai.ui.components.BrutalBox
import com.xzy.ai.ui.components.Chip
import com.xzy.ai.ui.components.DotGrid
import com.xzy.ai.ui.components.ErrorBanner
import com.xzy.ai.ui.components.Field
import com.xzy.ai.ui.components.LocalNav
import com.xzy.ai.ui.components.PrimaryButton
import com.xzy.ai.ui.components.XzyLogo
import com.xzy.ai.ui.components.enterAnim
import com.xzy.ai.ui.theme.Brand
import com.xzy.ai.ui.theme.X
import kotlinx.coroutines.launch

@Composable
fun AuthScreen() {
    val nav = LocalNav.current
    val scope = rememberCoroutineScope()
    var register by rememberSaveable { mutableStateOf(false) }
    var mfa by rememberSaveable { mutableStateOf(false) }
    var name by rememberSaveable { mutableStateOf("") }
    var email by rememberSaveable { mutableStateOf("") }
    var password by rememberSaveable { mutableStateOf("") }
    var confirm by rememberSaveable { mutableStateOf("") }
    var code by rememberSaveable { mutableStateOf("") }
    var loading by remember { mutableStateOf(false) }
    var error by remember { mutableStateOf<String?>(null) }

    fun done() {
        nav.navigate("main") { popUpTo(nav.graph.id) { inclusive = true } }
    }

    fun submit() {
        if (loading) return
        error = null
        scope.launch {
            loading = true
            try {
                when {
                    mfa -> { Session.user = Repo.verifyMfa(code.trim()); done() }
                    register -> {
                        if (password != confirm) throw IllegalStateException("Konfirmasi password tidak sama.")
                        Session.user = Repo.register(name.trim(), email.trim(), password, confirm); done()
                    }
                    else -> {
                        val u = Repo.login(email.trim(), password)
                        if (u == null) mfa = true else { Session.user = u; done() }
                    }
                }
            } catch (e: Exception) {
                error = e.message ?: "Gagal"
            } finally {
                loading = false
            }
        }
    }

    Box(Modifier.fillMaxSize()) {
        AuroraBackground()
        DotGrid()
        Column(
            Modifier
                .fillMaxSize()
                .verticalScroll(rememberScrollState())
                .statusBarsPadding()
                .navigationBarsPadding()
                .imePadding()
                .padding(horizontal = 22.dp, vertical = 24.dp),
            horizontalAlignment = Alignment.CenterHorizontally,
        ) {
            Spacer(Modifier.height(20.dp))
            Box(Modifier.enterAnim(0)) { XzyLogo(76.dp, animated = true) }
            Spacer(Modifier.height(18.dp))
            Text(
                if (mfa) "Verifikasi 2 langkah" else if (register) "Buat akun baru" else "Selamat datang lagi",
                style = MaterialTheme.typography.headlineMedium,
                color = X.c.ink,
                textAlign = TextAlign.Center,
                modifier = Modifier.enterAnim(1).animateContentSize(),
            )
            Spacer(Modifier.height(6.dp))
            Text(
                if (mfa) "Masukkan 6 digit kode dari aplikasi autentikator kamu." else "Chat dengan agen, kelola proyek, dan pantau kuota dari mana saja.",
                style = MaterialTheme.typography.bodyMedium,
                color = X.c.muted,
                textAlign = TextAlign.Center,
                modifier = Modifier.enterAnim(2),
            )
            Spacer(Modifier.height(22.dp))

            BrutalBox(Modifier.fillMaxWidth().enterAnim(3), shadow = 6.dp, contentPadding = 18.dp) {
                Column(Modifier.animateContentSize(spring(dampingRatio = Spring.DampingRatioLowBouncy, stiffness = Spring.StiffnessMediumLow))) {
                    if (!mfa) {
                        Segmented(register) { register = it; error = null }
                        Spacer(Modifier.height(18.dp))
                    }
                    AnimatedContent(
                        targetState = when { mfa -> 2; register -> 1; else -> 0 },
                        transitionSpec = {
                            val dir = if (targetState > initialState) 1 else -1
                            (slideInHorizontally { it / 3 * dir } + fadeIn()) togetherWith (slideOutHorizontally { -it / 3 * dir } + fadeOut())
                        },
                        label = "form",
                    ) { mode ->
                        Column {
                            when (mode) {
                                2 -> {
                                    Box(Modifier.fillMaxWidth(), contentAlignment = Alignment.Center) { Chip("Akun dilindungi 2FA", icon = Icons.Rounded.Shield, color = Brand.Lime) }
                                    Spacer(Modifier.height(14.dp))
                                    Field(code, { v -> code = v.filter { it.isDigit() }.take(6) }, "Kode 6 digit", leading = Icons.Rounded.Key, keyboardType = KeyboardType.NumberPassword)
                                }
                                1 -> {
                                    Field(name, { name = it }, "Nama lengkap", leading = Icons.Rounded.Badge)
                                    Spacer(Modifier.height(12.dp))
                                    Field(email, { email = it }, "Email", leading = Icons.Rounded.AlternateEmail, keyboardType = KeyboardType.Email)
                                    Spacer(Modifier.height(12.dp))
                                    Field(password, { password = it }, "Password (min. 8 karakter)", leading = Icons.Rounded.Lock, password = true)
                                    Spacer(Modifier.height(12.dp))
                                    Field(confirm, { confirm = it }, "Ulangi password", leading = Icons.Rounded.Lock, password = true)
                                }
                                else -> {
                                    Field(email, { email = it }, "Email", leading = Icons.Rounded.AlternateEmail, keyboardType = KeyboardType.Email)
                                    Spacer(Modifier.height(12.dp))
                                    Field(password, { password = it }, "Password", leading = Icons.Rounded.Lock, password = true)
                                }
                            }
                        }
                    }
                    AnimatedVisibility(error != null, enter = expandVertically() + fadeIn(), exit = shrinkVertically() + fadeOut()) {
                        Column {
                            Spacer(Modifier.height(14.dp))
                            ErrorBanner(error.orEmpty())
                        }
                    }
                    Spacer(Modifier.height(18.dp))
                    val enabled = when {
                        mfa -> code.length == 6
                        register -> name.isNotBlank() && email.contains("@") && password.length >= 8 && confirm.isNotBlank()
                        else -> email.isNotBlank() && password.isNotBlank()
                    }
                    PrimaryButton(
                        when { mfa -> "Verifikasi"; register -> "Daftar sekarang"; else -> "Masuk" },
                        modifier = Modifier.fillMaxWidth(),
                        icon = when { mfa -> Icons.Rounded.Shield; register -> Icons.Rounded.PersonAdd; else -> Icons.AutoMirrored.Rounded.Login },
                        loading = loading,
                        enabled = enabled,
                    ) { submit() }
                    if (mfa) {
                        Spacer(Modifier.height(12.dp))
                        Text(
                            "Kembali ke login",
                            color = Brand.Violet2,
                            fontWeight = FontWeight.Bold,
                            modifier = Modifier.align(Alignment.CenterHorizontally).clip(RoundedCornerShape(8.dp)).clickable { mfa = false; code = ""; error = null }.padding(8.dp),
                        )
                    }
                }
            }
            Spacer(Modifier.height(22.dp))
            Row(Modifier.enterAnim(5), verticalAlignment = Alignment.CenterVertically) {
                Text("Akun yang sama dengan versi web xyzku.my.id", style = MaterialTheme.typography.bodySmall, color = X.c.muted)
                Spacer(Modifier.width(4.dp))
                androidx.compose.material3.Icon(Icons.AutoMirrored.Rounded.ArrowForward, null, tint = X.c.muted, modifier = Modifier.height(14.dp))
            }
        }
    }
}

/** Two-option switch with a sliding highlight. */
@Composable
private fun Segmented(register: Boolean, onChange: (Boolean) -> Unit) {
    BoxWithConstraints(
        Modifier
            .fillMaxWidth()
            .height(46.dp)
            .clip(RoundedCornerShape(14.dp))
            .background(X.c.card2)
            .border(2.dp, if (X.c.isDark) X.c.line else Brand.Ink, RoundedCornerShape(14.dp))
            .padding(4.dp),
    ) {
        val half = maxWidth / 2
        val x by animateDpAsState(if (register) half else 0.dp, spring(dampingRatio = 0.7f, stiffness = Spring.StiffnessMediumLow), label = "seg")
        Box(Modifier.offset(x = x).width(half).fillMaxHeight().clip(RoundedCornerShape(10.dp)).background(Brand.gradient))
        Row(Modifier.fillMaxSize()) {
            listOf(false to "Masuk", true to "Daftar").forEach { (value, label) ->
                Box(
                    Modifier
                        .weight(1f)
                        .fillMaxHeight()
                        .clickable(remember { MutableInteractionSource() }, null) { onChange(value) },
                    contentAlignment = Alignment.Center,
                ) {
                    Text(label, fontWeight = FontWeight.ExtraBold, color = if (register == value) androidx.compose.ui.graphics.Color.White else X.c.muted)
                }
            }
        }
    }
}
