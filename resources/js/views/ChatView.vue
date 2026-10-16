<template>
  <div data-no-motion class="flex h-[calc(100vh-4rem)] overflow-hidden">
    <!-- Conversation list -->
    <aside
      class="flex shrink-0 flex-col border-r-2 border-line bg-card/80 backdrop-blur-xl transition-all duration-200"
      :class="isMobileListOpen ? 'fixed inset-x-0 top-16 bottom-0 z-30 w-full' : 'hidden md:flex md:w-72'"
    >
      <div class="flex items-center justify-between gap-2 border-b-2 border-line p-4">
        <h2 class="flex items-center gap-2 font-display text-base font-bold text-ink">
          <Icon name="message-square" class="h-5 w-5 text-violet-600 dark:text-violet-300" />
          Percakapan
        </h2>
        <div class="flex items-center gap-1.5">
          <button type="button" class="btn btn-brutal btn-sm" title="Percakapan baru" @click="startNewConversation">
            <Icon name="plus" class="h-4 w-4" />
            Baru
          </button>
          <button type="button" class="btn-ghost grid h-8 w-8 place-items-center rounded-lg md:hidden" title="Tutup" @click="isMobileListOpen = false">
            <Icon name="x" class="h-4 w-4" />
          </button>
        </div>
      </div>

      <div class="flex-1 space-y-1 overflow-y-auto p-2">
        <p v-if="conversations.length === 0" class="px-3 py-8 text-center text-sm text-muted">Belum ada percakapan.</p>
        <div
          v-for="conv in conversations"
          :key="conv.id"
          class="group flex cursor-pointer items-center justify-between gap-2 rounded-xl border-2 p-2.5 text-sm transition-all"
          :class="activeConvId === conv.id
            ? 'border-ink bg-yellow-300 font-semibold text-zinc-900 shadow-brutal-sm'
            : 'border-transparent text-muted hover:translate-x-1 hover:bg-surface-2 hover:text-ink'"
          @click="selectConversation(conv.id)"
        >
          <div class="min-w-0 flex-1">
            <p class="truncate">{{ conv.title }}</p>
            <span class="text-[11px] font-normal opacity-70">{{ formatDate(conv.updated_at) }}</span>
          </div>
          <button
            type="button"
            class="grid h-7 w-7 shrink-0 place-items-center rounded-lg opacity-0 transition-opacity group-hover:opacity-100 hover:bg-rose-500 hover:text-white"
            title="Hapus"
            @click.stop="deleteConversation(conv.id)"
          >
            <Icon name="trash" class="h-3.5 w-3.5" />
          </button>
        </div>
      </div>
    </aside>

    <!-- Chat area -->
    <section class="flex flex-1 flex-col overflow-hidden">
      <!-- Header -->
      <div class="flex shrink-0 flex-wrap items-center justify-between gap-2 border-b-2 border-line bg-card/80 px-3 py-2 text-xs backdrop-blur-xl sm:px-4">
        <div class="flex min-w-0 items-center gap-2">
          <button type="button" class="btn btn-secondary btn-sm md:hidden" @click="isMobileListOpen = !isMobileListOpen">
            <Icon name="list" class="h-4 w-4" />
            Daftar
          </button>
          <span class="max-w-[8rem] truncate font-display text-sm font-bold text-ink sm:max-w-xs">{{ activeConv?.title || 'Percakapan baru' }}</span>
          <label class="relative">
            <Icon name="folder" class="pointer-events-none absolute top-1/2 left-2.5 h-3.5 w-3.5 -translate-y-1/2 text-muted" />
            <select
              v-model="selectedProjectId"
              :disabled="isStreaming"
              title="Proyek yang terhubung: perintah 'push ke <link GitHub>' akan mem-push seluruh berkas proyek ini"
              class="max-w-[9rem] rounded-xl border-2 border-line bg-surface py-1.5 pr-2 pl-7 text-xs font-semibold text-ink outline-none transition-colors hover:border-violet-400 focus:border-violet-500 sm:max-w-[13rem]"
              @change="onProjectChange"
            >
              <option value="">Tanpa proyek</option>
              <option v-for="p in projects" :key="p.id" :value="p.id">{{ p.name }}</option>
            </select>
          </label>
        </div>

        <div class="flex items-center gap-2">
          <button
            v-if="activeConvId && conversationFiles.length > 0"
            type="button"
            :disabled="isStreaming"
            class="btn btn-secondary btn-sm"
            :title="`Push ${conversationFiles.length} berkas terbaru dari percakapan ini`"
            @click="openPushModal(conversationFiles)"
          >
            <Icon name="github" class="h-4 w-4" />
            <span class="hidden sm:inline">Push</span>
            <span class="rounded-md bg-violet-500 px-1.5 text-[10px] font-bold text-white">{{ conversationFiles.length }}</span>
          </button>
          <label class="relative">
            <Icon name="cpu" class="pointer-events-none absolute top-1/2 left-2.5 h-3.5 w-3.5 -translate-y-1/2 text-violet-600 dark:text-violet-300" />
            <select
              v-model="selectedModel"
              :disabled="isStreaming || models.length === 0"
              :title="modelSource === 'live' ? 'Katalog live dari TokenomixAI' : 'Katalog lokal (gateway tidak terjangkau)'"
              class="max-w-[11rem] rounded-xl border-2 border-ink bg-yellow-300 py-1.5 pr-2 pl-7 font-mono text-xs font-semibold text-zinc-900 shadow-brutal-sm outline-none transition-transform hover:-translate-y-0.5 disabled:opacity-60 sm:max-w-[16rem]"
              @change="onModelChange"
            >
              <option v-if="models.length === 0" value="">Memuat model...</option>
              <option v-for="m in models" :key="m.id" :value="m.id" :disabled="!m.available">
                {{ modelLabel(m) }}
              </option>
            </select>
          </label>
        </div>
      </div>

      <!-- Messages -->
      <div ref="messagesContainer" class="flex-1 space-y-6 overflow-y-auto p-4 select-text sm:p-6">
        <div v-if="messages.length === 0 && !isStreaming" class="flex h-full flex-col items-center justify-center text-center">
          <span class="grid h-20 w-20 place-items-center rounded-3xl border-2 border-ink bg-brand-gradient text-white shadow-brutal animate-float">
            <Icon name="bot" class="h-10 w-10" />
          </span>
          <h3 class="mt-6 font-display text-2xl font-bold text-ink sm:text-3xl">Mau bikin apa hari ini?</h3>
          <p class="mt-2 max-w-md text-sm text-muted">
            Tanya soal kode, minta agent membaca repo, menulis fitur, atau push langsung ke GitHub.
          </p>
          <div class="mt-8 grid w-full max-w-2xl gap-3 sm:grid-cols-2">
            <button
              v-for="(s, i) in suggestions"
              :key="s.text"
              type="button"
              class="group flex items-start gap-3 rounded-2xl border-2 border-line bg-card p-4 text-left text-sm transition-all hover:-translate-y-1 hover:border-ink hover:shadow-brutal"
              @click="useSuggestion(s.text)"
            >
              <span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl border-2 border-zinc-950 transition-transform group-hover:rotate-6" :class="tileColors[i % tileColors.length]">
                <Icon :name="s.icon" class="h-4 w-4" />
              </span>
              <span class="text-ink/80 group-hover:text-ink">{{ s.text }}</span>
            </button>
          </div>
        </div>

        <div
          v-for="msg in messages"
          :key="msg.id || msg.tempId"
          class="flex max-w-3xl gap-3"
          :class="msg.role === 'user' ? 'ml-auto justify-end' : 'mr-auto justify-start'"
        >
          <div v-if="msg.role !== 'user'" class="mt-1 grid h-9 w-9 shrink-0 place-items-center rounded-xl border-2 border-ink bg-brand-gradient text-white shadow-brutal-sm">
            <Icon name="bot" class="h-4 w-4" />
          </div>

          <div
            class="max-w-2xl overflow-hidden rounded-2xl border-2 p-4 text-sm leading-relaxed"
            :class="msg.role === 'user'
              ? 'rounded-br-md border-ink bg-violet-600 text-white shadow-brutal-sm'
              : 'rounded-bl-md border-line bg-card text-ink'"
          >
            <div class="chat-md font-sans whitespace-pre-wrap" v-html="renderMarkdown(msg.content)"></div>
            <div v-if="msg.role === 'assistant' && codeFilesOf(msg).length > 0" class="mt-3 flex flex-wrap items-center gap-2 border-t border-line pt-3 text-[11px]">
              <button type="button" :disabled="isStreaming" class="btn btn-brutal btn-sm" @click="openPushModal(codeFilesOf(msg))">
                <Icon name="upload" class="h-3.5 w-3.5" />
                Push ke GitHub
              </button>
              <span class="truncate text-muted">
                {{ codeFilesOf(msg).map(f => f.path || 'tanpa path').join(', ') }}
              </span>
            </div>
          </div>
        </div>

        <!-- Live agent activity -->
        <div v-if="isStreaming" class="mr-auto flex max-w-3xl justify-start gap-3">
          <div class="mt-1 grid h-9 w-9 shrink-0 place-items-center rounded-xl border-2 border-ink bg-brand-gradient text-white shadow-brutal-sm animate-pulse">
            <Icon name="bot" class="h-4 w-4" />
          </div>
          <div class="glow-border max-w-2xl rounded-2xl rounded-bl-md border-2 border-violet-400/60 bg-card p-4 text-sm leading-relaxed text-ink">
            <AgentActivity
              class="mb-3"
              :status="agentStatusLabel"
              :steps="toolActivity"
              :started-at="agentStartedAt"
              :active="isStreaming"
            />
            <div v-if="streamingText" class="chat-md font-sans whitespace-pre-wrap" v-html="renderMarkdown(streamingText)"></div>
          </div>
        </div>
      </div>

      <!-- Composer -->
      <div class="shrink-0 border-t-2 border-line bg-card/80 p-3 backdrop-blur-xl sm:p-4">
        <div class="mx-auto flex max-w-3xl items-end gap-2 rounded-[1.4rem] border-2 border-ink bg-surface p-2 shadow-brutal-sm transition-shadow focus-within:shadow-brutal">
          <textarea
            ref="composerRef"
            v-model="inputContent"
            rows="2"
            :placeholder="selectedProjectId
              ? 'Ketik pesan... atau: push ke https://github.com/username/repo-baru'
              : 'Ketik pesan (Enter kirim, Shift+Enter baris baru)'"
            class="max-h-48 flex-1 resize-none bg-transparent px-2 py-1.5 text-sm text-ink outline-none placeholder:text-muted"
            @keydown.enter.exact.prevent="sendMessage"
          ></textarea>

          <button v-if="isStreaming" type="button" class="btn btn-sm shrink-0 border-2 border-ink bg-rose-500 text-white shadow-brutal-sm hover:-translate-y-0.5" @click="stopGeneration">
            <Icon name="stop" class="h-4 w-4" />
            Berhenti
          </button>
          <button v-else type="button" :disabled="!inputContent.trim()" class="btn btn-primary btn-sm shine shrink-0" @click="sendMessage">
            <Icon name="send" class="h-4 w-4" />
            Kirim
          </button>
        </div>
        <p class="mx-auto mt-2 max-w-3xl px-1 text-[11px] text-muted">
          Tip: tulis <span class="font-mono text-ink">push ke https://github.com/user/repo</span> untuk mengirim berkas langsung.
        </p>
      </div>
    </section>

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
import { api, getCsrfToken, redirectToLoginIfNeeded } from '@/api/client';
import { useToast } from '@/composables/useToast';
import { marked } from 'marked';
import DOMPurify from 'dompurify';
import GitHubPushModal from '@/components/GitHubPushModal.vue';
import AgentActivity from '@/components/AgentActivity.vue';
import type { AgentStep } from '@/types/agent';
import { extractCodeFiles, collectLatestFiles, type CodeFile } from '@/utils/codeBlocks';
import { useModels } from '@/composables/useModels';
import { notifyUsageChanged } from '@/composables/useUsage';

