<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            // Margins are stored in inches. The defaults follow the standard
            // rule: 1.0" top, 1.0" right, 1.0" bottom, 1.5" left (binding).
            $table->decimal('margin_top', 4, 2)->default(1.00)->after('reference_format');
            $table->decimal('margin_right', 4, 2)->default(1.00)->after('margin_top');
            $table->decimal('margin_bottom', 4, 2)->default(1.00)->after('margin_right');
            $table->decimal('margin_left', 4, 2)->default(1.50)->after('margin_bottom');
            $table->decimal('line_spacing', 4, 2)->default(1.15)->after('margin_left');
        });
    }

    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->dropColumn(['margin_top', 'margin_right', 'margin_bottom', 'margin_left', 'line_spacing']);
        });
    }
};
