<?php

return [

    /*
    | Zona waktu klinik. Dipakai untuk menentukan "hari ini" pada nomor antrean,
    | validasi janji temu, dan jadwal reset harian — sehingga logikanya benar
    | walaupun APP_TIMEZONE server masih UTC.
    */
    'timezone' => env('CLINIC_TIMEZONE', 'Asia/Jakarta'),

    // Kuota default pasien per sesi dokter.
    'default_max_quota' => 30,

    /*
    | Token sederhana untuk endpoint staf/admin (header "X-Staff-Token" atau
    | "Authorization: Bearer <token>"). Kosong = terbuka di local/testing,
    | TERTUTUP di production.
    */
    'staff_token' => env('STAFF_API_TOKEN'),

    // Opsional: hapus antrean yang lebih lama dari N hari saat reset harian.
    'queue_retention_days' => env('QUEUE_RETENTION_DAYS'),
];
