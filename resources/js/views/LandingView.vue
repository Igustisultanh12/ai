<template>
  <div class="relative min-h-screen bg-slate-950 text-slate-100 flex flex-col overflow-x-hidden selection:bg-violet-500 selection:text-white">
    <!-- ============ Header ============ -->
    <header class="sticky top-0 z-50 border-b border-white/5 bg-slate-950/60 backdrop-blur-xl">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 h-16 flex items-center justify-between">
        <router-link to="/" class="flex items-center gap-3 group">
          <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-violet-600 via-fuchsia-500 to-cyan-400 flex items-center justify-center font-black text-white text-lg shadow-lg shadow-violet-500/30 transition-transform duration-300 group-hover:rotate-12 group-hover:scale-110">
            X
          </div>
          <span class="text-2xl font-black tracking-tight text-white">XZY</span>
        </router-link>

        <nav class="hidden md:flex items-center gap-1 text-sm font-medium text-slate-300">
          <a
            v-for="link in navLinks"
            :key="link.href"
            :href="link.href"
            class="relative px-3 py-2 rounded-lg hover:text-white transition-colors after:absolute after:left-3 after:right-3 after:bottom-1 after:h-px after:bg-gradient-to-r after:from-violet-400 after:to-cyan-400 after:scale-x-0 hover:after:scale-x-100 after:origin-left after:transition-transform after:duration-300"
          >{{ link.label }}</a>
        </nav>

        <div class="flex items-center gap-2">
          <router-link
            to="/login"
            class="hidden sm:inline-flex px-4 py-2 text-sm font-semibold text-slate-300 hover:text-white hover:bg-white/5 rounded-xl transition-colors"
          >
            Masuk
          </router-link>
          <router-link
            to="/register"
            class="px-4 py-2 text-sm font-semibold rounded-xl bg-gradient-to-r from-violet-600 to-cyan-500 text-white shadow-md shadow-violet-600/30"
          >
            Mulai Gratis
          </router-link>
          <button
            type="button"
            class="md:hidden ml-1 w-9 h-9 rounded-xl border border-white/10 text-slate-300 hover:text-white hover:bg-white/5 flex items-center justify-center transition-colors"
            :aria-expanded="isMenuOpen"
            aria-label="Menu"
            @click="isMenuOpen = !isMenuOpen"
          >
            <span class="text-lg leading-none">{{ isMenuOpen ? '✕' : '☰' }}</span>
          </button>
        </div>
      </div>

      <transition name="page">
        <nav v-if="isMenuOpen" class="md:hidden border-t border-white/5 bg-slate-950/95 backdrop-blur-xl px-4 py-3 space-y-1">
          <a
            v-for="link in navLinks"
            :key="link.href"
            :href="link.href"
            class="block px-3 py-2.5 rounded-xl text-sm text-slate-300 hover:text-white hover:bg-white/5"
            @click="isMenuOpen = false"
          >{{ link.label }}</a>
          <router-link to="/login" class="block px-3 py-2.5 rounded-xl text-sm text-slate-300 hover:text-white hover:bg-white/5">Masuk</router-link>
        </nav>
      </transition>
    </header>

    <main class="flex-1">
      <!-- ============ Hero ============ -->
      <section class="relative pt-20 sm:pt-28 pb-16 px-4 text-center">
        <div class="aurora" aria-hidden="true"><span></span><span></span><span></span></div>
        <div class="absolute inset-0 grid-bg pointer-events-none" aria-hidden="true"></div>

        <div class="relative z-10 max-w-4xl mx-auto space-y-7">
          <div v-reveal:zoom class="glow-border inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-slate-950/80 text-violet-200 text-xs font-semibold backdrop-blur">
            <span class="relative flex w-2 h-2">
              <span class="absolute inline-flex w-full h-full rounded-full bg-emerald-400 opacity-75 animate-ping"></span>
              <span class="relative inline-flex w-2 h-2 rounded-full bg-emerald-400"></span>
            </span>
            AI yang bisa baca repo, ngoding, dan push sendiri
          </div>

          <h1 v-reveal="80" class="text-[2.6rem] leading-[1.05] sm:text-7xl font-black tracking-tight text-white">
            Ngobrol sama AI,<br class="hidden sm:block" />
            jadi
            <span class="text-gradient-animated caret whitespace-nowrap">{{ typedWord }}</span>
          </h1>

          <p v-reveal="160" class="text-base sm:text-xl text-slate-400 max-w-2xl mx-auto leading-relaxed">
            Pilih model TokenomixAI favoritmu, ceritakan idenya, lalu lihat agent XZY membaca kode, menulis perubahan,
            dan langsung commit ke GitHub. Semua kelihatan prosesnya, langkah demi langkah.
          </p>

          <div v-reveal="240" class="flex flex-col sm:flex-row items-center justify-center gap-3 pt-2">
            <router-link
              v-magnetic
              to="/register"
              class="w-full sm:w-auto px-8 py-3.5 rounded-2xl bg-gradient-to-r from-violet-600 via-fuchsia-600 to-cyan-500 text-white font-bold text-base shadow-xl shadow-violet-600/30"
            >
              Coba Sekarang, Gratis →
            </router-link>
            <a
              href="#cara-kerja"
              class="w-full sm:w-auto px-8 py-3.5 rounded-2xl bg-white/5 border border-white/10 hover:bg-white/10 hover:border-white/20 text-slate-200 font-semibold text-base transition-colors"
            >
              Lihat Cara Kerjanya
            </a>
          </div>

          <!-- Stats -->
          <div ref="statsEl" class="grid grid-cols-3 gap-3 sm:gap-6 max-w-xl mx-auto pt-6">
            <div v-for="(stat, i) in stats" :key="stat.label" v-reveal="300 + i * 80" class="text-center">
              <div class="text-2xl sm:text-4xl font-black text-white tabular-nums">{{ stat.prefix }}{{ statValues[i] }}{{ stat.suffix }}</div>
              <div class="mt-1 text-[11px] sm:text-xs text-slate-500 leading-snug">{{ stat.label }}</div>
            </div>
          </div>
        </div>

        <!-- Live demo window -->
        <div v-reveal:zoom="200" class="relative z-10 max-w-5xl mx-auto mt-16">
          <div class="absolute -inset-6 bg-gradient-to-r from-violet-600/20 via-fuchsia-500/10 to-cyan-500/20 blur-3xl rounded-[3rem] pointer-events-none" aria-hidden="true"></div>
          <div v-tilt="4" class="relative rounded-3xl border border-white/10 bg-slate-900/80 shadow-2xl backdrop-blur-xl overflow-hidden text-left">
            <div class="h-11 px-4 bg-slate-950/80 border-b border-white/5 flex items-center justify-between">
              <div class="flex items-center gap-2">
                <span class="w-3 h-3 rounded-full bg-rose-500/80"></span>
                <span class="w-3 h-3 rounded-full bg-amber-500/80"></span>
                <span class="w-3 h-3 rounded-full bg-emerald-500/80"></span>
                <span class="ml-3 font-mono text-[11px] text-slate-500 hidden sm:inline">xzy.chat / proyek-kamu</span>
              </div>
              <span class="font-mono text-[11px] text-cyan-300 px-2 py-0.5 rounded-md bg-cyan-500/10 border border-cyan-400/20">claude-opus-5-5 · ×32</span>
            </div>

            <div class="grid md:grid-cols-5 min-h-[360px]">
              <!-- Chat side -->
              <div class="md:col-span-3 p-5 space-y-4 border-b md:border-b-0 md:border-r border-white/5">
                <div class="flex justify-end">
                  <div class="max-w-[85%] px-4 py-2.5 rounded-2xl rounded-br-md bg-violet-600 text-white text-sm shadow-lg shadow-violet-600/20">
                    {{ demoPrompt }}<span v-if="demoPhase === 0" class="caret"></span>
                  </div>
                </div>

                <transition name="page">
                  <div v-if="demoPhase >= 1" class="flex gap-3">
                    <div class="w-8 h-8 rounded-xl bg-gradient-to-tr from-violet-600 to-cyan-500 flex items-center justify-center text-[11px] font-bold text-white shrink-0">AI</div>
                    <div class="flex-1 min-w-0 p-3.5 rounded-2xl rounded-tl-md bg-slate-950/70 border border-white/5 space-y-3">
                      <AgentActivity
                        :status="demoStatus"
                        :steps="demoSteps"
                        :started-at="demoStartedAt"
                        :active="demoPhase < 5"
                      />
                      <p v-if="demoPhase >= 5" class="text-sm text-slate-300 leading-relaxed">
                        Beres! Navbar sekarang punya animasi muncul saat di-scroll dan tombolnya bercahaya saat disentuh kursor. Sudah aku push ke <span class="text-cyan-300 font-mono">main</span>. 🚀
                      </p>
                    </div>
                  </div>
                </transition>
              </div>

              <!-- Code side -->
              <div class="md:col-span-2 p-5 bg-slate-950/60 font-mono text-[12px] leading-6 overflow-hidden" v-pre>
                <div class="text-slate-500 mb-2">resources/js/components/Navbar.vue</div>
                <div><span class="text-violet-400">&lt;template&gt;</span></div>
                <div class="pl-4"><span class="text-cyan-300">&lt;header</span> <span class="text-amber-300">v-reveal</span><span class="text-cyan-300">&gt;</span></div>
                <div class="pl-8"><span class="text-cyan-300">&lt;button</span> <span class="text-amber-300">v-magnetic</span><span class="text-cyan-300">&gt;</span></div>
                <div class="pl-12 text-slate-200">Mulai ✨</div>
                <div class="pl-8"><span class="text-cyan-300">&lt;/button&gt;</span></div>
                <div class="pl-4"><span class="text-cyan-300">&lt;/header&gt;</span></div>
                <div><span class="text-violet-400">&lt;/template&gt;</span></div>
                <div class="mt-3 text-emerald-400">+ 3 berkas · commit a1b2c3d</div>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- ============ Model marquee ============ -->
      <section id="model" class="py-10 border-y border-white/5 bg-slate-950/60">
        <p class="text-center text-xs uppercase tracking-[0.2em] text-slate-500 mb-5">Pilih model favoritmu langsung dari katalog TokenomixAI</p>
        <div class="marquee">
          <div v-for="copy in 2" :key="copy" class="marquee-track" :aria-hidden="copy === 2 ? 'true' : undefined">
            <span
              v-for="model in models"
              :key="model + copy"
              class="px-4 py-2 rounded-full border border-white/10 bg-white/[0.03] text-sm font-mono text-slate-300 whitespace-nowrap hover:text-white hover:border-violet-400/50 hover:bg-violet-500/10 transition-colors"
            >{{ model }}</span>
          </div>
        </div>
      </section>

      <!-- ============ Features ============ -->
      <section id="fitur" class="relative py-24 max-w-7xl mx-auto px-4 sm:px-6">
        <div class="text-center space-y-3 mb-14">
          <span v-reveal class="inline-block text-xs font-bold tracking-[0.2em] uppercase text-violet-300">Fitur</span>
          <h2 v-reveal="60" class="text-3xl sm:text-5xl font-black text-white tracking-tight">Semua yang kamu butuh buat<br class="hidden sm:block" /> ngoding bareng AI</h2>
          <p v-reveal="120" class="text-slate-400 max-w-2xl mx-auto">Bukan mockup. Setiap tombol di sini benar-benar jalan.</p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
          <div
            v-for="(feature, i) in features"
            :key="feature.title"
            v-reveal="i * 70"
            v-tilt="5"
            class="group p-6 rounded-3xl bg-slate-900/70 border border-white/5 hover:border-white/15"
          >
            <div
              class="w-12 h-12 rounded-2xl flex items-center justify-center text-2xl mb-5 transition-transform duration-500 group-hover:scale-110 group-hover:-rotate-6"
              :class="feature.tile"
            >
              {{ feature.icon }}
            </div>
            <h3 class="text-lg font-bold text-white mb-2">{{ feature.title }}</h3>
            <p class="text-sm text-slate-400 leading-relaxed">{{ feature.text }}</p>
          </div>
        </div>
      </section>

      <!-- ============ How it works ============ -->
      <section id="cara-kerja" class="relative py-24 px-4 sm:px-6 bg-gradient-to-b from-transparent via-violet-950/20 to-transparent">
        <div class="max-w-5xl mx-auto">
          <div class="text-center space-y-3 mb-14">
            <span v-reveal class="inline-block text-xs font-bold tracking-[0.2em] uppercase text-cyan-300">Cara Kerja</span>
            <h2 v-reveal="60" class="text-3xl sm:text-5xl font-black text-white tracking-tight">Tiga langkah, langsung jadi</h2>
          </div>

          <ol class="relative grid md:grid-cols-3 gap-6">
            <li class="hidden md:block absolute top-8 left-[16%] right-[16%] h-px bg-gradient-to-r from-violet-500/0 via-violet-500/60 to-cyan-400/0" aria-hidden="true"></li>
            <li
              v-for="(step, i) in steps"
              :key="step.title"
              v-reveal="i * 120"
              class="relative text-center px-4"
            >
              <div class="relative mx-auto w-16 h-16 rounded-2xl bg-slate-900 border border-white/10 flex items-center justify-center text-2xl shadow-xl shadow-violet-900/30" :class="i === 1 ? 'float-slow' : 'float-slower'">
                {{ step.icon }}
                <span class="absolute -top-2 -right-2 w-6 h-6 rounded-full bg-gradient-to-tr from-violet-600 to-cyan-500 text-[11px] font-black text-white flex items-center justify-center">{{ i + 1 }}</span>
              </div>
              <h3 class="mt-5 text-lg font-bold text-white">{{ step.title }}</h3>
              <p class="mt-2 text-sm text-slate-400 leading-relaxed">{{ step.text }}</p>
            </li>
          </ol>
        </div>
      </section>

      <!-- ============ Security ============ -->
      <section id="keamanan" class="py-24 max-w-6xl mx-auto px-4 sm:px-6">
        <div class="grid lg:grid-cols-2 gap-10 items-center">
          <div class="space-y-4">
            <span v-reveal class="inline-block text-xs font-bold tracking-[0.2em] uppercase text-emerald-300">Keamanan</span>
            <h2 v-reveal="60" class="text-3xl sm:text-5xl font-black text-white tracking-tight">Cepat, tapi tetap aman</h2>
            <p v-reveal="120" class="text-slate-400 leading-relaxed">
              Agent bekerja dengan token GitHub milikmu sendiri, tanpa force push, dan setiap perubahan bisa ditelusuri.
            </p>
          </div>
          <ul class="space-y-3">
            <li
              v-for="(item, i) in securityItems"
              :key="item"
              v-reveal:right="i * 80"
              class="flex items-start gap-3 p-4 rounded-2xl bg-slate-900/70 border border-white/5"
            >
              <span class="mt-0.5 w-6 h-6 rounded-lg bg-emerald-500/15 border border-emerald-400/30 text-emerald-300 flex items-center justify-center text-xs shrink-0">✓</span>
              <span class="text-sm text-slate-300 leading-relaxed">{{ item }}</span>
            </li>
          </ul>
        </div>
      </section>

      <!-- ============ CTA ============ -->
      <section class="px-4 sm:px-6 pb-24">
        <div v-reveal:zoom class="glow-border relative max-w-5xl mx-auto rounded-[2rem] bg-slate-900/80 overflow-hidden px-6 py-14 sm:p-16 text-center">
          <div class="aurora opacity-70" aria-hidden="true"><span></span><span></span><span></span></div>
          <div class="relative z-10 space-y-5">
            <h2 class="text-3xl sm:text-5xl font-black text-white tracking-tight">Ide kamu, tinggal ngomong.</h2>
            <p class="text-slate-300 max-w-xl mx-auto">Buat akun, sambungkan GitHub, pilih model, dan biarkan AI mengerjakan sisanya.</p>
            <router-link
              v-magnetic
              to="/register"
              class="inline-flex px-8 py-3.5 rounded-2xl bg-gradient-to-r from-violet-600 via-fuchsia-600 to-cyan-500 text-white font-bold shadow-xl shadow-violet-600/30"
            >
              Buat Akun Gratis
            </router-link>
          </div>
        </div>
      </section>
    </main>

    <!-- ============ Footer ============ -->
    <footer class="border-t border-white/5 py-8 text-slate-500 text-xs">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 flex flex-col sm:flex-row items-center justify-between gap-3">
        <p>© {{ year }} XZY Platform.</p>
        <p>Didukung gateway OpenAI-compatible TokenomixAI.</p>
      </div>
    </footer>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, onBeforeUnmount } from 'vue';
