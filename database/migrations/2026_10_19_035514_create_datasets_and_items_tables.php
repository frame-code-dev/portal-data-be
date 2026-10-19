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
        Schema::create('datasets', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title');
            $table->string('value_label')->default('Nilai (rupiah)');
            $table->json('categories')->nullable();
            $table->json('statuses')->nullable();
            $table->timestamps();
        });

        Schema::create('dataset_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dataset_id')->constrained('datasets')->onDelete('cascade');
            $table->string('code')->index();
            $table->string('name');
            $table->string('category')->index();
            $table->string('unit')->index();
            $table->date('date');
            $table->decimal('value', 15, 2)->default(0);
            $table->string('status')->index();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dataset_items');
        Schema::dropIfExists('datasets');
    }
};
