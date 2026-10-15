<template>
  <div class="h-[calc(100vh-4rem)] flex bg-slate-950 overflow-hidden">
    <!-- Conversations List Sidebar -->
    <div
      class="border-r border-slate-800 bg-slate-950 flex flex-col shrink-0 transition-all duration-150"
      :class="isMobileListOpen ? 'fixed inset-0 z-30 w-full' : 'hidden md:flex md:w-72'"
    >
      <div class="p-3 border-b border-slate-800 flex items-center justify-between">
        <h2 class="font-bold text-sm text-white flex items-center gap-2">
          <span>💬</span>
          <span>Percakapan AI</span>
        </h2>
        <button
          @click="startNewConversation"
          class="p-1.5 rounded-lg bg-violet-600/30 border border-violet-500/40 text-violet-300 hover:bg-violet-600/50 text-xs font-semibold"
          title="Percakapan Baru"
        >
          + Baru
        </button>
      </div>

      <!-- Conversations list -->
      <div class="flex-1 overflow-y-auto p-2 space-y-1">
        <div
          v-for="conv in conversations"
          :key="conv.id"
          @click="selectConversation(conv.id)"
          class="flex items-center justify-between p-2.5 rounded-xl cursor-pointer transition-colors group text-xs"
          :class="activeConvId === conv.id ? 'bg-violet-600/20 text-cyan-300 font-semibold' : 'text-slate-400 hover:bg-slate-900 hover:text-white'"
        >
          <div class="truncate flex-1 mr-2">
            <p class="truncate">{{ conv.title }}</p>
            <span class="text-[10px] text-slate-500 font-normal">{{ formatDate(conv.updated_at) }}</span>
          </div>
          <button
            @click.stop="deleteConversation(conv.id)"
            class="opacity-0 group-hover:opacity-100 hover:text-rose-400 text-slate-500 p-1 text-[11px]"
            title="Hapus"
          >
            ✕
          </button>
        </div>
      </div>
    </div>

    <!-- Active Chat Messages Area -->
    <div class="flex-1 flex flex-col overflow-hidden bg-slate-950">
      <!-- Chat Header -->
      <div class="h-12 px-4 bg-slate-900 border-b border-slate-800 flex items-center justify-between text-xs shrink-0">
        <div class="flex items-center gap-3">
          <button @click="isMobileListOpen = !isMobileListOpen" class="md:hidden text-slate-400 hover:text-white">
            ☰ Daftar
          </button>
          <span class="font-semibold text-white truncate max-w-xs">{{ activeConv?.title || 'Percakapan Baru' }}</span>
        </div>

        <div class="flex items-center gap-2">
          <button
            v-if="activeConvId && conversationFiles.length > 0"
            @click="openPushModal(conversationFiles)"
            :disabled="isStreaming"
            class="px-2 py-1 rounded-lg bg-slate-950 border border-slate-800 hover:border-cyan-400 text-slate-300 hover:text-white disabled:opacity-40"
            :title="`Push ${conversationFiles.length} berkas terbaru dari percakapan ini`"
          >
            <span class="hidden sm:inline">Push ke GitHub</span><span class="sm:hidden">⬆ GitHub</span>
            <span class="ml-1 text-cyan-400">({{ conversationFiles.length }})</span>
          </button>
          <span class="text-slate-400 hidden sm:inline">Model:</span>
          <select
            v-model="selectedModel"
            @change="onModelChange"
            :disabled="isStreaming || models.length === 0"
            :title="modelSource === 'live' ? 'Katalog live dari TokenomixAI' : 'Katalog lokal (gateway tidak terjangkau)'"
            class="max-w-[11rem] sm:max-w-[16rem] px-2 py-1 rounded-lg bg-slate-950 border border-slate-800 text-cyan-300 font-mono text-xs outline-none disabled:opacity-60"
          >
            <option v-if="models.length === 0" value="">Memuat model...</option>
            <option v-for="m in models" :key="m.id" :value="m.id">
              {{ m.id }}{{ m.multiplier ? ` · ×${formatMultiplier(m.multiplier)}` : '' }}
            </option>
          </select>
        </div>
      </div>

      <!-- Messages Stream List -->
      <div ref="messagesContainer" class="flex-1 overflow-y-auto p-4 sm:p-6 space-y-5 select-text">
        <div v-if="messages.length === 0" class="h-full flex flex-col items-center justify-center text-center text-slate-500 space-y-3">
          <div class="text-4xl">🤖</div>
          <h3 class="font-bold text-white text-base">Mulai Diskusi dengan AI XZY</h3>
          <p class="text-xs text-slate-400 max-w-sm leading-relaxed">
            Ajukan pertanyaan teknis, konsultasi arsitektur kode, atau diskusikan implementasi fitur aplikasi Anda.
          </p>
        </div>

        <div
          v-for="msg in messages"
          :key="msg.id || msg.tempId"
          class="flex gap-3 max-w-3xl"
          :class="msg.role === 'user' ? 'ml-auto justify-end' : 'mr-auto justify-start'"
        >
          <div
            v-if="msg.role !== 'user'"
            class="w-8 h-8 rounded-xl bg-gradient-to-tr from-violet-600 to-cyan-500 flex items-center justify-center text-xs font-bold text-white shrink-0 mt-1 shadow-md"
          >
            AI
          </div>

          <div
            class="p-4 rounded-2xl text-xs sm:text-sm leading-relaxed max-w-2xl overflow-hidden"
            :class="msg.role === 'user' ? 'bg-violet-600 text-white rounded-br-none shadow-md' : 'bg-slate-900 border border-slate-800 text-slate-200 rounded-bl-none shadow-xl'"
          >
            <div class="whitespace-pre-wrap font-sans" v-html="renderMarkdown(msg.content)"></div>
            <div
              v-if="msg.role === 'assistant' && codeFilesOf(msg).length > 0"
              class="mt-3 pt-2 border-t border-slate-800 flex flex-wrap items-center gap-2 text-[11px]"
            >
              <button
                @click="openPushModal(codeFilesOf(msg))"
                :disabled="isStreaming"
                class="px-2.5 py-1 rounded-lg bg-violet-600/30 border border-violet-500/40 text-violet-200 hover:bg-violet-600/50 font-semibold disabled:opacity-40"
              >
                ⬆ Push ke GitHub
              </button>
              <span class="text-slate-500 truncate">
                {{ codeFilesOf(msg).map(f => f.path || 'tanpa path').join(', ') }}
              </span>
            </div>
          </div>
        </div>

        <!-- Real-time streaming chunk -->
        <div v-if="isStreaming" class="flex gap-3 max-w-3xl mr-auto justify-start">
          <div class="w-8 h-8 rounded-xl bg-gradient-to-tr from-violet-600 to-cyan-500 flex items-center justify-center text-xs font-bold text-white shrink-0 mt-1 animate-pulse">
            AI
          </div>
          <div class="p-4 rounded-2xl bg-slate-900 border border-violet-500/30 text-slate-200 text-xs sm:text-sm leading-relaxed max-w-2xl rounded-bl-none shadow-xl">
            <div class="whitespace-pre-wrap font-sans" v-html="renderMarkdown(streamingText)"></div>
            <div class="mt-2 flex items-center gap-2 text-[10px] text-cyan-400 font-mono">
              <span class="w-2 h-2 rounded-full bg-cyan-400 animate-ping"></span>
              <span>Streaming token dari gateway TokenomixAI...</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Chat Bottom Input Bar -->
      <div class="p-3 sm:p-4 bg-slate-900 border-t border-slate-800 shrink-0">
        <div class="max-w-3xl mx-auto flex items-end gap-2">
          <div class="flex-1 relative">
            <textarea
              v-model="inputContent"
              @keydown.enter.exact.prevent="sendMessage"
              rows="2"
              placeholder="Ketik pesan Anda... (Enter untuk kirim, Shift+Enter untuk baris baru)"
              class="w-full p-3 rounded-2xl bg-slate-950 border border-slate-800 focus:border-cyan-400 text-xs sm:text-sm text-white outline-none resize-none"
            ></textarea>
          </div>

          <button
            v-if="isStreaming"
            @click="stopGeneration"
            class="px-4 py-3 rounded-2xl bg-rose-600 hover:bg-rose-500 text-white font-semibold text-xs shadow-lg transition-colors shrink-0"
          >
            Berhenti
          </button>
          <button
            v-else
            @click="sendMessage"
            :disabled="!inputContent.trim()"
            class="px-5 py-3 rounded-2xl bg-gradient-to-r from-violet-600 to-cyan-500 hover:from-violet-500 hover:to-cyan-400 text-white font-bold text-xs shadow-lg transition-all disabled:opacity-40 disabled:pointer-events-none shrink-0"
          >
            Kirim
          </button>
        </div>
      </div>
    </div>

    <GitHubPushModal
      :open="pushModalOpen"
      :conversation-id="activeConvId"
      :conversation-title="activeConv?.title"
      :files="pushFiles"
      @close="pushModalOpen = false"
    />
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, nextTick } from 'vue';
import { api, getCsrfToken } from '@/api/client';
import { useToast } from '@/composables/useToast';
import { marked } from 'marked';
import DOMPurify from 'dompurify';
import GitHubPushModal from '@/components/GitHubPushModal.vue';
import { extractCodeFiles, collectLatestFiles, type CodeFile } from '@/utils/codeBlocks';

