<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A word used to label sections (e.g. "Chapter" → "Chapter 1").
     * When null, sections are numbered plainly: "1.", "2." …
     */
    public function up(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->string('section_label')->nullable()->after('abstract');
        });
    }

    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->dropColumn('section_label');
        });
    }
};