import AgentActivity from '@/components/AgentActivity.vue';
import type { AgentStep } from '@/types/agent';

const year = new Date().getFullYear();
const isMenuOpen = ref(false);

const navLinks = [
    { href: '#fitur', label: 'Fitur' },
    { href: '#cara-kerja', label: 'Cara Kerja' },
    { href: '#model', label: 'Model AI' },
    { href: '#keamanan', label: 'Keamanan' },
];

const models = [
    'glm-5.1', 'glm-5.2', 'glm-5.3-flash', 'kimi-k3', 'deepseek-v4-flash-0731',
    'claude-opus-5-5', 'claude-opus-5', 'gpt-6-astra', 'mimo-v2.6-pro',
];

const features = [
    { icon: '🤖', title: 'Agent yang benar-benar kerja', text: 'AI membaca repo, menulis berkas, lalu commit & push sendiri. Progresnya tampil langkah demi langkah.', tile: 'bg-violet-500/15 border border-violet-400/30' },
    { icon: '🎛️', title: 'Bebas pilih model', text: 'Katalog live dari TokenomixAI, lengkap dengan multiplier, dan tersimpan per percakapan.', tile: 'bg-fuchsia-500/15 border border-fuchsia-400/30' },
    { icon: '🐙', title: 'GitHub tanpa ribet', text: 'Cukup bilang "push ke <link repo>". Repo kosong, branch baru, satu commit rapi, tanpa force push.', tile: 'bg-sky-500/15 border border-sky-400/30' },
    { icon: '⚡', title: 'Workspace vibecoding', text: 'Editor kode, checkpoint otomatis, verifikasi sandbox, dan pratinjau instan dalam satu layar.', tile: 'bg-amber-500/15 border border-amber-400/30' },
    { icon: '📊', title: 'Kuota yang jujur', text: 'Token dihitung (prompt + completion) × multiplier, persis seperti gateway, dengan riwayat lengkap.', tile: 'bg-emerald-500/15 border border-emerald-400/30' },
    { icon: '📦', title: 'Ekspor kapan saja', text: 'Unduh proyekmu sebagai ZIP atau RAR asli kapan pun kamu mau.', tile: 'bg-cyan-500/15 border border-cyan-400/30' },
];

