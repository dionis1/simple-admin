<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('status', 20)->default('active')->index();
            $table->timestamps();
        });
        Schema::create('memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            // Preserve the original users table's 32-bit primary key.
            $table->unsignedInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users')->restrictOnDelete();
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->unique(['organization_id', 'user_id']);
            $table->unique(['id', 'organization_id']);
            $table->index(['user_id', 'status']);
            $table->index(['organization_id', 'status']);
        });
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 30);
            $table->timestamps();
            $table->unique(['organization_id', 'name']);
            $table->unique(['id', 'organization_id']);
        });
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('key', 80)->unique();
            $table->string('resource', 40);
            $table->string('action', 30);
            $table->unique(['resource', 'action']);
        });
        Schema::create('permission_role', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained()->restrictOnDelete();
            $table->primary(['role_id', 'permission_id']);
            $table->index('permission_id');
        });
        Schema::create('role_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('membership_id')->unique();
            $table->unsignedBigInteger('role_id');
            $table->timestamps();
            $table->foreign(['membership_id', 'organization_id'])->references(['id', 'organization_id'])->on('memberships')->cascadeOnDelete();
            $table->foreign(['role_id', 'organization_id'])->references(['id', 'organization_id'])->on('roles')->restrictOnDelete();
            $table->index(['role_id', 'organization_id']);
            $table->index(['membership_id', 'organization_id']);
        });

        DB::statement("ALTER TABLE organizations ADD CONSTRAINT organizations_status_check CHECK (status IN ('active', 'suspended'))");
        DB::statement("ALTER TABLE memberships ADD CONSTRAINT memberships_status_check CHECK (status IN ('active', 'suspended'))");
        DB::statement("ALTER TABLE roles ADD CONSTRAINT roles_name_check CHECK (name IN ('owner', 'admin', 'manager', 'support', 'viewer'))");

        foreach (['dashboard.view', 'organizations.view', 'organizations.update', 'memberships.view', 'memberships.create', 'memberships.update', 'memberships.delete', 'roles.view', 'roles.assign', 'permissions.view'] as $key) {
            [$resource, $action] = explode('.', $key);
            DB::table('permissions')->insert(compact('key', 'resource', 'action'));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('role_assignments');
        Schema::dropIfExists('permission_role');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('memberships');
        Schema::dropIfExists('organizations');
    }
};
