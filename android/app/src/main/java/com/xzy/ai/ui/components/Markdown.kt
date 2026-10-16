package com.xzy.ai.ui.components

import androidx.compose.animation.AnimatedVisibility
import androidx.compose.animation.core.animateFloatAsState
import androidx.compose.animation.expandVertically
import androidx.compose.animation.fadeIn
import androidx.compose.animation.fadeOut
import androidx.compose.animation.shrinkVertically
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.layout.widthIn
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.selection.SelectionContainer
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.rounded.Check
import androidx.compose.material.icons.rounded.ContentCopy
import androidx.compose.material.icons.rounded.ExpandMore
import androidx.compose.material.icons.rounded.Bolt
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.platform.LocalClipboardManager
import androidx.compose.ui.text.AnnotatedString
import androidx.compose.ui.text.LinkAnnotation
import androidx.compose.ui.text.SpanStyle
import androidx.compose.ui.text.TextLinkStyles
import androidx.compose.ui.text.buildAnnotatedString
import androidx.compose.ui.text.font.FontFamily
import androidx.compose.ui.text.font.FontStyle
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextDecoration
import androidx.compose.ui.text.withLink
import androidx.compose.ui.text.withStyle
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.xzy.ai.ui.theme.Brand
import com.xzy.ai.ui.theme.X
import kotlinx.coroutines.delay

/** Markdown blocks the renderer understands. */
private sealed class Block {
    data class Para(val text: String) : Block()
    data class Heading(val level: Int, val text: String) : Block()
    data class Code(val lang: String, val code: String) : Block()
    data class Bullets(val items: List<String>, val ordered: Boolean) : Block()
    data class Quote(val text: String) : Block()
    data class Table(val rows: List<List<String>>) : Block()
    data class Details(val summary: String, val body: String) : Block()
    object Rule : Block()
}

private val orderedRe = Regex("^\\d+[.)]\\s+")
private val bulletRe = Regex("^[-*+]\\s+")

private fun parse(src: String): List<Block> {
    val out = ArrayList<Block>()
    val lines = src.replace("\r\n", "\n").split("\n")
    var i = 0
    val para = StringBuilder()
    fun flush() {
        if (para.isNotBlank()) out.add(Block.Para(para.toString().trim()))
        para.clear()
    }
    while (i < lines.size) {
        val raw = lines[i]
        val line = raw.trimEnd()
        val t = line.trimStart()
        when {
            t.startsWith("<details") -> {
                flush()
                var summary = "Detail"
                val body = StringBuilder()
                val sm = Regex("<summary>(.*?)</summary>").find(t)
                if (sm != null) summary = sm.groupValues[1]
                var rest = t.substringAfter("</summary>", "")
                i++
                if (sm == null) {
                    // summary on a later line
                    while (i < lines.size && !lines[i].contains("</summary>")) i++
                    if (i < lines.size) {
                        Regex("<summary>(.*?)</summary>").find(lines[i])?.let { summary = it.groupValues[1] }
                        rest = lines[i].substringAfter("</summary>", "")
                        i++
                    }
                }
                if (rest.isNotBlank()) body.append(rest).append('\n')
                while (i < lines.size && !lines[i].contains("</details>")) {
                    body.append(lines[i]).append('\n'); i++
                }
                if (i < lines.size) {
                    body.append(lines[i].substringBefore("</details>")); i++
                }
                out.add(Block.Details(summary, body.toString().trim()))
                continue
            }
            t.startsWith("```") -> {
                flush()
                val lang = t.removePrefix("```").trim()
                val code = StringBuilder()
                i++
                while (i < lines.size && !lines[i].trimStart().startsWith("```")) {
                    code.append(lines[i]).append('\n'); i++
                }
                i++ // closing fence (or end of a still-streaming block)
                out.add(Block.Code(lang, code.toString().trimEnd('\n')))
                continue
            }
            t.startsWith("#") && t.trimStart('#').startsWith(" ") -> {
                flush()
                val level = t.takeWhile { it == '#' }.length.coerceIn(1, 4)
                out.add(Block.Heading(level, t.trimStart('#').trim()))
            }
            t == "---" || t == "***" || t == "___" -> { flush(); out.add(Block.Rule) }
            t.startsWith(">") -> {
                flush()
                val q = StringBuilder()
                while (i < lines.size && lines[i].trimStart().startsWith(">")) {
                    q.append(lines[i].trimStart().removePrefix(">").trim()).append('\n'); i++
                }
                out.add(Block.Quote(q.toString().trim()))
                continue
            }
            t.startsWith("|") && t.count { it == '|' } >= 2 -> {
                flush()
                val rows = ArrayList<List<String>>()
                while (i < lines.size && lines[i].trimStart().startsWith("|")) {
                    val cells = lines[i].trim().trim('|').split("|").map { it.trim() }
                    if (!cells.all { c -> c.isNotEmpty() && c.all { it == '-' || it == ':' || it == ' ' } }) rows.add(cells)
                    i++
                }
                out.add(Block.Table(rows))
                continue
            }
            bulletRe.containsMatchIn(t) || orderedRe.containsMatchIn(t) -> {
                flush()
                val ordered = orderedRe.containsMatchIn(t)
                val items = ArrayList<String>()
                while (i < lines.size) {
                    val l = lines[i].trimStart()
                    if (ordered && orderedRe.containsMatchIn(l)) items.add(l.replaceFirst(orderedRe, ""))
                    else if (!ordered && bulletRe.containsMatchIn(l)) items.add(l.replaceFirst(bulletRe, ""))
                    else if (l.isNotBlank() && items.isNotEmpty() && lines[i].startsWith("  ")) items[items.size - 1] = items.last() + "\n" + l
                    else break
                    i++
                }
                out.add(Block.Bullets(items, ordered))
                continue
            }
            t.isEmpty() -> flush()
            else -> para.append(if (para.isEmpty()) t else "\n$t")
        }
        i++
    }
    flush()
    return out
}

