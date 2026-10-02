# kotlinx.serialization — keep serializers for API DTOs
-keepattributes *Annotation*, InnerClasses
-dontnote kotlinx.serialization.**
-keepclassmembers class com.tempcompany.tempapp.data.api.** {
    *** Companion;
}
-keepclasseswithmembers class com.tempcompany.tempapp.data.api.** {
    kotlinx.serialization.KSerializer serializer(...);
}
# Retrofit
-keepattributes Signature, Exceptions
-dontwarn okhttp3.**
-dontwarn retrofit2.**
