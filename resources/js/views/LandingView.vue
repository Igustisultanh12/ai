<template>
  <div class="relative flex min-h-screen flex-col overflow-x-hidden bg-surface text-ink">
    <!-- ================= Header ================= -->
    <header class="fixed inset-x-0 top-0 z-50 transition-all duration-300" :class="isScrolled ? 'py-2' : 'py-4'">
      <div class="container-x">
        <div
          class="flex items-center justify-between gap-3 rounded-2xl border-2 px-3 py-2 transition-all duration-300 sm:px-4"
          :class="isScrolled ? 'border-ink bg-card/85 shadow-brutal-sm backdrop-blur-xl' : 'border-transparent'"
        >
          <router-link to="/" class="group flex items-center gap-2.5">
            <span class="grid h-10 w-10 place-items-center rounded-xl border-2 border-ink bg-brand-gradient font-display text-xl font-bold text-white shadow-brutal-sm transition-transform duration-300 group-hover:-rotate-12 group-hover:scale-110">X</span>
            <span class="font-display text-2xl font-bold tracking-tight">XZY</span>
          </router-link>

          <nav class="hidden items-center gap-1 lg:flex">
            <a
              v-for="link in navLinks"
              :key="link.href"
              :href="link.href"
              class="rounded-xl px-3.5 py-2 text-sm font-semibold text-muted transition-colors hover:bg-surface-2 hover:text-ink"
            >{{ link.label }}</a>
          </nav>

          <div class="flex items-center gap-2">
            <button
              type="button"
              class="grid h-10 w-10 place-items-center rounded-xl border-2 border-line bg-card transition-all hover:-translate-y-0.5 hover:border-violet-400"
              :title="theme === 'dark' ? 'Tema terang' : 'Tema gelap'"
              @click="toggleTheme"
            >
              <Icon :name="theme === 'dark' ? 'sun' : 'moon'" class="h-5 w-5" :class="theme === 'dark' ? 'text-amber-400' : 'text-violet-600'" />
            </button>
            <router-link to="/login" class="btn btn-ghost hidden sm:inline-flex">Masuk</router-link>
            <router-link v-magnetic to="/register" class="btn btn-primary shine">
              Daftar Gratis
              <Icon name="arrow-right" class="h-4 w-4" />
            </router-link>
            <button
              type="button"
              class="grid h-10 w-10 place-items-center rounded-xl border-2 border-line bg-card lg:hidden"
              :aria-expanded="isMenuOpen"
              aria-label="Menu"
              @click="isMenuOpen = !isMenuOpen"
            >
              <Icon :name="isMenuOpen ? 'x' : 'menu'" class="h-5 w-5" />
            </button>
          </div>
        </div>

        <transition name="page">
          <nav v-if="isMenuOpen" class="mt-2 space-y-1 rounded-2xl border-2 border-ink bg-card p-2 shadow-brutal lg:hidden">
            <a
              v-for="link in navLinks"
              :key="link.href"
              :href="link.href"
              class="block rounded-xl px-4 py-3 text-sm font-semibold hover:bg-surface-2"
              @click="isMenuOpen = false"
            >{{ link.label }}</a>
            <router-link to="/login" class="block rounded-xl px-4 py-3 text-sm font-semibold hover:bg-surface-2">Masuk</router-link>
          </nav>
        </transition>
      </div>
    </header>

    <main class="flex-1">
      <!-- ================= Hero ================= -->
      <section class="relative isolate overflow-hidden pt-32 pb-16 sm:pt-40 lg:pb-24">
        <div class="aurora" aria-hidden="true"><span></span><span></span><span></span></div>
        <div class="grid-bg pointer-events-none absolute inset-0 -z-10 [mask-image:radial-gradient(ellipse_at_top,black,transparent_70%)]" aria-hidden="true"></div>

        <div class="container-x grid items-center gap-14 lg:grid-cols-2">
          <div>
            <a
              v-reveal:zoom
              href="#cara-kerja"
              class="group inline-flex items-center gap-2 rounded-full border-2 border-ink bg-card py-1 pr-3 pl-1 text-xs font-bold shadow-brutal-sm transition hover:-translate-y-0.5"
            >
              <span class="rounded-full bg-lime-300 px-2 py-0.5 text-zinc-900">BARU</span>
              Agent bisa baca repo, ngoding, dan push sendiri
              <Icon name="arrow-up-right" class="h-3.5 w-3.5 transition group-hover:rotate-45" />
            </a>

            <h1 v-reveal="100" class="mt-6 font-display text-[2.6rem] leading-[1.03] font-bold tracking-tight sm:text-6xl xl:text-7xl">
              Ngoding bareng AI,
              <br />
              jadi
              <span class="relative inline-block whitespace-nowrap">
                <span class="text-gradient-animated caret">{{ typedWord }}</span>
                <svg class="absolute -bottom-2 left-0 h-3 w-full text-yellow-300" viewBox="0 0 200 12" preserveAspectRatio="none" aria-hidden="true">
                  <path d="M2 9c40-7 90-8 196-3" stroke="currentColor" stroke-width="5" fill="none" stroke-linecap="round" />
                </svg>
              </span>
            </h1>

            <p v-reveal="200" class="mt-7 max-w-xl text-lg leading-relaxed text-muted">
              Pilih model favorit dari katalog TokenomixAI, ceritakan idenya, lalu lihat agent XZY membaca kode, menulis perubahan,
              dan langsung commit ke GitHub. Setiap langkahnya kelihatan.
            </p>

            <div v-reveal="300" class="mt-8 flex flex-wrap items-center gap-3">
              <router-link v-magnetic to="/register" class="btn btn-primary btn-lg shine">
                <Icon name="sparkles" class="h-5 w-5" />
                Mulai Gratis Sekarang
              </router-link>
              <a href="#fitur" class="btn btn-brutal btn-lg">
                Lihat Fiturnya
                <Icon name="arrow-right" class="h-5 w-5" />
              </a>
            </div>

            <div v-reveal="400" class="mt-10 flex flex-wrap gap-x-6 gap-y-3 text-sm font-semibold text-muted">
              <span v-for="perk in heroPerks" :key="perk" class="inline-flex items-center gap-2">
                <Icon name="check-circle" class="h-5 w-5 text-emerald-500" />
                {{ perk }}
              </span>
            </div>
          </div>

          <!-- Hero visual: live agent demo -->
          <div v-reveal:zoom="200" class="relative mx-auto w-full max-w-xl">
            <div class="absolute -top-6 -left-4 z-20 hidden animate-float rounded-2xl border-2 border-ink bg-yellow-300 px-3 py-2 font-display text-sm font-bold text-zinc-900 shadow-brutal-sm sm:block">
              <span class="inline-flex items-center gap-1.5"><Icon name="git-commit" class="h-4 w-4" /> 1 commit rapi</span>
            </div>
            <div class="absolute -right-3 -bottom-5 z-20 hidden animate-float-slow rounded-2xl border-2 border-ink bg-lime-300 px-3 py-2 font-display text-sm font-bold text-zinc-900 shadow-brutal-sm sm:block">
              <span class="inline-flex items-center gap-1.5"><Icon name="shield-check" class="h-4 w-4" /> tanpa force push</span>
            </div>

            <div v-tilt="5" class="relative overflow-hidden rounded-[2rem] border-2 border-ink bg-card shadow-brutal-lg">
              <div class="flex items-center justify-between border-b-2 border-ink bg-surface-2 px-4 py-3">
                <div class="flex items-center gap-1.5">
                  <span class="h-3 w-3 rounded-full border-2 border-ink bg-rose-400"></span>
                  <span class="h-3 w-3 rounded-full border-2 border-ink bg-yellow-300"></span>
                  <span class="h-3 w-3 rounded-full border-2 border-ink bg-lime-300"></span>
                </div>
                <span class="rounded-lg border border-line bg-card px-2 py-0.5 font-mono text-[11px] text-muted">claude-opus-5-5 · x32</span>
              </div>

              <div class="space-y-4 p-5">
                <div class="flex justify-end">
                  <div class="max-w-[85%] rounded-2xl rounded-br-md bg-violet-600 px-4 py-2.5 text-sm text-white">
                    {{ demoPrompt }}<span v-if="demoPhase === 0" class="caret"></span>
                  </div>
                </div>

                <transition name="page">
                  <div v-if="demoPhase >= 1" class="flex gap-3">
                    <span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl border-2 border-ink bg-brand-gradient text-white">
                      <Icon name="bot" class="h-5 w-5" />
                    </span>
                    <div class="min-w-0 flex-1 space-y-3 rounded-2xl rounded-tl-md border-2 border-line bg-surface p-3.5">
                      <AgentActivity :status="demoStatus" :steps="demoSteps" :started-at="demoStartedAt" :active="demoPhase < 5" />
                      <p v-if="demoPhase >= 5" class="text-sm leading-relaxed text-ink/80">
                        Selesai. Navbar sekarang muncul dengan animasi saat di-scroll dan tombolnya berkilau saat disentuh kursor.
                        Sudah aku push ke <span class="font-mono font-semibold text-violet-600 dark:text-violet-300">main</span>.
                      </p>
                    </div>
                  </div>
                </transition>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- ================= Model marquee ================= -->
      <section id="model" class="relative -rotate-1 border-y-2 border-ink bg-card py-4">
        <div class="marquee">
          <div v-for="copy in 2" :key="copy" class="marquee-track" :aria-hidden="copy === 2 ? 'true' : undefined">
            <template v-for="(model, i) in models" :key="model + copy">
              <span
                :class="chipColors[i % chipColors.length]"
                class="inline-flex items-center gap-2 rounded-full border-2 border-zinc-950 px-4 py-1.5 font-display text-sm font-bold whitespace-nowrap text-zinc-900"
              >
                <Icon name="cpu" class="h-4 w-4" />
                {{ model }}
              </span>
              <Icon name="sparkles" class="h-4 w-4 text-violet-500" />
            </template>
          </div>
        </div>
      </section>

      <!-- ================= Features (bento) ================= -->
      <section id="fitur" class="section relative overflow-hidden">
        <div class="dot-bg pointer-events-none absolute inset-0 opacity-50" aria-hidden="true"></div>
        <div class="container-x relative">
          <div class="mx-auto max-w-3xl text-center">
            <span v-reveal:zoom class="eyebrow">Fitur</span>
            <h2 v-reveal="80" class="mt-5 font-display text-4xl font-bold tracking-tight sm:text-5xl">
              Bukan sekadar chat, <span class="text-gradient">tapi rekan ngoding.</span>
            </h2>
            <p v-reveal="160" class="mt-4 text-lg text-muted">Semua yang kamu butuhkan untuk mewujudkan ide jadi kode yang jalan, dalam satu tempat.</p>
          </div>

          <div class="mt-14 grid gap-5 lg:grid-cols-3">
            <div
              v-for="(f, i) in features"
              :key="f.title"
              v-reveal="i * 80"
              v-tilt="4"
              :data-reveal="i % 2 ? 'right' : 'left'"
              :class="f.cls"
              class="group relative overflow-hidden rounded-[2rem] border-2 border-ink p-7 shadow-brutal transition-shadow hover:shadow-brutal-lg"
            >
              <div :class="f.iconCls" class="grid h-14 w-14 place-items-center rounded-2xl border-2 border-zinc-950 transition-transform duration-500 group-hover:scale-110 group-hover:rotate-12">
                <Icon :name="f.icon" class="h-7 w-7" />
              </div>
              <h3 class="mt-6 font-display text-2xl font-bold">{{ f.title }}</h3>
              <p class="mt-2 max-w-md opacity-80">{{ f.text }}</p>
              <Icon name="star" class="absolute -right-6 -bottom-6 h-32 w-32 opacity-10 transition-transform duration-700 group-hover:rotate-45" />
            </div>
          </div>
        </div>
      </section>

      <!-- ================= How it works ================= -->
      <section id="cara-kerja" class="section">
        <div class="container-x">
          <div class="mx-auto max-w-3xl text-center">
            <span v-reveal:zoom class="eyebrow">Cara Kerja</span>
            <h2 v-reveal="80" class="mt-5 font-display text-4xl font-bold tracking-tight sm:text-5xl">
              Empat langkah, <span class="text-gradient">langsung jadi</span>
            </h2>
            <p v-reveal="160" class="mt-4 text-lg text-muted">Kamu cukup ngobrol. Sisanya agent yang kerjakan, dan kamu bisa memantau setiap langkahnya.</p>
          </div>

          <div ref="processRef" class="relative mt-16">
            <div class="absolute top-8 right-[12%] left-[12%] hidden h-1.5 rounded-full bg-line lg:block" aria-hidden="true">
              <div class="h-full rounded-full bg-brand-gradient transition-[width] duration-200" :style="{ width: `${processProgress * 100}%` }"></div>
            </div>
            <ol class="grid gap-8 sm:grid-cols-2 lg:grid-cols-4">
              <li v-for="(s, i) in steps" :key="s.title" v-reveal="i * 110" class="relative text-center">
                <div
                  :class="processProgress * steps.length > i ? 'scale-110 bg-yellow-300 text-zinc-900' : 'bg-card'"
                  class="relative mx-auto grid h-16 w-16 place-items-center rounded-2xl border-2 border-ink shadow-brutal-sm transition-all duration-500"
                >
                  <Icon :name="s.icon" class="h-7 w-7" />
                  <span class="absolute -top-2 -right-2 grid h-6 w-6 place-items-center rounded-full bg-ink text-xs font-extrabold text-surface">{{ i + 1 }}</span>
                </div>
                <h3 class="mt-5 font-display text-lg font-bold">{{ s.title }}</h3>
                <p class="mx-auto mt-1 max-w-[240px] text-sm text-muted">{{ s.text }}</p>
              </li>
            </ol>
          </div>
        </div>
      </section>

      <!-- ================= Stats ================= -->
      <section class="container-x pb-8">
        <div ref="statsEl" class="grid gap-6 rounded-[2rem] border-2 border-ink bg-ink p-8 text-surface shadow-brutal sm:grid-cols-3 sm:p-10">
          <div v-for="(stat, i) in stats" :key="stat.label" v-reveal:zoom="i * 80" class="text-center">
            <p class="font-display text-5xl font-bold tabular-nums">{{ statValues[i] }}{{ stat.suffix }}</p>
            <p class="mt-2 text-sm opacity-70">{{ stat.label }}</p>
          </div>
        </div>
      </section>

      <!-- ================= Security ================= -->
      <section id="keamanan" class="section">
        <div class="container-x grid items-center gap-12 lg:grid-cols-2">
          <div>
            <span v-reveal:zoom class="eyebrow">Keamanan</span>
            <h2 v-reveal="80" class="mt-5 font-display text-4xl font-bold tracking-tight sm:text-5xl">
              Cepat, <span class="text-gradient">tapi tetap aman.</span>
            </h2>
            <p v-reveal="160" class="mt-4 text-lg leading-relaxed text-muted">
              Agent bekerja dengan token GitHub milikmu sendiri, tanpa force push, dan setiap aksinya tercatat sehingga mudah ditelusuri.
            </p>
          </div>
          <ul class="space-y-4">
            <li
              v-for="(item, i) in securityItems"
              :key="item.title"
              v-reveal:right="i * 90"
              class="group flex items-start gap-4 rounded-3xl border-2 border-ink bg-card p-5 shadow-brutal-sm transition-all hover:-translate-y-0.5 hover:shadow-brutal"
            >
              <span class="grid h-11 w-11 shrink-0 place-items-center rounded-2xl border-2 border-zinc-950 transition-transform group-hover:rotate-12" :class="item.tile">
                <Icon :name="item.icon" class="h-5 w-5" />
              </span>
              <span>
                <span class="block font-display text-lg font-bold">{{ item.title }}</span>
                <span class="mt-1 block text-sm leading-relaxed text-muted">{{ item.text }}</span>
              </span>
            </li>
          </ul>
        </div>
      </section>

      <!-- ================= CTA ================= -->
      <section class="container-x pb-24">
        <div v-reveal:zoom class="relative overflow-hidden rounded-[2.5rem] border-2 border-ink bg-brand-gradient px-6 py-14 text-center text-white shadow-brutal-lg animate-gradient sm:px-16 sm:py-20">
          <Icon name="star" class="absolute -top-10 -left-10 h-40 w-40 animate-spin-slow opacity-15" />
          <Icon name="sparkles" class="absolute -right-6 -bottom-8 h-44 w-44 opacity-15" />
          <h2 class="relative font-display text-4xl font-bold tracking-tight sm:text-5xl">Ide kamu, tinggal diobrolin.</h2>
          <p class="relative mx-auto mt-4 max-w-xl text-lg text-white/85">Bikin akun, sambungkan GitHub, pilih model, lalu biarkan AI mengerjakan sisanya.</p>
          <div class="relative mt-8 flex flex-wrap items-center justify-center gap-3">
            <router-link v-magnetic to="/register" class="btn btn-lg border-2 border-ink bg-card text-ink shadow-brutal-sm hover:-translate-y-0.5 hover:shadow-brutal">
              Buat Akun Gratis
              <Icon name="arrow-right" class="h-5 w-5" />
            </router-link>
            <router-link to="/login" class="btn btn-lg border-2 border-white/60 text-white hover:bg-white/10">Saya sudah punya akun</router-link>
          </div>
        </div>
      </section>
    </main>

    <!-- ================= Footer ================= -->
    <footer class="border-t-2 border-ink bg-card">
      <div class="container-x flex flex-col items-center justify-between gap-4 py-8 text-sm text-muted sm:flex-row">
        <div class="flex items-center gap-2.5">
          <span class="grid h-8 w-8 place-items-center rounded-lg border-2 border-ink bg-brand-gradient font-display font-bold text-white">X</span>
          <span>© {{ year }} XZY. Ngoding bareng AI.</span>
        </div>
        <p>Didukung gateway OpenAI-compatible TokenomixAI.</p>
      </div>
    </footer>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, onBeforeUnmount } from 'vue';