private val inlineRe = Regex(
    "(\\*\\*(.+?)\\*\\*)|(`([^`]+)`)|(\\[([^\\]]+)]\\((https?://[^)\\s]+)\\))|(https?://[^\\s)<>]+)|(\\*([^*\\s][^*]*?)\\*)|(\\b_([^_]+)_\\b)",
)

@Composable
private fun mdInline(text: String, base: Color): AnnotatedString {
    val codeBg = if (X.c.isDark) Color(0xFF2A2540) else Color(0xFFEDE7FF)
    val linkColor = if (X.c.isDark) Color(0xFFC4B5FD) else Brand.Violet
    val cleaned = text.replace("<br>", "\n").replace("<br/>", "\n")
    return buildAnnotatedString {
        var last = 0
        for (m in inlineRe.findAll(cleaned)) {
            append(cleaned.substring(last, m.range.first))
            val g = m.groups
            when {
                g[1] != null -> withStyle(SpanStyle(fontWeight = FontWeight.ExtraBold, color = X.c.ink)) { append(g[2]!!.value) }
                g[3] != null -> withStyle(SpanStyle(fontFamily = FontFamily.Monospace, background = codeBg, fontSize = 13.sp, color = if (X.c.isDark) Color(0xFFF0ABFC) else Color(0xFF86198F))) { append(" " + g[4]!!.value + " ") }
                g[5] != null -> withLink(LinkAnnotation.Url(g[7]!!.value, TextLinkStyles(SpanStyle(color = linkColor, fontWeight = FontWeight.Bold, textDecoration = TextDecoration.Underline)))) { append(g[6]!!.value) }
                g[8] != null -> withLink(LinkAnnotation.Url(g[8]!!.value, TextLinkStyles(SpanStyle(color = linkColor, textDecoration = TextDecoration.Underline)))) { append(g[8]!!.value) }
                g[9] != null -> withStyle(SpanStyle(fontStyle = FontStyle.Italic)) { append(g[10]!!.value) }
                g[11] != null -> withStyle(SpanStyle(fontStyle = FontStyle.Italic)) { append(g[12]!!.value) }
            }
            last = m.range.last + 1
        }
        append(cleaned.substring(last))
    }
}

@Composable
fun MarkdownText(text: String, modifier: Modifier = Modifier, color: Color = X.c.text) {
    val blocks = remember(text) { parse(text) }
    SelectionContainer(modifier) {
        Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
            for (b in blocks) MdBlock(b, color)
        }
    }
}

