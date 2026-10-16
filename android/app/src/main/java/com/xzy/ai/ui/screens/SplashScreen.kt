package com.xzy.ai.ui.screens

import androidx.compose.animation.AnimatedVisibility
import androidx.compose.animation.core.Animatable
import androidx.compose.animation.core.Spring
import androidx.compose.animation.core.spring
import androidx.compose.animation.core.tween
import androidx.compose.animation.fadeIn
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.padding
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.rounded.Refresh
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import com.xzy.ai.data.ApiException
import com.xzy.ai.data.Repo
import com.xzy.ai.data.Session
import com.xzy.ai.data.Updater
import com.xzy.ai.ui.components.AuroraBackground
import com.xzy.ai.ui.components.DotGrid
import com.xzy.ai.ui.components.ErrorBanner
import com.xzy.ai.ui.components.GradientSpinner
import com.xzy.ai.ui.components.LocalNav
import com.xzy.ai.ui.components.PrimaryButton
import com.xzy.ai.ui.components.SecondaryButton
import com.xzy.ai.ui.components.XzyLogo
import com.xzy.ai.ui.theme.X
import kotlinx.coroutines.async
import kotlinx.coroutines.coroutineScope
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch

@Composable
fun SplashScreen() {
    val nav = LocalNav.current
    val scale = remember { Animatable(0.3f) }
    val rot = remember { Animatable(-25f) }
    val textAlpha = remember { Animatable(0f) }
    var error by remember { mutableStateOf<String?>(null) }
    var attempt by remember { mutableIntStateOf(0) }

    LaunchedEffect(Unit) {
        coroutineScope {
            launch { scale.animateTo(1f, spring(dampingRatio = 0.45f, stiffness = Spring.StiffnessLow)) }
            launch { rot.animateTo(0f, spring(dampingRatio = 0.4f, stiffness = Spring.StiffnessLow)) }
            launch { delay(250); textAlpha.animateTo(1f, tween(500)) }
        }
    }

    LaunchedEffect(attempt) {
        error = null
        val minDelay = async { delay(1100) }
        val dest = try {
            Session.user = Repo.me()
            "main"
        } catch (e: ApiException) {
            if (e.code == 401 || e.code == 419 || e.code == 403) "auth" else { error = e.message; null }
        } catch (e: Exception) {
            error = e.message ?: "Gagal terhubung"
            null
        }
        minDelay.await()
        if (dest != null) {
            nav.navigate(dest) { popUpTo("splash") { inclusive = true } }
        }
    }

    Box(Modifier.fillMaxSize()) {
        AuroraBackground(intensity = 1.3f)
        DotGrid()
        Column(
            Modifier.fillMaxSize().padding(32.dp),
            horizontalAlignment = Alignment.CenterHorizontally,
            verticalArrangement = Arrangement.Center,
        ) {
            Box(Modifier.graphicsLayer { scaleX = scale.value; scaleY = scale.value; rotationZ = rot.value }) {
                XzyLogo(112.dp, animated = true)
            }
            Spacer(Modifier.height(28.dp))
            Column(Modifier.graphicsLayer { alpha = textAlpha.value; translationY = (1 - textAlpha.value) * 40f }, horizontalAlignment = Alignment.CenterHorizontally) {
                Text("XZY AI", style = MaterialTheme.typography.displaySmall, color = X.c.ink)
                Spacer(Modifier.height(6.dp))
                Text("Agen AI untuk ngoding, langsung dari saku kamu", style = MaterialTheme.typography.bodyMedium, color = X.c.muted, textAlign = TextAlign.Center)
            }
            Spacer(Modifier.height(36.dp))
            if (error == null) {
                GradientSpinner(30.dp, 3.5.dp)
            }
            AnimatedVisibility(error != null, enter = fadeIn()) {
                Column(Modifier.fillMaxWidth(), horizontalAlignment = Alignment.CenterHorizontally) {
                    ErrorBanner(error.orEmpty())
                    Spacer(Modifier.height(16.dp))
                    PrimaryButton("Coba lagi", icon = Icons.Rounded.Refresh, modifier = Modifier.fillMaxWidth()) { attempt++ }
                    Spacer(Modifier.height(12.dp))
                    SecondaryButton("Masuk dengan akun lain", modifier = Modifier.fillMaxWidth()) {
                        nav.navigate("auth") { popUpTo("splash") { inclusive = true } }
                    }
                }
            }
        }
        Text(
            "versi ${Updater.currentVersion}",
            style = MaterialTheme.typography.labelSmall,
            color = X.c.muted,
            modifier = Modifier.align(Alignment.BottomCenter).navigationBarsPadding().padding(bottom = 16.dp),
        )
    }
}
