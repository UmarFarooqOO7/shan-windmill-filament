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
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('rid')->nullable();
            $table->string('plaintiff')->nullable();
            $table->string('defendant_first_name')->nullable();
            $table->string('defendant_last_name')->nullable();
            $table->string('address')->nullable();
            $table->string('county')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('zip')->nullable();
            $table->string('case_number')->nullable();
            $table->date('setout_date')->nullable();
            $table->string('setout_time')->nullable();
            $table->string('status')->nullable();
            $table->string('setout')->nullable();
            $table->string('writ')->nullable();



            $table->string('lbx')->nullable();
            $table->decimal('amount_owed', 15, 2)->nullable();
            $table->decimal('amount_cleared', 15, 2)->nullable();



            $table->string('vis_setout')->nullable();
            $table->string('vis_to')->nullable();
            $table->text('notes')->nullable();
            $table->string('time_on')->nullable();
            $table->string('setout_st')->nullable();
            $table->string('setout_en')->nullable();
            $table->string('time_en')->nullable();
            $table->string('locs')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};