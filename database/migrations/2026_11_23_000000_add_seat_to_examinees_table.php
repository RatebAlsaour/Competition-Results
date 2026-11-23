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
        Schema::table('examinees', function (Blueprint $table) {
            // من ملف التوزيع: بناء القاعة الخاصة، والصف والمقعد داخل القاعة
            $table->string('building', 100)->nullable()->after('hall_name');
            $table->string('seat_row', 20)->nullable()->after('building');
            $table->string('seat_number', 20)->nullable()->after('seat_row');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('examinees', function (Blueprint $table) {
            $table->dropColumn(['building', 'seat_row', 'seat_number']);
        });
    }
};