const steps = [
    { icon: '💬', title: 'Ceritakan idemu', text: 'Tulis apa yang mau dibuat atau diperbaiki, sertakan link repo kalau ada.' },
    { icon: '🧠', title: 'AI mengerjakan', text: 'Agent membaca kode, merancang perubahan, dan menulis berkas lengkap. Kamu bisa pantau tiap langkahnya.' },
    { icon: '🚀', title: 'Langsung ter-push', text: 'Perubahan masuk ke GitHub dalam satu commit, lengkap dengan link-nya.' },
];

const securityItems = [
    'Pemindaian kredensial sebelum push: berkas .env, private key, dan API key ditolak otomatis.',
    'Tidak pernah force push. Konflik remote dideteksi dan dilaporkan.',
    'Autentikasi dua faktor (TOTP), rotasi sesi, dan audit log untuk setiap aksi penting.',
    'Workspace sandbox dengan validasi path untuk mencegah directory traversal.',
];

/* ---------- Typewriter headline ---------- */
const words = ['aplikasi nyata.', 'landing page keren.', 'dashboard admin.', 'API Laravel.', 'commit di GitHub.'];
const typedWord = ref('');
let typeTimer: ReturnType<typeof setTimeout> | null = null;

const runTypewriter = (wordIndex = 0, charIndex = 0, deleting = false) => {
    const word = words[wordIndex];
    if (!deleting) {
        typedWord.value = word.slice(0, charIndex + 1);
        if (charIndex + 1 === word.length) {
            typeTimer = setTimeout(() => runTypewriter(wordIndex, charIndex, true), 1800);
        } else {
            typeTimer = setTimeout(() => runTypewriter(wordIndex, charIndex + 1, false), 65);
        }
    } else {
        typedWord.value = word.slice(0, charIndex);
        if (charIndex === 0) {
            typeTimer = setTimeout(() => runTypewriter((wordIndex + 1) % words.length, 0, false), 250);
        } else {
            typeTimer = setTimeout(() => runTypewriter(wordIndex, charIndex - 1, true), 35);
        }
    }
};

