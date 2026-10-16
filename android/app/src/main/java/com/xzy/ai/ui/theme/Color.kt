package com.xzy.ai.ui.theme

import androidx.compose.ui.graphics.Color

// Modern Dark Surface Layers - Stepped Tonal Palette
val Surface0 = Color(0xFF05070C)  // Deepest - 3% lightness
val Surface1 = Color(0xFF0A0D12)  // 5% lightness
val Surface2 = Color(0xFF0F131C)  // 7% lightness  
val Surface3 = Color(0xFF161D2B)  // 10% lightness
val Surface4 = Color(0xFF1E2636)  // 14% lightness
val Surface5 = Color(0xFF2A3447)  // 18% lightness

// Light Surface Layers
val SurfaceLight0 = Color(0xFFF8F9FA)
val SurfaceLight1 = Color(0xFFFFFFFF)
val SurfaceLight2 = Color(0xFFF3F4F6)
val SurfaceLight3 = Color(0xFFE5E7EB)
val SurfaceLight4 = Color(0xFFD1D5DB)

// Luminous Accent Colors - High Saturation
val AccentCyan = Color(0xFF38BDF8)      // Primary accent - cyan/sky
val AccentEmerald = Color(0xFF6EE7B7)   // Success/positive
val AccentAmber = Color(0xFFE9A568)     // Warning/highlight
val AccentBlue = Color(0xFF3B6DFF)      // Info/link
val AccentRose = Color(0xFFF472B6)      // Error/destructive

// Muted Supporting Tints
val MutedCyan = Color(0xFF0E7490)
val MutedEmerald = Color(0xFF059669)
val MutedAmber = Color(0xFFD97706)
val MutedRose = Color(0xFFBE185D)

// Gradient Definitions
val GradientCyan = listOf(
    Color(0xFF06B6D4),
    Color(0xFF0EA5E9),
    Color(0xFF3B82F6)
)

val GradientEmerald = listOf(
    Color(0xFF10B981),
    Color(0xFF059669),
    Color(0xFF047857)
)

val GradientAmber = listOf(
    Color(0xFFF59E0B),
    Color(0xFFFB923C),
    Color(0xFFF97316)
)

val GradientPurple = listOf(
    Color(0xFF8B5CF6),
    Color(0xFF7C3AED),
    Color(0xFF6366F1)
)

val GradientRainbow = listOf(
    Color(0xFF06B6D4), // Cyan
    Color(0xFF8B5CF6), // Purple
    Color(0xFFEC4899), // Pink
    Color(0xFFF59E0B)  // Amber
)

// Message Bubble Colors - Modern with depth
val UserBubbleGradient = listOf(
    Color(0xFF3B82F6),
    Color(0xFF2563EB)
)
val UserBubbleText = Color(0xFFFFFFFF)

val AiBubbleGradient = listOf(
    Color(0xFF1E293B),
    Color(0xFF0F172A)
)
val AiBubbleText = Color(0xFFF1F5F9)

val UserBubbleGradientLight = listOf(
    Color(0xFF3B82F6),
    Color(0xFF2563EB)
)
val UserBubbleTextLight = Color(0xFFFFFFFF)

val AiBubbleGradientLight = listOf(
    Color(0xFFE5E7EB),
    Color(0xFFD1D5DB)
)
val AiBubbleTextLight = Color(0xFF111827)

// Status Colors - Vibrant and Clear
val SuccessColor = Color(0xFF10B981)
val WarningColor = Color(0xFFF59E0B)
val ErrorColor = Color(0xFFEF4444)
val InfoColor = Color(0xFF3B82F6)

// Special Effects
val GlowCyan = Color(0xFF06B6D4).copy(alpha = 0.4f)
val GlowPurple = Color(0xFF8B5CF6).copy(alpha = 0.4f)
val GlowPink = Color(0xFFEC4899).copy(alpha = 0.4f)

// Shimmer Animation Colors
val ShimmerDark = listOf(
    Surface2,
    Surface3,
    Surface2
)

val ShimmerLight = listOf(
    SurfaceLight2,
    SurfaceLight3,
    SurfaceLight2
)

// Legacy compatibility (akan dihapus bertahap)
val Purple80 = Color(0xFFD0BCFF)
val PurpleGrey80 = Color(0xFFCCC2DC)
val Pink80 = Color(0xFFEFB8C8)
val Purple40 = Color(0xFF6650a4)
val PurpleGrey40 = Color(0xFF625b71)
val Pink40 = Color(0xFF7D5260)

// Alias untuk backward compatibility
val XzyPrimary = AccentCyan
val XzySecondary = AccentAmber
val XzyTertiary = AccentEmerald
val XzyAccent = AccentRose
val ElectricBlue = AccentCyan
val VibrantPink = AccentRose
val DeepPurple = Color(0xFF8B5CF6)
val NeonOrange = AccentAmber

// Gradient aliases
val GradientStart = Color(0xFF06B6D4)
val GradientMiddle = Color(0xFF8B5CF6)
val GradientEnd = Color(0xFFEC4899)
