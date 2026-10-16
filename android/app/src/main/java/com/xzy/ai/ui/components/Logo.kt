package com.xzy.ai.ui.components

import androidx.compose.animation.core.LinearEasing
import androidx.compose.animation.core.animateFloat
import androidx.compose.animation.core.infiniteRepeatable
import androidx.compose.animation.core.rememberInfiniteTransition
import androidx.compose.animation.core.tween
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.offset
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.StrokeCap
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.dp
import com.xzy.ai.ui.theme.Brand

/** The XZY mark: gradient tile with a bold X and a yellow spark. */
@Composable
fun XzyLogo(size: Dp = 48.dp, animated: Boolean = false) {
    val t = rememberInfiniteTransition(label = "logo")
    val shift by t.animateFloat(0f, 1f, infiniteRepeatable(tween(4000, easing = LinearEasing)), label = "s")
    val spin by t.animateFloat(0f, 360f, infiniteRepeatable(tween(6000, easing = LinearEasing)), label = "r")
    val corner = size * 0.28f
    val brush = if (animated) {
        Brush.sweepGradient(listOf(Brand.Violet, Brand.Fuchsia, Brand.Orange, Brand.Violet), center = Offset.Unspecified)
    } else Brand.gradient
    Box(Modifier.size(size)) {
        Box(Modifier.size(size).offset(size * 0.07f, size * 0.07f).clip(RoundedCornerShape(corner)).background(Brand.Ink))
        Box(
            Modifier
                .size(size)
                .clip(RoundedCornerShape(corner))
                .background(Brand.Violet)
                .border(size * 0.045f, Brand.Ink, RoundedCornerShape(corner)),
            contentAlignment = Alignment.Center,
        ) {
            Canvas(Modifier.size(size).graphicsLayer { if (animated) rotationZ = spin }) {
                drawCircle(brush, radius = this.size.maxDimension * (0.75f + 0.05f * shift))
            }
            Canvas(Modifier.size(size)) {
                val w = this.size.width
                val stroke = w * 0.13f
                val p = w * 0.3f
                drawLine(Color.White, Offset(p, p), Offset(w - p, w - p), stroke, StrokeCap.Round)
                drawLine(Color.White, Offset(w - p, p), Offset(p, w - p), stroke, StrokeCap.Round)
                drawCircle(Brand.Yellow, radius = w * 0.085f, center = Offset(w * 0.79f, w * 0.21f))
                drawCircle(Brand.Ink, radius = w * 0.085f, center = Offset(w * 0.79f, w * 0.21f), style = androidx.compose.ui.graphics.drawscope.Stroke(w * 0.025f))
            }
        }
    }
}
