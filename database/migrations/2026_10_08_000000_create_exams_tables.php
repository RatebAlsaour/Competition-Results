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
        // امتحان (مثال: مسابقة انتقاء التراجمة المحلفين)
        Schema::create('exams', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->text('instructions')->nullable();              // تعليمات للمتقدمين (ما يجب إحضاره...)
            $table->string('status', 20)->default('draft');        // draft | published | archived
            $table->boolean('results_published')->default(false);  // النتائج تظهر للعموم فقط عند تفعيلها
            $table->timestamp('published_at')->nullable();
            $table->string('public_version', 40)->nullable();
            $table->timestamps();

            $table->index('status');
        });

        // جلسة امتحانية: لغة/مادة لها موعد ومكان
        Schema::create('exam_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);                 // اللغة
            $table->date('exam_date')->nullable();
            $table->string('start_time', 5)->nullable(); // HH:MM
            $table->string('end_time', 5)->nullable();
            $table->string('location')->nullable();      // مكان الامتحان
            $table->text('notes')->nullable();           // ملاحظة عامة تظهر للمتقدم
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['exam_id', 'name']);
        });

        // قاعة: مجال من الأرقام الامتحانية داخل الجلسة
        Schema::create('exam_halls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_session_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->unsignedInteger('from_number');
            $table->unsignedInteger('to_number');
            $table->timestamps();

            $table->index(['exam_session_id', 'from_number']);
        });

        Schema::create('examinees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained()->cascadeOnDelete();
            $table->foreignId('exam_session_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('exam_number');      // الرقم الامتحاني (يبدأ من 1 لكل لغة)
            $table->string('file_number', 50)->nullable(); // رقم ملف المتسابق
            $table->string('full_name');
            $table->string('search_key');
            $table->string('hall_name', 100)->nullable(); // قاعة خاصة تتجاوز التوزيع التلقائي
            $table->text('admin_note')->nullable();       // ملاحظة داخلية — لا تُنشر أبداً
            $table->string('result', 10)->nullable();     // passed | failed | absent
            $table->decimal('score', 6, 2)->nullable();
            $table->string('result_note')->nullable();    // ملاحظة تظهر مع النتيجة
            $table->timestamps();

            $table->index(['exam_id', 'search_key']);
            $table->index(['exam_session_id', 'exam_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('examinees');
        Schema::dropIfExists('exam_halls');
        Schema::dropIfExists('exam_sessions');
        Schema::dropIfExists('exams');
    }
};
