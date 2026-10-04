import { Api, ApiError, lastId } from './api';
import { t, type Locale, type MessageKey } from './i18n';
import { render } from './markdown';
import type { ChatMessage, ConversationState, DocSummary, FaqEntry, SearchHit, WidgetConfig } from './types';

const NS = 'fp-support';

/** Elements on the host page that open the chat. */
export const TRIGGER_SELECTOR = '[data-support-open], a[href="#support"]';

type Tab = 'ask' | 'docs' | 'faq';

const TAB_KEY = 'fp-support:tab';

const POLL_PENDING_MS = 1500;
const POLL_ESCALATED_MS = 20000;

/**
 * Inside a shadow root, so the host app's CSS cannot reach in and ours cannot
 * leak out.
 */
export function createRoot(): ShadowRoot {
    const host = document.createElement('div');
    host.id = `${NS}-root`;
    host.style.cssText = 'all:initial;position:fixed;z-index:2147483646;';
    document.body.appendChild(host);
    return host.attachShadow({ mode: 'open' });
}

const STYLES = `
:host{all:initial;--accent:#14161a;--accent-ink:#fff}
*,*::before,*::after{box-sizing:border-box}
.launcher{position:fixed;bottom:20px;width:52px;height:52px;border-radius:999px;border:0;cursor:pointer;
  background:var(--accent);color:var(--accent-ink);display:flex;align-items:center;justify-content:center;
  box-shadow:0 2px 4px rgb(20 22 26/.08),0 12px 28px -10px rgb(20 22 26/.45);transition:transform .15s ease}
.launcher:hover{transform:translateY(-1px)}
.launcher:focus-visible{outline:3px solid var(--accent);outline-offset:3px}
.launcher svg{width:26px;height:26px}
.right{right:20px}.left{left:20px}
.panel{position:fixed;bottom:84px;width:380px;max-width:calc(100vw - 32px);height:560px;max-height:calc(100vh - 110px);
  background:#fff;color:#14161a;border:1px solid #e6e7e9;border-radius:18px;display:flex;flex-direction:column;
  box-shadow:0 2px 4px rgb(20 22 26/.05),0 24px 48px -20px rgb(20 22 26/.28);font:14px/1.5 system-ui,sans-serif;overflow:hidden}
.panel.solo{bottom:20px}
@media (max-width:480px){.panel{left:16px!important;right:16px!important;width:auto;bottom:16px;max-height:calc(100vh - 32px)}}
.head{display:flex;align-items:center;justify-content:space-between;padding:12px 14px;border-bottom:1px solid #e6e7e9;font-weight:700}
.tools{display:flex;gap:4px}
.icon{border:0;background:none;cursor:pointer;color:#8e94a3;padding:4px 6px;border-radius:6px;font:600 12px system-ui,sans-serif}
.icon:hover{color:#14161a;background:#f3f4f5}
.icon:focus-visible{outline:2px solid var(--accent)}
.log{flex:1;overflow-y:auto;padding:14px;display:flex;flex-direction:column;gap:10px}
.intro{color:#5a6070;font-size:13px;background:#f6f7f8;border-radius:12px;padding:10px 12px}
.msg{max-width:88%;padding:8px 12px;border-radius:14px;overflow-wrap:anywhere}
.msg p{margin:0 0 6px}.msg p:last-child{margin:0}
.msg ul,.msg ol{margin:4px 0 6px;padding-left:20px}
.msg code{font:12px ui-monospace,monospace;background:#eceef0;padding:1px 4px;border-radius:4px}
.msg sup{font-size:10px;color:#5a6070;margin-left:1px}
.user{align-self:flex-end;background:var(--accent);color:var(--accent-ink);white-space:pre-wrap}
.assistant,.agent{align-self:flex-start;background:#f3f4f5}
.agent{border:1px solid #f7c53e;background:#fffaf0}
.who{display:block;font-size:11px;font-weight:700;color:#8a6d1a;margin-bottom:2px}
.muted{color:#5a6070}
.dots::after{content:'';animation:dots 1.2s steps(4) infinite}
@keyframes dots{0%{content:''}25%{content:'.'}50%{content:'..'}75%{content:'...'}}
.sources{display:flex;flex-wrap:wrap;gap:6px;margin-top:6px}
.chip{font-size:11px;border:1px solid #d9dbdf;border-radius:999px;padding:2px 8px;color:#14161a;text-decoration:none;background:#fff}
a.chip:hover{border-color:#14161a}
.human{margin-top:6px;border:1px solid #14161a;background:#fff;color:#14161a;border-radius:999px;padding:4px 10px;cursor:pointer;font:600 12px system-ui,sans-serif}
.human:hover{background:#14161a;color:#fff}
.foot{border-top:1px solid #e6e7e9;padding:10px;display:flex;flex-direction:column;gap:6px}
.row{display:flex;gap:8px;align-items:flex-end}
textarea{flex:1;resize:none;border:1px solid #e6e7e9;border-radius:10px;padding:8px 10px;font:inherit;color:#14161a;max-height:120px;min-height:40px}
textarea:focus{outline:none;border-color:var(--accent);box-shadow:0 0 0 3px rgb(20 22 26/.08)}
.send{border:0;border-radius:10px;background:var(--accent);color:var(--accent-ink);padding:0 14px;height:40px;cursor:pointer;font:700 13px system-ui,sans-serif}
.send:disabled{opacity:.5;cursor:default}
.link{align-self:flex-start;border:0;background:none;color:#5a6070;cursor:pointer;font:12px system-ui,sans-serif;text-decoration:underline;padding:0}
.notice{font-size:13px;color:#5a6070;background:#f6f7f8;border-radius:10px;padding:10px 12px}
.err{color:#a93a24;font-size:12px}
.tabs{display:flex;gap:4px;padding:6px 10px 0;border-bottom:1px solid #e6e7e9}
.tab{border:0;background:none;padding:8px 10px;cursor:pointer;font:600 13px system-ui,sans-serif;color:#8e94a3;border-bottom:2px solid transparent;margin-bottom:-1px}
.tab[aria-selected="true"]{color:#14161a;border-bottom-color:var(--accent)}
.tab:focus-visible{outline:2px solid var(--accent)}
.pane{flex:1;overflow-y:auto;padding:14px;display:flex;flex-direction:column;gap:10px}
.search{width:100%;border:1px solid #e6e7e9;border-radius:10px;padding:8px 10px;font:inherit;color:#14161a}
.search:focus{outline:none;border-color:var(--accent);box-shadow:0 0 0 3px rgb(20 22 26/.08)}
.item{display:block;width:100%;text-align:left;text-decoration:none;border:1px solid #e6e7e9;border-radius:12px;background:#fff;padding:10px 12px;cursor:pointer;font:inherit;color:#14161a}
.item:hover{border-color:#14161a}
.item b{display:block;font-size:13px}
.item span{display:block;color:#5a6070;font-size:12px;margin-top:2px}
.doc{font-size:13.5px;overflow-wrap:anywhere}
.doc h2{font-size:15px;margin:16px 0 6px}.doc h3{font-size:14px;margin:12px 0 4px}
.doc p{margin:0 0 8px}.doc ul,.doc ol{margin:4px 0 8px;padding-left:20px}
.doc code{font:12px ui-monospace,monospace;background:#eceef0;padding:1px 4px;border-radius:4px}
.doc pre{background:#f3f4f5;border-radius:8px;padding:8px 10px;overflow-x:auto}.doc pre code{background:none;padding:0}
.doc table{border-collapse:collapse;display:block;overflow-x:auto;font-size:12px}.doc th,.doc td{border:1px solid #e6e7e9;padding:4px 6px}
.doc a{color:#14161a}
.full{align-self:flex-start;font-size:12px;font-weight:600;color:#14161a}
details{border:1px solid #e6e7e9;border-radius:12px;padding:8px 12px}
summary{cursor:pointer;font-weight:600;font-size:13px}
details .doc{margin-top:6px}
.ask{display:flex;gap:8px;align-items:center;font-size:12px;color:#5a6070;margin-top:4px}
`;

