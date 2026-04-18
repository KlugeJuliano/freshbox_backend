<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('customer_name', 80);
            $table->string('customer_phone', 20);
            $table->string('delivery_type', 20);
            $table->string('delivery_street')->nullable();
            $table->string('delivery_number', 30)->nullable();
            $table->string('delivery_complement')->nullable();
            $table->string('delivery_neighborhood')->nullable();
            $table->string('delivery_city')->nullable();
            $table->string('delivery_zip', 9)->nullable();
            $table->decimal('subtotal', 10, 2);
            $table->decimal('delivery_fee', 10, 2)->default(0);
            $table->decimal('total', 10, 2);
            $table->string('payment_method', 40)->nullable();
            $table->text('observations')->nullable();
            $table->string('status', 20)->default('new');
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'status', 'created_at']);
            $table->index(['company_id', 'store_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
