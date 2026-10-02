import type { ChatMessage, ConversationState, DocPage, DocSummary, FaqEntry, SearchHit, WidgetConfig } from './types';

/** An HTTP failure, with the status so the UI can say something specific. */
export class ApiError extends Error {
    constructor(public status: number) {
        super(`HTTP ${status}`);
    }
}

/**
 * The widget talks to its own app's backend, so the user is whoever is logged
 * in: a session cookie plus Laravel's XSRF cookie, or a Sanctum bearer token
 * for apps whose frontend lives elsewhere.
 */
export class Api {
    constructor(private config: WidgetConfig) {}

    private url(path: string): string {
        return `${this.config.endpoint.replace(/\/$/, '')}/${path}`;
    }

    private headers(): Record<string, string> {
        const headers: Record<string, string> = {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        };

        if (this.config.token) {
            headers.Authorization = `Bearer ${this.config.token}`;
        } else {
            const xsrf = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);
            if (xsrf) headers['X-XSRF-TOKEN'] = decodeURIComponent(xsrf[1]);
        }

        return headers;
    }

    private async request<T>(method: 'GET' | 'POST', path: string, body?: unknown): Promise<T> {
        const response = await fetch(this.url(path), {
            method,
            headers: this.headers(),
            body: body === undefined ? undefined : JSON.stringify(body),
            credentials: this.config.token ? 'omit' : 'same-origin',
        });

        if (!response.ok) throw new ApiError(response.status);

        return (await response.json()) as T;
    }

    async current(): Promise<ConversationState | null> {
        return (await this.request<{ conversation: ConversationState | null }>('GET', 'conversation')).conversation;
    }

    async since(id: string, after: number): Promise<ConversationState> {
        return (await this.request<{ conversation: ConversationState }>('GET', `conversations/${id}?after=${after}`))
            .conversation;
    }

    async send(content: string, conversationId: string | null, locale: string): Promise<ConversationState> {
        return (
            await this.request<{ conversation: ConversationState }>('POST', 'messages', {
                content,
                conversation_id: conversationId,
                page_url: window.location.href,
                locale,
            })
        ).conversation;
    }

    async escalate(id: string): Promise<ConversationState> {
        return (await this.request<{ conversation: ConversationState }>('POST', `conversations/${id}/escalate`))
            .conversation;
    }

    async close(id: string): Promise<void> {
        await this.request('POST', `conversations/${id}/close`);
    }

    async docs(): Promise<{ docs: DocSummary[]; has_faq: boolean }> {
        return this.request('GET', 'docs');
    }

    async doc(slug: string): Promise<DocPage> {
        return (await this.request<{ doc: DocPage }>('GET', `docs/${encodeURIComponent(slug)}`)).doc;
    }

    async search(query: string): Promise<SearchHit[]> {
        return (await this.request<{ results: SearchHit[] }>('GET', `docs/search?q=${encodeURIComponent(query)}`))
            .results;
    }

    async faq(): Promise<FaqEntry[]> {
        return (await this.request<{ faq: FaqEntry[] }>('GET', 'faq')).faq;
    }
}

export function lastId(messages: ChatMessage[]): number {
    return messages.reduce((max, m) => Math.max(max, m.id), 0);
}