const ICON = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12a8 8 0 0 1-11.6 7.1L4 20l1-4.6A8 8 0 1 1 21 12z"/><path d="M9.6 9.5a2.5 2.5 0 0 1 4.8.9c0 1.7-2.4 2.1-2.4 3.4"/><path d="M12 16.5h.01"/></svg>`;

export class SupportUI {
    private api: Api;

    private panel: HTMLElement | null = null;

    private launcher: HTMLButtonElement | null = null;

    private conversation: ConversationState | null = null;

    private loaded = false;

    private sending = false;

    private error: MessageKey | null = null;

    private timer: number | null = null;

    private opener: HTMLElement | null = null;

    private tab: Tab = 'ask';

    private docs: DocSummary[] | null = null;

    private hasFaq = false;

    private home: string | null = null;

    private query = '';

    private hits: SearchHit[] | null = null;

    private faq: FaqEntry[] | null = null;

    private loadFailed = false;

    private searchTimer: number | null = null;

    constructor(
        private root: ShadowRoot,
        private config: WidgetConfig,
        private locale: Locale,
        showLauncher: boolean,
    ) {
        this.api = new Api(config);

        const style = document.createElement('style');
        style.textContent = STYLES;
        this.root.appendChild(style);

        if (showLauncher) this.renderLauncher();

        try {
            const saved = sessionStorage.getItem(TAB_KEY);
            if (saved === 'docs' || saved === 'faq') this.tab = saved;
        } catch {
            // No storage: start on Ask.
        }
    }

