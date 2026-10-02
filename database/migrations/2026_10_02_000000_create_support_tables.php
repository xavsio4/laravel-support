<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_documents', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title');
            $table->string('url')->nullable();
            $table->longText('content');
            $table->string('checksum', 64);
            $table->unsignedInteger('tokens');
            $table->timestamps();
        });

        Schema::create('support_conversations', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            // A string, so the host app's key type (int, uuid, ulid) does not matter.
            $table->string('user_id')->index();
            $table->string('user_email');
            $table->string('user_name')->nullable();
            $table->string('locale', 10)->nullable();
            $table->text('page_url')->nullable();
            $table->string('status', 20)->default('ai');
            $table->unsignedBigInteger('freescout_conversation_id')->nullable()->index();
            $table->timestamp('escalated_at')->nullable();
            $table->timestamps();
        });

        Schema::create('support_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('support_conversations')->cascadeOnDelete();
            $table->string('role', 20);
            $table->text('content')->nullable();
            $table->jsonb('citations')->nullable();
            $table->string('status', 20)->default('done');
            $table->boolean('declined')->default(false);
            $table->jsonb('usage')->nullable();
            $table->text('error')->nullable();
            // Agent replies arrive by webhook, possibly more than once.
            $table->unsignedBigInteger('freescout_thread_id')->nullable()->unique();
            $table->timestamps();

            $table->index(['conversation_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_messages');
        Schema::dropIfExists('support_conversations');
        Schema::dropIfExists('support_documents');
    }
};
