<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sample_semphony_user', function (Blueprint $table): void {
            $table->foreignId('sample_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['sample_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sample_semphony_user');
    }
};
