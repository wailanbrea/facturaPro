<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('permissions', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('permission_role', function (Blueprint $table): void {
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['permission_id', 'role_id']);
        });

        Schema::create('role_user', function (Blueprint $table): void {
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['role_id', 'user_id']);
        });

        Schema::create('clients', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->index();
            $table->string('tax_id')->nullable()->index();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable()->index();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('currencies', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->char('code', 3)->unique();
            $table->string('symbol', 8);
            $table->string('decimal_separator', 4)->default('.');
            $table->string('thousand_separator', 4)->default(',');
            $table->unsignedTinyInteger('decimal_places')->default(2);
            $table->enum('symbol_position', ['before', 'after'])->default('before');
            $table->boolean('is_default')->default(false)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('taxes', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->decimal('rate', 7, 4);
            $table->boolean('is_default')->default(false)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('payment_terms', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->unsignedSmallInteger('days')->default(0);
            $table->text('description')->nullable();
            $table->boolean('is_default')->default(false)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('warranties', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('duration_months')->nullable();
            $table->text('full_text');
            $table->boolean('is_default')->default(false)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('fiscal_profiles', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('tax_id')->nullable()->index();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('logo_path')->nullable();
            $table->boolean('is_default')->default(false)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('bank_accounts', function (Blueprint $table): void {
            $table->id();
            $table->string('label');
            $table->string('account_holder');
            $table->string('bank_name');
            $table->string('account_number')->nullable();
            $table->string('iban')->nullable();
            $table->string('swift')->nullable();
            $table->foreignId('currency_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_default')->default(false)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('legal_texts', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->text('legal_footer')->nullable();
            $table->text('warranty_text')->nullable();
            $table->text('conformity_text')->nullable();
            $table->text('client_copy_text')->nullable();
            $table->text('seller_copy_text')->nullable();
            $table->boolean('is_default')->default(false)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('invoice_number_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('prefix')->default('FAC-');
            $table->unsignedBigInteger('next_number')->default(1);
            $table->unsignedTinyInteger('number_length')->default(6);
            $table->string('serie')->nullable();
            $table->boolean('reset_yearly')->default(false);
            $table->boolean('reset_monthly')->default(false);
            $table->boolean('allow_manual_number')->default(false);
            $table->unsignedSmallInteger('current_year')->nullable();
            $table->unsignedTinyInteger('current_month')->nullable();
            $table->timestamps();
        });

        Schema::create('settings', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->json('value')->nullable();
            $table->string('group')->default('general')->index();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('invoices', function (Blueprint $table): void {
            $table->id();
            $table->string('invoice_number')->nullable()->unique();
            $table->date('invoice_date')->index();
            $table->date('due_date')->nullable()->index();
            $table->foreignId('payment_term_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->string('client_name');
            $table->string('client_tax_id')->nullable();
            $table->string('client_address')->nullable();
            $table->foreignId('currency_id')->constrained()->restrictOnDelete();
            $table->char('currency_code', 3);
            $table->string('currency_symbol', 8);
            $table->string('currency_decimal_separator', 4);
            $table->string('currency_thousand_separator', 4);
            $table->unsignedTinyInteger('currency_decimal_places');
            $table->enum('currency_symbol_position', ['before', 'after']);
            $table->foreignId('fiscal_profile_id')->nullable()->constrained()->nullOnDelete();
            $table->string('seller_name')->nullable();
            $table->string('seller_tax_id')->nullable();
            $table->string('seller_address')->nullable();
            $table->string('seller_city')->nullable();
            $table->foreignId('bank_account_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('warranty_id')->nullable()->constrained()->nullOnDelete();
            $table->text('warranty_text')->nullable();
            $table->text('legal_text')->nullable();
            $table->text('observations')->nullable();
            $table->decimal('amount_received', 15, 4)->default(0);
            $table->decimal('subtotal', 15, 4)->default(0);
            $table->decimal('tax_total', 15, 4)->default(0);
            $table->decimal('total', 15, 4)->default(0);
            $table->decimal('balance_due', 15, 4)->default(0);
            $table->string('status', 32)->default('draft')->index();
            $table->string('prepared_by')->nullable();
            $table->string('received_by')->nullable();
            $table->string('customer_signature_path')->nullable();
            $table->timestamp('customer_accepted_at')->nullable();
            $table->string('pdf_path')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['client_id', 'invoice_date']);
            $table->index(['status', 'due_date']);
            $table->index(['currency_code', 'invoice_date']);
        });

        Schema::create('invoice_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->text('description');
            $table->decimal('quantity', 15, 4);
            $table->decimal('unit_cost', 15, 4)->default(0);
            $table->foreignId('tax_id')->nullable()->constrained()->nullOnDelete();
            $table->string('tax_name')->nullable();
            $table->decimal('tax_rate', 7, 4)->default(0);
            $table->decimal('tax_amount', 15, 4)->default(0);
            $table->decimal('unit_price', 15, 4)->default(0);
            $table->decimal('line_subtotal', 15, 4)->default(0);
            $table->decimal('line_total', 15, 4)->default(0);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['invoice_id', 'sort_order']);
        });

        Schema::create('invoice_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->date('payment_date')->index();
            $table->decimal('amount', 15, 4);
            $table->string('method')->nullable();
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('activity_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action')->index();
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('properties')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index(['subject_type', 'subject_id']);
            $table->index(['created_at', 'action']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('invoice_payments');
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('settings');
        Schema::dropIfExists('invoice_number_settings');
        Schema::dropIfExists('legal_texts');
        Schema::dropIfExists('bank_accounts');
        Schema::dropIfExists('fiscal_profiles');
        Schema::dropIfExists('warranties');
        Schema::dropIfExists('payment_terms');
        Schema::dropIfExists('taxes');
        Schema::dropIfExists('currencies');
        Schema::dropIfExists('clients');
        Schema::dropIfExists('role_user');
        Schema::dropIfExists('permission_role');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
    }
};
