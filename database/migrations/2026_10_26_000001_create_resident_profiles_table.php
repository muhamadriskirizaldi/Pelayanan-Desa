<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('resident_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')
                ->constrained('users')
                ->cascadeOnDelete()
                ->unique();

            // Identitas Kependudukan (Sensitif)
            $table->char('nik', 16)->unique()->index();
            $table->char('no_kk', 16)->index();

            // Data Demografis
            $table->string('tempat_lahir', 100);
            $table->date('tanggal_lahir');
            $table->string('jenis_kelamin', 20); // Laki-Laki / Perempuan

            // Alamat Domisili Lengkap
            $table->text('alamat');
            $table->string('rt', 5)->nullable();
            $table->string('rw', 5)->nullable();
            $table->string('desa', 100);
            $table->string('kecamatan', 100);
            $table->string('kabupaten', 100);
            $table->string('provinsi', 100);
            $table->string('kode_pos', 10)->nullable();

            // Metadata Tambahan Administrasi
            $table->string('agama', 30)->nullable();
            $table->string('pekerjaan', 100)->nullable();
            $table->string('status_perkawinan', 50)->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('resident_profiles');
    }
};
