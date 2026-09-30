<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>لوحة التحكم — نتائج المسابقات</title>
    <meta name="api-base" content="{{ url('/api') }}">
    <meta name="app-base" content="{{ url('/admin') }}">
    <meta name="portal-url" content="{{ url('/') }}">
    <meta name="theme-color" content="#002623">
    <link rel="icon" type="image/png" href="{{ asset('assets/syrian-logo.png') }}">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@400;500;600;700&display=swap">
    <style>
        @font-face{font-family:'Qomra';src:url('{{ asset('assets/fonts/itfQomraArabic-Medium.woff2') }}') format('woff2');font-weight:500;font-display:swap}
        @font-face{font-family:'Qomra';src:url('{{ asset('assets/fonts/itfQomraArabic-Bold.woff2') }}') format('woff2');font-weight:700;font-display:swap}
    </style>

    @viteReactRefresh
    @vite('resources/js/admin/main.jsx')
</head>
<body>
    <div id="admin"></div>
</body>
</html>
