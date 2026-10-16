package com.xzy.ai.ui.screens

import androidx.compose.animation.core.Animatable
import androidx.compose.animation.core.FastOutSlowInEasing
import androidx.compose.animation.core.tween
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.itemsIndexed
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.rounded.Insights
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.mutableStateListOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.geometry.Size
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.StrokeCap
import androidx.compose.ui.graphics.drawscope.Stroke
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.xzy.ai.data.LedgerEntry
import com.xzy.ai.data.Repo
import com.xzy.ai.data.Session
import com.xzy.ai.data.Usage
import com.xzy.ai.ui.components.BrutalBox
import com.xzy.ai.ui.components.Chip
import com.xzy.ai.ui.components.CountUp
import com.xzy.ai.ui.components.EmptyState
import com.xzy.ai.ui.components.ErrorBanner
import com.xzy.ai.ui.components.GradientSpinner
import com.xzy.ai.ui.components.LocalNav
import com.xzy.ai.ui.components.LocalToast
import com.xzy.ai.ui.components.ScreenHeader
import com.xzy.ai.ui.components.SecondaryButton
import com.xzy.ai.ui.components.Shimmer
import com.xzy.ai.ui.components.enterAnim
import com.xzy.ai.ui.components.formatNumber
import com.xzy.ai.ui.components.relativeTime
import com.xzy.ai.ui.theme.Brand
import com.xzy.ai.ui.theme.X
import kotlinx.coroutines.launch

@Composable
fun UsageScreen() {
    val nav = LocalNav.current
    val toast = LocalToast.current
    val scope = rememberCoroutineScope()
    var usage by remember { mutableStateOf<Usage?>(null) }
    val ledger = remember { mutableStateListOf<LedgerEntry>() }
    var loaded by remember { mutableStateOf(false) }
    var page by remember { mutableIntStateOf(1) }
    var lastPage by remember { mutableIntStateOf(1) }
    var loadingMore by remember { mutableStateOf(false) }
    var error by remember { mutableStateOf<String?>(null) }

    LaunchedEffect(Unit) {
        try {
            usage = Repo.usage()
            val p = Repo.ledger(1)
            ledger.addAll(p.items); page = 1; lastPage = p.lastPage
        } catch (e: Exception) {
            error = Session.fail(e)
        }
        loaded = true
    }

    Column(Modifier.fillMaxSize()) {
        ScreenHeader("Pemakaian token", "Kuota dan riwayat pemakaian", onBack = { nav.popBackStack() })
        LazyColumn(
            Modifier.fillMaxSize(),
            contentPadding = PaddingValues(start = 16.dp, end = 16.dp, top = 6.dp, bottom = 40.dp),
            verticalArrangement = Arrangement.spacedBy(12.dp),
        ) {
            item {
                val u = usage
                if (u == null) {
                    if (!loaded) Shimmer(height = 210.dp)
                } else {
                    BrutalBox(Modifier.fillMaxWidth().enterAnim(0), contentPadding = 18.dp) {
                        Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceEvenly) {
                            Ring("Hari ini", u.dailyPercent.toFloat(), u.remainingDaily, u.dailyQuota, Brand.Yellow)
                            Ring("Bulan ini", u.monthlyPercent.toFloat(), u.remainingMonthly, u.monthlyQuota, Brand.Cyan)
                        }
                    }
                }
            }
            usage?.let { u ->
                item {
                    Row(Modifier.enterAnim(1), horizontalArrangement = Arrangement.spacedBy(12.dp)) {
                        Stat("Terpakai hari ini", u.todayWeighted, Modifier.weight(1f))
                        Stat("Total terpakai", u.totalWeighted, Modifier.weight(1f))
                    }
                }
            }
            error?.let { item { ErrorBanner(it) } }
            item { Text("Riwayat", style = MaterialTheme.typography.titleLarge, color = X.c.ink, modifier = Modifier.enterAnim(2)) }
            if (!loaded) {
                items(5) { Shimmer(height = 70.dp) }
            } else if (ledger.isEmpty()) {
                item { EmptyState(Icons.Rounded.Insights, "Belum ada pemakaian", "Riwayat muncul setelah kamu chat dengan agen.") }
            } else {
                itemsIndexed(ledger, key = { _, l -> l.id }) { i, l -> LedgerRow(l, Modifier.enterAnim(i.coerceAtMost(10))) }
                if (page < lastPage) item {
                    Box(Modifier.fillMaxWidth(), contentAlignment = Alignment.Center) {
                        if (loadingMore) GradientSpinner(24.dp) else SecondaryButton("Muat lebih banyak") {
                            loadingMore = true
                            scope.launch {
                                try {
                                    val p = Repo.ledger(page + 1)
                                    ledger.addAll(p.items.filter { n -> ledger.none { it.id == n.id } })
                                    page = p.page; lastPage = p.lastPage
                                } catch (e: Exception) { toast(Session.fail(e)) }
                                loadingMore = false
                            }
                        }
                    }
                }
            }
            item { Spacer(Modifier.navigationBarsPadding()) }
        }
    }
}

