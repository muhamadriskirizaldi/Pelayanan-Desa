<?php

use App\Enums\SktmStatus;
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
        Schema::create('sktm_applications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('nomor_pengajuan', 50)->unique()->index();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();

            // Identitas Pemohon (Snapshot pada saat pengajuan)
            $table->string('nama_pemohon');
            $table->char('nik', 16)->index();
            $table->char('no_kk', 16)->index();
            $table->text('alamat');
            $table->string('rt', 5)->nullable();
            $table->string('rw', 5)->nullable();
            $table->string('desa', 100)->default('Sukamaju');
            $table->string('kecamatan', 100)->default('Cibinong');
            $table->string('kabupaten', 100)->default('Kabupaten Bogor');
            $table->string('provinsi', 100)->default('Jawa Barat');

            // Keterangan Permohonan & Kondisi Ekonomi
            $table->string('keperluan');
            $table->string('pekerjaan', 100)->nullable();
            $table->unsignedBigInteger('penghasilan_bulanan')->nullable();
            $table->unsignedTinyInteger('jumlah_tanggungan')->default(1);
            $table->text('kondisi_ekonomi');

            // Berkas Dokumen Persyaratan (Tersimpan di Private Storage)
            $table->string('dokumen_ktp_path')->nullable();
            $table->string('dokumen_ktp_original_name')->nullable();
            $table->unsignedInteger('dokumen_ktp_size')->nullable(); // Dalam bytes

            $table->string('dokumen_kk_path')->nullable();
            $table->string('dokumen_kk_original_name')->nullable();
            $table->unsignedInteger('dokumen_kk_size')->nullable();

            $table->string('dokumen_pendukung_path')->nullable();
            $table->string('dokumen_pendukung_original_name')->nullable();
            $table->unsignedInteger('dokumen_pendukung_size')->nullable();

            // Status dan Metadata Alur Kerja
            $table->string('status', 30)->default(SktmStatus::DRAFT->value)->index();
            $table->timestamp('tanggal_pengajuan')->nullable()->index();
            $table->text('catatan')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sktm_applications');
    }
};
