<?php

namespace App\Services\GitHub;

/**
 * Detects "push this to <github link>" style instructions written in free text,
 * e.g. "push ke https://github.com/budi/project-a", "tolong upload ke
 * github.com/budi/project-a branch dev", "commit ke git@github.com:budi/a.git".
 */
class PushCommandParser
{
    protected const VERBS = '(?:push|upload|unggah|kirim|commit|simpan|deploy|sync|sinkron(?:kan|isasi)?)';

    /** Words that carry no task meaning in a push request (ID + EN). */
    protected const FILLER = [
        'push', 'pushkan', 'dipush', 'upload', 'uploadkan', 'unggah', 'kirim', 'commit', 'simpan', 'deploy', 'sync',
        'sinkron', 'sinkronkan', 'sinkronisasi', 'ke', 'kesini', 'sini', 'situ', 'ini', 'itu', 'repo', 'repositori',
        'repository', 'github', 'git', 'link', 'url', 'tolong', 'dong', 'ya', 'yah', 'yuk', 'please', 'pls', 'to',
        'the', 'into', 'this', 'that', 'my', 'project', 'proyek', 'projek', 'semua', 'seluruh', 'file', 'files',
        'berkas', 'kode', 'code', 'source', 'sekarang', 'now', 'langsung', 'aja', 'saja', 'coba', 'di', 'yang',
        'baru', 'new', 'aku', 'saya', 'gue', 'gw', 'kita', 'kamu', 'buat', 'bikin', 'dibuat', 'tadi', 'udah',
        'sudah', 'barusan', 'and', 'dan', 'lalu', 'terus', 'trus', 'habis', 'itu', 'nya', 'branch', 'cabang', 'on',
        'pada', 'dalam', 'with', 'dengan', 'pesan', 'message', 'hasil', 'hasilnya', 'kodenya', 'projectnya',
        'proyeknya', 'filenya', 'semuanya', 'it', 'all', 'a', 'an', 'of', 'for', 'untuk', 'agar', 'supaya', 'biar',
        'aplikasi', 'app', 'web', 'website', 'mau', 'ingin', 'pengen', 'pingin', 'boleh', 'bisa', 'minta',
    ];

    /**
     * @return array{repo: string, branch: ?string, commit_message: ?string, push_only: bool, remainder: string}|null
     */
    public function parse(string $text): ?array
    {
        $repo = null;
        $branch = null;
        $matchedUrl = null;

        $patterns = [
            // https://github.com/owner/repo(.git)(/tree/branch)
            '#(?:https?://)?(?:www\.)?github\.com/([A-Za-z0-9](?:[A-Za-z0-9-]{0,38}))/([A-Za-z0-9_.-]+?)(?:\.git)?(?:/tree/([A-Za-z0-9._/\-]+?))?(?=[\s,;)\]>"\'`]|[.!?](?:\s|$)|$)#i',
            // git@github.com:owner/repo.git
            '#git@github\.com:([A-Za-z0-9](?:[A-Za-z0-9-]{0,38}))/([A-Za-z0-9_.-]+?)(?:\.git)?(?=[\s,;)\]>"\'`]|[.!?](?:\s|$)|$)#i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $m)) {
                $repo = $m[1] . '/' . $m[2];
                $branch = !empty($m[3] ?? null) ? rtrim($m[3], '/') : null;
                $matchedUrl = $m[0];
                break;
            }
        }

        if (!$repo) {
            return null;
        }

        // Must actually ask for a push, not just mention a link.
        if (!preg_match('/\b' . self::VERBS . '\w*\b/iu', $text)) {
            return null;
        }

        if (!$branch && preg_match('/\b(?:branch|cabang)\s+[`"\']?([A-Za-z0-9._\/\-]+)[`"\']?/iu', $text, $bm)) {
            $candidate = rtrim($bm[1], '.,/');
            if (!in_array(strtolower($candidate), ['baru', 'new', 'ini', 'itu', 'yang'], true)) {
                $branch = $candidate;
            }
        }

        $remainder = trim(str_replace($matchedUrl, ' ', $text));

        $commitMessage = null;
        if (preg_match('/\b(?:pesan(?:\s+commit)?|commit\s+message|message|msg)\s*[:=]?\s*(["\'`“])(.+?)(?:\1|”)/iu', $remainder, $cm)) {
            $commitMessage = trim($cm[2]);
            $remainder = str_replace($cm[0], ' ', $remainder);
        }
        if ($branch) {
            $remainder = preg_replace('/\b(?:branch|cabang)\s+[`"\']?' . preg_quote($branch, '/') . '[`"\']?/iu', ' ', $remainder);
        }

        return [
            'repo' => $repo,
            'branch' => $branch,
            'commit_message' => $commitMessage !== '' ? $commitMessage : null,
            'push_only' => $this->isOnlyPushRequest($remainder),
            'remainder' => trim(preg_replace('/\s+/', ' ', $remainder)),
        ];
    }

    /**
     * True when nothing but filler words remain once the link is removed,
     * i.e. the message is a pure push command and not also a coding task.
     */
    protected function isOnlyPushRequest(string $remainder): bool
    {
        $words = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($remainder), -1, PREG_SPLIT_NO_EMPTY);
        $meaningful = array_filter($words, fn ($w) => !in_array($w, self::FILLER, true) && mb_strlen($w) > 1);

        return count($meaningful) === 0;
    }
}