@Composable
private fun Ring(label: String, usedPercent: Float, remaining: Long, quota: Long, color: Color, size: Dp = 130.dp) {
    val anim = remember { Animatable(0f) }
    LaunchedEffect(usedPercent) { anim.animateTo((usedPercent / 100f).coerceIn(0f, 1f), tween(1300, easing = FastOutSlowInEasing)) }
    val track = X.c.card2
    val outline = if (X.c.isDark) X.c.line else Brand.Ink
    Column(horizontalAlignment = Alignment.CenterHorizontally) {
        Box(Modifier.size(size), contentAlignment = Alignment.Center) {
            Canvas(Modifier.size(size)) {
                val stroke = 16.dp.toPx()
                val inset = stroke / 2 + 2.dp.toPx()
                val arcSize = Size(this.size.width - inset * 2, this.size.height - inset * 2)
                val tl = androidx.compose.ui.geometry.Offset(inset, inset)
                drawArc(outline, 0f, 360f, false, tl, arcSize, style = Stroke(stroke + 4.dp.toPx()))
                drawArc(track, 0f, 360f, false, tl, arcSize, style = Stroke(stroke))
                drawArc(
                    Brush.sweepGradient(listOf(Brand.Violet, Brand.Fuchsia, Brand.Orange, Brand.Violet)),
                    -90f, 360f * anim.value, false, tl, arcSize, style = Stroke(stroke, cap = StrokeCap.Round),
                )
            }
            Column(horizontalAlignment = Alignment.CenterHorizontally) {
                Text("${"%.0f".format(anim.value * 100)}%", fontWeight = FontWeight.Black, fontSize = 24.sp, color = X.c.ink)
                Text("terpakai", style = MaterialTheme.typography.labelSmall, color = X.c.muted)
            }
        }
        Spacer(Modifier.height(10.dp))
        Chip(label, color = color)
        Spacer(Modifier.height(6.dp))
        Text(if (quota > 0) "sisa ${formatNumber(remaining)}" else "tanpa batas", style = MaterialTheme.typography.bodySmall, color = X.c.muted)
    }
}

@Composable
private fun Stat(label: String, value: Long, modifier: Modifier) {
    BrutalBox(modifier, contentPadding = 14.dp, shadow = 4.dp) {
        Column {
            Text(label, style = MaterialTheme.typography.labelMedium, color = X.c.muted)
            Spacer(Modifier.height(6.dp))
            CountUp(value, MaterialTheme.typography.titleLarge.copy(fontWeight = FontWeight.Black))
            Text("token berbobot", style = MaterialTheme.typography.bodySmall, color = X.c.muted)
        }
    }
}

@Composable
private fun LedgerRow(l: LedgerEntry, modifier: Modifier) {
    BrutalBox(modifier.fillMaxWidth(), contentPadding = 12.dp, shadow = 3.dp, shape = RoundedCornerShape(16.dp)) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            Column(Modifier.weight(1f)) {
                Text(l.model, fontWeight = FontWeight.ExtraBold, color = X.c.ink, maxLines = 1, overflow = TextOverflow.Ellipsis)
                Text(
                    listOfNotNull(l.projectName, "${formatNumber(l.promptTokens)} masuk · ${formatNumber(l.completionTokens)} keluar", relativeTime(l.createdAt)).joinToString(" · "),
                    style = MaterialTheme.typography.bodySmall, color = X.c.muted, maxLines = 2, overflow = TextOverflow.Ellipsis,
                )
            }
            Spacer(Modifier.width(8.dp))
            Column(horizontalAlignment = Alignment.End) {
                Text(formatNumber(l.weighted), fontWeight = FontWeight.Black, color = X.c.ink)
                if (l.multiplier > 0) Chip("x" + (if (l.multiplier % 1.0 == 0.0) l.multiplier.toLong().toString() else "%.2f".format(l.multiplier)), color = Brand.Cyan)
            }
        }
    }
}
