package com.xzy.ai.ui.components

import androidx.compose.animation.*
import androidx.compose.animation.core.*
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.interaction.MutableInteractionSource
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material.ripple.rememberRipple
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.alpha
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.rotate
import androidx.compose.ui.draw.scale
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.Path
import androidx.compose.ui.graphics.drawscope.DrawScope
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.xzy.ai.ui.theme.*
import kotlin.math.cos
import kotlin.math.sin

/**
 * Animated Gradient Button dengan ripple effect
 */
@Composable
fun AnimatedGradientButton(
    text: String,
    onClick: () -> Unit,
    modifier: Modifier = Modifier,
    enabled: Boolean = true,
    isLoading: Boolean = false,
    icon: ImageVector? = null,
    gradient: List<Color> = listOf(BrightCyan, ElectricPurple, VividMagenta)
) {
    var isPressed by remember { mutableStateOf(false) }
    val scale by animateFloatAsState(
        targetValue = if (isPressed) 0.96f else 1f,
        animationSpec = spring(dampingRatio = Spring.DampingRatioMediumBouncy),
        label = "buttonScale"
    )
    
    val infiniteTransition = rememberInfiniteTransition(label = "gradientShift")
    val offset by infiniteTransition.animateFloat(
        initialValue = 0f,
        targetValue = 1000f,
        animationSpec = infiniteRepeatable(
            animation = tween(3000, easing = LinearEasing),
            repeatMode = RepeatMode.Reverse
        ),
        label = "gradientOffset"
    )
    
    Box(
        modifier = modifier
            .scale(scale)
            .height(56.dp)
            .clip(RoundedCornerShape(28.dp))
            .background(
                if (enabled) {
                    Brush.linearGradient(
                        colors = gradient,
                        start = Offset(offset, offset),
                        end = Offset(offset + 500f, offset + 500f)
                    )
                } else {
                    Brush.linearGradient(
                        colors = listOf(Color.Gray.copy(alpha = 0.3f), Color.Gray.copy(alpha = 0.3f))
                    )
                }
            )
            .clickable(
                enabled = enabled && !isLoading,
                onClick = onClick,
                indication = rememberRipple(color = Color.White),
                interactionSource = remember { MutableInteractionSource() }
            ),
        contentAlignment = Alignment.Center
    ) {
        Row(
            horizontalArrangement = Arrangement.Center,
            verticalAlignment = Alignment.CenterVertically,
            modifier = Modifier.padding(horizontal = 24.dp)
        ) {
            if (isLoading) {
                CircularProgressIndicator(
                    modifier = Modifier.size(20.dp),
                    color = Color.White,
                    strokeWidth = 2.dp
                )
                Spacer(modifier = Modifier.width(12.dp))
            } else if (icon != null) {
                Icon(
                    imageVector = icon,
                    contentDescription = null,
                    tint = Color.White,
                    modifier = Modifier.size(20.dp)
                )
                Spacer(modifier = Modifier.width(12.dp))
            }
            
            Text(
                text = text,
                color = Color.White,
                fontWeight = FontWeight.Bold,
                fontSize = 16.sp
            )
        }
    }
}

/**
 * Shimmer Loading Effect
 */
@Composable
fun ShimmerBox(
    modifier: Modifier = Modifier,
    isLoading: Boolean = true
) {
    val infiniteTransition = rememberInfiniteTransition(label = "shimmer")
    val offset by infiniteTransition.animateFloat(
        initialValue = -1000f,
        targetValue = 1000f,
        animationSpec = infiniteRepeatable(
            animation = tween(1000, easing = LinearEasing),
            repeatMode = RepeatMode.Restart
        ),
        label = "shimmerOffset"
    )
    
    Box(
        modifier = modifier
            .background(
                if (isLoading) {
                    Brush.linearGradient(
                        colors = listOf(
                            SurfaceLevel2.copy(alpha = 0.3f),
                            SurfaceLevel3.copy(alpha = 0.5f),
                            SurfaceLevel2.copy(alpha = 0.3f)
                        ),
                        start = Offset(offset, offset),
                        end = Offset(offset + 400f, offset + 400f)
                    )
                } else {
                    Brush.linearGradient(
                        colors = listOf(SurfaceLevel2, SurfaceLevel2)
                    )
                }
            )
    )
}

