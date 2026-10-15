/**
 * Extract fenced code blocks (and the file path they belong to) from chat markdown,
 * so files written by the AI can be pushed to GitHub straight from the chat.
 *
 * Recognised path labels:
 *   ```php:app/Models/User.php
 *   ```php path=app/Models/User.php   (also file=, filename=, title=)
 *   ```app/Models/User.php
 *   first line comment: // File: app/Models/User.php   # path: x.py   <!-- file: a.html -->
 */

export interface CodeFile {
    path: string;
    language: string;
    content: string;
}

const EXT_BY_LANG: Record<string, string> = {
    php: 'php', js: 'js', javascript: 'js', ts: 'ts', typescript: 'ts', vue: 'vue',
    py: 'py', python: 'py', html: 'html', css: 'css', json: 'json', yaml: 'yml', yml: 'yml',
    sh: 'sh', bash: 'sh', sql: 'sql', go: 'go', rust: 'rs', java: 'java', kotlin: 'kt',
    dart: 'dart', md: 'md', markdown: 'md', blade: 'blade.php', tsx: 'tsx', jsx: 'jsx',
};

const PATH_LIKE = /^[\w@.\-\/]+\.[\w.\-]+$|^(?:Dockerfile|Makefile|Procfile|\.\w[\w.\-]*)$/;

const FIRST_LINE_LABEL =
    /^\s*(?:\/\/|#|--|;|\/\*|<!--|\{\{--)\s*(?:file(?:name)?|path)\s*:\s*([^\s*]+?)\s*(?:\*\/|-->|--\}\})?\s*$/i;

export function normalisePath(raw: string): string {
    let p = (raw || '').trim().replace(/^["'`]|["'`]$/g, '').replace(/\\/g, '/');
    p = p.replace(/^\.\//, '').replace(/\/{2,}/g, '/');
    return p;
}

export function isSafePath(path: string): boolean {
    if (!path || path.startsWith('/') || /^[A-Za-z]:/.test(path) || path.length > 400) return false;
    return path.split('/').every(seg => seg !== '' && seg !== '.' && seg !== '..' && seg.toLowerCase() !== '.git');
}

function parseInfo(info: string): { language: string; path: string } {
    const trimmed = info.trim();
    if (!trimmed) return { language: '', path: '' };

    const attr = trimmed.match(/(?:^|\s)(?:path|file|filename|title)\s*=\s*("([^"]+)"|'([^']+)'|(\S+))/i);
    const firstToken = trimmed.split(/\s+/)[0];

    if (attr) {
        const path = attr[2] || attr[3] || attr[4] || '';
        const language = /=/.test(firstToken) ? '' : firstToken;
        return { language, path: normalisePath(path) };
    }

    const colon = firstToken.indexOf(':');
    if (colon > 0) {
        return { language: firstToken.slice(0, colon), path: normalisePath(firstToken.slice(colon + 1)) };
    }

    if (firstToken.includes('/') || PATH_LIKE.test(firstToken)) {
        const ext = firstToken.split('.').pop() || '';
        return { language: ext, path: normalisePath(firstToken) };
    }

    return { language: firstToken, path: '' };
}

export function extractCodeFiles(markdown: string): CodeFile[] {
    if (!markdown) return [];
    const files: CodeFile[] = [];
    const fence = /(^|\n)(`{3,}|~{3,})([^\n]*)\n([\s\S]*?)\n\2[ \t]*(?=\n|$)/g;

    let match: RegExpExecArray | null;
    while ((match = fence.exec(markdown)) !== null) {
        const { language, path: infoPath } = parseInfo(match[3]);
        let content = match[4];
        let path = infoPath;

        const lines = content.split('\n');
        const labelMatch = lines[0]?.match(FIRST_LINE_LABEL);
        if (labelMatch) {
            if (!path) path = normalisePath(labelMatch[1]);
            lines.shift();
            content = lines.join('\n');
        }

        if (!content.trim()) continue;
        files.push({ path, language, content });
    }

    return files;
}

/**
 * Collect the latest version of every labelled file across several messages.
 */
export function collectLatestFiles(messages: { role: string; content: string }[]): CodeFile[] {
    const byPath = new Map<string, CodeFile>();
    for (const msg of messages) {
        if (msg.role !== 'assistant') continue;
        for (const file of extractCodeFiles(msg.content)) {
            if (file.path) byPath.set(file.path, file);
        }
    }
    return Array.from(byPath.values());
}

export function suggestPath(file: CodeFile, index: number): string {
    const ext = EXT_BY_LANG[file.language?.toLowerCase()] || file.language?.toLowerCase() || 'txt';
    return `snippet-${index + 1}.${ext}`;
}
