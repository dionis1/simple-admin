<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('status', 20)->default('active')->index();
            $table->string('locale', 2)->default('en');
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();
        });
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_status_check CHECK (status IN ('active','suspended','deactivated'))");
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_locale_check CHECK (locale IN ('en','de'))");
        DB::statement('ALTER TABLE memberships DROP CONSTRAINT memberships_status_check');
        DB::statement("ALTER TABLE memberships ADD CONSTRAINT memberships_status_check CHECK (status IN ('active','suspended','deactivated'))");
        Schema::table('memberships', function (Blueprint $table) {
            $table->string('display_name', 120)->nullable();
            $table->string('job_title', 120)->nullable();
        });
        Schema::create('invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('role_id');
            $table->foreign(['role_id', 'organization_id'])->references(['id', 'organization_id'])->on('roles')->restrictOnDelete();
            $table->unsignedInteger('inviter_id')->nullable();
            $table->foreign('inviter_id')->references('id')->on('users')->nullOnDelete();
            $table->string('email')->index();
            $table->char('token_hash', 64)->unique();
            $table->timestamp('expires_at')->index();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'email']);
            $table->index(['role_id', 'organization_id']);
        });
        DB::statement('CREATE UNIQUE INDEX invitations_pending_unique ON invitations (organization_id,email) WHERE accepted_at IS NULL AND revoked_at IS NULL');
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->timestamps();
            $table->unique(['organization_id', 'name']);
            $table->unique(['id', 'organization_id']);
        });
        Schema::table('memberships', function (Blueprint $table) {
            $table->unsignedBigInteger('department_id')->nullable();
            $table->foreign(['department_id', 'organization_id'])->references(['id', 'organization_id'])->on('departments')->restrictOnDelete();
            $table->index(['department_id', 'organization_id']);
        });
        Schema::create('teams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('department_id')->nullable();
            $table->unsignedBigInteger('owner_membership_id');
            $table->string('name', 120);
            $table->timestamps();
            $table->unique(['organization_id', 'name']);
            $table->unique(['id', 'organization_id']);
            $table->foreign(['department_id', 'organization_id'])->references(['id', 'organization_id'])->on('departments')->restrictOnDelete();
            $table->foreign(['owner_membership_id', 'organization_id'])->references(['id', 'organization_id'])->on('memberships')->restrictOnDelete();
            $table->index(['owner_membership_id', 'organization_id']);
            $table->index(['department_id', 'organization_id']);
        });
        Schema::create('team_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('team_id');
            $table->unsignedBigInteger('membership_id');
            $table->unique(['team_id', 'membership_id']);
            $table->foreign(['team_id', 'organization_id'])->references(['id', 'organization_id'])->on('teams')->cascadeOnDelete();
            $table->foreign(['membership_id', 'organization_id'])->references(['id', 'organization_id'])->on('memberships')->cascadeOnDelete();
            $table->index(['membership_id', 'organization_id']);
            $table->index(['team_id', 'organization_id']);
        });
        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('tokenable_type');
            $table->unsignedInteger('tokenable_id');
            $table->foreign('tokenable_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index(['tokenable_type', 'tokenable_id']);
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->index();
            $table->timestamps();
        });
        foreach (['invitations.manage', 'teams.view', 'teams.manage', 'departments.view', 'departments.manage'] as $key) {
            [$resource,$action] = explode('.', $key);
            $id = DB::table('permissions')->insertGetId(compact('key', 'resource', 'action'));
            $names = $key === 'invitations.manage' ? ['owner', 'admin'] : (str_ends_with($key, '.manage') ? ['owner', 'admin', 'manager'] : ['owner', 'admin', 'manager', 'support', 'viewer']);
            foreach (DB::table('roles')->whereIn('name', $names)->pluck('id') as $roleId) {
                DB::table('permission_role')->insert(['role_id' => $roleId, 'permission_id' => $id]);
            }
        }
    }

    public function down(): void
    {
        foreach (['invitations.manage', 'teams.view', 'teams.manage', 'departments.view', 'departments.manage'] as $key) {
            $id = DB::table('permissions')->where('key', $key)->value('id');
            DB::table('permission_role')->where('permission_id', $id)->delete();
            DB::table('permissions')->where('id', $id)->delete();
        }
        Schema::dropIfExists('personal_access_tokens');
        Schema::dropIfExists('team_memberships');
        Schema::dropIfExists('teams');
        Schema::table('memberships', function (Blueprint $table) {
            $table->dropForeign(['department_id', 'organization_id']);
            $table->dropIndex(['department_id', 'organization_id']);
            $table->dropColumn('department_id');
        });
        Schema::dropIfExists('departments');
        Schema::dropIfExists('invitations');
        Schema::table('memberships', fn (Blueprint $table) => $table->dropColumn(['display_name', 'job_title']));
        DB::table('memberships')->where('status', 'deactivated')->update(['status' => 'suspended']);
        DB::statement('ALTER TABLE memberships DROP CONSTRAINT memberships_status_check');
        DB::statement("ALTER TABLE memberships ADD CONSTRAINT memberships_status_check CHECK (status IN ('active','suspended'))");
        DB::statement('ALTER TABLE users DROP CONSTRAINT users_status_check');
        DB::statement('ALTER TABLE users DROP CONSTRAINT users_locale_check');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['status', 'locale', 'two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at']));
    }
};