    private t(key: MessageKey): string {
        return t(this.locale, key, this.config.appName);
    }

    private renderLauncher(): void {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = `launcher ${this.config.position}`;
        button.setAttribute('aria-label', this.t('launcher'));
        button.innerHTML = ICON;
        button.addEventListener('click', () => this.toggle());
        this.root.appendChild(button);
        this.launcher = button;
    }

    toggle(): void {
        if (this.panel) this.close();
        else this.open();
    }

    open(): void {
        if (this.panel) return;

        this.opener = document.activeElement instanceof HTMLElement ? document.activeElement : null;

        const panel = document.createElement('div');
        panel.className = `panel ${this.config.position}${this.launcher ? '' : ' solo'}`;
        panel.setAttribute('role', 'dialog');
        panel.setAttribute('aria-label', this.t('title'));
        this.root.appendChild(panel);
        this.panel = panel;

        document.addEventListener('keydown', this.onEscape, true);
        this.render();

        // Fetched once: it decides whether the FAQ tab exists.
        if (this.docs === null) void this.loadDocs();
        if (this.tab === 'faq' && this.faq === null) void this.loadFaq();

        // Every open refreshes, so an agent's reply that arrived meanwhile shows.
        void this.api
            .current()
            .then((conversation) => {
                this.conversation = conversation;
                this.loaded = true;
                this.render();
                this.schedule();
            })
            .catch(() => {
                this.loaded = true;
                this.render();
            });
    }

    close(): void {
        this.panel?.remove();
        this.panel = null;
        this.stop();
        document.removeEventListener('keydown', this.onEscape, true);
    }

    private onEscape = (event: KeyboardEvent): void => {
        if (event.key !== 'Escape') return;
        event.stopPropagation();
        const opener = this.opener;
        this.close();
        (opener?.isConnected ? opener : this.launcher)?.focus();
    };

    private render(): void {
        const panel = this.panel;
        if (!panel) return;

        // Keep a half-typed question across re-renders.
        const draft = panel.querySelector('textarea')?.value ?? '';
        const hadFocus = this.root.activeElement?.tagName === 'TEXTAREA';

        const searchFocused = this.root.activeElement?.classList.contains('search') ?? false;

        if (this.tab === 'ask') {
            panel.replaceChildren(this.renderHead(), this.renderTabs(), this.renderLog(), this.renderFoot(draft));

            const log = panel.querySelector('.log');
            if (log) log.scrollTop = log.scrollHeight;

            const textarea = panel.querySelector('textarea');
            if (textarea && (hadFocus || draft === '')) textarea.focus();

            return;
        }

        panel.replaceChildren(this.renderHead(), this.renderTabs(), this.tab === 'docs' ? this.renderDocs() : this.renderFaq());

        if (searchFocused) {
            const search = panel.querySelector<HTMLInputElement>('.search');
            search?.focus();
            search?.setSelectionRange(search.value.length, search.value.length);
        }
    }

    private renderTabs(): HTMLElement {
        const tabs = el('div', 'tabs');
        tabs.setAttribute('role', 'tablist');

        const entries: Array<[Tab, MessageKey]> = [['ask', 'tabAsk'], ['docs', 'tabDocs'], ['faq', 'tabFaq']];

        for (const [tab, label] of entries) {
            if (tab === 'faq' && this.docs !== null && !this.hasFaq) continue;

            const button = el('button', 'tab', this.t(label)) as HTMLButtonElement;
            button.type = 'button';
            button.setAttribute('role', 'tab');
            button.setAttribute('aria-selected', String(this.tab === tab));
            button.addEventListener('click', () => this.select(tab));
            tabs.append(button);
        }

        return tabs;
    }

