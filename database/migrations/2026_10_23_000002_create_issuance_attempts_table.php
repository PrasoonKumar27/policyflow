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
        Schema::create('issuance_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('policy_request_id')->constrained('policy_requests')->cascadeOnDelete();
            $table->unsignedInteger('attempt_number');
            $table->string('scenario')->nullable();
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->string('status'); // SUCCESS, FAILED, TIMEOUT, PENDING
            $table->integer('http_status')->nullable();
            $table->text('error_message')->nullable();
            $table->string('provider_reference')->nullable();
            $table->timestamps();

            $table->index(['policy_request_id', 'attempt_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('issuance_attempts');
    }
};