const toast = useToast();

const conversations = ref<any[]>([]);
const activeConvId = ref<string>('');
const activeConv = ref<any>(null);
const messages = ref<any[]>([]);
const MODEL_STORAGE_KEY = 'xzy_chat_model';
const { models, defaultModel, source: modelSource, load: loadModels, isAvailable: isKnownModel, label: modelLabel } = useModels();
const selectedModel = ref('');
const projects = ref<any[]>([]);
const writingProgress = ref('');
const isWaitingServer = ref(false);
const toolActivity = ref<AgentStep[]>([]);
const agentPhase = ref('Menghubungi model');
const agentStartedAt = ref(Date.now());

// What the AI is doing right now, shown next to the animated indicator
const agentStatusLabel = computed(() => {
    if (isWaitingServer.value) return 'Koneksi terputus, menunggu hasil dari server';
    if (writingProgress.value) return writingProgress.value;
    const running = [...toolActivity.value].reverse().find(t => t.status === 'running');
    if (running) return running.label;
    return agentPhase.value;
});

const TOOL_WRITING_LABEL: Record<string, string> = {
    github_push_files: 'Menulis berkas untuk di-push',
    project_write_files: 'Menulis berkas proyek',
    github_create_repo: 'Menyiapkan repositori baru',
};
const selectedProjectId = ref('');
const pushModalOpen = ref(false);
const pushFiles = ref<CodeFile[]>([]);
const inputContent = ref('');
const isStreaming = ref(false);
const streamingText = ref('');
const isMobileListOpen = ref(false);
const composerRef = ref<HTMLTextAreaElement | null>(null);