interface ChatModel {
    id: string;
    multiplier: number | null;
    description?: string | null;
}

const toast = useToast();

const conversations = ref<any[]>([]);
const activeConvId = ref<string>('');
const activeConv = ref<any>(null);
const messages = ref<any[]>([]);
const MODEL_STORAGE_KEY = 'xzy_chat_model';
const models = ref<ChatModel[]>([]);
const defaultModel = ref('');
const modelSource = ref('');
const selectedModel = ref('');
const pushModalOpen = ref(false);
const pushFiles = ref<CodeFile[]>([]);
const inputContent = ref('');
const isStreaming = ref(false);
const streamingText = ref('');
const isMobileListOpen = ref(false);
const messagesContainer = ref<HTMLDivElement | null>(null);

const renderMarkdown = (text: string) => {
    if (!text) return '';
    try {
        const raw = marked.parse(text) as string;
        return DOMPurify.sanitize(raw);
    } catch {
        return text;
    }
};

const formatMultiplier = (m: number) => (Number.isInteger(m) ? String(m) : m.toFixed(2).replace(/0+$/, ''));

const codeFileCache = new Map<string, CodeFile[]>();
const codeFilesOf = (msg: any): CodeFile[] => {
    const content: string = msg.content || '';
    const key = `${msg.id || msg.tempId}:${content.length}`;
    let files = codeFileCache.get(key);
    if (!files) {
        files = extractCodeFiles(content);
        codeFileCache.set(key, files);
    }
    return files;
};

