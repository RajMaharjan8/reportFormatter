<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add the report title (shown on the title page) and the abstract.
     */
    public function up(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->string('title')->nullable()->after('module_title');
            $table->longText('abstract')->nullable()->after('title');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->dropColumn(['title', 'abstract']);
        });
    }
};
