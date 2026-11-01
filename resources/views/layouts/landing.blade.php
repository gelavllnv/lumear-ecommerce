<!doctype html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Lumear is a Philippine marketplace for considered pieces and everyday discoveries.">
    <title>@yield('title', 'Lumear — Discover more, beautifully')</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:ital,wght@0,500;0,600;1,500;1,600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/landing.css') }}">
    <script defer src="{{ asset('js/landing-carousel.js') }}"></script>
</head>
<body class="landing-body">
    @yield('content')
</body>
</html>