/**
 * Animated Floating Action Button dengan pulse effect
 */
@Composable
fun PulsatingFAB(
    onClick: () -> Unit,
    icon: ImageVector,
    modifier: Modifier = Modifier,
    containerColor: Color = ElectricPurple,
    contentColor: Color = Color.White
) {
    val infiniteTransition = rememberInfiniteTransition(label = "pulse")
    val pulseScale by infiniteTransition.animateFloat(
        initialValue = 1f,
        targetValue = 1.1f,
        animationSpec = infiniteRepeatable(
            animation = tween(800, easing = FastOutSlowInEasing),
            repeatMode = RepeatMode.Reverse
        ),
        label = "pulseScale"
    )
    
    val rotation by infiniteTransition.animateFloat(
        initialValue = 0f,
        targetValue = 360f,
        animationSpec = infiniteRepeatable(
            animation = tween(20000, easing = LinearEasing),
            repeatMode = RepeatMode.Restart
        ),
        label = "rotation"
    )
    
    FloatingActionButton(
        onClick = onClick,
        modifier = modifier
            .scale(pulseScale)
            .rotate(rotation * 0.1f),
        containerColor = containerColor,
        contentColor = contentColor,
        elevation = FloatingActionButtonDefaults.elevation(8.dp, 12.dp)
    ) {
        Icon(
            imageVector = icon,
            contentDescription = null,
            modifier = Modifier.size(28.dp)
        )
    }
}

/**
 * Animated Wave Background
 */
@Composable
fun WaveBackground(
    modifier: Modifier = Modifier,
    colors: List<Color> = listOf(
        BrightCyan.copy(alpha = 0.1f),
        ElectricPurple.copy(alpha = 0.1f),
        VividMagenta.copy(alpha = 0.1f)
    )
) {
    val infiniteTransition = rememberInfiniteTransition(label = "wave")
    val animationProgress by infiniteTransition.animateFloat(
        initialValue = 0f,
        targetValue = 1f,
        animationSpec = infiniteRepeatable(
            animation = tween(8000, easing = LinearEasing),
            repeatMode = RepeatMode.Restart
        ),
        label = "waveProgress"
    )
    
    Canvas(modifier = modifier.fillMaxSize()) {
        val width = size.width
        val height = size.height
        val waveHeight = height * 0.15f
        
        colors.forEachIndexed { index, color ->
            val path = Path().apply {
                moveTo(0f, height * 0.5f)
                
                for (x in 0..width.toInt()) {
                    val y = height * 0.5f + 
                            waveHeight * sin((x / width * 4 * Math.PI) + (animationProgress * 2 * Math.PI) + (index * Math.PI / 3)).toFloat()
                    lineTo(x.toFloat(), y)
                }
                
                lineTo(width, height)
                lineTo(0f, height)
                close()
            }
            
            drawPath(
                path = path,
                brush = Brush.verticalGradient(
                    colors = listOf(color, color.copy(alpha = 0f)),
                    startY = height * 0.3f,
                    endY = height
                )
            )
        }
    }
}

/**
 * Particle Animation Effect
 */
data class Particle(
    var x: Float,
    var y: Float,
    var radius: Float,
    var alpha: Float,
    var speedX: Float,
    var speedY: Float,
    var color: Color
)

