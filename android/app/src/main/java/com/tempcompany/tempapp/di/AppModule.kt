package com.tempcompany.tempapp.di

import com.tempcompany.tempapp.BuildConfig
import com.tempcompany.tempapp.data.api.ApiService
import com.tempcompany.tempapp.data.api.AuthInterceptor
import dagger.Module
import dagger.Provides
import dagger.hilt.InstallIn
import dagger.hilt.components.SingletonComponent
import kotlinx.serialization.json.Json
import okhttp3.MediaType.Companion.toMediaType
import okhttp3.OkHttpClient
import okhttp3.logging.HttpLoggingInterceptor
import retrofit2.Retrofit
import retrofit2.converter.kotlinx.serialization.asConverterFactory
import java.util.concurrent.TimeUnit
import javax.inject.Named
import javax.inject.Singleton

@Module
@InstallIn(SingletonComponent::class)
object AppModule {

    /**
     * Tolerant of additive server changes (ignoreUnknownKeys), strict about ours
     * (explicitNulls=false keeps optional request fields out of the JSON entirely —
     * clients/update.php requires "at least one of status/notes PRESENT").
     */
    @Provides
    @Singleton
    fun provideJson(): Json = Json {
        ignoreUnknownKeys = true
        explicitNulls = false
        coerceInputValues = true
    }

    private fun baseClient(): OkHttpClient.Builder = OkHttpClient.Builder()
        .connectTimeout(15, TimeUnit.SECONDS)
        .readTimeout(30, TimeUnit.SECONDS)
        .writeTimeout(30, TimeUnit.SECONDS)
        .apply {
            if (BuildConfig.DEBUG) {
                addInterceptor(HttpLoggingInterceptor().apply {
                    level = HttpLoggingInterceptor.Level.BASIC // never BODY — tokens in headers
                })
            }
        }

    private fun retrofit(json: Json, client: OkHttpClient): Retrofit = Retrofit.Builder()
        // The ONLY reference to the backend host in the entire app.
        .baseUrl(BuildConfig.API_BASE_URL)
        .client(client)
        .addConverterFactory(json.asConverterFactory("application/json".toMediaType()))
        .build()

    /** Bare client: login + refresh only. NO auth interceptor → no recursion. */
    @Provides
    @Singleton
    @Named("bare")
    fun provideBareApi(json: Json): ApiService =
        retrofit(json, baseClient().build()).create(ApiService::class.java)

    /** Authed client: everything else. Bearer + proactive refresh via AuthInterceptor. */
    @Provides
    @Singleton
    fun provideApi(json: Json, authInterceptor: AuthInterceptor): ApiService =
        retrofit(json, baseClient().addInterceptor(authInterceptor).build())
            .create(ApiService::class.java)
}