import AgentActivity from '@/components/AgentActivity.vue';
import type { AgentStep } from '@/types/agent';
import { useTheme } from '@/composables/useTheme';

const { theme, toggleTheme } = useTheme();
const year = new Date().getFullYear();
const isMenuOpen = ref(false);
const isScrolled = ref(false);

const navLinks = [
    { href: '#fitur', label: 'Fitur' },
    { href: '#cara-kerja', label: 'Cara Kerja' },
    { href: '#model', label: 'Model AI' },
    { href: '#keamanan', label: 'Keamanan' },
];

const heroPerks = ['Gratis untuk dicoba', 'Pilih model sendiri', 'Push langsung ke GitHub'];

const models = ['glm-5.1', 'glm-5.2', 'glm-5.3-flash', 'kimi-k3', 'deepseek-v4-flash-0731', 'claude-opus-5-5', 'claude-opus-5', 'gpt-6-astra', 'mimo-v2.6-pro'];
const chipColors = ['bg-violet-200', 'bg-fuchsia-200', 'bg-orange-200', 'bg-lime-200', 'bg-cyan-200', 'bg-yellow-200'];

const features = [
    { icon: 'bot', title: 'Agent yang benar-benar kerja', text: 'AI membaca repo, menulis berkas, lalu commit dan push sendiri. Progresnya tampil langkah demi langkah, lengkap dengan link commit.', cls: 'lg:col-span-2 bg-violet-500 text-white', iconCls: 'bg-white text-violet-600' },
    { icon: 'cpu', title: 'Bebas pilih model', text: 'Katalog live TokenomixAI lengkap dengan multiplier, tersimpan per percakapan.', cls: 'bg-lime-300 text-zinc-950', iconCls: 'bg-zinc-950 text-lime-300' },
    { icon: 'github', title: 'GitHub tanpa ribet', text: 'Cukup bilang "push ke link repo". Repo kosong, branch baru, satu commit rapi.', cls: 'bg-card', iconCls: 'bg-zinc-950 text-white' },
    { icon: 'code', title: 'Workspace vibecoding', text: 'Editor kode, checkpoint otomatis, verifikasi sandbox, dan pratinjau instan.', cls: 'bg-card', iconCls: 'bg-fuchsia-500 text-white' },
    { icon: 'coins', title: 'Kuota yang transparan', text: 'Token dihitung (prompt + completion) dikali multiplier, persis seperti gateway, dengan riwayat lengkap dan pembaruan otomatis.', cls: 'lg:col-span-2 bg-orange-400 text-zinc-950', iconCls: 'bg-zinc-950 text-orange-300' },
];

