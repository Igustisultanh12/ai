package com.xzy.ai.ui.components

import androidx.compose.foundation.border
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.ColumnScope
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import com.xzy.ai.ui.theme.Brand
import com.xzy.ai.ui.theme.X

@Composable
fun XzyDialog(
    title: String,
    confirmLabel: String,
    onConfirm: () -> Unit,
    onDismiss: () -> Unit,
    danger: Boolean = false,
    confirmEnabled: Boolean = true,
    loading: Boolean = false,
    content: @Composable ColumnScope.() -> Unit,
) {
    AlertDialog(
        onDismissRequest = { if (!loading) onDismiss() },
        modifier = Modifier.border(2.dp, if (X.c.isDark) X.c.line else Brand.Ink, RoundedCornerShape(26.dp)),
        shape = RoundedCornerShape(26.dp),
        containerColor = X.c.card,
        title = { Text(title, style = MaterialTheme.typography.titleLarge, color = X.c.ink) },
        text = { Column(content = content) },
        confirmButton = {
            if (danger) {
                SecondaryButton(if (loading) "Memproses..." else confirmLabel, color = Brand.Rose, textColor = Color.White) { if (confirmEnabled && !loading) onConfirm() }
            } else {
                PrimaryButton(confirmLabel, loading = loading, enabled = confirmEnabled) { onConfirm() }
            }
        },
        dismissButton = {
            TextButton(onClick = { if (!loading) onDismiss() }) { Text("Batal", color = X.c.muted, fontWeight = FontWeight.Bold) }
        },
    )
}

@Composable
fun ConfirmDialog(title: String, text: String, confirmLabel: String, loading: Boolean = false, onConfirm: () -> Unit, onDismiss: () -> Unit) {
    XzyDialog(title, confirmLabel, onConfirm, onDismiss, danger = true, loading = loading) {
        Text(text, style = MaterialTheme.typography.bodyMedium, color = X.c.text)
    }
}
