package com.xzy.ai.ui.components

import androidx.compose.animation.core.FastOutSlowInEasing
import androidx.compose.animation.core.LinearEasing
import androidx.compose.animation.core.RepeatMode
import androidx.compose.animation.core.animateFloat
import androidx.compose.animation.core.animateFloatAsState
import androidx.compose.animation.core.infiniteRepeatable
import androidx.compose.animation.core.rememberInfiniteTransition
import androidx.compose.animation.core.tween
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.OutlinedTextFieldDefaults
import androidx.compose.material3.Text
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.rounded.Visibility
import androidx.compose.material.icons.rounded.VisibilityOff
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.input.PasswordVisualTransformation
import androidx.compose.ui.text.input.VisualTransformation
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.xzy.ai.ui.theme.Brand
import com.xzy.ai.ui.theme.X
import java.text.NumberFormat
import java.util.Locale
import kotlin.math.cos
import kotlin.math.sin

/** Slowly drifting colour blobs behind the screens. */
@Composable
fun AuroraBackground(modifier: Modifier = Modifier, intensity: Float = 1f) {
    val t = rememberInfiniteTransition(label = "aurora")
    val phase by t.animateFloat(0f, (2 * Math.PI).toFloat(), infiniteRepeatable(tween(18000, easing = LinearEasing)), label = "phase")
    val dark = X.c.isDark
    val alpha = (if (dark) 0.38f else 0.28f) * intensity
    Canvas(modifier.fillMaxSize()) {
        val w = size.width
        val h = size.height
        fun blob(color: Color, cx: Float, cy: Float, r: Float) {
            drawCircle(Brush.radialGradient(listOf(color.copy(alpha = alpha), Color.Transparent), center = Offset(cx, cy), radius = r), radius = r, center = Offset(cx, cy))
        }
        blob(Brand.Violet, w * (0.2f + 0.12f * cos(phase)), h * (0.18f + 0.06f * sin(phase * 2)), w * 0.75f)
        blob(Brand.Fuchsia, w * (0.85f + 0.1f * sin(phase)), h * (0.45f + 0.08f * cos(phase)), w * 0.65f)
        blob(Brand.Orange, w * (0.35f + 0.15f * sin(phase * 1.5f)), h * (0.92f + 0.05f * cos(phase * 2)), w * 0.6f)
    }
}

/** Dotted texture like the web app's background. */
@Composable
fun DotGrid(modifier: Modifier = Modifier) {
    val color = X.c.muted.copy(alpha = if (X.c.isDark) 0.12f else 0.18f)
    Canvas(modifier.fillMaxSize()) {
        val step = 22.dp.toPx()
        var y = step / 2
        while (y < size.height) {
            var x = step / 2
            while (x < size.width) {
                drawCircle(color, radius = 1.2.dp.toPx(), center = Offset(x, y))
                x += step
            }
            y += step
        }
    }
}

/** Gradient button with a hard shadow and press bounce. */
@Composable
fun PrimaryButton(
    text: String,
    modifier: Modifier = Modifier,
    icon: ImageVector? = null,
    loading: Boolean = false,
    enabled: Boolean = true,
    brush: Brush = Brand.gradient,
    onClick: () -> Unit,
) {
    val t = rememberInfiniteTransition(label = "btn")
    val shift by t.animateFloat(0f, 1f, infiniteRepeatable(tween(3500, easing = LinearEasing), RepeatMode.Reverse), label = "shift")
    val animated = Brush.linearGradient(
        listOf(Brand.Violet, Brand.Fuchsia, Brand.Orange),
        start = Offset(-400f + 800f * shift, 0f),
        end = Offset(400f + 800f * shift, 200f),
    )
    BrutalBox(
        modifier = modifier.graphicsLayer { alpha = if (enabled) 1f else 0.55f },
        shape = RoundedCornerShape(16.dp),
        brush = if (brush == Brand.gradient) animated else brush,
        shadow = 4.dp,
        borderColor = X.c.shadow.let { if (X.c.isDark) Color.Black else it },
        contentPadding = 0.dp,
        onClick = if (enabled && !loading) onClick else null,
    ) {
        Row(
            Modifier.fillMaxWidth().padding(horizontal = 18.dp, vertical = 14.dp),
            horizontalArrangement = Arrangement.Center,
            verticalAlignment = Alignment.CenterVertically,
        ) {
            if (loading) {
                CircularProgressIndicator(Modifier.size(18.dp), color = Color.White, strokeWidth = 2.5.dp)
                Spacer(Modifier.width(10.dp))
            } else if (icon != null) {
                Icon(icon, null, tint = Color.White, modifier = Modifier.size(20.dp))
                Spacer(Modifier.width(8.dp))
            }
            Text(text, color = Color.White, style = MaterialTheme.typography.labelLarge, fontSize = 15.sp)
        }
    }
}

