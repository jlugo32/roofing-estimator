package com.tempcompany.tempapp

import android.app.Application
import android.app.NotificationChannel
import android.app.NotificationManager
import dagger.hilt.android.HiltAndroidApp

@HiltAndroidApp
class TempApp : Application() {

    override fun onCreate() {
        super.onCreate()
        createLeadChannel()
    }

    /** Channel for new-lead push alerts (inert until FCM is configured). */
    private fun createLeadChannel() {
        val manager = getSystemService(NOTIFICATION_SERVICE) as NotificationManager
        manager.createNotificationChannel(
            NotificationChannel(
                CHANNEL_LEADS,
                getString(R.string.channel_leads_name),
                NotificationManager.IMPORTANCE_HIGH,
            ).apply {
                description = getString(R.string.channel_leads_description)
            }
        )
    }

    companion object {
        const val CHANNEL_LEADS = "leads"
    }
}
