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