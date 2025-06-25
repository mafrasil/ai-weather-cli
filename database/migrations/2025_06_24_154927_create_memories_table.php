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
        Schema::create('memories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('key'); // Semantic keys like 'home_location', 'preferred_units', 'favorite_cuisine'
            $table->json('value'); // The actual data stored as JSON
            $table->json('context')->nullable(); // Additional context about when/how this was learned
            $table->timestamps();

            $table->index(['user_id', 'key']); // Simple index for lookups
            $table->unique(['user_id', 'key']); // Ensure no duplicate keys per user
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('memories');
    }
};
