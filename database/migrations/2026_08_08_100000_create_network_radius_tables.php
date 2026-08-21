<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('routers', function (Blueprint $table) {
            $table->id();
            $table->ulid('tenant_id')->index();
            $table->string('name');
            $table->string('host');
            $table->unsignedInteger('rest_port')->default(443);
            $table->string('api_username');
            $table->text('api_password_encrypted');
            $table->boolean('verify_tls')->default(true);
            $table->string('status')->default('unknown')->index();
            $table->string('routeros_version')->nullable();
            $table->string('board_name')->nullable();
            $table->timestampTz('last_seen_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'name']);
        });

        Schema::create('network_nas', function (Blueprint $table) {
            $table->id();
            $table->ulid('tenant_id')->index();
            $table->foreignId('router_id')->nullable()->constrained('routers')->nullOnDelete();
            $table->string('nasname');
            $table->string('shortname');
            $table->string('type')->default('mikrotik');
            $table->text('secret_encrypted');
            $table->unsignedInteger('coa_port')->default(3799);
            $table->boolean('active')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();
            $table->unique('nasname');
        });

        Schema::create('internet_plans', function (Blueprint $table) {
            $table->id();
            $table->ulid('tenant_id')->index();
            $table->string('name');
            $table->string('code');
            $table->unsignedBigInteger('price')->default(0);
            $table->unsignedInteger('download_kbps');
            $table->unsignedInteger('upload_kbps');
            $table->boolean('active')->default(true)->index();
            $table->jsonb('radius_attributes')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'code']);
        });

        Schema::create('ip_pools', function (Blueprint $table) {
            $table->id();
            $table->ulid('tenant_id')->index();
            $table->string('name');
            $table->string('start_ip');
            $table->string('end_ip');
            $table->string('gateway')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['tenant_id', 'name']);
        });

        // FreeRADIUS 3.2.x PostgreSQL projection tables.
        Schema::create('radacct', function (Blueprint $table) {
            $table->bigIncrements('radacctid');
            $table->text('acctsessionid');
            $table->text('acctuniqueid')->unique();
            $table->text('username')->nullable();
            $table->text('realm')->nullable();
            $table->ipAddress('nasipaddress');
            $table->text('nasportid')->nullable();
            $table->text('nasporttype')->nullable();
            $table->timestampTz('acctstarttime')->nullable();
            $table->timestampTz('acctupdatetime')->nullable();
            $table->timestampTz('acctstoptime')->nullable();
            $table->bigInteger('acctinterval')->nullable();
            $table->bigInteger('acctsessiontime')->nullable();
            $table->text('acctauthentic')->nullable();
            $table->text('connectinfo_start')->nullable();
            $table->text('connectinfo_stop')->nullable();
            $table->bigInteger('acctinputoctets')->nullable();
            $table->bigInteger('acctoutputoctets')->nullable();
            $table->text('calledstationid')->nullable();
            $table->text('callingstationid')->nullable();
            $table->text('acctterminatecause')->nullable();
            $table->text('servicetype')->nullable();
            $table->text('framedprotocol')->nullable();
            $table->ipAddress('framedipaddress')->nullable();
            $table->ipAddress('framedipv6address')->nullable();
            $table->string('framedipv6prefix')->nullable();
            $table->text('framedinterfaceid')->nullable();
            $table->string('delegatedipv6prefix')->nullable();
            $table->text('class')->nullable();
            $table->index(['acctstarttime', 'username'], 'radacct_start_user_idx');
            $table->index(['nasipaddress', 'acctstarttime'], 'radacct_bulk_close');
        });

        Schema::create('radcheck', function (Blueprint $table) {
            $table->increments('id');
            $table->text('username')->default('');
            $table->text('attribute')->default('');
            $table->string('op', 2)->default('==');
            $table->text('value')->default('');
            $table->index(['username', 'attribute']);
        });

        Schema::create('radgroupcheck', function (Blueprint $table) {
            $table->increments('id');
            $table->text('groupname')->default('');
            $table->text('attribute')->default('');
            $table->string('op', 2)->default('==');
            $table->text('value')->default('');
            $table->index(['groupname', 'attribute']);
        });

        Schema::create('radgroupreply', function (Blueprint $table) {
            $table->increments('id');
            $table->text('groupname')->default('');
            $table->text('attribute')->default('');
            $table->string('op', 2)->default('=');
            $table->text('value')->default('');
            $table->index(['groupname', 'attribute']);
        });

        Schema::create('radreply', function (Blueprint $table) {
            $table->increments('id');
            $table->text('username')->default('');
            $table->text('attribute')->default('');
            $table->string('op', 2)->default('=');
            $table->text('value')->default('');
            $table->index(['username', 'attribute']);
        });

        Schema::create('radusergroup', function (Blueprint $table) {
            $table->increments('id');
            $table->text('username')->default('');
            $table->text('groupname')->default('');
            $table->integer('priority')->default(0);
            $table->index('username');
        });

        Schema::create('radpostauth', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->text('username');
            $table->text('pass')->nullable();
            $table->text('reply')->nullable();
            $table->text('calledstationid')->nullable();
            $table->text('callingstationid')->nullable();
            $table->timestampTz('authdate')->useCurrent();
            $table->text('class')->nullable();
            $table->index('username');
        });

        // FreeRADIUS SQL client projection. Secrets must be plaintext here because
        // the RADIUS daemon must read them; encrypted source-of-truth stays in network_nas.
        Schema::create('nas', function (Blueprint $table) {
            $table->increments('id');
            $table->text('nasname');
            $table->text('shortname');
            $table->text('type')->default('other');
            $table->integer('ports')->nullable();
            $table->text('secret');
            $table->text('server')->nullable();
            $table->text('community')->nullable();
            $table->text('description')->nullable();
            $table->index('nasname');
        });

        Schema::create('nasreload', function (Blueprint $table) {
            $table->ipAddress('nasipaddress')->primary();
            $table->timestampTz('reloadtime');
        });
    }

    public function down(): void
    {
        foreach ([
            'nasreload', 'nas', 'radpostauth', 'radusergroup', 'radreply',
            'radgroupreply', 'radgroupcheck', 'radcheck', 'radacct', 'ip_pools',
            'internet_plans', 'network_nas', 'routers',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
