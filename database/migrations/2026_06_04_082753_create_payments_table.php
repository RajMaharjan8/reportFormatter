<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('report_id')->constrained()->cascadeOnDelete();
            $table->string('gateway'); // esewa | khalti
            $table->string('mode'); // test | live
            $table->decimal('amount', 10, 2);
            $table->string('transaction_uuid')->unique(); // our id (eSewa transaction_uuid / Khalti purchase_order_id)
            $table->string('pidx')->nullable(); // Khalti payment identifier
            $table->string('ref_id')->nullable(); // gateway transaction reference
            $table->string('status')->default('pending'); // pending | completed | failed | canceled
            $table->timestamp('consumed_at')->nullable(); // set when the paid download is taken
            $table->json('meta')->nullable(); // raw gateway response
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
