<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sender_id'); // ID of the sender (user or vendor)
            $table->string('sender_type'); // 'user' or 'vendor'
            $table->unsignedBigInteger('receiver_id'); // ID of the receiver (user or vendor)
            $table->string('receiver_type'); // 'user' or 'vendor'
            $table->decimal('amount', 10, 2); // Transaction amount
            $table->string('status')->default('pending'); // Transaction status (e.g., pending, completed, failed)
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
