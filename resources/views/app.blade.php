<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name') }}</title>
    <script>
        // Apply the saved language before the app mounts to avoid a direction flash.
        (function () {
            try {
                var locale = localStorage.getItem('locale') === 'en' ? 'en' : 'ar';
                document.documentElement.lang = locale;
                document.documentElement.dir = locale === 'ar' ? 'rtl' : 'ltr';
                if (localStorage.getItem('theme') === 'dark') document.documentElement.classList.add('dark');
            } catch (e) {}
        })();
    </script>
    @php
        $appConfig = [
            'name' => config('app.name'),
            'env' => app()->environment(),
            'currency' => config('app.currency'),
            'demo' => [
                'password' => 'password',
                'accounts' => [
                    ['email' => 'demo@example.com', 'role' => 'admin'],
                    ['email' => 'staff@example.com', 'role' => 'staff'],
                    ['email' => 'viewer@example.com', 'role' => 'viewer'],
                ],
            ],
        ];
    @endphp
    <script>window.__APP__ = @json($appConfig);</script>
    @vite(['resources/css/app.css', 'resources/js/app.ts'])
</head>
<body class="bg-slate-50 text-slate-800 antialiased dark:bg-slate-950 dark:text-slate-200">
    <div id="app"></div>
</body>
</html>