/* ---------- Count-up stats ---------- */
const stats = [
    { value: 10, prefix: '', suffix: '', label: 'aksi GitHub & proyek untuk agent' },
    { value: 4, prefix: '', suffix: '×', label: 'coba ulang otomatis saat gateway sibuk' },
    { value: 0, prefix: '', suffix: '', label: 'force push. Kode kamu aman' },
];
const statValues = ref<number[]>(stats.map(() => 0));
const statsEl = ref<HTMLElement | null>(null);
let statsObserver: IntersectionObserver | null = null;
let countFrame = 0;

const countUp = () => {
    const start = performance.now();
    const duration = 1200;
    const tick = (now: number) => {
        const t = Math.min(1, (now - start) / duration);
        const eased = 1 - Math.pow(1 - t, 3);
        statValues.value = stats.map((s) => Math.round(s.value * eased));
        if (t < 1) countFrame = requestAnimationFrame(tick);
    };
    countFrame = requestAnimationFrame(tick);
};

/* ---------- Animated agent demo ---------- */
const fullPrompt = 'Bikin navbar-nya beranimasi & tombolnya glowing, terus push ya!';
const demoPrompt = ref('');
const demoPhase = ref(0);
const demoSteps = ref<AgentStep[]>([]);
const demoStatus = ref('Berpikir');
const demoStartedAt = ref(Date.now());
const demoTimers: ReturnType<typeof setTimeout>[] = [];

