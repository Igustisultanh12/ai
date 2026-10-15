<?php

namespace App\Support;

/**
 * Server-side twin of resources/js/utils/codeBlocks.ts: pulls labelled files out of
 * fenced code blocks in chat messages (```php:app/Models/User.php, ```js path=src/a.js,
 * ```routes/web.php, or a first-line "// File: path" comment).
 */
class CodeBlockExtractor
{
    /**
     * @return array<int, array{path: string, language: string, content: string}>
     */
    public static function extract(string $markdown): array
    {
        $files = [];
        if ($markdown === '') {
            return $files;
        }

        preg_match_all('/(?:^|\n)(`{3,}|~{3,})([^\n]*)\n(.*?)\n\1[ \t]*(?=\n|$)/s', $markdown, $matches, PREG_SET_ORDER);

        foreach ($matches as $m) {
            [$language, $path] = self::parseInfo($m[2]);
            $content = $m[3];

            $lines = explode("\n", $content);
            if (preg_match('/^\s*(?:\/\/|#|--|;|\/\*|<!--|\{\{--)\s*(?:file(?:name)?|path)\s*:\s*([^\s*]+?)\s*(?:\*\/|-->|--\}\})?\s*$/i', $lines[0] ?? '', $lm)) {
                if ($path === '') {
                    $path = self::normalise($lm[1]);
                }
                array_shift($lines);
                $content = implode("\n", $lines);
            }

            if (trim($content) === '') {
                continue;
            }

            $files[] = ['path' => $path, 'language' => $language, 'content' => $content];
        }

        return $files;
    }

    /**
     * Latest version of each labelled file across messages (later messages win).
     *
     * @param iterable<string> $contents
     * @return array<string, string> path => content
     */
    public static function latestFiles(iterable $contents): array
    {
        $byPath = [];
        foreach ($contents as $content) {
            foreach (self::extract((string) $content) as $file) {
                if ($file['path'] !== '') {
                    $byPath[$file['path']] = $file['content'];
                }
            }
        }
        return $byPath;
    }

    protected static function parseInfo(string $info): array
    {
        $info = trim($info);
        if ($info === '') {
            return ['', ''];
        }

        $first = preg_split('/\s+/', $info)[0];

        if (preg_match('/(?:^|\s)(?:path|file|filename|title)\s*=\s*(?:"([^"]+)"|\'([^\']+)\'|(\S+))/i', $info, $a)) {
            $path = ($a[1] ?? '') ?: (($a[2] ?? '') ?: ($a[3] ?? ''));
            return [str_contains($first, '=') ? '' : $first, self::normalise($path)];
        }

        $colon = strpos($first, ':');
        if ($colon !== false && $colon > 0) {
            return [substr($first, 0, $colon), self::normalise(substr($first, $colon + 1))];
        }

        if (str_contains($first, '/') || preg_match('/^[\w@.\-\/]+\.[\w.\-]+$|^(?:Dockerfile|Makefile|Procfile|\.\w[\w.\-]*)$/', $first)) {
            $ext = pathinfo($first, PATHINFO_EXTENSION);
            return [$ext, self::normalise($first)];
        }

        return [$first, ''];
    }

    protected static function normalise(string $path): string
    {
        $path = trim($path, " \t\"'`");
        $path = str_replace('\\', '/', $path);
        $path = preg_replace('#^\./#', '', $path);
        return preg_replace('#/{2,}#', '/', $path);
    }
}
