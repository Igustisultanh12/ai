package com.xzy.ai

import android.content.Intent
import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.SystemBarStyle
import androidx.activity.compose.setContent
import androidx.activity.enableEdgeToEdge
import androidx.compose.runtime.LaunchedEffect
import androidx.lifecycle.lifecycleScope
import com.xzy.ai.data.Agent
import com.xzy.ai.data.DeepLink
import com.xzy.ai.data.Prefs
import com.xzy.ai.data.Session
import com.xzy.ai.data.Updater
import com.xzy.ai.ui.XzyRoot
import kotlinx.coroutines.launch

class MainActivity : ComponentActivity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        applyBars(Prefs.dark)
        super.onCreate(savedInstanceState)
        handle(intent)
        setContent {
            val dark = Prefs.dark
            LaunchedEffect(dark) { applyBars(dark) }
            XzyRoot()
        }
    }

    /** Light or dark status/navigation bar icons to match the app theme. */
    private fun applyBars(dark: Boolean) {
        val t = android.graphics.Color.TRANSPARENT
        val style = if (dark) SystemBarStyle.dark(t) else SystemBarStyle.light(t, t)
        enableEdgeToEdge(statusBarStyle = style, navigationBarStyle = style)
    }

    override fun onNewIntent(intent: Intent) {
        super.onNewIntent(intent)
        handle(intent)
    }

    private fun handle(intent: Intent?) {
        intent?.getStringExtra("target")?.let { DeepLink.pending = it }
        intent?.removeExtra("target")
    }

    override fun onStart() {
        super.onStart()
        Agent.foreground = true
        if (Session.user != null) lifecycleScope.launch { Updater.checkIfDue() }
    }

    override fun onStop() {
        Agent.foreground = false
        super.onStop()
    }
}
