<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function getConnection(): ?string
    {
        return config('back-in-stock.database.connection');
    }

    public function up(): void
    {
        // Each product, or variant, that someone has asked about: the stock
        // last seen, so a rise can be noticed, and any emails still to send.
        Schema::create('back_in_stock_items', function (Blueprint $table) {
            $table->id();
            $table->string('product_id');
            $table->string('variant')->default('');
            $table->integer('stock')->nullable();
            $table->boolean('stock_tracked')->default(true);
            $table->boolean('pending')->default(false);
            $table->unsignedInteger('pending_limit')->nullable();
            $table->timestamp('send_after')->nullable();
            $table->timestamp('restocked_at')->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'variant']);
            $table->index(['pending', 'send_after']);
        });

        Schema::create('back_in_stock_alerts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('item_id')->constrained('back_in_stock_items')->cascadeOnDelete();
            $table->string('product_id');
            $table->string('variant')->default('');
            $table->string('site');
            $table->string('locale')->nullable();
            $table->string('email');
            $table->string('user_id')->nullable();
            $table->string('status', 20);
            $table->text('consent')->nullable();
            $table->timestamp('confirmation_sent_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('notified_at')->nullable();
            $table->timestamp('converted_at')->nullable();
            $table->string('order_id')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['item_id', 'status', 'confirmed_at']);
            $table->index(['email', 'status']);
            $table->index('user_id');
            $table->index(['status', 'updated_at']);
        });

        // Each time stock came back, and how many people were emailed.
        Schema::create('back_in_stock_restocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('back_in_stock_items')->cascadeOnDelete();
            $table->integer('stock_before')->nullable();
            $table->integer('stock_after')->nullable();
            $table->unsignedInteger('sent')->default(0);
            $table->string('trigger', 20);
            $table->timestamps();
        });

        // Addresses that unsubscribed, hashed: never emailed about requests
        // made before they did.
        Schema::create('back_in_stock_suppressions', function (Blueprint $table) {
            $table->string('email_hash', 64)->primary();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('back_in_stock_suppressions');
        Schema::dropIfExists('back_in_stock_restocks');
        Schema::dropIfExists('back_in_stock_alerts');
        Schema::dropIfExists('back_in_stock_items');
    }
};
