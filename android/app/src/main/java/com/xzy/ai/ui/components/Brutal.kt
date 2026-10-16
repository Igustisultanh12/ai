package com.xzy.ai.ui.components

import androidx.compose.animation.core.Animatable
import androidx.compose.animation.core.FastOutSlowInEasing
import androidx.compose.animation.core.Spring
import androidx.compose.animation.core.animateDpAsState
import androidx.compose.animation.core.animateFloatAsState
import androidx.compose.animation.core.spring
import androidx.compose.animation.core.tween
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.interaction.MutableInteractionSource
import androidx.compose.foundation.interaction.collectIsPressedAsState
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.BoxScope
import androidx.compose.foundation.layout.offset
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.remember
import androidx.compose.ui.Modifier
import androidx.compose.ui.composed
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.Shape
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.dp
import com.xzy.ai.ui.theme.X
import kotlinx.coroutines.coroutineScope
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch

/**
 * Neo-brutalist card: thick outline and a hard offset shadow. When clickable, pressing it
 * pushes the card into its shadow and springs back.
 */
@Composable
fun BrutalBox(
    modifier: Modifier = Modifier,
    shape: Shape = RoundedCornerShape(20.dp),
    color: Color = X.c.card,
    brush: Brush? = null,
    shadow: Dp = 5.dp,
    borderColor: Color = if (X.c.isDark) X.c.line else X.c.shadow,
    contentPadding: Dp = 16.dp,
    onClick: (() -> Unit)? = null,
    content: @Composable BoxScope.() -> Unit,
) {
    val interaction = remember { MutableInteractionSource() }
    val pressed by interaction.collectIsPressedAsState()
    val off by animateDpAsState(if (pressed) 1.dp else shadow, spring(dampingRatio = Spring.DampingRatioMediumBouncy, stiffness = Spring.StiffnessMedium), label = "shadow")
    val scale by animateFloatAsState(if (pressed) 0.975f else 1f, spring(dampingRatio = Spring.DampingRatioMediumBouncy), label = "scale")
    val shadowColor = if (X.c.isDark) Color.Black else X.c.shadow
    Box(modifier.graphicsLayer { scaleX = scale; scaleY = scale }) {
        // Hard shadow: same size as the card, shifted right/down
        Box(
            Modifier
                .matchParentSize()
                .padding(start = shadow, top = shadow)
                .clip(shape)
                .background(shadowColor),
        )
        Box(
            Modifier
                .offset(x = shadow - off, y = shadow - off)
                .padding(end = shadow, bottom = shadow)
                .clip(shape)
                .then(if (brush != null) Modifier.background(brush) else Modifier.background(color))
                .border(2.dp, borderColor, shape)
                .then(if (onClick != null) Modifier.clickable(interactionSource = interaction, indication = null, onClick = onClick) else Modifier)
                .padding(contentPadding),
            content = content,
        )
    }
}

/** Fade + slide in once, delayed by its index, for staggered lists. */
fun Modifier.enterAnim(index: Int, distance: Float = 60f): Modifier = composed {
    val alpha = remember { Animatable(0f) }
    val dy = remember { Animatable(distance) }
    LaunchedEffect(Unit) {
        delay((index.coerceAtMost(12) * 55).toLong())
        coroutineScope {
            launch { alpha.animateTo(1f, tween(420, easing = FastOutSlowInEasing)) }
            launch { dy.animateTo(0f, spring(dampingRatio = 0.72f, stiffness = Spring.StiffnessLow)) }
        }
    }
    graphicsLayer { this.alpha = alpha.value; translationY = dy.value }
}

/** Slight press-to-shrink for any clickable element. */
fun Modifier.bounceClick(onClick: () -> Unit): Modifier = composed {
    val interaction = remember { MutableInteractionSource() }
    val pressed by interaction.collectIsPressedAsState()
    val scale by animateFloatAsState(if (pressed) 0.92f else 1f, spring(dampingRatio = Spring.DampingRatioMediumBouncy), label = "bounce")
    graphicsLayer { scaleX = scale; scaleY = scale }.clickable(interactionSource = interaction, indication = null, onClick = onClick)
}
