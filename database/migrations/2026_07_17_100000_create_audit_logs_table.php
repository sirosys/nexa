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
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            // Null berarti aksi sistem (scheduler/webhook tanpa sesi login,
            // mis. auto-suspend, auto-cancel invoice) — dibedakan dari staff
            // sungguhan, bukan diasumsikan "unknown".
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            // Format 'modul.aksi', konsisten pola permission di PermissionSeeder.
            $table->string('action')->index();
            $table->nullableMorphs('auditable');
            $table->text('description');
            // Snapshot ringkas old/new value yang relevan, bukan full model diff.
            $table->json('changes')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
