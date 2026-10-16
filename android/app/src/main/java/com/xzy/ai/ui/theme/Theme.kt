package com.xzy.ai.ui.theme

import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Typography
import androidx.compose.material3.darkColorScheme
import androidx.compose.material3.lightColorScheme
import androidx.compose.runtime.Composable
import androidx.compose.runtime.CompositionLocalProvider
import androidx.compose.runtime.Immutable
import androidx.compose.runtime.staticCompositionLocalOf
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.TextStyle
import androidx.compose.ui.text.font.FontFamily
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.sp

object Brand {
    val Violet = Color(0xFF7C3AED)
    val Violet2 = Color(0xFF8B5CF6)
    val Fuchsia = Color(0xFFC026D3)
    val Orange = Color(0xFFF97316)
    val Yellow = Color(0xFFFDE047)
    val Lime = Color(0xFFBEF264)
    val Cyan = Color(0xFF67E8F9)
    val Rose = Color(0xFFF43F5E)
    val Green = Color(0xFF34D399)
    val Ink = Color(0xFF0B0A12)

    val gradient = Brush.linearGradient(listOf(Violet, Fuchsia, Orange))
    val gradientSoft = Brush.linearGradient(listOf(Violet.copy(alpha = 0.85f), Fuchsia.copy(alpha = 0.85f)))
}

@Immutable
data class XzyColors(
    val bg: Color,
    val surface: Color,
    val card: Color,
    val card2: Color,
    val line: Color,
    val ink: Color,
    val text: Color,
    val muted: Color,
    val shadow: Color,
    val isDark: Boolean,
)

val DarkColors = XzyColors(
    bg = Color(0xFF0B0A12),
    surface = Color(0xFF12111C),
    card = Color(0xFF191726),
    card2 = Color(0xFF221F33),
    line = Color(0xFF2E2A45),
    ink = Color(0xFFF4F1FF),
    text = Color(0xFFE6E2F5),
    muted = Color(0xFF9A94B8),
    shadow = Color(0xFF000000),
    isDark = true,
)

val LightColors = XzyColors(
    bg = Color(0xFFFFF8EE),
    surface = Color(0xFFFFFFFF),
    card = Color(0xFFFFFFFF),
    card2 = Color(0xFFF5F0FF),
    line = Color(0xFF1B1530),
    ink = Color(0xFF14101F),
    text = Color(0xFF221C33),
    muted = Color(0xFF6B6485),
    shadow = Color(0xFF14101F),
    isDark = false,
)

val LocalXzy = staticCompositionLocalOf { DarkColors }

object X {
    val c: XzyColors
        @Composable get() = LocalXzy.current
}

private val Display = FontFamily.SansSerif

val XzyType = Typography(
    displaySmall = TextStyle(fontFamily = Display, fontWeight = FontWeight.Black, fontSize = 34.sp, lineHeight = 38.sp, letterSpacing = (-1).sp),
    headlineMedium = TextStyle(fontFamily = Display, fontWeight = FontWeight.Black, fontSize = 26.sp, lineHeight = 30.sp, letterSpacing = (-0.5).sp),
    headlineSmall = TextStyle(fontFamily = Display, fontWeight = FontWeight.ExtraBold, fontSize = 21.sp, lineHeight = 26.sp),
    titleLarge = TextStyle(fontFamily = Display, fontWeight = FontWeight.ExtraBold, fontSize = 18.sp, lineHeight = 24.sp),
    titleMedium = TextStyle(fontFamily = Display, fontWeight = FontWeight.Bold, fontSize = 15.sp, lineHeight = 21.sp),
    bodyLarge = TextStyle(fontSize = 15.sp, lineHeight = 23.sp),
    bodyMedium = TextStyle(fontSize = 14.sp, lineHeight = 21.sp),
    bodySmall = TextStyle(fontSize = 12.sp, lineHeight = 17.sp),
    labelLarge = TextStyle(fontWeight = FontWeight.Bold, fontSize = 14.sp),
    labelMedium = TextStyle(fontWeight = FontWeight.Bold, fontSize = 12.sp),
    labelSmall = TextStyle(fontWeight = FontWeight.Bold, fontSize = 11.sp, letterSpacing = 0.8.sp),
)

@Composable
fun XzyTheme(dark: Boolean, content: @Composable () -> Unit) {
    val c = if (dark) DarkColors else LightColors
    val scheme = if (dark) {
        darkColorScheme(
            primary = Brand.Violet2, onPrimary = Color.White, secondary = Brand.Fuchsia, tertiary = Brand.Orange,
            background = c.bg, onBackground = c.text, surface = c.surface, onSurface = c.text,
            surfaceVariant = c.card2, onSurfaceVariant = c.muted, outline = c.line, error = Brand.Rose,
        )
    } else {
        lightColorScheme(
            primary = Brand.Violet, onPrimary = Color.White, secondary = Brand.Fuchsia, tertiary = Brand.Orange,
            background = c.bg, onBackground = c.text, surface = c.surface, onSurface = c.text,
            surfaceVariant = c.card2, onSurfaceVariant = c.muted, outline = c.line, error = Brand.Rose,
        )
    }
    CompositionLocalProvider(LocalXzy provides c) {
        MaterialTheme(colorScheme = scheme, typography = XzyType, content = content)
    }
}