    private select(tab: Tab): void {
        this.tab = tab;
        this.loadFailed = false;

        try {
            sessionStorage.setItem(TAB_KEY, tab);
        } catch {
            // Not remembered; harmless.
        }

        this.render();

        if (tab === 'docs' && this.docs === null) void this.loadDocs();
        if (tab === 'faq' && this.faq === null) void this.loadFaq();
    }

    private async loadDocs(): Promise<void> {
        try {
            const result = await this.api.docs();
            this.docs = result.docs;
            this.home = result.home;
            this.hasFaq = result.has_faq;
        } catch {
            this.loadFailed = true;
        }
        this.render();
    }

    private async loadFaq(): Promise<void> {
        try {
            this.faq = await this.api.faq();
        } catch {
            this.loadFailed = true;
        }
        this.render();
    }

    private renderDocs(): HTMLElement {
        const pane = el('div', 'pane');

        // Pages are read on the public help pages, in their own window.
        if (this.home && /^https?:\/\//.test(this.home)) {
            const home = el('a', 'full', `${this.t('openHelpCentre')} ↗`) as HTMLAnchorElement;
            home.href = this.home;
            home.target = '_blank';
            home.rel = 'noopener';
            pane.append(home);
        }

        const search = document.createElement('input');
        search.type = 'search';
        search.className = 'search';
        search.placeholder = this.t('searchDocs');
        search.setAttribute('aria-label', this.t('searchDocs'));
        search.value = this.query;
        search.addEventListener('input', () => {
            this.query = search.value;
            if (this.searchTimer !== null) window.clearTimeout(this.searchTimer);
            this.searchTimer = window.setTimeout(() => void this.runSearch(), 250);
        });
        pane.append(search);

        if (this.loadFailed) {
            pane.append(el('div', 'err', this.t('loadFailed')));
            return pane;
        }

        if (this.query.trim().length >= 2 && this.hits !== null) {
            if (this.hits.length === 0) pane.append(el('div', 'muted', this.t('noResults')));

            for (const hit of this.hits) {
                pane.append(this.item(hit.section ? `${hit.title} › ${hit.section}` : hit.title, hit.snippet, hit.url));
            }

            return pane;
        }

        if (this.docs === null) {
            pane.append(el('div', 'muted dots', this.t('loading')));
            return pane;
        }

        for (const doc of this.docs) pane.append(this.item(doc.title, doc.description, doc.url));

        return pane;
    }

    /** A page, opened in a new window on the public help pages. */
    private item(title: string, detail: string, url: string | null): HTMLElement {
        const link = el('a', 'item') as HTMLAnchorElement;
        if (url && /^https?:\/\//.test(url)) {
            link.href = url;
            link.target = '_blank';
            link.rel = 'noopener';
        }
        link.append(el('b', '', title));
        if (detail) link.append(el('span', '', detail));
        return link;
    }

    private async runSearch(): Promise<void> {
        const query = this.query.trim();

        if (query.length < 2) {
            this.hits = null;
            this.render();
            return;
        }

        try {
            const hits = await this.api.search(query);
            if (this.query.trim() === query) this.hits = hits;
        } catch {
            this.hits = [];
        }

        this.render();
    }

    private renderFaq(): HTMLElement {
        const pane = el('div', 'pane');

        if (this.loadFailed) {
            pane.append(el('div', 'err', this.t('loadFailed')));
            return pane;
        }

        if (this.faq === null) {
            pane.append(el('div', 'muted dots', this.t('loading')));
            return pane;
        }

        if (this.faq.length === 0) pane.append(el('div', 'muted', this.t('faqEmpty')));

        for (const entry of this.faq) {
            const details = document.createElement('details');
            details.append(el('summary', '', entry.question));
            const answer = el('div', 'doc');
            answer.innerHTML = entry.html;
            this.wireLinks(answer);
            details.append(answer);
            pane.append(details);
        }

        const ask = el('div', 'ask', this.t('askInstead'));
        const button = el('button', 'human', this.t('askAssistant')) as HTMLButtonElement;
        button.type = 'button';
        button.style.marginTop = '0';
        button.addEventListener('click', () => this.select('ask'));
        ask.append(button);
        pane.append(ask);

        return pane;
    }

