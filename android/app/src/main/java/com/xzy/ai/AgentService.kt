package com.xzy.ai

import android.app.Service
import android.content.Context
import android.content.Intent
import android.content.pm.ServiceInfo
import android.os.Build
import android.os.IBinder
import androidx.compose.runtime.snapshotFlow
import androidx.core.app.ServiceCompat
import androidx.core.content.ContextCompat
import com.xzy.ai.data.Agent
import com.xzy.ai.data.Notifier
import kotlinx.coroutines.Job
import kotlinx.coroutines.MainScope
import kotlinx.coroutines.cancel
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch

/**
 * Keeps the process alive while an agent is working, so answers still arrive (and notify)
 * after the user leaves the app. Stops itself when no run is active.
 */
class AgentService : Service() {
    private val scope = MainScope()
    private var watcher: Job? = null

    override fun onBind(intent: Intent?): IBinder? = null

    override fun onStartCommand(intent: Intent?, flags: Int, startId: Int): Int {
        val n = Notifier.running(Agent.activeCount().coerceAtLeast(1), null)
        try {
            ServiceCompat.startForeground(
                this, Notifier.RUNNING_ID, n,
                if (Build.VERSION.SDK_INT >= 29) ServiceInfo.FOREGROUND_SERVICE_TYPE_DATA_SYNC else 0,
            )
        } catch (_: Exception) {
            // Not allowed right now (e.g. started from the background); the run continues anyway
        }
        if (watcher == null) {
            watcher = scope.launch {
                snapshotFlow { Agent.activeCount() }.collect { count ->
                    if (count == 0) {
                        delay(800)
                        if (Agent.activeCount() == 0) {
                            ServiceCompat.stopForeground(this@AgentService, ServiceCompat.STOP_FOREGROUND_REMOVE)
                            stopSelf()
                        }
                    } else {
                        Notifier.notifyRunning(count, null)
                    }
                }
            }
        }
        return START_NOT_STICKY
    }

    override fun onDestroy() {
        scope.cancel()
        super.onDestroy()
    }

    companion object {
        fun start(context: Context) {
            try {
                ContextCompat.startForegroundService(context, Intent(context, AgentService::class.java))
            } catch (_: Exception) {
            }
        }
    }
}
