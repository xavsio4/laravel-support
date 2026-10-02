import { resolveLocale } from './i18n';
import type { WidgetConfig } from './types';
import { createRoot, SupportUI, TRIGGER_SELECTOR } from './ui';

/** The page-facing API on window.support once the widget is ready. */
interface PublicApi {
    open(): void;
    close(): void;
    toggle(): void;
}

type QueuedCall = [keyof PublicApi, ...unknown[]];

declare global {
    interface Window {
        support?: Partial<WidgetConfig> & Partial<PublicApi> & { q?: QueuedCall[] };
    }
}

let ui: SupportUI | null = null;
let pending = false;

// Delegated, so triggers rendered later by an SPA work without re-binding.
document.addEventListener('click', (event) => {
    const trigger = (event.target as Element | null)?.closest?.(TRIGGER_SELECTOR);
    if (!(trigger instanceof HTMLElement)) return;

    event.preventDefault();
    if (ui) ui.toggle();
    else pending = true;
});

function readConfig(): WidgetConfig {
    // currentScript is null in an ES module, so the tag is found by its marker.
    const dataset = document.querySelector<HTMLScriptElement>('script[data-support]')?.dataset ?? {};
    const global = window.support ?? {};

    return {
        endpoint: global.endpoint ?? dataset.endpoint ?? '/support/api',
        token: global.token ?? dataset.token,
        locale: global.locale ?? dataset.locale,
        launcher: (global.launcher ?? dataset.launcher ?? 'auto') as WidgetConfig['launcher'],
        position: (global.position ?? dataset.position) === 'left' ? 'left' : 'right',
        appName: global.appName ?? dataset.appName ?? document.title,
    };
}

function boot(): void {
    const config = readConfig();
    const root = createRoot();

    const accent = document.querySelector<HTMLScriptElement>('script[data-support]')?.dataset.accent;
    if (accent && /^#[0-9a-f]{3,8}$/i.test(accent)) (root.host as HTMLElement).style.setProperty('--accent', accent);

    // Auto: a page with its own Help link doesn't also need the floating button.
    const showLauncher =
        config.launcher === 'show' || (config.launcher === 'auto' && !document.querySelector(TRIGGER_SELECTOR));

    const widget = new SupportUI(root, config, resolveLocale(config.locale), showLauncher);
    ui = widget;

    const api: PublicApi = {
        open: () => widget.open(),
        close: () => widget.close(),
        toggle: () => widget.toggle(),
    };

    const global = Object.assign(window.support ?? {}, api);
    window.support = global;

    const queued = global.q ?? [];
    global.q = undefined;
    queued.forEach(([method]) => api[method]?.());

    if (pending) widget.open();
    pending = false;
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
} else {
    boot();
}
