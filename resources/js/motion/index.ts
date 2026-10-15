/**
 * XZY motion layer: scroll reveal, cursor spotlight, magnetic/shine/ripple buttons,
 * 3D tilt and a scroll progress bar.
 *
 * It works in two ways:
 *  - automatically: cards, sections and primary (gradient) buttons that appear in the
 *    page get the effects without touching every view's template;
 *  - explicitly: v-reveal / v-tilt / v-magnetic directives for hand-tuned elements.
 *
 * Opt out per subtree with the attribute `data-no-motion` (e.g. code editors).
 * Everything is disabled when the user prefers reduced motion.
 */
import type { App, Directive } from 'vue';

const reduceMotion = (): boolean =>
    typeof window !== 'undefined' && !!window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

const canHover = (): boolean =>
    typeof window !== 'undefined' && !!window.matchMedia && window.matchMedia('(hover: hover) and (pointer: fine)').matches;

/* ------------------------------------------------------------------ */
/* Scroll reveal                                                       */
/* ------------------------------------------------------------------ */

const AUTO_REVEAL_SELECTOR = [
    '[data-reveal]',
    'main section',
    'main .rounded-2xl',
    'main .rounded-3xl',
    'main table',
].join(',');

let revealObserver: IntersectionObserver | null = null;

/** Show the element, then drop the reveal classes so its own transitions/transforms apply again. */
const markRevealed = (el: Element): void => {
    el.classList.add('is-revealed');
    const delay = parseInt((el as HTMLElement).style.getPropertyValue('--reveal-delay') || '0', 10) || 0;
    window.setTimeout(() => {
        el.classList.remove('reveal', 'is-revealed', 'reveal-up', 'reveal-down', 'reveal-left', 'reveal-right', 'reveal-zoom', 'reveal-fade');
        (el as HTMLElement).style.removeProperty('--reveal-delay');
    }, delay + 900);
};

const getRevealObserver = (): IntersectionObserver | null => {
    if (revealObserver || typeof IntersectionObserver === 'undefined') return revealObserver;
    revealObserver = new IntersectionObserver(
        (entries) => {
            for (const entry of entries) {
                if (entry.isIntersecting) {
                    markRevealed(entry.target);
                    revealObserver?.unobserve(entry.target);
                }
            }
        },
        { rootMargin: '0px 0px -8% 0px', threshold: 0.08 },
    );
    return revealObserver;
};

const isOptedOut = (el: Element): boolean => !!el.closest('[data-no-motion]');

/** Elements inside fixed layers (modals, toasts, sidebars) must never start hidden. */
const insideFixedLayer = (el: Element): boolean => {
    let node: Element | null = el;
    while (node && node !== document.body) {
        const pos = getComputedStyle(node).position;
        if (pos === 'fixed' || pos === 'sticky') return true;
        node = node.parentElement;
    }
    return false;
};

const reveal = (el: HTMLElement, delayMs = 0, variant = 'up'): void => {
    if (el.dataset.motionReveal === 'done' || el.classList.contains('reveal')) return;
    el.dataset.motionReveal = 'done';

    if (reduceMotion()) return;

    // Already on screen when it appears: show it straight away (no flash of hidden content)
    const rect = el.getBoundingClientRect();
    const onScreen = rect.top < window.innerHeight * 0.92 && rect.bottom > 0;

    el.classList.add('reveal', `reveal-${variant}`);
    el.style.setProperty('--reveal-delay', `${Math.min(delayMs, 480)}ms`);

    const observer = getRevealObserver();
    if (!observer) {
        markRevealed(el);
        return;
    }
    if (onScreen) {
        requestAnimationFrame(() => requestAnimationFrame(() => markRevealed(el)));
    } else {
        observer.observe(el);
    }
};

const autoReveal = (root: ParentNode): void => {
    const candidates = root.querySelectorAll<HTMLElement>(AUTO_REVEAL_SELECTOR);
    const perParent = new Map<Element | null, number>();

    candidates.forEach((el) => {
        if (el.dataset.motionReveal || isOptedOut(el) || insideFixedLayer(el)) return;
        // Do not animate a card inside a card that is already animating, nor a container
        // whose children are animated individually (v-reveal) - that would double the motion
        const ancestor = el.parentElement?.closest('.reveal');
        if (ancestor || el.querySelector('[data-motion-reveal]')) {
            el.dataset.motionReveal = 'done';
            return;
        }
        const parent = el.parentElement;
        const index = perParent.get(parent) ?? 0;
        perParent.set(parent, index + 1);
        reveal(el, index * 70, el.dataset.reveal || 'up');
    });
};

