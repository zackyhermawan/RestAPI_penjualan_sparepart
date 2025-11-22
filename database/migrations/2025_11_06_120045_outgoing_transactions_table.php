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
        //
        Schema::create('outgoing_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->integer('qty');
            $table->decimal('price_per_item', 15, 2)->nullable(); // bisa kosong untuk online
            $table->decimal('total_price', 15, 2)->nullable();
            $table->string('type')->default('offline'); // 'offline' / 'online'
            $table->unsignedBigInteger('order_id')->nullable(); // referensi ke order jika online
            $table->unsignedBigInteger('created_by')->nullable(); // admin input offline
            $table->timestamps();

            $table->foreign('product_id')->references('id')->on('products')->onDelete('restrict');
            $table->foreign('order_id')->references('id')->on('orders')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
        Schema::dropIfExists('outgoing_transactions');
    }
};