/** Flat secondary button (outlined, solid fill). */
@Composable
fun SecondaryButton(text: String, modifier: Modifier = Modifier, icon: ImageVector? = null, color: Color = X.c.card, textColor: Color = X.c.ink, onClick: () -> Unit) {
    BrutalBox(modifier = modifier, shape = RoundedCornerShape(14.dp), color = color, shadow = 3.dp, contentPadding = 0.dp, onClick = onClick) {
        Row(Modifier.padding(horizontal = 14.dp, vertical = 11.dp), verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.Center) {
            if (icon != null) {
                Icon(icon, null, tint = textColor, modifier = Modifier.size(18.dp))
                Spacer(Modifier.width(6.dp))
            }
            Text(text, color = textColor, style = MaterialTheme.typography.labelLarge, maxLines = 1)
        }
    }
}

@Composable
fun Field(
    value: String,
    onValueChange: (String) -> Unit,
    label: String,
    modifier: Modifier = Modifier,
    leading: ImageVector? = null,
    password: Boolean = false,
    keyboardType: KeyboardType = KeyboardType.Text,
    singleLine: Boolean = true,
    minLines: Int = 1,
    placeholder: String? = null,
) {
    var visible by remember { mutableStateOf(false) }
    OutlinedTextField(
        value = value,
        onValueChange = onValueChange,
        modifier = modifier.fillMaxWidth(),
        label = { Text(label) },
        placeholder = placeholder?.let { { Text(it, color = X.c.muted) } },
        leadingIcon = leading?.let { { Icon(it, null, tint = X.c.muted) } },
        trailingIcon = if (password) {
            {
                IconButton(onClick = { visible = !visible }) {
                    Icon(if (visible) Icons.Rounded.VisibilityOff else Icons.Rounded.Visibility, null, tint = X.c.muted)
                }
            }
        } else null,
        visualTransformation = if (password && !visible) PasswordVisualTransformation() else VisualTransformation.None,
        keyboardOptions = KeyboardOptions(keyboardType = if (password) KeyboardType.Password else keyboardType),
        singleLine = singleLine,
        minLines = minLines,
        shape = RoundedCornerShape(16.dp),
        colors = OutlinedTextFieldDefaults.colors(
            focusedBorderColor = Brand.Violet2,
            unfocusedBorderColor = X.c.line,
            focusedContainerColor = X.c.card,
            unfocusedContainerColor = X.c.card,
            focusedLabelColor = Brand.Violet2,
            unfocusedLabelColor = X.c.muted,
            cursorColor = Brand.Fuchsia,
            focusedTextColor = X.c.ink,
            unfocusedTextColor = X.c.ink,
        ),
    )
}

