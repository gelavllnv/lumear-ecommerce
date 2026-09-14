<!doctype html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Lumear - beautifully considered pieces for everyday life.">
    <title>@yield('title', 'Lumear - Things made to be kept')</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:ital,wght@0,500;0,600;1,500;1,600&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: { ink: '#3a0e17', wine: '#8f142e', paper: '#f7eedc', sand: '#e8d5af' },
                    fontFamily: { display: ['Playfair Display', 'serif'], body: ['DM Sans', 'sans-serif'] },
                },
            },
        };
    </script>
    <style>
        body { font-family: 'DM Sans', sans-serif; }
        .font-display { font-family: 'Playfair Display', serif; }
        .product-image { transition: transform 600ms cubic-bezier(.16, 1, .3, 1); }
        .product-card:hover .product-image { transform: scale(1.045); }
        :focus-visible { outline: 2px solid #8f142e; outline-offset: 3px; }
    </style>
</head>
<body class="bg-paper text-ink antialiased">
    @yield('content')
</body>
</html>