/* ------------------------------------------------------------------ */
/* Cursor spotlight on cards + primary button enhancements             */
/* ------------------------------------------------------------------ */

const CARD_SELECTOR = 'main .rounded-3xl.border, main .rounded-2xl.border, main .rounded-xl.border, [data-spotlight]';
const PRIMARY_BUTTON_SELECTOR = 'button[class*="bg-gradient-to"], a[class*="bg-gradient-to"], [data-shine]';

const isStatic = (el: Element): boolean => getComputedStyle(el).position === 'static';

const enhanceCards = (root: ParentNode): void => {
    root.querySelectorAll<HTMLElement>(CARD_SELECTOR).forEach((el) => {
        if (el.dataset.motionCard || isOptedOut(el)) return;
        el.dataset.motionCard = '1';
        // The glow is a background-image layer: skip cards that already paint a gradient,
        // and never touch positioning (absolute children such as dropdowns stay put).
        if (getComputedStyle(el).backgroundImage === 'none') {
            el.classList.add('spotlight-card');
        }
    });
};

const enhanceButtons = (root: ParentNode): void => {
    root.querySelectorAll<HTMLElement>(PRIMARY_BUTTON_SELECTOR).forEach((el) => {
        if (el.dataset.motionBtn || isOptedOut(el)) return;
        // Avatars/logos also use gradients: only real controls get the button effects
        if (!(el instanceof HTMLButtonElement) && !(el instanceof HTMLAnchorElement) && !el.hasAttribute('data-shine')) return;
        el.dataset.motionBtn = '1';
        if (isStatic(el) || getComputedStyle(el).position === 'relative') {
            el.classList.add('btn-shine');
        }
        if (canHover() && !reduceMotion()) {
            el.classList.add('btn-magnetic');
        }
    });
};

let spotlightFrame = 0;
const onPointerMove = (event: PointerEvent): void => {
    if (spotlightFrame) return;
    spotlightFrame = requestAnimationFrame(() => {
        spotlightFrame = 0;
        const target = event.target as Element | null;
        if (!target || !(target instanceof Element)) return;

        const card = target.closest<HTMLElement>('.spotlight-card');
        if (card) {
            const r = card.getBoundingClientRect();
            card.style.setProperty('--mx', `${event.clientX - r.left}px`);
            card.style.setProperty('--my', `${event.clientY - r.top}px`);
        }

        const btn = target.closest<HTMLElement>('.btn-magnetic');
        if (btn && !(btn as HTMLButtonElement).disabled) {
            const r = btn.getBoundingClientRect();
            const dx = (event.clientX - (r.left + r.width / 2)) / (r.width / 2);
            const dy = (event.clientY - (r.top + r.height / 2)) / (r.height / 2);
            btn.style.setProperty('--magnet-x', `${(dx * 4).toFixed(2)}px`);
            btn.style.setProperty('--magnet-y', `${(dy * 3).toFixed(2)}px`);
            btn.style.setProperty('--mx', `${event.clientX - r.left}px`);
        }
    });
};

const onPointerOut = (event: PointerEvent): void => {
    const target = event.target as Element | null;
    if (!target || !(target instanceof Element)) return;
    const btn = target.closest<HTMLElement>('.btn-magnetic');
    if (btn && !btn.contains(event.relatedTarget as Node | null)) {
        btn.style.setProperty('--magnet-x', '0px');
        btn.style.setProperty('--magnet-y', '0px');
    }
};

const onPointerDown = (event: PointerEvent): void => {
    const target = event.target as Element | null;
    if (!target || !(target instanceof Element) || reduceMotion()) return;
    const btn = target.closest<HTMLElement>('.btn-shine');
    if (!btn || (btn as HTMLButtonElement).disabled) return;

    const r = btn.getBoundingClientRect();
    const size = Math.max(r.width, r.height) * 1.6;
    const ripple = document.createElement('span');
    ripple.className = 'ripple';
    ripple.style.width = ripple.style.height = `${size}px`;
    ripple.style.left = `${event.clientX - r.left - size / 2}px`;
    ripple.style.top = `${event.clientY - r.top - size / 2}px`;
    btn.appendChild(ripple);
    window.setTimeout(() => ripple.remove(), 650);
};

