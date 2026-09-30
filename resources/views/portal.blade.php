<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>نتائج المسابقات — وزارة العدل</title>
    <meta name="description" content="الاستعلام عن أسماء المقبولين في مسابقات وزارة العدل، مديرية التنمية الإدارية.">
    <meta name="data-base" content="{{ asset('data') }}">
    <meta name="theme-color" content="#002623">
    <meta property="og:title" content="نتائج المسابقات — وزارة العدل">
    <meta property="og:description" content="الاستعلام عن أسماء المقبولين حسب المحافظة والمسمى الوظيفي.">
    <meta property="og:image" content="{{ asset('assets/syrian-logo.png') }}">
    <link rel="icon" type="image/png" href="{{ asset('assets/syrian-logo.png') }}">
    <base href="{{ url('/') }}/">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@400;500;600;700&display=swap">
    <link rel="preload" href="{{ asset('assets/fonts/itfQomraArabic-Bold.woff2') }}" as="font" type="font/woff2" crossorigin>

    <style>
        @font-face{font-family:'Qomra';src:url('{{ asset('assets/fonts/itfQomraArabic-Regular.woff2') }}') format('woff2');font-weight:400;font-display:swap}
        @font-face{font-family:'Qomra';src:url('{{ asset('assets/fonts/itfQomraArabic-Medium.woff2') }}') format('woff2');font-weight:500;font-display:swap}
        @font-face{font-family:'Qomra';src:url('{{ asset('assets/fonts/itfQomraArabic-Bold.woff2') }}') format('woff2');font-weight:700;font-display:swap}
        @font-face{font-family:'Qomra';src:url('{{ asset('assets/fonts/itfQomraArabic-Black.woff2') }}') format('woff2');font-weight:900;font-display:swap}
        *{box-sizing:border-box}
        html,body{margin:0;background:#EDEBE0;color:#002623;font-family:'IBM Plex Sans Arabic','Noto Sans Arabic',sans-serif;-webkit-font-smoothing:antialiased;overflow-x:hidden}
        button,input{font-family:inherit}
        a{color:#054239}
        a:hover{color:#428177}
        ::selection{background:#B9A779;color:#002623}
        :focus-visible{outline:2px solid #988561;outline-offset:2px}
        input::placeholder{color:#6B736F}
        .sr-only{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap}

        /* حالات التمرير والتركيز (تتغلب على الأنماط المضمّنة) */
        .h-light:hover{color:#EDEBE0!important}
        .h-gold:hover{background:rgba(185,167,121,.12)!important;border-color:#B9A779!important;color:#EDEBE0!important}
        .h-dark:hover{background:#054239!important}
        .h-dark:active{transform:scale(.985)}
        .h-outline:hover{background:#002623!important;color:#EDEBE0!important}
        .h-link:hover{color:#428177!important}
        .h-clear:hover{background:#E6E1D2!important}
        .h-row:hover{background:#F1EEE3!important}
        .h-name:hover{color:#054239!important;text-decoration-color:#988561!important}
        .h-pager:hover:not(:disabled){border-color:#988561!important}
        .h-pager:disabled{cursor:default!important}
        .h-close:hover{background:rgba(185,167,121,.15)!important}
        .h-doc:hover{border-color:#B9A779!important}
        .f-input:focus{border-color:#988561!important;background:#FFFFFF!important}
        .f-search:focus{border-color:#988561!important;box-shadow:0 0 0 3px rgba(185,167,121,.3)!important}

        @keyframes sp-out{0%,88%{opacity:1}100%{opacity:0;visibility:hidden}}
        @keyframes sp-bg{0%{opacity:0}20%,100%{opacity:1}}
        @keyframes sp-pat{0%,6%{opacity:0}30%,100%{opacity:.11}}
        @keyframes sp-logo{0%{opacity:0;transform:scale(.9)}22%{opacity:1;transform:scale(1)}60%,86%{opacity:1;transform:scale(1.015)}100%{opacity:0;transform:scale(1.03)}}
        @keyframes sp-sweep{0%,24%{opacity:0;background-position:110% 0}30%{opacity:1}54%{opacity:1;background-position:-10% 0}60%,100%{opacity:0;background-position:-10% 0}}
        @keyframes sp-line{0%,28%{opacity:0;transform:scaleX(0)}52%,100%{opacity:1;transform:scaleX(1)}}
        @keyframes sp-t1{0%,60%{opacity:0;transform:translateY(8px)}70%,100%{opacity:1;transform:none}}
        @keyframes sp-t2{0%,64%{opacity:0;transform:translateY(8px)}74%,100%{opacity:1;transform:none}}
        @keyframes sp-t3{0%,68%{opacity:0;transform:translateY(8px)}78%,100%{opacity:1;transform:none}}
        @keyframes sp-barwrap{0%,78%{opacity:0}82%,100%{opacity:1}}
        @keyframes sp-bar{0%,80%{transform:scaleX(0)}97%,100%{transform:scaleX(1)}}
        @keyframes dd-in{from{opacity:0;transform:translateY(-4px)}to{opacity:1;transform:none}}
        @keyframes fade-up{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:none}}
        @keyframes shimmer{from{background-position:100% 0}to{background-position:-100% 0}}
        @keyframes spin{to{transform:rotate(360deg)}}
        @media (prefers-reduced-motion: reduce){
            [data-anim]{animation:none !important}
            *,*::before,*::after{transition-duration:.01ms !important;animation-duration:.01ms !important;animation-iteration-count:1 !important;scroll-behavior:auto !important}
        }
    </style>

    @viteReactRefresh
    @vite('resources/js/portal/main.jsx')
</head>
<body>
    <div id="app"></div>
    <noscript>
        <p style="padding:24px;text-align:center">يتطلب عرض النتائج تفعيل JavaScript في المتصفح.</p>
    </noscript>
</body>
</html>
