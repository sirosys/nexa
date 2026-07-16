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
        Schema::create('pops', function (Blueprint $table) {
            $table->id();
            $table->string('code')->nullable()->unique();
            $table->string('name');
            $table->foreignId('subdistrict_id')->constrained('subdistricts')->restrictOnDelete();
            $table->string('serial')->nullable();
            $table->string('model')->nullable();
            $table->string('location')->nullable();
            $table->text('token')->nullable();
            // Alamat router untuk integrasi MikroTik (lihat CLAUDE.md
            // "Integrasi MikroTik") — token (di atas, encrypted) dipakai
            // sebagai password Basic Auth, tidak ada kolom password baru.
            $table->string('host')->nullable();
            $table->unsignedSmallInteger('api_port')->nullable();
            $table->string('api_username')->nullable();
            $table->timestamp('last_online_at')->nullable();
            // Kolom enum eksplisit (bukan disimpulkan dari last_online_at),
            // diisi scheduled command monitoring:check-pop-status.
            $table->string('status')->default('unknown');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pops');
    }
};
