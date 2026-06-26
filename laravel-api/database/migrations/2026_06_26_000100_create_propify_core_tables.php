<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('role');
            $table->string('phone', 11)->index();
            $table->string('national_id')->nullable();
            $table->string('stage')->default('عميل محتمل');
            $table->string('source')->nullable();
            $table->timestamps();
        });

        Schema::create('properties', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('type');
            $table->string('mode');
            $table->string('province')->default('بغداد');
            $table->string('area')->index();
            $table->decimal('space', 12, 2);
            $table->unsignedInteger('rooms')->default(0);
            $table->decimal('price', 15, 2);
            $table->string('status')->default('قيد المراجعة')->index();
            $table->string('owner');
            $table->boolean('negotiable')->default(true);
            $table->timestamps();
        });

        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('property_code')->index();
            $table->string('client')->index();
            $table->string('kind');
            $table->decimal('total', 15, 2);
            $table->decimal('paid', 15, 2)->default(0);
            $table->decimal('due', 15, 2)->default(0);
            $table->decimal('commission', 15, 2)->default(0);
            $table->string('status')->default('نشط')->index();
            $table->timestamps();
        });

        Schema::create('installments', function (Blueprint $table) {
            $table->id();
            $table->string('contract_code')->index();
            $table->unsignedInteger('number');
            $table->date('due_date');
            $table->decimal('amount', 15, 2);
            $table->decimal('paid_amount', 15, 2)->default(0);
            $table->string('status')->default('بانتظار')->index();
            $table->timestamps();
        });

        Schema::create('vouchers', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('type')->index();
            $table->string('client')->index();
            $table->decimal('amount', 15, 2);
            $table->string('reason');
            $table->string('property_code')->nullable();
            $table->string('contract_code')->nullable();
            $table->date('issued_at');
            $table->timestamps();
        });

        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('direction')->index();
            $table->decimal('amount', 15, 2);
            $table->string('description');
            $table->date('entry_date');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_entries');
        Schema::dropIfExists('vouchers');
        Schema::dropIfExists('installments');
        Schema::dropIfExists('contracts');
        Schema::dropIfExists('properties');
        Schema::dropIfExists('clients');
    }
};
