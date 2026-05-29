<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->string('tu_institute')->nullable()->after('tu_college_name');
            $table->string('tu_department')->nullable()->after('tu_institute');
            $table->string('tu_campus_address')->nullable()->after('tu_department');
            $table->string('tu_report_type')->nullable()->after('tu_campus_address');
            $table->string('tu_supervisor_name')->nullable()->after('tu_report_type');
            $table->string('tu_degree')->nullable()->after('tu_supervisor_name');
            $table->json('tu_students')->nullable()->after('tu_degree');
        });
    }

    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->dropColumn([
                'tu_institute',
                'tu_department',
                'tu_campus_address',
                'tu_report_type',
                'tu_supervisor_name',
                'tu_degree',
                'tu_students',
            ]);
        });
    }
};
