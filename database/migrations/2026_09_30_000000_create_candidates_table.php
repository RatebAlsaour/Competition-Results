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
        Schema::create('candidates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competition_id')->constrained()->cascadeOnDelete();
            $table->string('full_name');
            $table->string('search_key');                // الاسم بعد توحيد الأحرف للبحث
            $table->string('governorate', 100);
            $table->string('job_title', 150);
            $table->date('interview_date')->nullable();
            $table->decimal('score', 5, 2)->nullable();
            $table->string('status', 10);                // primary | reserve
            $table->unsignedInteger('rank')->default(0); // الترتيب داخل (المحافظة + المسمى)
            $table->unsignedInteger('source_row')->nullable();
            $table->string('follow_up_status', 30)->default('pending');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['competition_id', 'governorate', 'job_title', 'rank']);
            $table->index(['competition_id', 'search_key']);
            $table->index(['competition_id', 'follow_up_status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('candidates');
    }
};
