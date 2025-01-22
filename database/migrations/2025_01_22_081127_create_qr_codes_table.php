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
        Schema::create('qr_codes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable(); // ID of the user who generated the QR code
            $table->unsignedBigInteger('vendor_id')->nullable(); // ID of the vendor who generated the QR code
            $table->string('qr_code'); // Unique QR code string
            $table->decimal('amount', 10, 2); // Amount associated with the QR code
            $table->string('status')->default('active'); // QR code status (e.g., active, used)
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('qr_codes');
    }
};
