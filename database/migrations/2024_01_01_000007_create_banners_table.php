<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('banners', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('company_id')->constrained()->cascadeOnDelete();
            $table->string('title', 80)->nullable();
            $table->string('subtitle', 120)->nullable();
            $table->string('image_url');
            $table->string('image_mobile_url')->nullable();
            $table->string('link_type', 20)->nullable()->default('none');
            $table->string('link_value')->nullable();
            $table->unsignedInteger('priority')->default(0);
            $table->timestamp('period_start')->nullable();
            $table->timestamp('period_end')->nullable();
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();

            $table->index(['company_id', 'is_active', 'priority']);
            $table->index(['company_id', 'period_start', 'period_end']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banners');
    }
};
