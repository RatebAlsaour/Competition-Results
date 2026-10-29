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
        Schema::table('result_imports', function (Blueprint $table) {
            // وضع «تحديث وإضافة»: imported_count = الجدد، updated_count = من حُدّثت بياناتهم
            $table->unsignedInteger('updated_count')->default(0)->after('imported_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('result_imports', function (Blueprint $table) {
            $table->dropColumn('updated_count');
        });
    }
};
