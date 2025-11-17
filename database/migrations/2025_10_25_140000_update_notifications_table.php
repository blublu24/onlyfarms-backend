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
        Schema::table('notifications', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->string('type')->nullable()->after('user_id');
            $table->string('title')->nullable()->after('type');
            $table->text('message')->nullable()->after('title');
            $table->json('data')->nullable()->after('message');
            $table->boolean('is_read')->default(false)->after('data');
            $table->timestamp('read_at')->nullable()->after('is_read');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn([
                'user_id',
                'type',
                'title',
                'message',
                'data',
                'is_read',
                'read_at',
            ]);
        });
    }
};

