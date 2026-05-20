<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add cover format selection and the Tribhuvan University cover fields.
     */
    public function up(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->string('cover_format')->default('london_met')->after('id');
            $table->string('tu_college_name')->nullable()->after('cover_format');
            $table->string('tu_roll_number', 50)->nullable()->after('tu_college_name');
            $table->string('tu_submitted_to_position')->nullable()->after('tu_roll_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->dropColumn([
                'cover_format',
                'tu_college_name',
                'tu_roll_number',
                'tu_submitted_to_position',
            ]);
        });
    }
};