const conversationFiles = computed(() => collectLatestFiles(messages.value));

const openPushModal = (files: CodeFile[]) => {
    if (!activeConvId.value) return;
    pushFiles.value = files.map(f => ({ ...f }));
    pushModalOpen.value = true;
};

const isKnownModel = (id?: string | null) => !!id && models.value.some(m => m.id === id);

const fetchModels = async () => {
    try {
        const res = await api.get('/api/models');
        models.value = res.models || [];
        defaultModel.value = res.default || models.value[0]?.id || '';
        modelSource.value = res.source || '';

        let preferred = '';
        try {
            preferred = localStorage.getItem(MODEL_STORAGE_KEY) || '';
        } catch { /* storage unavailable */ }

        if (activeConv.value?.model && isKnownModel(activeConv.value.model)) {
            selectedModel.value = activeConv.value.model;
        } else if (!isKnownModel(selectedModel.value)) {
            selectedModel.value = isKnownModel(preferred) ? preferred : defaultModel.value;
        }
    } catch (e: any) {
        toast.error('Gagal memuat daftar model: ' + e.message);
    }
};

const onModelChange = async () => {
    try {
        localStorage.setItem(MODEL_STORAGE_KEY, selectedModel.value);
    } catch { /* storage unavailable */ }

    if (!activeConvId.value || !selectedModel.value) return;
    try {
        const res = await api.put(`/api/conversations/${activeConvId.value}`, { model: selectedModel.value });
        activeConv.value = res.conversation;
        const idx = conversations.value.findIndex(c => c.id === activeConvId.value);
        if (idx >= 0) conversations.value[idx] = { ...conversations.value[idx], model: res.conversation.model };
    } catch (e: any) {
        toast.error('Gagal mengganti model: ' + e.message);
    }
};

const formatDate = (dateStr: string) => {
    if (!dateStr) return '';
    return new Date(dateStr).toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'short',
    });
};

const scrollToBottom = () => {
    nextTick(() => {
        if (messagesContainer.value) {
            messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight;
        }
    });
};

const fetchConversations = async () => {
    try {
        const res = await api.get('/api/conversations');
        conversations.value = res.data || [];
        if (!activeConvId.value && conversations.value.length > 0) {
            selectConversation(conversations.value[0].id);
        }
    } catch (e: any) {
        toast.error('Gagal memuat percakapan: ' + e.message);
    }
};

const startNewConversation = async (): Promise<string | null> => {
    try {
        const res = await api.post('/api/conversations', {
            title: 'Percakapan Baru',
            model: selectedModel.value,
        });
        if (res.conversation?.id) {
            conversations.value.unshift(res.conversation);
            await selectConversation(res.conversation.id);
            isMobileListOpen.value = false;
            return res.conversation.id;
        }
        return null;
    } catch (e: any) {
        toast.error('Gagal membuat percakapan: ' + e.message);
        return null;
    }
};

const selectConversation = async (id: string) => {
    if (!id) return;
    activeConvId.value = id;
    isMobileListOpen.value = false;
    try {
        const res = await api.get(`/api/conversations/${id}`);
        activeConv.value = res.conversation;
        messages.value = res.conversation.messages || [];
        if (isKnownModel(res.conversation.model)) {
            selectedModel.value = res.conversation.model;
        }
        scrollToBottom();
    } catch (e: any) {
        toast.error('Gagal memuat pesan: ' + e.message);
    }
};