const steps = [
    { icon: 'message-square', title: 'Ceritakan idemu', text: 'Tulis apa yang mau dibuat, sertakan link repo kalau ada.' },
    { icon: 'cpu', title: 'Pilih model', text: 'Dari model cepat sampai model premium, sesuai kebutuhan.' },
    { icon: 'wand', title: 'AI mengerjakan', text: 'Agent membaca kode, merancang perubahan, dan menulis berkas lengkap.' },
    { icon: 'rocket', title: 'Langsung ter-push', text: 'Perubahan masuk ke GitHub dalam satu commit, lengkap dengan link-nya.' },
];

const securityItems = [
    { icon: 'shield-check', title: 'Pemindaian kredensial', text: 'Berkas .env, private key, dan API key ditolak otomatis sebelum push.', tile: 'bg-lime-300 text-zinc-900' },
    { icon: 'git-branch', title: 'Tanpa force push', text: 'Konflik remote dideteksi dan dilaporkan, kode orang lain tidak tertimpa.', tile: 'bg-yellow-300 text-zinc-900' },
    { icon: 'lock', title: 'Akun terlindungi', text: 'Autentikasi dua faktor, rotasi sesi, dan audit log untuk setiap aksi penting.', tile: 'bg-violet-500 text-white' },
    { icon: 'layers', title: 'Workspace terisolasi', text: 'Validasi path di sandbox mencegah akses keluar dari folder proyek.', tile: 'bg-orange-400 text-zinc-900' },
];

