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
        Schema::table('sales', function (Blueprint $table) {
            $table->boolean('is_renewal')->default(false)->after('is_starter');
            $table->timestamp('renewal_reminder_h3_sent_at')->nullable()->after('expired_at');
            $table->timestamp('renewal_reminder_h1_sent_at')->nullable()->after('renewal_reminder_h3_sent_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn(['is_renewal', 'renewal_reminder_h3_sent_at', 'renewal_reminder_h1_sent_at']);
        });
    }
};
