<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('harvests', function (Blueprint $t) {
            $t->id();
            $t->foreignId('crop_schedule_id')->constrained('crop_schedules')->cascadeOnDelete();
            $t->dateTime('harvested_at');
            $t->decimal('yield_qty', 12, 2);
            $t->string('yield_unit'); // kg,g,bunch,tray,piece
            $t->string('grade')->nullable(); // A,B,C,reject
            $t->decimal('waste_qty', 12, 2)->nullable();
            $t->decimal('moisture_pct', 5, 2)->nullable();
            $t->string('lot_code')->unique();

            $t->boolean('verified')->default(false);
            $t->timestamp('verified_at')->nullable();
            $t->foreignId('verified_by')->nullable()->constrained('admins');

            $t->boolean('published')->default(false);
            $t->timestamp('published_at')->nullable();
            $t->unsignedBigInteger('product_id')->nullable(); // matches products.product_id (custom PK)

            $t->string('created_by_type'); // seller|admin
            $t->unsignedBigInteger('created_by_id');
            $t->json('photos_json')->nullable();

            $t->softDeletes();
            $t->timestamps();

            // Enforce one active (non-deleted) harvest per schedule
            $t->unique(['crop_schedule_id', 'deleted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('harvests');
    }
};
