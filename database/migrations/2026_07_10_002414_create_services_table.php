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
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('code')->nullable()->unique();
            $table->string('pin', 6);
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->text('address');
            $table->string('residential_name')->nullable();
            $table->foreignId('subdistrict_id')->constrained('subdistricts')->restrictOnDelete();
            $table->string('rw')->nullable();
            $table->string('rt')->nullable();
            $table->foreignId('coverage_id')->constrained('coverages')->restrictOnDelete();
            $table->foreignId('package_id')->constrained('packages')->restrictOnDelete();
            // Kolom enum eksplisit (bukan dihitung dari kombinasi
            // timestamp) — lihat App\Models\Service::STATUSES.
            $table->string('status')->default('pending_payment');
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->timestamp('dismantled_at')->nullable();
            $table->timestamp('canceled_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
