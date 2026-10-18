<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_unavailable_dates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
            $table->date('unavailable_date');
            $table->string('reason', 500)->nullable();
            $table->timestamps();

            $table->unique(['service_id', 'unavailable_date']);
            $table->index(['service_id', 'unavailable_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_unavailable_dates');
    }
};
