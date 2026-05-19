<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The cover fields that drafts may leave blank.
     *
     * @var list<string>
     */
    protected array $columns = [
        'module_code',
        'module_title',
        'student_name',
        'london_id',
        'college_id',
    ];

    /**
     * Allow partially filled reports to be saved as drafts.
     */
    public function up(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            foreach ($this->columns as $column) {
                $table->string($column)->nullable()->change();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            foreach ($this->columns as $column) {
                $table->string($column)->nullable(false)->change();
            }
        });
    }
};
