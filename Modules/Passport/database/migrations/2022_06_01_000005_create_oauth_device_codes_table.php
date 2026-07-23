<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;
use Illuminate\Support\Facades\Schema;

/**
 * Passport enables the device code grant by default (Passport::$deviceCodeGrantEnabled)
 * and registers the passport.device route, so this table must exist or those
 * requests fail on a missing table.
 */
return new class extends Migration
{
    /**
     * The database schema.
     *
     * @var Builder
     */
    protected $schema;

    /**
     * Create a new migration instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->schema = Schema::connection($this->getConnection());
    }

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $this->schema->create('oauth_device_codes', function (Blueprint $table) {
            $table->char('id', 80)->primary();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->uuid('client_id')->index();
            $table->char('user_code', 8)->unique();
            $table->text('scopes');
            $table->boolean('revoked');
            $table->dateTime('user_approved_at')->nullable();
            $table->dateTime('last_polled_at')->nullable();
            $table->dateTime('expires_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $this->schema->dropIfExists('oauth_device_codes');
    }

    /**
     * Get the migration connection name.
     *
     * @return string|null
     */
    public function getConnection()
    {
        return config('passport.connection');
    }
};
