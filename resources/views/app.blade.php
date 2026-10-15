<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#7c3aed">
    <title>XZY — Ngoding Bareng AI</title>
    <script>
        // Apply the saved/OS theme before first paint (no light-dark flash)
        (function () {
            try {
                var t = localStorage.getItem('xzy-theme');
                if (t !== 'light' && t !== 'dark') {
                    t = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
                }
                if (t === 'dark') document.documentElement.classList.add('dark');
                document.documentElement.style.colorScheme = t;
            } catch (e) {}
        })();
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Space+Grotesk:wght@500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.ts'])
</head>
<body class="antialiased font-sans min-h-screen">
    <div id="app"></div>
</body>
</html>