@Composable
private fun MdBlock(b: Block, color: Color) {
    val body = MaterialTheme.typography.bodyLarge
    when (b) {
        is Block.Para -> Text(mdInline(b.text, color), style = body, color = color)
        is Block.Heading -> Text(
            mdInline(b.text, color),
            style = when (b.level) {
                1 -> MaterialTheme.typography.headlineSmall
                2 -> MaterialTheme.typography.titleLarge
                else -> MaterialTheme.typography.titleMedium
            },
            color = X.c.ink,
            modifier = Modifier.padding(top = 4.dp),
        )
        is Block.Code -> CodeBlock(b.lang, b.code)
        is Block.Bullets -> Column(verticalArrangement = Arrangement.spacedBy(6.dp)) {
            b.items.forEachIndexed { idx, item ->
                Row {
                    if (b.ordered) {
                        Text("${idx + 1}.", style = body, fontWeight = FontWeight.Black, color = Brand.Fuchsia, modifier = Modifier.width(24.dp))
                    } else {
                        Box(Modifier.padding(top = 9.dp, end = 10.dp).size(7.dp).clip(RoundedCornerShape(2.dp)).background(Brand.gradient))
                    }
                    Text(mdInline(item, color), style = body, color = color)
                }
            }
        }
        is Block.Quote -> Row(
            Modifier
                .fillMaxWidth()
                .clip(RoundedCornerShape(12.dp))
                .background(X.c.card2)
                .padding(end = 12.dp),
        ) {
            Box(Modifier.width(4.dp).height(IntrinsicHeightFallback).background(Brand.gradient))
            Text(mdInline(b.text, color), style = body, color = X.c.muted, fontStyle = FontStyle.Italic, modifier = Modifier.padding(start = 12.dp, top = 10.dp, bottom = 10.dp))
        }
        is Block.Table -> TableBlock(b.rows, color)
        is Block.Details -> DetailsBlock(b.summary, b.body, color)
        Block.Rule -> Box(Modifier.fillMaxWidth().height(2.dp).clip(RoundedCornerShape(50)).background(Brand.gradient))
    }
}

private val IntrinsicHeightFallback = 44.dp

@Composable
fun CodeBlock(lang: String, code: String) {
    val clipboard = LocalClipboardManager.current
    var copied by remember { mutableStateOf(false) }
    LaunchedEffect(copied) { if (copied) { delay(1600); copied = false } }
    val bg = Color(0xFF0F0D1A)
    Column(
        Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(14.dp))
            .background(bg)
            .border(2.dp, if (X.c.isDark) X.c.line else Brand.Ink, RoundedCornerShape(14.dp)),
    ) {
        Row(
            Modifier.fillMaxWidth().background(Color(0xFF1B1830)).padding(start = 12.dp, end = 6.dp, top = 6.dp, bottom = 6.dp),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            Row(horizontalArrangement = Arrangement.spacedBy(5.dp)) {
                listOf(Brand.Rose, Brand.Yellow, Brand.Green).forEach { Box(Modifier.size(9.dp).clip(RoundedCornerShape(50)).background(it)) }
            }
            Spacer(Modifier.width(10.dp))
            Text(lang.ifBlank { "kode" }, color = Color(0xFFA78BFA), fontSize = 12.sp, fontWeight = FontWeight.Bold, fontFamily = FontFamily.Monospace, modifier = Modifier.weight(1f))
            Row(
                Modifier
                    .clip(RoundedCornerShape(8.dp))
                    .clickable {
                        clipboard.setText(AnnotatedString(code))
                        copied = true
                    }
                    .padding(horizontal = 8.dp, vertical = 4.dp),
                verticalAlignment = Alignment.CenterVertically,
            ) {
                Icon(if (copied) Icons.Rounded.Check else Icons.Rounded.ContentCopy, null, tint = if (copied) Brand.Green else Color(0xFFCBC5E8), modifier = Modifier.size(14.dp))
                Spacer(Modifier.width(4.dp))
                Text(if (copied) "Tersalin" else "Salin", color = if (copied) Brand.Green else Color(0xFFCBC5E8), fontSize = 12.sp, fontWeight = FontWeight.Bold)
            }
        }
        Box(Modifier.horizontalScroll(rememberScrollState()).padding(12.dp)) {
            Text(highlight(code), fontFamily = FontFamily.Monospace, fontSize = 12.5.sp, lineHeight = 19.sp, color = Color(0xFFE9E5FF), softWrap = false)
        }
    }
}

