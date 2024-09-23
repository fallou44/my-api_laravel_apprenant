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
        Schema::create('apprenants', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id'); // Clé étrangère pour l'utilisateur
            $table->string('nom_tuteur');
            $table->string('prenom_tuteur');
            $table->text('photocopie_cni')->nullable();
            $table->text('diplome')->nullable();
            $table->string('contact_tuteur')->nullable();
            $table->text('extrait_naissance')->nullable();
            $table->text('casier_judiciaire')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('apprenants');
    }
};
