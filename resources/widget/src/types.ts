export interface Citation {
    n: number;
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
