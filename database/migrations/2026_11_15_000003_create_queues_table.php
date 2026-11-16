<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('queues', function (Blueprint $table) {
            $table->id();
            $table->string('patient_name');
            $table->string('poly', 100);
            $table->unsignedInteger('queue_number');
            $table->date('date');
            $table->enum('status', ['waiting', 'called', 'finished', 'cancelled'])->default('waiting');
            $table->timestamp('called_at')->nullable();
            $table->timestamps();

            // Penjaga integritas: nomor tidak boleh kembar per poli per hari,
            // walaupun dua pasien mengambil nomor pada detik yang sama.
            $table->unique(['poly', 'date', 'queue_number']);
            $table->index(['poly', 'date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('queues');
    }
};
