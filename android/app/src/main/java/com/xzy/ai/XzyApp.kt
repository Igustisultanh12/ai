package com.xzy.ai

import android.app.Application
import com.xzy.ai.data.Agent
import com.xzy.ai.data.Api
import com.xzy.ai.data.Notifier

class XzyApp : Application() {
    override fun onCreate() {
        super.onCreate()
        Api.init(this)
        Notifier.init(this)
        Agent.init(this)
    }
}