const deleteConversation = async (id: string) => {
    if (!confirm('Hapus percakapan ini?')) return;
    try {
        await api.delete(`/api/conversations/${id}`);
        conversations.value = conversations.value.filter(c => c.id !== id);
        if (activeConvId.value === id) {
            activeConvId.value = conversations.value[0]?.id || '';
            if (activeConvId.value) {
                await selectConversation(activeConvId.value);
            } else {
                messages.value = [];
                activeConv.value = null;
            }
        }
        toast.success('Percakapan dihapus.');
    } catch (e: any) {
        toast.error('Gagal menghapus percakapan: ' + e.message);
    }
};

const sendMessage = async () => {
    if (!inputContent.value.trim() || isStreaming.value) return;

    if (!activeConvId.value) {
        const newId = await startNewConversation();
        if (!newId || !activeConvId.value) {
            toast.error('Gagal membuat percakapan aktif. Silakan coba lagi.');
            return;
        }
    }

    const convId = activeConvId.value;
    const userText = inputContent.value.trim();
    inputContent.value = '';

    messages.value.push({
        tempId: Date.now(),
        role: 'user',
        content: userText,
    });
    scrollToBottom();

    isStreaming.value = true;
    streamingText.value = '';

    try {
        const csrfToken = getCsrfToken();
        // SSE request with HttpOnly session cookies
        const response = await fetch(`/api/conversations/${convId}/stream`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'text/event-stream, application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken,
            },
            body: JSON.stringify({
                content: userText,
                model: selectedModel.value || undefined,
            }),
        });

        if (!response.ok) {
            const errJson = await response.json().catch(() => ({}));
            throw new Error(errJson.message || `HTTP ${response.status}`);
        }

        const reader = response.body?.getReader();
        const decoder = new TextDecoder();

        let streamError = '';
        if (reader) {
            let buffer = '';
            while (true) {
                const { done, value } = await reader.read();
                if (done) break;

                buffer += decoder.decode(value, { stream: true });
                const lines = buffer.split('\n');
                buffer = lines.pop() || '';

                for (const line of lines) {
                    const trimmed = line.trim();
                    if (trimmed.startsWith('data:')) {
                        const dataStr = trimmed.substring(5).trim();
                        if (dataStr === '[DONE]') {
                            break;
                        }
                        let parsed: any;
                        try {
                            parsed = JSON.parse(dataStr);
                        } catch {
                            continue; // ignore parse error on fragments
                        }
                        if (parsed.content) {
                            streamingText.value += parsed.content;
                            scrollToBottom();
                        } else if (parsed.event === 'stopped') {
                            toast.info(parsed.message || 'Generasi dihentikan.');
                        } else if (parsed.error) {
                            streamError = parsed.error;
                        }
                    }
                }
            }
        }

        // Commit streamed message to local list
        if (streamError) {
            toast.error(streamError);
        }
        messages.value.push({
            tempId: Date.now() + 1,
            role: 'assistant',
            content: streamError
                ? `${streamingText.value}${streamingText.value ? '\n\n' : ''}**[Kesalahan]** ${streamError}`
                : streamingText.value,
        });
        streamingText.value = '';
        const idx = conversations.value.findIndex(c => c.id === convId);
        if (idx > 0) {
            const [conv] = conversations.value.splice(idx, 1);
            conversations.value.unshift({ ...conv, updated_at: new Date().toISOString() });
        }

        // Sync with server copy (real IDs, saved content, auto-generated title)
        try {
            const fresh = await api.get(`/api/conversations/${convId}`);
            if (activeConvId.value === convId) {
                activeConv.value = fresh.conversation;
                messages.value = fresh.conversation.messages || messages.value;
            }
            const i = conversations.value.findIndex(c => c.id === convId);
            if (i >= 0) conversations.value[i] = { ...conversations.value[i], title: fresh.conversation.title };
        } catch {
            // keep local copy
        }
    } catch (e: any) {
        toast.error('Gagal mengirim pesan: ' + e.message);
        messages.value.push({
            tempId: Date.now() + 1,
            role: 'assistant',
            content: `[Gagal menerima respons AI]: ${e.message}`,
        });
    } finally {
        isStreaming.value = false;
        scrollToBottom();
    }
};

const stopGeneration = async () => {
    if (!activeConvId.value) return;
    try {
        await api.post(`/api/conversations/${activeConvId.value}/stop`);
        toast.info('Permintaan pembatalan dikirim.');
    } catch (e) {
        // Ignore
    }
};

onMounted(async () => {
    await fetchModels();
    fetchConversations();
});
</script>
