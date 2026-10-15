<template>
  <div class="relative w-full h-full flex flex-col bg-surface overflow-hidden">
    <!-- Editor Header Toolbar -->
    <div class="h-9 px-3 bg-card border-b border-line flex items-center justify-between text-xs text-muted select-none">
      <div class="flex items-center gap-2">
        <span class="font-mono text-ink">{{ filePath || 'Berkas Tanpa Nama' }}</span>
        <span v-if="isDirty" class="w-2 h-2 rounded-full bg-amber-400" title="Ada perubahan belum disimpan"></span>
      </div>
      <div class="flex items-center gap-2">
        <span class="font-mono text-[10px] text-muted uppercase">{{ detectedLanguage }}</span>
        <span v-if="readOnly" class="px-1.5 py-0.5 rounded bg-surface-2 text-[10px] text-muted">Read-Only</span>
      </div>
    </div>

    <!-- Monaco Editor Container -->
    <div ref="editorContainer" class="flex-1 w-full h-full min-h-[300px]"></div>

    <!-- Fallback Textarea if Monaco loader is waiting or offline -->
    <div v-if="!isMonacoLoaded && !isMonacoError" class="absolute inset-0 top-9 bg-surface flex items-center justify-center text-xs text-muted gap-2">
      <div class="w-4 h-4 border-2 border-violet-400 border-t-transparent rounded-full animate-spin"></div>
      <span>Memuat Monaco Editor...</span>
    </div>

    <textarea
      v-if="isMonacoError"
      v-model="internalValue"
      :readonly="readOnly"
      class="flex-1 w-full p-4 font-mono text-sm bg-surface text-ink outline-none resize-none"
      spellcheck="false"
    ></textarea>
  </div>
</template>

<script setup lang="ts">
import { useTheme } from '@/composables/useTheme';
import { ref, watch, onMounted, onBeforeUnmount, computed } from 'vue';
import loader from '@monaco-editor/loader';

const props = withDefaults(
    defineProps<{
        modelValue: string;
        filePath?: string;
        readOnly?: boolean;
        isDirty?: boolean;
    }>(),
    {
        modelValue: '',
        filePath: '',
        readOnly: false,
        isDirty: false,
    }
);

const emit = defineEmits<{
    (e: 'update:modelValue', value: string): void;
    (e: 'save'): void;
}>();

const editorContainer = ref<HTMLDivElement | null>(null);
const isMonacoLoaded = ref(false);
const isMonacoError = ref(false);
const internalValue = ref(props.modelValue);

const { theme } = useTheme();

let editorInstance: any = null;
let monacoInstance: any = null;

const detectedLanguage = computed(() => {
    const ext = props.filePath.split('.').pop()?.toLowerCase();
    switch (ext) {
        case 'vue':
        case 'html':
            return 'html';
        case 'js':
            return 'javascript';
        case 'ts':
            return 'typescript';
        case 'json':
            return 'json';
        case 'php':
            return 'php';
        case 'css':
            return 'css';
        case 'md':
            return 'markdown';
        default:
            return 'plaintext';
    }
});

const initMonaco = async () => {
    if (!editorContainer.value) return;

    try {
        monacoInstance = await loader.init();
        isMonacoLoaded.value = true;

        editorInstance = monacoInstance.editor.create(editorContainer.value, {
            value: props.modelValue,
            language: detectedLanguage.value,
            theme: theme.value === 'dark' ? 'vs-dark' : 'vs',
            readOnly: props.readOnly,
            automaticLayout: true,
            fontSize: 13,
            lineHeight: 20,
            fontFamily: "'JetBrains Mono', 'Fira Code', Consolas, monospace",
            minimap: { enabled: true, maxColumn: 80 },
            scrollBeyondLastLine: false,
            wordWrap: 'on',
            tabSize: 2,
            smoothScrolling: true,
            cursorBlinking: 'smooth',
        });

        // Listen for content changes
        editorInstance.onDidChangeModelContent(() => {
            const val = editorInstance.getValue();
            internalValue.value = val;
            emit('update:modelValue', val);
        });

        // Keybinding Ctrl+S / Cmd+S
        editorInstance.addCommand(
            monacoInstance.KeyMod.CtrlCmd | monacoInstance.KeyCode.KeyS,
            () => {
                emit('save');
            }
        );
    } catch (err) {
        console.warn('Monaco failed to load, falling back to clean textarea editor:', err);
        isMonacoError.value = true;
    }
};

watch(
    () => props.modelValue,
    newVal => {
        if (editorInstance && newVal !== editorInstance.getValue()) {
            editorInstance.setValue(newVal);
        }
    }
);

watch(
    () => detectedLanguage.value,
    newLang => {
        if (editorInstance && monacoInstance) {
            const model = editorInstance.getModel();
            if (model) {
                monacoInstance.editor.setModelLanguage(model, newLang);
            }
        }
    }
);

// Follow the app's light/dark toggle
watch(theme, t => {
    monacoInstance?.editor.setTheme(t === 'dark' ? 'vs-dark' : 'vs');
});

watch(
    () => props.readOnly,
    newReadOnly => {
        if (editorInstance) {
            editorInstance.updateOptions({ readOnly: newReadOnly });
        }
    }
);

onMounted(() => {
    initMonaco();
});

onBeforeUnmount(() => {
    if (editorInstance) {
        editorInstance.dispose();
    }
});
</script>
