package com.xzy.ai.ui.screens

import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import com.xzy.ai.data.Repo
import com.xzy.ai.data.Session
import com.xzy.ai.ui.components.LocalNav
import com.xzy.ai.ui.components.LocalToast
import kotlinx.coroutines.launch

/** Creates a conversation (optionally linked to a project) and opens it. */
class NewChat(val start: (projectId: String?, prompt: String?) -> Unit, val busy: () -> Boolean)

@Composable
fun rememberNewChat(): NewChat {
    val nav = LocalNav.current
    val toast = LocalToast.current
    val scope = rememberCoroutineScope()
    var busy by remember { mutableStateOf(false) }
    return remember(nav) {
        NewChat(
            start = { projectId, prompt ->
                if (!busy) {
                    busy = true
                    scope.launch {
                        try {
                            val c = Repo.createConversation(null, projectId)
                            ChatDraft.pending[c.id] = prompt.orEmpty()
                            nav.navigate("chat/${c.id}")
                        } catch (e: Exception) {
                            toast(Session.fail(e))
                        } finally {
                            busy = false
                        }
                    }
                }
            },
            busy = { busy },
        )
    }
}

/** Text to drop into a freshly created chat's input box. */
object ChatDraft {
    val pending = HashMap<String, String>()
}

const val BOTTOM_SPACE = 120