const later = (ms: number, fn: () => void) => {
    demoTimers.push(setTimeout(fn, ms));
};

const runDemo = () => {
    demoTimers.forEach(clearTimeout);
    demoTimers.length = 0;
    demoPrompt.value = '';
    demoPhase.value = 0;
    demoSteps.value = [];
    demoStatus.value = 'Berpikir';

    for (let i = 1; i <= fullPrompt.length; i++) {
        later(i * 38, () => (demoPrompt.value = fullPrompt.slice(0, i)));
    }

    const t0 = fullPrompt.length * 38 + 400;
    const step = (id: string, label: string, at: number, duration: number, status: string) => {
        later(at, () => {
            demoStatus.value = label;
            demoSteps.value = [...demoSteps.value, { id, label, status: 'running', startedAt: Date.now() }];
        });
        later(at + duration, () => {
            demoSteps.value = demoSteps.value.map((s) => (s.id === id ? { ...s, status: 'done', endedAt: Date.now() } : s));
            demoStatus.value = status;
        });
    };

    later(t0, () => {
        demoPhase.value = 1;
        demoStartedAt.value = Date.now();
    });
    step('read', 'Membaca 3 berkas dari repo', t0 + 600, 1300, 'Berpikir');
    later(t0 + 2100, () => (demoPhase.value = 2));
    step('write', 'Menulis berkas untuk di-push · 8.4 KB', t0 + 2300, 1700, 'Berpikir');
    later(t0 + 4200, () => (demoPhase.value = 3));
    step('push', 'Push 3 berkas ke repo:main', t0 + 4300, 1400, 'Menulis jawaban');
    later(t0 + 6000, () => {
        demoPhase.value = 5;
        demoStatus.value = 'Selesai';
    });
    later(t0 + 11000, runDemo);
};

onMounted(() => {
    const reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    if (reduce) {
        typedWord.value = words[0];
        statValues.value = stats.map((s) => s.value);
        demoPrompt.value = fullPrompt;
        demoPhase.value = 5;
        demoStatus.value = 'Selesai';
        demoSteps.value = [
            { id: 'read', label: 'Membaca 3 berkas dari repo', status: 'done' },
            { id: 'push', label: 'Push 3 berkas ke repo:main', status: 'done' },
        ];
        return;
    }

    runTypewriter();
    runDemo();

    if (statsEl.value && typeof IntersectionObserver !== 'undefined') {
        statsObserver = new IntersectionObserver((entries) => {
            if (entries.some((e) => e.isIntersecting)) {
                countUp();
                statsObserver?.disconnect();
            }
        }, { threshold: 0.4 });
        statsObserver.observe(statsEl.value);
    } else {
        statValues.value = stats.map((s) => s.value);
    }
});

onBeforeUnmount(() => {
    if (typeTimer) clearTimeout(typeTimer);
    demoTimers.forEach(clearTimeout);
    statsObserver?.disconnect();
    cancelAnimationFrame(countFrame);
});
</script>
