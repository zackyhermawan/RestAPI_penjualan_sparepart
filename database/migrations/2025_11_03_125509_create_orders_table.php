<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOrdersTable extends Migration
{
    public function up()
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_code')->unique(); // INV-20251103-0001
            $table->unsignedBigInteger('user_id')->nullable(); // guest allowed

            $table->decimal('total_amount', 15, 2);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('shipping_cost', 15, 2)->default(0);
            $table->decimal('grand_total', 15, 2); // total_amount + shipping - discount

            $table->string('order_status')->default('pending'); // pending, paid, processed, shipped, completed, canceled
            $table->string('payment_status')->default('pending'); // pending, paid, expired, failed
            $table->string('shipping_status')->default('waiting_confirmation'); // waiting_confirmation, processing, shipped, delivered

            $table->string('payment_method')->nullable(); // midtrans, xendit, manual_transfer
            $table->string('shipping_method')->nullable(); // jne, jnt, sicepat

            $table->string('midtrans_order_id')->nullable();

            $table->string('customer_name');
            $table->string('customer_phone');
            $table->string('customer_email')->nullable();

            // alamat (lebih fleksibel dibanding JSON)
            $table->string('province');
            $table->string('city');
            $table->string('district');
            $table->string('postal_code', 10);
            $table->text('address_detail');
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::dropIfExists('orders');
    }
}