    /** Links in FAQ answers (to doc pages or elsewhere) open in a new window. */
    private wireLinks(container: HTMLElement): void {
        container.querySelectorAll<HTMLAnchorElement>('a[href]').forEach((link) => {
            if (/^https?:\/\//.test(link.getAttribute('href') ?? '')) {
                link.target = '_blank';
                link.rel = 'noopener';
            }
        });
    }

    private renderHead(): HTMLElement {
        const head = el('div', 'head');
        head.append(el('span', '', this.t('title')));

        const tools = el('div', 'tools');

        if (this.tab === 'ask' && this.conversation && this.conversation.messages.length > 0) {
            const fresh = el('button', 'icon', this.t('newChat')) as HTMLButtonElement;
            fresh.type = 'button';
            fresh.addEventListener('click', () => void this.startOver());
            tools.append(fresh);
        }

        const close = el('button', 'icon', '✕') as HTMLButtonElement;
        close.type = 'button';
        close.setAttribute('aria-label', this.t('close'));
        close.addEventListener('click', () => this.close());
        tools.append(close);

        head.append(tools);
        return head;
    }

    private renderLog(): HTMLElement {
        const log = el('div', 'log');
        log.setAttribute('aria-live', 'polite');
        log.append(el('div', 'intro', this.t('intro')));

        const messages = this.conversation?.messages ?? [];
        const escalated = this.conversation?.status !== undefined && this.conversation.status !== 'ai';

        messages.forEach((message, index) => {
            const isLatest = index === messages.length - 1;
            log.append(this.renderMessage(message, isLatest && !escalated));
        });

        return log;
    }

    private renderMessage(message: ChatMessage, offerHuman: boolean): HTMLElement {
        if (message.role === 'user') return el('div', 'msg user', message.content ?? '');

        const bubble = el('div', `msg ${message.role}`);

        if (message.role === 'agent') {
            bubble.append(el('span', 'who', this.t('agent')));
            const body = el('div');
            body.innerHTML = render(message.content ?? '');
            bubble.append(body);
            return bubble;
        }

        if (message.status === 'pending') {
            bubble.append(el('span', 'muted dots', this.t('thinking')));
            return bubble;
        }

        if (message.status === 'failed' || message.declined) {
            bubble.append(el('div', 'muted', this.t(message.declined ? 'declined' : 'failed')));
            if (offerHuman) bubble.append(this.humanButton());
            return bubble;
        }

        const body = el('div');
        body.innerHTML = render(message.content ?? '');
        bubble.append(body);

        if (message.citations.length > 0) {
            const sources = el('div', 'sources');
            sources.setAttribute('aria-label', this.t('sources'));

            for (const citation of message.citations) {
                const label = `${citation.n}. ${citation.title}`;

                if (citation.url && /^https?:\/\//.test(citation.url)) {
                    const link = el('a', 'chip', label) as HTMLAnchorElement;
                    link.href = citation.url;
                    link.target = '_blank';
                    link.rel = 'noopener';
                    sources.append(link);
                } else {
                    sources.append(el('span', 'chip', label));
                }
            }

            bubble.append(sources);
        }

        return bubble;
    }

    private humanButton(): HTMLElement {
        const button = el('button', 'human', this.t('human')) as HTMLButtonElement;
        button.type = 'button';
        button.addEventListener('click', () => void this.escalate());
        return button;
    }

