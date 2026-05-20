<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('references', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            $table->json('data');
            $table->timestamps();

            $table->index(['report_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('references');
    }
};