@Composable
fun Chip(text: String, modifier: Modifier = Modifier, color: Color = Brand.Yellow, textColor: Color = Brand.Ink, icon: ImageVector? = null) {
    Row(
        modifier
            .clip(RoundedCornerShape(50))
            .background(color)
            .border(1.5.dp, Brand.Ink, RoundedCornerShape(50))
            .padding(horizontal = 10.dp, vertical = 4.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        if (icon != null) {
            Icon(icon, null, tint = textColor, modifier = Modifier.size(13.dp))
            Spacer(Modifier.width(4.dp))
        }
        Text(text, color = textColor, fontSize = 11.sp, fontWeight = FontWeight.ExtraBold, maxLines = 1, overflow = TextOverflow.Ellipsis)
    }
}

/** Rounded tile with an icon, used for stats and list rows. */
@Composable
fun IconTile(icon: ImageVector, bg: Color, tint: Color = Brand.Ink, size: Dp = 42.dp) {
    Box(
        Modifier
            .size(size)
            .clip(RoundedCornerShape(12.dp))
            .background(bg)
            .border(2.dp, Brand.Ink, RoundedCornerShape(12.dp)),
        contentAlignment = Alignment.Center,
    ) {
        Icon(icon, null, tint = tint, modifier = Modifier.size(size * 0.5f))
    }
}

@Composable
fun Avatar(initials: String, size: Dp = 40.dp) {
    Box(
        Modifier.size(size).clip(CircleShape).background(Brand.gradient).border(2.dp, Brand.Ink, CircleShape),
        contentAlignment = Alignment.Center,
    ) {
        Text(initials, color = Color.White, fontWeight = FontWeight.Black, fontSize = (size.value * 0.38f).sp)
    }
}

/** Progress bar that fills up with an animation when it appears. */
@Composable
fun AnimatedBar(fraction: Float, modifier: Modifier = Modifier, brush: Brush = Brand.gradient, height: Dp = 10.dp) {
    var started by remember { mutableStateOf(false) }
    LaunchedEffect(Unit) { started = true }
    val f by animateFloatAsState(if (started) fraction.coerceIn(0f, 1f) else 0f, tween(1100, easing = FastOutSlowInEasing), label = "bar")
    Box(
        modifier
            .fillMaxWidth()
            .height(height)
            .clip(RoundedCornerShape(50))
            .background(X.c.card2)
            .border(1.5.dp, if (X.c.isDark) X.c.line else Brand.Ink, RoundedCornerShape(50)),
    ) {
        Box(Modifier.fillMaxWidth(f).height(height).clip(RoundedCornerShape(50)).background(brush))
    }
}

/** Number that counts up when it first appears. */
@Composable
fun CountUp(value: Long, style: androidx.compose.ui.text.TextStyle, color: Color = X.c.ink) {
    var started by remember(value) { mutableStateOf(false) }
    LaunchedEffect(value) { started = true }
    val v by animateFloatAsState(if (started) value.toFloat() else 0f, tween(1200, easing = FastOutSlowInEasing), label = "count")
    Text(formatNumber(v.toLong()), style = style, color = color)
}

fun formatNumber(n: Long): String = NumberFormat.getIntegerInstance(Locale("id", "ID")).format(n)

/** Shimmering placeholder block while loading. */
@Composable
fun Shimmer(modifier: Modifier = Modifier, height: Dp = 80.dp) {
    val t = rememberInfiniteTransition(label = "shimmer")
    val x by t.animateFloat(-1f, 2f, infiniteRepeatable(tween(1300, easing = LinearEasing)), label = "x")
    val base = X.c.card2
    val hi = if (X.c.isDark) Color(0xFF2E2A45) else Color(0xFFFFFFFF)
    Box(
        modifier
            .fillMaxWidth()
            .height(height)
            .clip(RoundedCornerShape(18.dp))
            .background(Brush.linearGradient(listOf(base, hi, base), start = Offset(x * 900f, 0f), end = Offset(x * 900f + 600f, 300f))),
    )
}

/** Three bouncing dots, shown while the agent thinks. */
@Composable
fun TypingDots(color: Color = Brand.Fuchsia) {
    val t = rememberInfiniteTransition(label = "dots")
    Row(horizontalArrangement = Arrangement.spacedBy(4.dp), verticalAlignment = Alignment.CenterVertically) {
        for (i in 0 until 3) {
            val y by t.animateFloat(0f, 1f, infiniteRepeatable(tween(520, delayMillis = i * 140, easing = FastOutSlowInEasing), RepeatMode.Reverse), label = "d$i")
            Box(Modifier.graphicsLayer { translationY = -y * 9f }.size(7.dp).clip(CircleShape).background(color))
        }
    }
}

/** Spinning gradient ring used as a loading indicator. */
@Composable
fun GradientSpinner(size: Dp = 18.dp, stroke: Dp = 2.5.dp) {
    val t = rememberInfiniteTransition(label = "spin")
    val angle by t.animateFloat(0f, 360f, infiniteRepeatable(tween(900, easing = LinearEasing)), label = "a")
    Canvas(Modifier.size(size).graphicsLayer { rotationZ = angle }) {
        drawArc(
            brush = Brush.sweepGradient(listOf(Color.Transparent, Brand.Violet2, Brand.Fuchsia, Brand.Orange)),
            startAngle = 0f, sweepAngle = 300f, useCenter = false,
            style = androidx.compose.ui.graphics.drawscope.Stroke(width = stroke.toPx(), cap = androidx.compose.ui.graphics.StrokeCap.Round),
        )
    }
}

@Composable
fun SectionTitle(title: String, modifier: Modifier = Modifier, action: (@Composable () -> Unit)? = null) {
    Row(modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
        Text(title, style = MaterialTheme.typography.titleLarge, color = X.c.ink, modifier = Modifier.weight(1f))
        if (action != null) action()
    }
}

@Composable
fun EmptyState(icon: ImageVector, title: String, subtitle: String, modifier: Modifier = Modifier, action: (@Composable () -> Unit)? = null) {
    val t = rememberInfiniteTransition(label = "empty")
    val y by t.animateFloat(0f, 1f, infiniteRepeatable(tween(1600, easing = FastOutSlowInEasing), RepeatMode.Reverse), label = "float")
    Column(modifier.fillMaxWidth().padding(28.dp), horizontalAlignment = Alignment.CenterHorizontally) {
        Box(Modifier.graphicsLayer { translationY = -y * 14f; rotationZ = -6f + 12f * y }) {
            IconTile(icon, Brand.Yellow, size = 72.dp)
        }
        Spacer(Modifier.height(18.dp))
        Text(title, style = MaterialTheme.typography.titleLarge, color = X.c.ink)
        Spacer(Modifier.height(6.dp))
        Text(subtitle, style = MaterialTheme.typography.bodyMedium, color = X.c.muted, textAlign = androidx.compose.ui.text.style.TextAlign.Center)
        if (action != null) {
            Spacer(Modifier.height(18.dp))
            action()
        }
    }
}

@Composable
fun ErrorBanner(message: String, modifier: Modifier = Modifier) {
    Row(
        modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(14.dp))
            .background(Brand.Rose.copy(alpha = 0.14f))
            .border(1.5.dp, Brand.Rose.copy(alpha = 0.6f), RoundedCornerShape(14.dp))
            .padding(12.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Text(message, color = if (X.c.isDark) Color(0xFFFDA4AF) else Color(0xFFBE123C), style = MaterialTheme.typography.bodyMedium)
    }
}
