<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Where the page number sits in the printed report: left, center or right.
     */
    public function up(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->string('page_number_align')->default('right')->after('section_label');
        });
    }

    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->dropColumn('page_number_align');
        });
    }
};
