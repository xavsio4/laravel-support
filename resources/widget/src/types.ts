export interface Citation {
    n: number;
    slug?: string;
    title: string;
    url: string | null;
}

export interface ChatMessage {
    id: number;
    role: 'user' | 'assistant' | 'agent';
    status: 'pending' | 'done' | 'failed';
    declined: boolean;
    content: string | null;
    citations: Citation[];
    created_at: string | null;
}

export interface ConversationState {
    id: string;
    status: 'ai' | 'escalated' | 'closed';
    messages: ChatMessage[];
}

export interface WidgetConfig {
    endpoint: string;
    token?: string;
    locale?: string;
    launcher: 'show' | 'hide' | 'auto';
    position: 'left' | 'right';
    appName: string;
}

export interface DocSummary {
    slug: string;
    title: string;
    description: string;
    url: string | null;
}

export interface SearchHit {
    slug: string;
    title: string;
    section: string;
    snippet: string;
    url: string | null;
}

export interface FaqEntry {
    question: string;
    html: string;
}
