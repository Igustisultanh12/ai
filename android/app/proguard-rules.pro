# App code uses org.json (no reflection); keep model names readable in crash logs
-keepattributes SourceFile,LineNumberTable
-dontwarn org.conscrypt.**
-dontwarn org.bouncycastle.**
-dontwarn org.openjsse.**
