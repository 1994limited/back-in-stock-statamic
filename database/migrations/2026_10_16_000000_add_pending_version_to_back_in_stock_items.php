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
        // Bumped each time emails are lined up, so a send that finishes
        // can tell whether more were lined up while it was sending.
        Schema::table('back_in_stock_items', function (Blueprint $table) {
            $table->unsignedInteger('pending_version')->default(0)->after('pending_limit');
        });
    }

    public function down(): void
    {
        Schema::table('back_in_stock_items', function (Blueprint $table) {
            $table->dropColumn('pending_version');
        });
    }
};