@Composable
fun ParticleField(
    modifier: Modifier = Modifier,
    particleCount: Int = 30,
    colors: List<Color> = listOf(BrightCyan, ElectricPurple, VividMagenta, SunsetOrange)
) {
    var particles by remember { mutableStateOf<List<Particle>>(emptyList()) }
    
    val infiniteTransition = rememberInfiniteTransition(label = "particles")
    val animationTime by infiniteTransition.animateFloat(
        initialValue = 0f,
        targetValue = 1f,
        animationSpec = infiniteRepeatable(
            animation = tween(50, easing = LinearEasing),
            repeatMode = RepeatMode.Restart
        ),
        label = "particleTime"
    )
    
    Canvas(modifier = modifier.fillMaxSize()) {
        if (particles.isEmpty()) {
            particles = List(particleCount) {
                Particle(
                    x = (0..size.width.toInt()).random().toFloat(),
                    y = (0..size.height.toInt()).random().toFloat(),
                    radius = (2..6).random().toFloat(),
                    alpha = (0.3f..0.8f).random(),
                    speedX = (-1f..1f).random(),
                    speedY = (-1f..1f).random(),
                    color = colors.random()
                )
            }
        }
        
        particles = particles.map { particle ->
            var newX = particle.x + particle.speedX
            var newY = particle.y + particle.speedY
            
            if (newX < 0 || newX > size.width) {
                newX = if (newX < 0) size.width else 0f
            }
            if (newY < 0 || newY > size.height) {
                newY = if (newY < 0) size.height else 0f
            }
            
            particle.copy(x = newX, y = newY)
        }
        
        particles.forEach { particle ->
            drawCircle(
                color = particle.color.copy(alpha = particle.alpha),
                radius = particle.radius,
                center = Offset(particle.x, particle.y)
            )
        }
    }
}

/**
 * Typing Indicator Animation
 */
@Composable
fun TypingIndicator(
    modifier: Modifier = Modifier,
    color: Color = ElectricPurple
) {
    Row(
        modifier = modifier,
        horizontalArrangement = Arrangement.spacedBy(4.dp),
        verticalAlignment = Alignment.CenterVertically
    ) {
        repeat(3) { index ->
            val infiniteTransition = rememberInfiniteTransition(label = "dot$index")
            val scale by infiniteTransition.animateFloat(
                initialValue = 0.5f,
                targetValue = 1f,
                animationSpec = infiniteRepeatable(
                    animation = tween(600, delayMillis = index * 200, easing = FastOutSlowInEasing),
                    repeatMode = RepeatMode.Reverse
                ),
                label = "dotScale$index"
            )
            
            Box(
                modifier = Modifier
                    .size(8.dp)
                    .scale(scale)
                    .background(color, CircleShape)
            )
        }
    }
}

/**
 * Animated Icon Button with bounce effect
 */
@Composable
fun BounceIconButton(
    onClick: () -> Unit,
    icon: ImageVector,
    modifier: Modifier = Modifier,
    tint: Color = ElectricPurple,
    contentDescription: String? = null
) {
    var isPressed by remember { mutableStateOf(false) }
    val scale by animateFloatAsState(
        targetValue = if (isPressed) 0.85f else 1f,
        animationSpec = spring(
            dampingRatio = Spring.DampingRatioMediumBouncy,
            stiffness = Spring.StiffnessLow
        ),
        label = "iconScale"
    )
    
    IconButton(
        onClick = {
            isPressed = true
            onClick()
        },
        modifier = modifier.scale(scale)
    ) {
        Icon(
            imageVector = icon,
            contentDescription = contentDescription,
            tint = tint
        )
    }
    
    LaunchedEffect(isPressed) {
        if (isPressed) {
            kotlinx.coroutines.delay(100)
            isPressed = false
        }
    }
}

/**
 * Glowing Card with animated border
 */
@Composable
fun GlowingCard(
    modifier: Modifier = Modifier,
    glowColor: Color = ElectricPurple,
    content: @Composable () -> Unit
) {
    val infiniteTransition = rememberInfiniteTransition(label = "glow")
    val alpha by infiniteTransition.animateFloat(
        initialValue = 0.3f,
        targetValue = 0.8f,
        animationSpec = infiniteRepeatable(
            animation = tween(1500, easing = FastOutSlowInEasing),
            repeatMode = RepeatMode.Reverse
        ),
        label = "glowAlpha"
    )
    
    Card(
        modifier = modifier
            .border(
                width = 2.dp,
                brush = Brush.linearGradient(
                    colors = listOf(
                        glowColor.copy(alpha = alpha),
                        glowColor.copy(alpha = alpha * 0.5f)
                    )
                ),
                shape = RoundedCornerShape(16.dp)
            ),
        shape = RoundedCornerShape(16.dp),
        colors = CardDefaults.cardColors(
            containerColor = SurfaceLevel2
        ),
        elevation = CardDefaults.cardElevation(8.dp)
    ) {
        content()
    }
}