    private renderFoot(draft: string): HTMLElement {
        const foot = el('div', 'foot');

        if (this.conversation && this.conversation.status === 'escalated') {
            foot.append(el('div', 'notice', this.t('escalated')));
            return foot;
        }

        if (this.error) foot.append(el('div', 'err', this.t(this.error)));

        const row = el('div', 'row');
        const textarea = document.createElement('textarea');
        textarea.rows = 1;
        textarea.maxLength = 2000;
        textarea.placeholder = this.t('placeholder');
        textarea.setAttribute('aria-label', this.t('placeholder'));
        textarea.value = draft;

        const pending = this.hasPending();
        const send = el('button', 'send', this.t('send')) as HTMLButtonElement;
        send.type = 'button';
        send.disabled = this.sending || pending || !this.loaded;

        const submit = (): void => {
            if (!send.disabled && textarea.value.trim()) void this.send(textarea.value.trim());
        };

        textarea.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' && !event.shiftKey && !event.isComposing) {
                event.preventDefault();
                submit();
            }
        });
        send.addEventListener('click', submit);

        row.append(textarea, send);
        foot.append(row);

        const hasAnswer = this.conversation?.messages.some((m) => m.role === 'assistant' && m.status !== 'pending');
        if (hasAnswer) {
            const link = el('button', 'link', this.t('human')) as HTMLButtonElement;
            link.type = 'button';
            link.addEventListener('click', () => void this.escalate());
            foot.append(link);
        }

        return foot;
    }

    private hasPending(): boolean {
        return this.conversation?.messages.some((m) => m.status === 'pending') ?? false;
    }

    private async send(content: string): Promise<void> {
        this.sending = true;
        this.error = null;

        // Shown at once; replaced by the server's copy.
        const optimistic: ChatMessage = {
            id: Number.MAX_SAFE_INTEGER,
            role: 'user',
            status: 'done',
            declined: false,
            content,
            citations: [],
            created_at: null,
        };
        const before = this.conversation;
        this.conversation = {
            id: before?.id ?? '',
            status: 'ai',
            messages: [...(before?.messages ?? []), optimistic],
        };

        const textarea = this.panel?.querySelector('textarea');
        if (textarea) textarea.value = '';
        this.render();

        try {
            const result = await this.api.send(content, before?.id || null, this.locale);
            // A new conversation id means the old one was closed server-side.
            const kept = before && before.id === result.id ? before.messages : [];
            this.conversation = { ...result, messages: merge(kept, result.messages) };
        } catch (error) {
            this.conversation = before;
            this.error = errorKey(error);
            const area = this.panel?.querySelector('textarea');
            if (area) area.value = content;
        } finally {
            this.sending = false;
            this.render();
            this.schedule();
        }
    }

    private async escalate(): Promise<void> {
        if (!this.conversation?.id) return;

        try {
            const result = await this.api.escalate(this.conversation.id);
            this.conversation = { ...this.conversation, status: result.status };
        } catch (error) {
            this.error = errorKey(error);
        }

        this.render();
        this.schedule();
    }

    private async startOver(): Promise<void> {
        const current = this.conversation;
        this.conversation = null;
        this.error = null;
        this.stop();
        this.render();

        if (current?.id) await this.api.close(current.id).catch(() => undefined);
    }

    /** Polls quickly while an answer is pending, slowly while a person has it. */
    private schedule(): void {
        this.stop();
        if (!this.panel || !this.conversation?.id) return;

        const delay = this.hasPending()
            ? POLL_PENDING_MS
            : this.conversation.status === 'escalated'
              ? POLL_ESCALATED_MS
              : null;

        if (delay === null) return;

        this.timer = window.setTimeout(() => void this.poll(), delay);
    }

    private async poll(): Promise<void> {
        const conversation = this.conversation;
        if (!conversation?.id) return;

        // From just before the oldest pending message, so it comes back filled in.
        const pending = conversation.messages.filter((m) => m.status === 'pending').map((m) => m.id);
        const after = pending.length > 0 ? Math.min(...pending) - 1 : lastId(conversation.messages);

        try {
            const update = await this.api.since(conversation.id, after);
            if (this.conversation?.id !== conversation.id) return;

            this.conversation = { ...update, messages: merge(this.conversation.messages, update.messages) };
            this.render();
        } catch {
            // A missed poll is retried by the next one.
        }

        this.schedule();
    }

    private stop(): void {
        if (this.timer !== null) window.clearTimeout(this.timer);
        this.timer = null;
    }
}

function merge(existing: ChatMessage[], incoming: ChatMessage[]): ChatMessage[] {
    const byId = new Map(existing.filter((m) => m.id !== Number.MAX_SAFE_INTEGER).map((m) => [m.id, m]));
    for (const message of incoming) byId.set(message.id, message);
    return [...byId.values()].sort((a, b) => a.id - b.id);
}

function errorKey(error: unknown): MessageKey {
    if (error instanceof ApiError && error.status === 429) return 'errBusy';
    if (error instanceof ApiError && error.status === 422) return 'errLong';
    return 'errGeneric';
}

function el(tag: string, className = '', text?: string): HTMLElement {
    const node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== undefined) node.textContent = text;
    return node;
}
