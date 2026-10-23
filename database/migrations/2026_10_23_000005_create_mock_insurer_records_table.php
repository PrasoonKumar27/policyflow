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
        Schema::create('mock_insurer_records', function (Blueprint $table) {
            $table->id();
            $table->string('provider_reference')->unique();
            $table->string('client_request_id')->index();
            $table->string('agent_id')->index();
            $table->string('policy_number')->nullable()->unique();
            $table->string('status'); // ISSUED, PENDING, FAILED
            $table->string('scenario'); // SUCCESS, TIMEOUT_AFTER_ISSUE, DELAYED_CALLBACK, etc.
            $table->decimal('premium', 10, 2);
            $table->json('payload')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mock_insurer_records');
    }
};
