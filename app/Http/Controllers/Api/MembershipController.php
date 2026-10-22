<?php

namespace App\Http\Controllers\Api;

use App\Actions\Memberships\UpdateMembership;
use App\Enums\AccessStatus;
use App\Http\Controllers\Controller;
use App\Models\Membership;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class MembershipController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Membership::class);
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        $org = app(TenantContext::class)->resolve($user)->organization_id;
        $rows = Membership::where('organization_id', $org)->with('user')->orderBy('id')->paginate(20);

        return response()->json(['data' => $rows->map(fn ($member) => $this->resource($member)), 'meta' => ['page' => $rows->currentPage(), 'last_page' => $rows->lastPage(), 'total' => $rows->total()]]);
    }

    public function show(Request $request, int $membership): JsonResponse
    {
        $member = $this->find($request, $membership);
        Gate::authorize('view', $member);

        return response()->json(['data' => $this->resource($member)]);
    }

    public function update(Request $request, int $membership, UpdateMembership $update): JsonResponse
    {
        $member = $this->find($request, $membership);
        Gate::authorize('update', $member);
        $data = $request->validate(['role_id' => ['required', 'integer', 'min:1'], 'status' => ['required', Rule::enum(AccessStatus::class)]]);
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        $update($user, $member->id, (int) $data['role_id'], $data['status']);

        return response()->json(['data' => $this->resource($member->refresh())]);
    }

    private function find(Request $request, int $id): Membership
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return Membership::where('organization_id', app(TenantContext::class)->resolve($user)->organization_id)->with('user')->findOrFail($id);
    }

    /** @return array<string,mixed> */
    private function resource(Membership $member): array
    {
        return ['id' => $member->id, 'user' => ['name' => $member->user->name, 'email' => $member->user->email], 'display_name' => $member->display_name, 'job_title' => $member->job_title, 'status' => $member->status->value, 'role' => $member->assignedRole()?->name->value];
    }
}
