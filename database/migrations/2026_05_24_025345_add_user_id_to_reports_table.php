<?php

use App\Models\Report;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->foreignIdFor(User::class)
                ->nullable()
                ->after('id')
                ->constrained()
                ->cascadeOnDelete();
        });

        $firstUserId = User::query()->orderBy('id')->value('id');

        if ($firstUserId !== null) {
            Report::query()->whereNull('user_id')->update(['user_id' => $firstUserId]);
        }
    }

    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->dropConstrainedForeignIdFor(User::class);
        });
    }
};
