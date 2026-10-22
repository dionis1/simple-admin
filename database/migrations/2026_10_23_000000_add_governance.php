<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_events', function (Blueprint $table) {
            $table->bigIncrements('id');
            // Historical IDs deliberately survive identity/organization deletion.
            $table->unsignedBigInteger('organization_id')->nullable();
            $table->unsignedInteger('actor_id')->nullable();
            $table->unsignedInteger('subject_user_id')->nullable();
            $table->string('event', 100);
            $table->string('target_type', 80);
            $table->string('target_id', 80)->nullable();
            $table->uuid('correlation_id')->index();
            $table->jsonb('changes');
            $table->timestampTz('occurred_at')->useCurrent();
            $table->index(['organization_id', 'id']);
            $table->index(['organization_id', 'event', 'occurred_at']);
            $table->index(['organization_id', 'actor_id']);
        });
        DB::unprepared("CREATE OR REPLACE FUNCTION simple_admin_protect_audit_events() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN RAISE EXCEPTION 'Audit events are append-only'; END; $$");
        DB::unprepared('CREATE TRIGGER audit_events_immutable BEFORE UPDATE OR DELETE ON audit_events FOR EACH ROW EXECUTE FUNCTION simple_admin_protect_audit_events()');

        Schema::create('approval_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('requester_id');
            $table->unsignedInteger('reviewer_id')->nullable();
            $table->string('operation', 40);
            $table->string('state', 20)->default('pending');
            $table->string('reason', 500);
            $table->jsonb('payload');
            $table->timestampTz('expires_at');
            $table->timestampTz('decided_at')->nullable();
            $table->timestampTz('executed_at')->nullable();
            $table->timestampsTz();
            $table->index(['organization_id', 'state', 'id']);
            $table->unique(['id', 'organization_id']);
        });
        DB::statement("ALTER TABLE approval_requests ADD CONSTRAINT approval_state_check CHECK (state IN ('pending','approved','executed','rejected','cancelled','expired'))");
        DB::statement("ALTER TABLE approval_requests ADD CONSTRAINT approval_operation_check CHECK (operation IN ('revoke_org_tokens','support_access'))");
        DB::statement('ALTER TABLE approval_requests ADD CONSTRAINT approval_no_self_review CHECK (reviewer_id IS NULL OR reviewer_id <> requester_id)');

        Schema::create('support_grants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('approval_request_id')->unique();
            $table->foreign(['approval_request_id', 'organization_id'])->references(['id', 'organization_id'])->on('approval_requests')->restrictOnDelete();
            $table->unsignedInteger('actor_id');
            $table->foreignId('target_membership_id');
            $table->foreign(['target_membership_id', 'organization_id'])->references(['id', 'organization_id'])->on('memberships')->cascadeOnDelete();
            $table->string('session_hash', 64)->nullable();
            $table->timestampTz('expires_at');
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('ended_at')->nullable();
            $table->timestampsTz();
            $table->index(['actor_id', 'expires_at']);
        });

        Schema::create('queue_incidents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id')->nullable()->index();
            $table->uuid('job_uuid')->nullable();
            $table->string('exception_class', 160);
            $table->timestampTz('failed_at');
            $table->index(['organization_id', 'failed_at']);
        });

        foreach (['audit.view', 'audit.export', 'approvals.request', 'approvals.review', 'support.request', 'operations.view'] as $key) {
            [$resource, $action] = explode('.', $key);
            $id = DB::table('permissions')->insertGetId(['key' => $key, 'resource' => $resource, 'action' => $action]);
            $roles = $key === 'support.request' ? ['owner', 'admin', 'support'] : ['owner', 'admin'];
            foreach (DB::table('roles')->whereIn('name', $roles)->pluck('id') as $roleId) {
                DB::table('permission_role')->insert(['role_id' => $roleId, 'permission_id' => $id]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('queue_incidents');
        Schema::dropIfExists('support_grants');
        Schema::dropIfExists('approval_requests');
        Schema::dropIfExists('audit_events');
        DB::unprepared('DROP FUNCTION IF EXISTS simple_admin_protect_audit_events()');
        $ids = DB::table('permissions')->whereIn('key', ['audit.view', 'audit.export', 'approvals.request', 'approvals.review', 'support.request', 'operations.view'])->pluck('id');
        DB::table('permission_role')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();
    }
};
