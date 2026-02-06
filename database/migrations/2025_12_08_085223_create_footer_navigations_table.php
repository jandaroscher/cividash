<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the footer_navigations table used to store footer configuration.
     *
     * Creates columns for navigation items (`footer_navigation_items`), social links (`social_links`),
     * layout type (`layout_type`), number of columns (`columns`), a flag to enable social links
     * (`social_links_enabled`), localized copyright text (`copyright_text`), and timestamps.
     *
     * Singleton behavior is enforced in application code via FooterNavigation::getInstance()/getOrCreateInstance()
     * which always targets the record with id=1.
     */
    public function up(): void
    {
        Schema::create('footer_navigations', function (Blueprint $table) {
            $table->id();
            $table->json('footer_navigation_items')->nullable();
            $table->json('social_links')->nullable();
            $table->string('layout_type')->default('single-row');
            $table->integer('columns')->default(3);
            $table->boolean('social_links_enabled')->default(true);
            $table->json('copyright_text')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('footer_navigations');
    }
};