private val keywordRe = Regex("\\b(fun|val|var|class|object|function|return|if|else|for|while|import|from|export|const|let|def|public|private|protected|static|new|use|namespace|true|false|null|async|await|try|catch|throw|echo|package|interface|extends|implements)\\b")
private val stringRe = Regex("(\"[^\"\\n]*\"|'[^'\\n]*')")
private val commentRe = Regex("(//[^\\n]*|#[^\\n]*)")

private fun highlight(code: String): AnnotatedString = buildAnnotatedString {
    append(code)
    if (code.length > 20000) return@buildAnnotatedString
    keywordRe.findAll(code).forEach { addStyle(SpanStyle(color = Color(0xFFF0ABFC), fontWeight = FontWeight.Bold), it.range.first, it.range.last + 1) }
    stringRe.findAll(code).forEach { addStyle(SpanStyle(color = Color(0xFFBEF264)), it.range.first, it.range.last + 1) }
    commentRe.findAll(code).forEach {
        // '#' inside CSS colours etc. is not a comment; only treat it as one at line start
        val start = it.range.first
        val atLineStart = start == 0 || code[start - 1] == '\n' || code.substring(code.lastIndexOf('\n', start - 1) + 1, start).isBlank()
        if (it.value.startsWith("//") || atLineStart) addStyle(SpanStyle(color = Color(0xFF8B85A8), fontStyle = FontStyle.Italic), start, it.range.last + 1)
    }
}

@Composable
private fun TableBlock(rows: List<List<String>>, color: Color) {
    if (rows.isEmpty()) return
    val cols = rows.maxOf { it.size }
    Box(
        Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(12.dp))
            .border(1.5.dp, X.c.line, RoundedCornerShape(12.dp))
            .horizontalScroll(rememberScrollState()),
    ) {
        Column {
            rows.forEachIndexed { r, row ->
                Row(Modifier.background(if (r == 0) X.c.card2 else Color.Transparent)) {
                    for (c in 0 until cols) {
                        Text(
                            mdInline(row.getOrElse(c) { "" }, color),
                            style = MaterialTheme.typography.bodyMedium,
                            fontWeight = if (r == 0) FontWeight.ExtraBold else FontWeight.Normal,
                            color = color,
                            modifier = Modifier.widthIn(min = 90.dp, max = 220.dp).padding(horizontal = 10.dp, vertical = 8.dp),
                        )
                    }
                }
            }
        }
    }
}

@Composable
private fun DetailsBlock(summary: String, body: String, color: Color) {
    var open by remember { mutableStateOf(false) }
    val rot by animateFloatAsState(if (open) 180f else 0f, label = "chev")
    Column(
        Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(14.dp))
            .background(X.c.card2)
            .border(1.5.dp, X.c.line, RoundedCornerShape(14.dp)),
    ) {
        Row(
            Modifier.fillMaxWidth().clickable { open = !open }.padding(horizontal = 12.dp, vertical = 10.dp),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            Icon(Icons.Rounded.Bolt, null, tint = Brand.Orange, modifier = Modifier.size(18.dp))
            Spacer(Modifier.width(8.dp))
            Text(summary.replace("&amp;", "&"), style = MaterialTheme.typography.labelLarge, color = X.c.ink, modifier = Modifier.weight(1f))
            Icon(Icons.Rounded.ExpandMore, null, tint = X.c.muted, modifier = Modifier.graphicsLayer { rotationZ = rot })
        }
        AnimatedVisibility(open, enter = expandVertically() + fadeIn(), exit = shrinkVertically() + fadeOut()) {
            Column(Modifier.padding(start = 12.dp, end = 12.dp, bottom = 12.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                for (b in parse(body)) MdBlock(b, color)
            }
        }
    }
}