const tileColors = ['bg-yellow-300 text-zinc-900', 'bg-lime-300 text-zinc-900', 'bg-violet-500 text-white', 'bg-orange-400 text-zinc-900'];
const suggestions = [
    { icon: 'code', text: 'Buatkan REST API CRUD produk dengan Laravel 12 lengkap dengan validasi' },
    { icon: 'github', text: 'Lihat isi repo GitHub saya lalu jelaskan strukturnya' },
    { icon: 'wand', text: 'Rapikan dan refactor komponen Vue ini agar lebih mudah dibaca' },
    { icon: 'shield', text: 'Cek potensi celah keamanan di controller autentikasi saya' },
];

// Fill the composer with a starter prompt so the user can tweak it before sending
const useSuggestion = (text: string) => {
    inputContent.value = text;
    nextTick(() => composerRef.value?.focus());
};
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

const fetchProjects = async () => {
    try {
        const res = await api.get('/api/projects');
        projects.value = res.data || res.projects || [];
    } catch {
        // project list is optional for chat
    }
};

const onProjectChange = async () => {
    if (!activeConvId.value) return;
    try {
        const res = await api.put(`/api/conversations/${activeConvId.value}`, { project_id: selectedProjectId.value || null });
        activeConv.value = res.conversation;
        const name = projects.value.find(p => p.id === selectedProjectId.value)?.name;
        toast.info(name ? `Percakapan terhubung ke proyek ${name}.` : 'Percakapan tidak lagi terhubung ke proyek.');
    } catch (e: any) {
        toast.error('Gagal mengganti proyek: ' + e.message);
    }
};

