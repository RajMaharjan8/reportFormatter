<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add the section placement: 'body' for numbered report sections,
     * 'front' for custom front-matter pages shown before the contents.
     */
    public function up(): void
    {
        Schema::table('sections', function (Blueprint $table) {
            $table->string('placement')->default('body')->after('report_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sections', function (Blueprint $table) {
            $table->dropColumn('placement');
        });
    }
};