/* ------------------------------------------------------------------ */
/* Scroll progress bar (window and the app's <main> scroller)          */
/* ------------------------------------------------------------------ */

let progressBar: HTMLDivElement | null = null;
let progressFrame = 0;

const updateProgress = (scroller: Element | Document): void => {
    if (!progressBar || progressFrame) return;
    progressFrame = requestAnimationFrame(() => {
        progressFrame = 0;
        const el = scroller instanceof Document ? document.scrollingElement || document.documentElement : (scroller as HTMLElement);
        const max = el.scrollHeight - el.clientHeight;
        const ratio = max > 40 ? Math.min(1, Math.max(0, el.scrollTop / max)) : 0;
        progressBar!.style.transform = `scaleX(${ratio})`;
        progressBar!.style.opacity = ratio > 0.002 ? '1' : '0';
    });
};

const onScroll = (event: Event): void => {
    const target = event.target;
    if (target === document) {
        updateProgress(document);
    } else if (target instanceof HTMLElement && target.tagName === 'MAIN') {
        updateProgress(target);
    }
};

/* ------------------------------------------------------------------ */
/* Directives                                                          */
/* ------------------------------------------------------------------ */

const vReveal: Directive<HTMLElement, number | string | undefined> = {
    mounted(el, binding) {
        const variant = binding.arg || el.dataset.reveal || 'up';
        const delay = typeof binding.value === 'number' ? binding.value : Number(binding.value || 0);
        reveal(el, delay, variant);
    },
};

const vTilt: Directive<HTMLElement, number | undefined> = {
    mounted(el, binding) {
        if (reduceMotion() || !canHover()) return;
        const max = typeof binding.value === 'number' ? binding.value : 6;
        el.classList.add('tilt');
        const move = (e: PointerEvent) => {
            const r = el.getBoundingClientRect();
            const px = (e.clientX - r.left) / r.width - 0.5;
            const py = (e.clientY - r.top) / r.height - 0.5;
            el.style.setProperty('--tilt-x', `${(-py * max).toFixed(2)}deg`);
            el.style.setProperty('--tilt-y', `${(px * max).toFixed(2)}deg`);
        };
        const leave = () => {
            el.style.setProperty('--tilt-x', '0deg');
            el.style.setProperty('--tilt-y', '0deg');
        };
        el.addEventListener('pointermove', move);
        el.addEventListener('pointerleave', leave);
        (el as any).__tiltCleanup = () => {
            el.removeEventListener('pointermove', move);
            el.removeEventListener('pointerleave', leave);
        };
    },
    unmounted(el) {
        (el as any).__tiltCleanup?.();
    },
};

const vMagnetic: Directive<HTMLElement> = {
    mounted(el) {
        el.dataset.motionBtn = '1';
        if (canHover() && !reduceMotion()) el.classList.add('btn-magnetic');
        if (isStatic(el) || getComputedStyle(el).position === 'relative') el.classList.add('btn-shine');
    },
};

/* ------------------------------------------------------------------ */
/* Install                                                             */
/* ------------------------------------------------------------------ */

let installed = false;

export function installMotion(app: App): void {
    app.directive('reveal', vReveal);
    app.directive('tilt', vTilt);
    app.directive('magnetic', vMagnetic);

    if (installed || typeof window === 'undefined') return;
    installed = true;

    const enhance = (root: ParentNode) => {
        autoReveal(root);
        enhanceCards(root);
        enhanceButtons(root);
    };

    const start = () => {
        progressBar = document.createElement('div');
        progressBar.className = 'scroll-progress';
        progressBar.setAttribute('aria-hidden', 'true');
        document.body.appendChild(progressBar);

        enhance(document);

        // New content (route changes, lists loaded from the API) gets the same treatment
        let pending = false;
        const observer = new MutationObserver(() => {
            if (pending) return;
            pending = true;
            requestAnimationFrame(() => {
                pending = false;
                enhance(document);
            });
        });
        observer.observe(document.body, { childList: true, subtree: true });

        document.addEventListener('pointermove', onPointerMove, { passive: true });
        document.addEventListener('pointerout', onPointerOut, { passive: true });
        document.addEventListener('pointerdown', onPointerDown, { passive: true });
        document.addEventListener('scroll', onScroll, { passive: true, capture: true });
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start, { once: true });
    } else {
        start();
    }
}
