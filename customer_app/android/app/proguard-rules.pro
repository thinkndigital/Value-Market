-keepclassmembers class * {
    @android.webkit.JavascriptInterface <methods>;
}

-keepattributes JavascriptInterface
-keepattributes *Annotation*

-dontwarn com.razorpay.**
-keep class com.razorpay.** {*;}

-optimizations !method/inlining/*

-keepclasseswithmembers class * {
  public void onPayment*(...);
}

# Keep Pusher classes
-keep class com.pusher.** { *; }
-keepattributes *Annotation*
-keepclassmembers class com.pusher.** { *; }

# Keep OkHttp (used by Pusher)
-dontwarn okhttp3.**
-keep class okhttp3.** { *; }

# Keep Gson (used for JSON serialization/deserialization)
-dontwarn com.google.gson.**
-keep class com.google.gson.** { *; }

# Keep WebSocket-related classes
-dontwarn org.java_websocket.**
-keep class org.java_websocket.** { *; }  

# Flutter-specific rules
-keep class io.flutter.** { *; }
-keep class io.flutter.embedding.** { *; }

# Keep application classes
-keepclassmembers class * extends android.app.Application {
    *;
}

# Keep annotation classes
-keep @interface androidx.annotation.Keep

# Prevent obfuscation for Firebase and Glide (if used)
-keep class com.google.firebase.** { *; }
-keep class com.bumptech.glide.** { *; }

# If you're using Gson or other serialization libraries:
-keepclassmembers class * {
    @com.google.gson.annotations.SerializedName <fields>;
}

# Retain access to model classes
-keep class com.wrteam.eshop.pro.models.** { *; }


#If ProGuard is enabled, it might be stripping essential speech recognition classes. To fix this, add the following rules to android/app/proguard-rules.pro:
-keep class android.speech.** { *; }
-keep class com.google.speech.** { *; }
-keep class com.android.speech.** { *; }

#=====for deeplinksing=====
# SPDX-FileCopyrightText: 2016, microG Project Team
# SPDX-License-Identifier: CC0-1.0

# Keep AutoSafeParcelables
-keep public class * extends org.microg.safeparcel.AutoSafeParcelable {
    @org.microg.safeparcel.SafeParcelable.Field *;
    @org.microg.safeparcel.SafeParceled *;
}

# Keep asInterface method cause it's accessed from SafeParcel
-keepattributes InnerClasses
-keepclassmembers interface * extends android.os.IInterface {
    public static class *;
}
-keep public class * extends android.os.Binder { public static *; }