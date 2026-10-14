<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Lumear')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,500;600&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="auth-page min-h-screen text-maroon-900"><nav class="mx-auto flex max-w-7xl items-center justify-between border-b border-maroon-900/10 px-6 py-5"><a class="flex items-center gap-3" href="{{route('shop')}}"><span class="grid h-10 w-10 place-items-center rounded-xl bg-maroon-700 font-display text-xl text-cream-50">L</span><span class="font-display text-3xl italic">Lumear</span></a><div class="flex items-center gap-5"><a class="hidden text-sm font-semibold text-maroon-900/65 hover:text-maroon-700 sm:block" href="{{route('shop')}}">Explore marketplace</a><a class="rounded-full bg-maroon-900 px-5 py-2.5 text-sm font-bold text-cream-50 transition hover:bg-maroon-700" href="{{route('shop')}}">Continue shopping</a></div></nav><main class="auth-grid-bg min-h-[calc(100vh-81px)] px-5 py-9">@if($errors->any())<div class="mx-auto mb-5 max-w-xl rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-900"><ul class="list-disc pl-5">@foreach($errors->all() as $error)<li>{{$error}}</li>@endforeach</ul></div>@endif @yield('content')</main></body>
</html>
