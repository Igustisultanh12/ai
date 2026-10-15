/** One action the chat agent performed (GitHub/project tool call). */
export interface AgentStep {
    id: string;
    label: string;
    status: 'running' | 'done' | 'error' | string;
    link?: string | null;
    error?: string | null;
    startedAt?: number;
    endedAt?: number;
}
