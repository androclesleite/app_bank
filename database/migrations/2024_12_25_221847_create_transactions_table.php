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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->onDelete('cascade'); // Relacionamento com Account
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // Relacionamento com User
            $table->enum('type', ['deposit', 'transfer', 'reverse']); // Tipo de transação
            $table->decimal('amount', 15, 2); // Valor da transação
            $table->foreignId('target_user_id')->nullable()->constrained('users')->onDelete('cascade'); // Relacionamento com o usuário alvo
            $table->unsignedBigInteger('reference_id')->nullable(); // Referência para uma transação
            $table->foreign('reference_id')->references('id')->on('transactions')->onDelete('cascade'); // Chave estrangeira para referência
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