const fetchModels = async () => {
    try {
        await loadModels(true);

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
            model: selectedModel.value || undefined,
            project_id: selectedProjectId.value || undefined,
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
        selectedProjectId.value = res.conversation.project_id || '';
        scrollToBottom();

        const last = [...messages.value].reverse().find((m: any) => m.role === 'assistant');
        const ageMs = last ? Date.now() - new Date(last.updated_at || last.created_at).getTime() : Infinity;
        if (last && last.status === 'streaming' && !isStreaming.value && ageMs < 20 * 60 * 1000) {
            waitForServerResult(id, new Date(last.created_at).getTime());
        }
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

// Long agent jobs: when the server reports its per-request step limit, continue by itself
const MAX_AUTO_CONTINUE = 5;
const autoContinueCount = ref(0);
let autoContinueNext = false;
let hitStepLimit = false;

const sendMessage = async () => {
    if (!inputContent.value.trim() || isStreaming.value) return;

    // A message typed by the user starts a fresh auto-continue budget
    if (autoContinueNext) {
        autoContinueNext = false;
    } else {
        autoContinueCount.value = 0;
    }
    hitStepLimit = false;

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
    toolActivity.value = [];
    writingProgress.value = '';
    agentPhase.value = 'Menghubungi model';
    agentStartedAt.value = Date.now();

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

        if (response.status === 401) {
            redirectToLoginIfNeeded();
        }

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
                            agentPhase.value = 'Menulis jawaban';
                            writingProgress.value = '';
                            scrollToBottom();
                        } else if (parsed.event === 'tool') {
                            writingProgress.value = '';
                            const existing = toolActivity.value.find(t => t.id === parsed.id);
                            if (existing) {
                                Object.assign(existing, parsed, parsed.status !== 'running' ? { endedAt: Date.now() } : {});
                            } else {
                                toolActivity.value.push({ ...parsed, startedAt: Date.now() });
                            }
                            if (parsed.status !== 'running') {
                                agentPhase.value = 'Berpikir';
                            }
                            if (parsed.project_id) {
                                selectedProjectId.value = parsed.project_id;
                                fetchProjects();
                            }
                            scrollToBottom();
                        } else if (parsed.event === 'start') {
                            agentPhase.value = 'Berpikir';
                        } else if (parsed.event === 'retry') {
                            // Gateway busy (502/429) or connection dropped: the server retries this step
                            streamingText.value = parsed.content ?? streamingText.value;
                            writingProgress.value = `Gateway sibuk, mencoba ulang (${parsed.attempt}/${parsed.max})`;
                            scrollToBottom();
                        } else if (parsed.event === 'progress') {
                            writingProgress.value = parsed.chars > 0
                                ? `${TOOL_WRITING_LABEL[parsed.tool] || 'Menyiapkan aksi'} · ${(parsed.chars / 1024).toFixed(1)} KB`
                                : '';
                            scrollToBottom();
                        } else if (parsed.event === 'step_limit') {
                            hitStepLimit = true;
                        } else if (parsed.event === 'stopped') {
                            hitStepLimit = false;
                            autoContinueCount.value = MAX_AUTO_CONTINUE;
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
            hitStepLimit = false;
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
        const networkDrop = e instanceof TypeError || /network|fetch|load failed|aborted/i.test(e?.message || '');
        if (networkDrop) {
            // The server keeps working after the browser connection drops: wait for its result
            toast.info('Koneksi terputus, AI masih bekerja di server. Menunggu hasilnya...');
            await waitForServerResult(convId);
            return;
        }
        toast.error('Gagal mengirim pesan: ' + e.message);
        messages.value.push({
            tempId: Date.now() + 1,
            role: 'assistant',
            content: `[Gagal menerima respons AI]: ${e.message}`,
        });
    } finally {
        if (!isWaitingServer.value) {
            isStreaming.value = false;
        }
        notifyUsageChanged();
        scrollToBottom();
        scheduleAutoContinue(convId);
    }
};

const scheduleAutoContinue = (convId: string) => {
    if (!hitStepLimit) return;
    hitStepLimit = false;
    if (autoContinueCount.value >= MAX_AUTO_CONTINUE) {
        toast.info('Pekerjaan masih panjang. Kirim "lanjutkan" untuk meneruskan.');
        return;
    }
    autoContinueCount.value++;
    toast.info(`Melanjutkan otomatis (${autoContinueCount.value}/${MAX_AUTO_CONTINUE})...`);
    window.setTimeout(() => {
        // Only if the user is still on this conversation and has not typed something else
        if (activeConvId.value !== convId || isStreaming.value || inputContent.value.trim()) return;
        inputContent.value = 'lanjutkan';
        autoContinueNext = true;
        sendMessage();
    }, 1500);
};


/**
 * Poll the conversation until the assistant message that is still being produced
 * on the server is finished, showing its saved progress meanwhile.
 */
const waitForServerResult = async (convId: string, startedAt?: number) => {
    if (isWaitingServer.value) return;
    if (!isStreaming.value) {
        // Resuming a conversation that is still running on the server
        toolActivity.value = [];
        agentStartedAt.value = startedAt ?? Date.now();
    }
    isWaitingServer.value = true;
    isStreaming.value = true;
    writingProgress.value = '';
    const deadline = Date.now() + 20 * 60 * 1000;

    try {
        while (Date.now() < deadline && activeConvId.value === convId) {
            let fresh: any;
            try {
                fresh = await api.get(`/api/conversations/${convId}`);
            } catch {
                await new Promise(r => setTimeout(r, 5000));
                continue;
            }
            const list = fresh.conversation.messages || [];
            const last = [...list].reverse().find((m: any) => m.role === 'assistant');

            if (last && last.status === 'streaming') {
                streamingText.value = (last.content || '') + '\n\n_Koneksi sempat terputus. AI masih bekerja di server, hasil akan muncul otomatis..._';
                messages.value = list.filter((m: any) => m.id !== last.id);
                scrollToBottom();
                await new Promise(r => setTimeout(r, 4000));
                continue;
            }

            activeConv.value = fresh.conversation;
            messages.value = list;
            selectedProjectId.value = fresh.conversation.project_id || '';
            break;
        }
        if (Date.now() >= deadline) {
            toast.warning('AI belum selesai setelah 20 menit. Buka lagi percakapan ini nanti untuk melihat hasilnya.');
        }
    } finally {
        streamingText.value = '';
        isWaitingServer.value = false;
        isStreaming.value = false;
        notifyUsageChanged();
        scrollToBottom();
    }
};

const stopGeneration = async () => {
    if (!activeConvId.value) return;
    // Stopping also cancels any pending automatic "lanjutkan"
    hitStepLimit = false;
    autoContinueCount.value = MAX_AUTO_CONTINUE;
    try {
        await api.post(`/api/conversations/${activeConvId.value}/stop`);
        toast.info('Permintaan pembatalan dikirim.');
    } catch (e) {
        // Ignore
    }
};

onMounted(async () => {
    fetchProjects();
    await fetchModels();
    fetchConversations();
});
</script>
