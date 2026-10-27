<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * search_query_log: one row per first-page search (written after the response), pruned
     * after storefront.search_log_retention_days. search_term_stat: nightly aggregate per term
     * (storefront:aggregate-search-terms) feeding trending suggestions and the admin report.
     */
    public function up(): void
    {
        Schema::create('search_query_log', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->bigIncrements('id');
            $table->string('term', 120)->comment('Normalised: lower case, trimmed, single spaces');
            $table->integer('results_count')->default(0);
            $table->unsignedInteger('customer_id')->nullable();
            $table->string('session_id', 64)->nullable();
            $table->unsignedInteger('clicked_product_id')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['term', 'created_at']);
            $table->index('created_at');
            $table->index(['session_id', 'term']);
        });

        Schema::create('search_term_stat', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->string('term', 120)->primary();
            $table->integer('searches_7d')->default(0);
            $table->integer('searches_30d')->default(0);
            $table->integer('avg_results')->default(0);
            $table->integer('zero_result_count')->default(0)->comment('Searches without results in the last 30 days');
            $table->integer('clicks_30d')->default(0);
            $table->timestamp('updated_at')->nullable();

            $table->index('searches_7d');
            $table->index('zero_result_count');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_term_stat');
        Schema::dropIfExists('search_query_log');
    }
};
