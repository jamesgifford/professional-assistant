<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_blocklist', function (Blueprint $table) {
            $table->id();
            $table->string('phone_number')->unique()->index();
            $table->string('reason')->default('opt_out');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_blocklist');
    }
};