/* ---------- Typewriter ---------- */
const words = ['aplikasi nyata', 'landing page keren', 'dashboard admin', 'API Laravel', 'commit di GitHub'];
const typedWord = ref('');
let typeTimer: ReturnType<typeof setTimeout> | null = null;

const runTypewriter = (wordIndex = 0, charIndex = 0, deleting = false) => {
    const word = words[wordIndex];
    if (!deleting) {
        typedWord.value = word.slice(0, charIndex + 1);
        typeTimer = charIndex + 1 === word.length
            ? setTimeout(() => runTypewriter(wordIndex, charIndex, true), 1800)
            : setTimeout(() => runTypewriter(wordIndex, charIndex + 1, false), 70);
    } else {
        typedWord.value = word.slice(0, charIndex);
        typeTimer = charIndex === 0
            ? setTimeout(() => runTypewriter((wordIndex + 1) % words.length, 0, false), 250)
            : setTimeout(() => runTypewriter(wordIndex, charIndex - 1, true), 35);
    }
};

/* ---------- Count-up stats ---------- */
const stats = [
    { value: 10, suffix: '', label: 'aksi GitHub & proyek yang bisa dijalankan agent' },
    { value: 4, suffix: 'x', label: 'coba ulang otomatis saat gateway sibuk' },
    { value: 0, suffix: '', label: 'force push, kode kamu aman' },
];
const statValues = ref<number[]>(stats.map(() => 0));
const statsEl = ref<HTMLElement | null>(null);
let statsObserver: IntersectionObserver | null = null;
let countFrame = 0;

