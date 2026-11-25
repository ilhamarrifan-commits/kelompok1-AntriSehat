<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk - AntriSehat</title>
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
    <style>
        /* Tiket keluar dari mesin antrean, sekali saat halaman dibuka */
        .ticket { animation: cetak 1.1s cubic-bezier(.2, .8, .2, 1) .2s both; }
        @keyframes cetak { from { transform: translateY(-103%); } to { transform: translateY(0); } }
        .barcode { background: repeating-linear-gradient(90deg, #0f172a 0 2px, transparent 2px 5px, #0f172a 5px 9px, transparent 9px 11px, #0f172a 11px 12px, transparent 12px 16px); }
        @media (prefers-reduced-motion: reduce) { .ticket { animation: none; } }
    </style>
</head>
<body class="min-h-screen bg-white font-sans text-slate-800 antialiased lg:grid lg:grid-cols-[1.1fr_1fr]">

    {{-- Panel kiri: ilustrasi tiket antrean (hanya layar lebar) --}}
    <aside class="hidden flex-col justify-between bg-teal-900 p-12 text-white lg:flex">
        <div class="flex items-center gap-3">
            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-white text-base font-bold text-teal-900">AS</div>
            <span class="text-lg font-bold tracking-tight">AntriSehat</span>
        </div>

        <div class="mx-auto my-10 w-80" aria-hidden="true">
            <div class="relative z-10 h-4 rounded-full bg-slate-950 shadow-lg"></div>
            <div class="-mt-2 overflow-hidden px-5 pb-8">
                <div class="ticket rounded-b-2xl bg-amber-50 text-slate-900 shadow-2xl">
                    <div class="px-7 pb-6 pt-9 text-center">
                        <p class="text-sm font-medium text-slate-600">Nomor antrean</p>
                        <p class="mt-1 text-8xl font-bold leading-none tracking-tighter tabular-nums">012</p>
                        <p class="mt-3 text-lg font-semibold">Poli Umum</p>
                    </div>
                    <div class="relative border-t-2 border-dashed border-slate-300">
                        <span class="absolute -left-3 -top-3 h-6 w-6 rounded-full bg-teal-900"></span>
                        <span class="absolute -right-3 -top-3 h-6 w-6 rounded-full bg-teal-900"></span>
                    </div>
                    <div class="px-7 py-6">
                        <div class="barcode h-10 w-full"></div>
                        <p class="mt-3 text-center text-xs text-slate-500">Contoh tampilan tiket</p>
                    </div>
                </div>
            </div>
        </div>

        <p class="max-w-md text-balance text-3xl font-semibold leading-snug tracking-tight">Antrean dan jadwal klinik di satu tempat.</p>
    </aside>

    {{-- Form masuk --}}
    <main class="flex min-h-screen flex-col justify-center px-6 py-12">
        <div class="mx-auto w-full max-w-sm">
            <div class="mb-10 flex items-center gap-3 lg:hidden">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-teal-700 text-base font-bold text-white">AS</div>
                <span class="text-lg font-bold tracking-tight text-slate-900">AntriSehat</span>
            </div>

            <h1 class="text-3xl font-bold tracking-tight text-slate-900">Masuk ke akun</h1>
            <p class="mt-2 text-sm text-slate-500">Untuk admin klinik dan pasien.</p>

            @if ($errors->any())
                <div id="loginError" role="alert" class="mt-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    {{ $errors->first() }}
                </div>
            @endif

            <form id="loginForm" method="POST" action="{{ route('login.store') }}" class="mt-8 space-y-5">
                @csrf

                <div>
                    <label for="email" class="block text-sm font-medium text-slate-700">Email</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username" placeholder="nama@domain.com"
                        class="mt-2 block w-full rounded-lg border border-slate-300 bg-white px-4 py-3 text-base text-slate-900 placeholder:text-slate-400 focus:border-teal-600 focus:outline-none focus:ring-4 focus:ring-teal-600/15">
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-slate-700">Kata sandi</label>
                    <div class="relative mt-2">
                        <input id="password" name="password" type="password" required autocomplete="current-password"
                            class="block w-full rounded-lg border border-slate-300 bg-white py-3 pl-4 pr-28 text-base text-slate-900 focus:border-teal-600 focus:outline-none focus:ring-4 focus:ring-teal-600/15">
                        <button type="button" id="togglePassword" aria-controls="password" aria-pressed="false"
                            class="absolute inset-y-1 right-1 rounded-md px-3 text-sm font-medium text-teal-700 hover:bg-teal-50 focus-visible:outline-2 focus-visible:outline-teal-600">
                            Tampilkan
                        </button>
                    </div>
                </div>

                <label class="flex items-center gap-2 text-sm text-slate-600">
                    <input type="checkbox" name="remember" value="1" class="h-4 w-4 accent-teal-700" @checked(old('remember'))>
                    Ingat saya di perangkat ini
                </label>

                <button type="submit" id="submitButton"
                    class="w-full rounded-lg bg-teal-700 px-4 py-3 text-base font-semibold text-white transition-colors hover:bg-teal-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700 disabled:cursor-wait disabled:opacity-70">
                    Masuk
                </button>
            </form>
        </div>
    </main>

    <script>
        const password = document.getElementById('password');
        const toggle = document.getElementById('togglePassword');
        const submit = document.getElementById('submitButton');

        toggle.addEventListener('click', () => {
            const show = password.type === 'password';
            password.type = show ? 'text' : 'password';
            toggle.textContent = show ? 'Sembunyikan' : 'Tampilkan';
            toggle.setAttribute('aria-pressed', String(show));
        });

        document.getElementById('loginForm').addEventListener('submit', () => {
            submit.disabled = true;
            submit.textContent = 'Memproses...';
        });

        // Aktifkan lagi tombol saat pengguna kembali lewat tombol back browser
        window.addEventListener('pageshow', () => {
            submit.disabled = false;
            submit.textContent = 'Masuk';
        });
    </script>
</body>
</html>
