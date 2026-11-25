<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Beranda Pasien - AntriSehat</title>
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
</head>
<body class="min-h-screen bg-slate-50 font-sans text-slate-800 antialiased">
    <header class="flex items-center justify-between bg-teal-900 px-4 py-3 text-white md:px-8">
        <span class="text-lg font-bold tracking-tight">AntriSehat</span>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="rounded-lg border border-white/30 px-4 py-2 text-sm font-medium hover:bg-white/10 focus-visible:outline-2 focus-visible:outline-white">Keluar</button>
        </form>
    </header>

    <main class="mx-auto max-w-2xl p-4 md:p-8">
        <h1 class="text-2xl font-bold tracking-tight text-slate-900">Halo, {{ auth()->user()->name }}</h1>
        <p class="mt-2 text-sm text-slate-600">Kamu masuk sebagai pasien. Fitur pasien belum tersedia di halaman ini.</p>
    </main>
</body>
</html>
