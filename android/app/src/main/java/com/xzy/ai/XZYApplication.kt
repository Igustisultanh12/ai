package com.xzy.ai

import android.app.Application
import dagger.hilt.android.HiltAndroidApp

@HiltAndroidApp
class XZYApplication : Application() {
    
    override fun onCreate() {
        super.onCreate()
        // Initialize any SDK or library here if needed
    }
}