const countUp = () => {
    const start = performance.now();
    const tick = (now: number) => {
        const t = Math.min(1, (now - start) / 1200);
        const eased = 1 - Math.pow(1 - t, 3);
        statValues.value = stats.map((s) => Math.round(s.value * eased));
        if (t < 1) countFrame = requestAnimationFrame(tick);
    };
    countFrame = requestAnimationFrame(tick);
};

/* ---------- Scroll-linked process line + header state ---------- */
const processRef = ref<HTMLElement | null>(null);
const processProgress = ref(0);
const onScroll = () => {
    isScrolled.value = window.scrollY > 12;
    const el = processRef.value;
    if (!el) return;
    const r = el.getBoundingClientRect();
    const vh = window.innerHeight;
    processProgress.value = Math.max(0, Math.min(1, (vh * 0.8 - r.top) / (r.height + vh * 0.3)));
};

/* ---------- Animated agent demo ---------- */
const fullPrompt = 'Bikin navbar-nya beranimasi dan tombolnya berkilau, terus push ya';
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
    const step = (id: string, label: string, at: number, duration: number, next: string) => {
        later(at, () => {
            demoStatus.value = label;
            demoSteps.value = [...demoSteps.value, { id, label, status: 'running', startedAt: Date.now() }];
        });
        later(at + duration, () => {
            demoSteps.value = demoSteps.value.map((s) => (s.id === id ? { ...s, status: 'done', endedAt: Date.now() } : s));
            demoStatus.value = next;
        });
    };

    later(t0, () => {
        demoPhase.value = 1;
        demoStartedAt.value = Date.now();
    });
    step('read', 'Membaca 3 berkas dari repo', t0 + 600, 1300, 'Berpikir');
    step('write', 'Menulis berkas untuk di-push · 8.4 KB', t0 + 2300, 1700, 'Berpikir');
    step('push', 'Push 3 berkas ke repo:main', t0 + 4300, 1400, 'Menulis jawaban');
    later(t0 + 6000, () => {
        demoPhase.value = 5;
        demoStatus.value = 'Selesai';
    });
    later(t0 + 11000, runDemo);
};

onMounted(() => {
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();

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
    window.removeEventListener('scroll', onScroll);
    if (typeTimer) clearTimeout(typeTimer);
    demoTimers.forEach(clearTimeout);
    statsObserver?.disconnect();
    cancelAnimationFrame(countFrame);
});
</script>
