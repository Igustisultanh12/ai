<template>
  <div data-no-motion class="h-[calc(100vh-4rem)] flex flex-col bg-surface overflow-hidden select-none">
    <!-- Top Workspace Bar -->
    <div class="h-11 px-4 bg-card border-b border-line flex items-center justify-between text-xs shrink-0">
      <div class="flex items-center gap-3">
        <router-link to="/projects" class="text-muted hover:text-ink transition-colors">
          <Icon name="arrow-left" class="h-4 w-4" /> Proyek
        </router-link>
        <span class="text-line">|</span>
        <span class="font-bold text-ink text-sm truncate max-w-[200px]">{{ project?.name }}</span>
        <span class="px-2 py-0.5 rounded bg-violet-500/10 border border-violet-700/40 text-violet-600 dark:text-violet-300 text-[10px] hidden sm:inline-block">
          {{ project?.template }}
        </span>
      </div>

      <!-- Action buttons -->
      <div class="flex items-center gap-2">
        <!-- Save button -->
        <button
          @click="saveCurrentFile"
          :disabled="!activeFile || !isDirty"
          class="px-2.5 py-1 rounded-lg text-xs font-semibold flex items-center gap-1 transition-colors"
          :class="isDirty ? 'bg-violet-600 hover:bg-violet-500 text-white shadow-sm' : 'bg-surface-2 text-muted cursor-not-allowed'"
        >
          <Icon name="save" class="h-4 w-4" />
          <span class="hidden sm:inline">Simpan</span>
        </button>

        <!-- Export button -->
        <button
          @click="openExportModal"
          class="btn btn-secondary btn-sm flex items-center gap-1"
        >
          <Icon name="package" class="h-4 w-4" />
          <span class="hidden sm:inline">Ekspor Proyek</span>
        </button>

        <!-- GitHub push button -->
        <button
          @click="openGithubModal"
          class="btn btn-secondary btn-sm flex items-center gap-1"
        >
          <Icon name="github" class="h-4 w-4" />
          <span class="hidden sm:inline">GitHub</span>
        </button>

        <!-- Mobile pane switcher tabs -->
        <div class="flex md:hidden border border-line rounded-lg p-0.5 bg-surface ml-1">
          <button
            @click="mobileActiveTab = 'files'"
            class="px-2 py-0.5 rounded text-[10px]"
            :class="mobileActiveTab === 'files' ? 'bg-violet-600 text-white' : 'text-muted'"
          >
            Berkas
          </button>
          <button
            @click="mobileActiveTab = 'editor'"
            class="px-2 py-0.5 rounded text-[10px]"
            :class="mobileActiveTab === 'editor' ? 'bg-violet-600 text-white' : 'text-muted'"
          >
            Editor
          </button>
          <button
            @click="mobileActiveTab = 'ai'"
            class="px-2 py-0.5 rounded text-[10px]"
            :class="mobileActiveTab === 'ai' ? 'bg-violet-600 text-white' : 'text-muted'"
          >
            AI Vibecoding
          </button>
        </div>
      </div>
    </div>

    <!-- 3-Pane Workspace Body -->
    <div class="flex-1 flex overflow-hidden">
      <!-- PANE 1: File Explorer -->
      <div
        class="border-r border-line bg-surface flex flex-col shrink-0 transition-all duration-150"
        :class="[
          mobileActiveTab === 'files' ? 'flex w-full md:w-60' : 'hidden md:flex md:w-60'
        ]"
      >
        <div class="h-9 px-3 bg-card/60 border-b border-line flex items-center justify-between text-xs text-muted">
          <span class="font-bold uppercase tracking-wider text-[10px] text-muted">Berkas Proyek</span>
          <div class="flex items-center gap-1">
            <button @click="openNewFileModal" class="p-1 hover:text-ink rounded" title="Tambah Berkas"><Icon name="plus" class="h-4 w-4" /></button>
            <button @click="refreshFiles" class="p-1 hover:text-ink rounded" title="Muat Ulang Berkas"><Icon name="refresh" class="h-4 w-4" /></button>
          </div>
        </div>

        <div class="flex-1 overflow-y-auto p-2 space-y-0.5 font-mono text-xs">
          <div
            v-for="file in files"
            :key="file.path"
            @click="openFile(file.path)"
            class="flex items-center justify-between px-2.5 py-1.5 rounded-lg cursor-pointer transition-colors group"
            :class="activeFilePath === file.path ? 'bg-violet-600/20 text-violet-600 dark:text-violet-300 font-semibold' : 'text-muted hover:text-ink hover:bg-card'"
          >
            <div class="flex items-center gap-2 truncate">
              <Icon name="file-code" class="h-3.5 w-3.5 shrink-0 text-muted" />
              <span class="truncate">{{ file.path }}</span>
            </div>
            <button
              @click.stop="confirmDeleteFile(file.path)"
              class="opacity-0 group-hover:opacity-100 hover:text-rose-600 dark:hover:text-rose-400 text-muted/70 text-[10px] px-1"
              title="Hapus Berkas"
            >
              <Icon name="trash" class="h-3.5 w-3.5" />
            </button>
          </div>
        </div>
      </div>

      <!-- PANE 2: Code Editor -->
      <div
        class="flex-1 flex flex-col bg-surface overflow-hidden border-r border-line"
        :class="[
          mobileActiveTab === 'editor' ? 'flex w-full' : 'hidden md:flex'
        ]"
      >
        <!-- Editor Tabs -->
        <div class="h-9 bg-card/90 border-b border-line flex items-center overflow-x-auto text-xs shrink-0">
          <div
            v-for="tab in openTabs"
            :key="tab"
            @click="activeFilePath = tab"
            class="h-full px-3.5 flex items-center gap-2 border-r border-line cursor-pointer font-mono select-none"
            :class="activeFilePath === tab ? 'bg-surface text-violet-600 dark:text-violet-300 border-t-2 border-t-violet-400' : 'text-muted hover:text-ink hover:bg-card'"
          >
            <span class="truncate max-w-[150px]">{{ tab.split('/').pop() }}</span>
            <button @click.stop="closeTab(tab)" class="ml-1 text-muted hover:text-ink"><Icon name="x" class="h-3 w-3" /></button>
          </div>
        </div>

        <!-- Monaco Editor Container -->
        <div class="flex-1 relative">
          <MonacoEditor
            v-if="activeFilePath"
            v-model="activeFileContent"
            :file-path="activeFilePath"
            :is-dirty="isDirty"
            @save="saveCurrentFile"
          />
          <div v-else class="h-full flex flex-col items-center justify-center text-muted/70 space-y-2 p-4 text-center">
            <span class="grid h-16 w-16 place-items-center rounded-2xl border-2 border-ink bg-yellow-300 text-zinc-900 shadow-brutal-sm animate-bounce-soft"><Icon name="code" class="h-8 w-8" /></span>
            <p class="text-sm">Pilih berkas dari explorer atau buat berkas baru untuk mulai mengedit kode.</p>
          </div>
        </div>
      </div>

      <!-- PANE 3: AI Chat, Preview, Build Logs, Checkpoints -->
      <div
        class="flex flex-col bg-surface shrink-0 transition-all duration-150"
        :class="[
          mobileActiveTab === 'ai' ? 'flex w-full md:w-[480px] lg:w-[540px]' : 'hidden md:flex md:w-[480px] lg:w-[540px]'
        ]"
      >
        <!-- Right Panel Navigation Tabs -->
        <div class="h-9 px-2 bg-card border-b border-line flex items-center justify-between text-xs shrink-0">
          <div class="flex items-center gap-1">
            <button
              @click="rightTab = 'agent'"
              class="px-2.5 py-1 rounded-md transition-colors"
              :class="rightTab === 'agent' ? 'bg-violet-600 text-white font-semibold' : 'text-muted hover:text-ink'"
            >
              <Icon name="bot" class="h-3.5 w-3.5" /> Vibecoding
            </button>
            <button
              @click="openPreviewTab"
              class="px-2.5 py-1 rounded-md transition-colors"
              :class="rightTab === 'preview' ? 'bg-violet-600 text-white font-semibold' : 'text-muted hover:text-ink'"
            >
              <Icon name="eye" class="h-3.5 w-3.5" /> Pratinjau
            </button>
            <button
              @click="rightTab = 'logs'"
              class="px-2.5 py-1 rounded-md transition-colors"
              :class="rightTab === 'logs' ? 'bg-violet-600 text-white font-semibold' : 'text-muted hover:text-ink'"
            >
              <Icon name="terminal" class="h-3.5 w-3.5" /> Logs
            </button>
            <button
              @click="rightTab = 'checkpoints'"
              class="px-2.5 py-1 rounded-md transition-colors"
              :class="rightTab === 'checkpoints' ? 'bg-violet-600 text-white font-semibold' : 'text-muted hover:text-ink'"
            >
              <Icon name="history" class="h-3.5 w-3.5" /> Versi
            </button>
          </div>
        </div>

        <!-- Tab 1: AI Vibecoding Chat & Runner -->
        <div v-show="rightTab === 'agent'" class="flex-1 flex flex-col overflow-hidden">
          <!-- Control Bar -->
          <div class="p-2.5 bg-card/60 border-b border-line flex items-center justify-between gap-2 text-xs">
            <div class="flex items-center gap-2">
              <span class="text-muted">Model:</span>
              <select
                v-model="selectedModel"
                class="max-w-[12rem] px-2 py-1 rounded-lg bg-surface border border-line text-violet-600 dark:text-violet-300 font-mono text-[11px] outline-none"
              >
                <option v-if="models.length === 0" value="">Memuat model...</option>
                <option v-for="m in models" :key="m.id" :value="m.id" :disabled="!m.available">{{ modelLabel(m) }}</option>
              </select>
            </div>

            <label class="flex items-center gap-1.5 cursor-pointer text-ink/80 select-none">
              <input type="checkbox" v-model="autoApply" class="rounded bg-surface border-line text-violet-600" />
              <span class="text-[11px]">Auto-apply patch</span>
            </label>
          </div>

          <!-- Execution Stream / Run Output Area -->
          <div class="flex-1 overflow-y-auto p-4 space-y-4 select-text">
            <div v-if="!currentRun && !isRunning" class="p-8 text-center space-y-3 text-muted text-xs">
              <span class="mx-auto grid h-14 w-14 place-items-center rounded-2xl border-2 border-ink bg-brand-gradient text-white shadow-brutal-sm animate-float"><Icon name="sparkles" class="h-7 w-7" /></span>
              <p class="font-semibold text-muted">Jelaskan aplikasi atau perubahan yang Anda inginkan</p>
              <p class="text-[11px] leading-relaxed max-w-xs mx-auto">
                AI akan membaca workspace Anda, merencanakan solusi, menerapkan patch secara atomik, dan memverifikasi kode di sandbox.
              </p>
            </div>

            <!-- Active Run Indicator -->
            <div v-if="isRunning" class="p-4 rounded-xl border border-violet-500/40 bg-violet-500/10 space-y-3 animate-pulse">
              <div class="flex items-center gap-2 text-violet-600 dark:text-violet-300 font-semibold text-xs">
                <div class="w-3.5 h-3.5 border-2 border-violet-400 border-t-transparent rounded-full animate-spin"></div>
                <span>Siklus Vibecoding Berjalan...</span>
              </div>
              <p class="text-xs text-ink/80 leading-relaxed">
                Membaca berkas proyek, mengusulkan perubahan kode, dan memvalidasi sintaks sandbox...
              </p>
            </div>

            <!-- Completed Run Result -->
            <div v-if="currentRun" class="space-y-4">
              <!-- Plan box -->
              <div class="p-3.5 rounded-xl bg-card border border-line text-xs text-ink space-y-2">
                <div class="font-bold text-violet-600 dark:text-violet-400 flex items-center justify-between">
                  <span>Rencana & Hasil Vibecoding</span>
                  <span
                    class="px-2 py-0.5 rounded text-[10px] font-semibold"
                    :class="currentRun.status === 'completed' ? 'bg-emerald-500/20 text-emerald-600 dark:text-emerald-300' : 'bg-rose-500/20 text-rose-600 dark:text-rose-300'"
                  >
                    Status: {{ currentRun.status }}
                  </span>
                </div>
                <p class="text-ink/80 leading-relaxed whitespace-pre-wrap">{{ currentRun.plan }}</p>
              </div>

              <!-- Proposed Diffs -->
              <div v-if="currentRun.proposed_patches && currentRun.proposed_patches.length > 0" class="space-y-2">
                <div class="flex items-center justify-between text-xs">
                  <span class="font-bold text-ink">Usulan Perubahan ({{ currentRun.proposed_patches.length }} berkas):</span>
                  <button
                    v-if="!autoApply && currentRun.status !== 'completed'"
                    @click="applyProposedPatches"
                    :disabled="isApplying"
                    class="px-3 py-1 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white font-semibold text-xs shadow-md"
                  >
                    {{ isApplying ? 'Menerapkan...' : 'Terapkan Semua Patch' }}
                  </button>
                </div>

                <DiffViewer
                  v-for="patch in currentRun.proposed_patches"
                  :key="patch.path"
                  :patch="patch"
                />
              </div>

              <!-- Build status summary -->
              <div v-if="currentRun.build_log" class="p-3 rounded-xl bg-card/60 border border-line font-mono text-[11px] text-muted max-h-48 overflow-y-auto whitespace-pre-wrap">
                {{ currentRun.build_log }}
              </div>
            </div>
          </div>

          <!-- Bottom Prompt Input -->
          <div class="p-3 bg-card border-t border-line shrink-0">
            <div class="relative">
              <textarea
                v-model="promptInput"
                @keydown.ctrl.enter="triggerRun"
                @keydown.meta.enter="triggerRun"
                rows="3"
                placeholder="Deskripsikan fitur/perbaikan kode, atau: push ke https://github.com/username/repo-baru (Ctrl+Enter untuk kirim)"
                class="input p-3 pr-20 text-xs resize-none"
              ></textarea>
              <button
                @click="triggerRun"
                :disabled="isRunning || !promptInput.trim()"
                class="btn btn-primary shine btn-sm absolute right-2.5 bottom-2.5 disabled:opacity-40 disabled:pointer-events-none"
              >
                {{ isRunning ? 'Proses...' : 'Kirim' }}
              </button>
            </div>
          </div>
        </div>

        <!-- Tab 2: Live Sandbox Preview -->
        <div v-show="rightTab === 'preview'" class="flex-1 flex flex-col bg-surface overflow-hidden">
          <div class="h-9 px-3 bg-card/60 border-b border-line flex items-center justify-between text-xs text-muted">
            <span class="font-bold text-[10px] uppercase text-cyan-600 dark:text-cyan-400">Sandboxed Preview Origin</span>
            <div class="flex items-center gap-2">
              <button @click="openPreviewTab" class="hover:text-ink p-1" title="Segarkan"><Icon name="refresh" class="h-3.5 w-3.5" /></button>
              <a
                v-if="previewUrl"
                :href="previewUrl"
                target="_blank"
                class="hover:text-violet-600 dark:hover:text-violet-400 p-1 flex items-center gap-1"
                title="Buka di tab baru"
              >
                <Icon name="external-link" class="h-3.5 w-3.5" /> Tab Baru
              </a>
            </div>
          </div>

          <div class="flex-1 w-full h-full bg-white relative">
            <iframe
              v-if="previewUrl"
              :src="previewUrl"
              class="w-full h-full border-0"
              sandbox="allow-scripts allow-forms allow-same-origin"
            ></iframe>
            <div v-else class="h-full flex items-center justify-center text-xs text-muted bg-surface">
              Membuat sesi token pratinjau...
            </div>
          </div>
        </div>

        <!-- Tab 3: Build & Test Logs -->
        <div v-show="rightTab === 'logs'" class="flex-1 p-4 font-mono text-xs bg-surface text-ink/80 overflow-y-auto space-y-2 select-text">
          <div class="text-muted text-[10px] uppercase font-bold tracking-wider mb-2">Build & Sandbox Diagnostics</div>
          <div v-if="currentRun?.build_log" class="whitespace-pre-wrap leading-relaxed">
            {{ currentRun.build_log }}
          </div>
          <div v-else class="text-muted text-center py-12">
            Belum ada log eksekusi build pada proyek ini.
          </div>
        </div>

        <!-- Tab 4: Version Checkpoints -->
        <div v-show="rightTab === 'checkpoints'" class="flex-1 p-3 overflow-y-auto space-y-2">
          <div class="text-[10px] font-bold text-muted uppercase px-1 mb-2">Riwayat Checkpoint Proyek</div>
          <div
            v-for="cp in checkpoints"
            :key="cp.id"
            class="p-3 rounded-xl border border-line bg-card/80 space-y-1.5 text-xs"
          >
            <div class="flex items-center justify-between">
              <span class="font-semibold text-ink truncate max-w-[200px]">{{ cp.commit_message }}</span>
              <span
                class="px-1.5 py-0.5 rounded text-[10px] font-semibold"
                :class="cp.build_status === 'passed' ? 'bg-emerald-500/20 text-emerald-600 dark:text-emerald-400' : 'bg-surface-2 text-muted'"
              >
                {{ cp.build_status || 'saved' }}
              </span>
            </div>
            <div class="flex items-center justify-between text-[10px] text-muted pt-1">
              <span>{{ formatDate(cp.created_at) }} • {{ cp.file_count }} berkas</span>
              <button
                @click="restoreCheckpoint(cp.id)"
                class="btn btn-secondary"
              >
                Pulihkan
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal New File -->
    <div
      v-if="isNewFileModalOpen"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-zinc-950/60 backdrop-blur-sm"
    >
      <div class="max-w-sm w-full rounded-[2rem] bg-card border-2 border-ink p-5 space-y-4 shadow-brutal-lg">
        <h3 class="font-bold text-sm text-ink">Buat Berkas Baru</h3>
        <input
          v-model="newFilePath"
          type="text"
          placeholder="resources/js/components/MyComponent.vue"
          class="input text-xs font-mono"
        />
        <div class="flex justify-end gap-2 text-xs">
          <button @click="isNewFileModalOpen = false" class="px-3 py-1.5 text-muted hover:text-ink">Batal</button>
          <button @click="createNewFile" class="btn btn-primary shine">Buat Berkas</button>
        </div>
      </div>
    </div>

    <!-- Modal Export ZIP / RAR -->
    <div
      v-if="isExportModalOpen"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-zinc-950/60 backdrop-blur-sm"
    >
      <div class="max-w-md w-full rounded-[2rem] bg-card border-2 border-ink p-6 space-y-4 shadow-brutal-lg">
        <div class="flex items-center justify-between border-b border-line pb-3">
          <h3 class="font-bold text-base text-ink">Ekspor Proyek</h3>
          <button @click="isExportModalOpen = false" class="btn-ghost grid h-9 w-9 place-items-center rounded-xl" title="Tutup"><Icon name="x" class="h-5 w-5" /></button>
        </div>

        <p class="text-xs text-muted leading-relaxed">
          Pilih format arsip proyek utuh. Kredensial rahasia, .git, dan vendor dikecualikan secara otomatis.
        </p>

        <!-- ZIP Option -->
        <div class="p-4 rounded-xl border border-line bg-surface flex items-center justify-between">
          <div>
            <h4 class="font-bold text-ink text-sm">Arsip ZIP Standar</h4>
            <p class="text-[11px] text-muted">Kompatibel universal dengan seluruh sistem operasi</p>
          </div>
          <button
            @click="triggerExport('zip')"
            :disabled="isExporting"
            class="btn btn-primary shine btn-sm"
          >
            {{ isExporting ? 'Memproses...' : 'Unduh ZIP' }}
          </button>
        </div>

        <!-- RAR Option with Readiness Status -->
        <div class="p-4 rounded-xl border border-line bg-surface space-y-2">
          <div class="flex items-center justify-between">
            <div>
              <h4 class="font-bold text-ink text-sm">Arsip Asli RAR</h4>
              <p class="text-[11px] text-muted">Kompresi tinggi menggunakan binary RAR resmi</p>
            </div>
            <button
              @click="triggerExport('rar')"
              :disabled="isExporting || !exportReadiness?.rar?.is_ready"
              class="px-4 py-2 rounded-lg font-semibold text-xs transition-colors"
              :class="exportReadiness?.rar?.is_ready ? 'bg-violet-600 hover:bg-violet-500 text-white' : 'bg-surface-2 text-muted cursor-not-allowed'"
            >
              Unduh RAR
            </button>
          </div>

          <div
            v-if="!exportReadiness?.rar?.is_ready"
            class="p-2.5 rounded-lg bg-amber-500/10 border border-amber-500/30 text-amber-700 dark:text-amber-300 text-[11px] space-y-1"
          >
            <p class="flex items-center gap-1.5 font-semibold"><Icon name="alert-triangle" class="h-3.5 w-3.5" /> Fitur RAR Belum Siap</p>
            <p class="text-[10px] leading-relaxed text-amber-700 dark:text-amber-200">
              {{ exportReadiness?.rar?.instructions || 'Binary RAR belum terpasang pada host server.' }}
            </p>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal GitHub Push -->
    <div
      v-if="isGithubModalOpen"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-zinc-950/60 backdrop-blur-sm"
    >
      <div class="max-w-md w-full rounded-[2rem] bg-card border-2 border-ink p-6 space-y-4 shadow-brutal-lg">
        <div class="flex items-center justify-between border-b border-line pb-3">
          <h3 class="font-bold text-base text-ink">Sinkronisasi ke GitHub</h3>
          <button @click="isGithubModalOpen = false" class="btn-ghost grid h-9 w-9 place-items-center rounded-xl" title="Tutup"><Icon name="x" class="h-5 w-5" /></button>
        </div>

        <div v-if="!githubStatus?.is_connected" class="p-4 rounded-xl bg-amber-500/10 border border-amber-500/30 text-amber-700 dark:text-amber-300 text-xs space-y-2">
          <p class="font-semibold">Akun GitHub Belum Terhubung</p>
          <p>Hubungkan Personal Access Token GitHub Anda pada menu Pengaturan Akun terlebih dahulu.</p>
          <router-link to="/settings" class="btn btn-primary shine btn-sm">
            Buka Pengaturan GitHub <Icon name="arrow-right" class="h-4 w-4" />
          </router-link>
        </div>

        <form v-else @submit.prevent="handleGithubPush" class="space-y-4 text-xs">
          <div>
            <label class="label">Target Repository (link GitHub atau owner/repo)</label>
            <input
              v-model="githubForm.repo"
              type="text"
              required
              class="input font-mono"
              placeholder="https://github.com/username/repo atau username/repo"
            />
          </div>

          <div>
            <label class="label">Target Branch</label>
            <input
              v-model="githubForm.branch"
              type="text"
              required
              class="input font-mono"
              placeholder="main"
            />
          </div>

          <div>
            <label class="label">Pesan Commit</label>
            <input
              v-model="githubForm.message"
              type="text"
              required
              class="input"
              placeholder="Perbarui komponen dan perbaikan logika"
            />
          </div>

          <div class="p-3 rounded-xl bg-surface border border-line text-[11px] text-muted">
            <span class="inline-flex items-center gap-1.5"><Icon name="shield-check" class="h-3.5 w-3.5 text-lime-600 dark:text-lime-300" /> Pemindaian secret otomatis aktif sebelum commit dibuat.</span>
          </div>

          <div class="flex justify-end gap-2 pt-2">
            <button type="button" @click="isGithubModalOpen = false" class="px-3 py-1.5 text-muted">Batal</button>
            <button
              type="submit"
              :disabled="isPushing"
              class="btn btn-primary shine btn-sm disabled:opacity-50"
            >
              {{ isPushing ? 'Memindai & Push...' : 'Push ke GitHub' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted, computed } from 'vue';
import { useRoute } from 'vue-router';
import { api } from '@/api/client';
import { useToast } from '@/composables/useToast';
import { useModels } from '@/composables/useModels';
import { notifyUsageChanged } from '@/composables/useUsage';
import MonacoEditor from '@/components/MonacoEditor.vue';
import DiffViewer from '@/components/DiffViewer.vue';

const route = useRoute();
const toast = useToast();
const projectId = route.params.id as string;

const project = ref<any>(null);
const files = ref<any[]>([]);
const checkpoints = ref<any[]>([]);
const openTabs = ref<string[]>([]);
const activeFilePath = ref<string>('');
const activeFileContent = ref<string>('');
const originalFileContent = ref<string>('');

const mobileActiveTab = ref<'files' | 'editor' | 'ai'>('editor');
const rightTab = ref<'agent' | 'preview' | 'logs' | 'checkpoints'>('agent');

const { models, defaultModel, load: loadModels, isAvailable: isModelAvailable, label: modelLabel } = useModels();
const selectedModel = ref('');
const autoApply = ref(true);
const promptInput = ref('');
const isRunning = ref(false);
const isApplying = ref(false);
const currentRun = ref<any>(null);

const previewUrl = ref<string>('');

// Modals
const isNewFileModalOpen = ref(false);
const newFilePath = ref('');
const isExportModalOpen = ref(false);
const isExporting = ref(false);
const exportReadiness = ref<any>(null);

const isGithubModalOpen = ref(false);
const isPushing = ref(false);
const githubStatus = ref<any>(null);
const githubForm = reactive({
    repo: '',
    branch: 'main',
    message: '',
});

const activeFile = computed(() => activeFilePath.value);
const isDirty = computed(() => activeFileContent.value !== originalFileContent.value);

const formatDate = (dateStr: string) => {
    if (!dateStr) return '';
    return new Date(dateStr).toLocaleString('id-ID', {
        dateStyle: 'short',
        timeStyle: 'short',
    });
};

const fetchProjectDetails = async () => {
    try {
        const res = await api.get(`/api/projects/${projectId}`);
        project.value = res.project;
        files.value = res.project.files || [];
        githubForm.repo = project.value.github_repo || '';
        githubForm.branch = project.value.github_branch || 'main';

        // Auto open resources/js/App.vue or README.md
        const defaultFile = files.value.find(f => f.path === 'resources/js/App.vue') || files.value[0];
        if (defaultFile) {
            openFile(defaultFile.path);
        }
    } catch (e: any) {
        toast.error('Gagal memuat proyek: ' + e.message);
    }
};

const refreshFiles = async () => {
    try {
        const res = await api.get(`/api/projects/${projectId}/files`);
        files.value = res.files || [];
    } catch (e: any) {
        toast.error('Gagal memuat berkas: ' + e.message);
    }
};

const openFile = async (path: string) => {
    if (!openTabs.value.includes(path)) {
        openTabs.value.push(path);
    }
    activeFilePath.value = path;

    try {
        const res = await api.get(`/api/projects/${projectId}/file?path=${encodeURIComponent(path)}`);
        activeFileContent.value = res.content ?? '';
        originalFileContent.value = res.content ?? '';
        mobileActiveTab.value = 'editor';
    } catch (e: any) {
        toast.error('Gagal membuka berkas: ' + e.message);
    }
};

const closeTab = (path: string) => {
    openTabs.value = openTabs.value.filter(t => t !== path);
    if (activeFilePath.value === path) {
        activeFilePath.value = openTabs.value[0] || '';
        if (activeFilePath.value) {
            openFile(activeFilePath.value);
        } else {
            activeFileContent.value = '';
            originalFileContent.value = '';
        }
    }
};

const saveCurrentFile = async () => {
    if (!activeFilePath.value) return;
    try {
        await api.post(`/api/projects/${projectId}/file`, {
            path: activeFilePath.value,
            content: activeFileContent.value,
        });
        originalFileContent.value = activeFileContent.value;
        toast.success(`Berkas ${activeFilePath.value} disimpan.`);
        refreshFiles();
    } catch (e: any) {
        toast.error('Gagal menyimpan berkas: ' + e.message);
    }
};

const openNewFileModal = () => {
    newFilePath.value = '';
    isNewFileModalOpen.value = true;
};

const createNewFile = async () => {
    if (!newFilePath.value.trim()) return;
    try {
        await api.post(`/api/projects/${projectId}/file`, {
            path: newFilePath.value.trim(),
            content: '',
        });
        toast.success('Berkas baru dibuat.');
        isNewFileModalOpen.value = false;
        await refreshFiles();
        openFile(newFilePath.value.trim());
    } catch (e: any) {
        toast.error('Gagal membuat berkas: ' + e.message);
    }
};

const confirmDeleteFile = async (path: string) => {
    if (!confirm(`Hapus berkas ${path}?`)) return;
    try {
        await api.delete(`/api/projects/${projectId}/file?path=${encodeURIComponent(path)}`);
        closeTab(path);
        refreshFiles();
        toast.success('Berkas dihapus.');
    } catch (e: any) {
        toast.error('Gagal menghapus berkas: ' + e.message);
    }
};

const triggerRun = async () => {
    if (!promptInput.value.trim() || isRunning.value) return;

    isRunning.value = true;
    currentRun.value = null;

    try {
        const res = await api.post(`/api/projects/${projectId}/vibecoding/run`, {
            prompt: promptInput.value,
            model: selectedModel.value,
            auto_apply: autoApply.value,
        });

        currentRun.value = res.run;
        if (res.project) {
            project.value = { ...project.value, ...res.project };
            githubForm.repo = res.project.github_repo || githubForm.repo;
            githubForm.branch = res.project.github_branch || githubForm.branch;
        }
        if (res.push?.success) {
            toast.success(res.push.message);
        } else if (res.push && !res.push.success) {
            toast.warning(res.push.message);
        } else if (res.run?.type !== 'github_push') {
            toast.success('Siklus vibecoding selesai.');
        }
        promptInput.value = '';
        await refreshFiles();
        if (activeFilePath.value) {
            openFile(activeFilePath.value);
        }
        fetchCheckpoints();
    } catch (e: any) {
        if (e.data?.run) {
            currentRun.value = e.data.run; // failed GitHub push: show the reason in the run panel
            toast.error(e.message);
        } else {
            toast.error('Vibecoding gagal: ' + e.message);
        }
    } finally {
        isRunning.value = false;
        notifyUsageChanged();
    }
};

const applyProposedPatches = async () => {
    if (!currentRun.value) return;
    isApplying.value = true;
    try {
        const res = await api.post(`/api/projects/${projectId}/vibecoding/runs/${currentRun.value.id}/apply`);
        toast.success('Patch berhasil diterapkan.');
        currentRun.value.status = 'completed';
        await refreshFiles();
        if (activeFilePath.value) {
            openFile(activeFilePath.value);
        }
        fetchCheckpoints();
    } catch (e: any) {
        toast.error('Gagal menerapkan patch: ' + e.message);
    } finally {
        isApplying.value = false;
    }
};

const openPreviewTab = async () => {
    rightTab.value = 'preview';
    try {
        const res = await api.post(`/api/projects/${projectId}/preview/token`);
        previewUrl.value = res.preview_url;
    } catch (e: any) {
        toast.error('Gagal membuat token pratinjau: ' + e.message);
    }
};

const fetchCheckpoints = async () => {
    try {
        const res = await api.get(`/api/projects/${projectId}/checkpoints`);
        checkpoints.value = res.checkpoints || [];
    } catch (e) {
        // Ignore failure
    }
};

const restoreCheckpoint = async (checkpointId: string) => {
    if (!confirm('Pulihkan ruang kerja ke checkpoint ini?')) return;
    try {
        await api.post(`/api/projects/${projectId}/checkpoints/${checkpointId}/restore`);
        toast.success('Ruang kerja berhasil dipulihkan.');
        await refreshFiles();
        if (activeFilePath.value) {
            openFile(activeFilePath.value);
        }
    } catch (e: any) {
        toast.error('Gagal memulihkan checkpoint: ' + e.message);
    }
};

const openExportModal = async () => {
    isExportModalOpen.value = true;
    try {
        exportReadiness.value = await api.get('/api/exports/readiness');
    } catch (e) {
        // Ignore
    }
};

const triggerExport = async (format: 'zip' | 'rar') => {
    isExporting.value = true;
    try {
        const res = await api.post(`/api/projects/${projectId}/export`, { format });
        toast.success(`Arsip ${format.toUpperCase()} siap.`);
        // Trigger download
        window.open(`/api/exports/${res.export.id}/download`, '_blank');
        isExportModalOpen.value = false;
    } catch (e: any) {
        toast.error(e.message || `Gagal ekspor ${format}`);
    } finally {
        isExporting.value = false;
    }
};

const openGithubModal = async () => {
    isGithubModalOpen.value = true;
    try {
        githubStatus.value = await api.get('/api/github/status');
    } catch (e) {
        // Ignore
    }
};

const handleGithubPush = async () => {
    isPushing.value = true;
    try {
        // Update project repo first if changed
        await api.put(`/api/projects/${projectId}`, {
            github_repo: githubForm.repo,
            github_branch: githubForm.branch,
        });

        const res = await api.post(`/api/projects/${projectId}/github/push`, {
            branch: githubForm.branch,
            message: githubForm.message,
        });

        toast.success('Berhasil push ke GitHub!');
        window.open(res.commit_url, '_blank');
        isGithubModalOpen.value = false;
    } catch (e: any) {
        toast.error('Push GitHub gagal: ' + e.message);
    } finally {
        isPushing.value = false;
    }
};

onMounted(() => {
    fetchProjectDetails();
    fetchCheckpoints();
    loadModels()
        .then(() => {
            if (!isModelAvailable(selectedModel.value)) selectedModel.value = defaultModel.value;
        })
        .catch((e: any) => toast.error('Gagal memuat daftar model: ' + e.message));
});
</script>
