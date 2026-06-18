<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('programme_branches', function (Blueprint $table): void {
            $table->id();
            $table->string('programme_name');
            $table->string('branch_name');
            $table->boolean('is_custom')->default(true);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['programme_name', 'branch_name', 'is_custom']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('programme_branches');
    }
};
