<?php

namespace App\Http\Controllers;

use App\Helpers\SearchOperator;
use App\Http\Requests\Organization\InviteUserRequest;
use App\Http\Requests\Organization\UpdateUserRoleRequest;
use App\Http\Resources\OrganizationUserResource;
use App\Models\Organization;
use App\Models\User;
use App\Services\AuditService;
use App\Services\OrganizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrganizationUserController extends Controller
{
    public function __construct(
        private OrganizationService $service,
        private AuditService $audit,
    ) {}

    public function index(Request $request, Organization $organization): JsonResponse
    {
        $this->authorize('manageUsers', $organization);

        $users = $organization->users()
            ->when($request->query('role'), fn ($q, $r) => $q->where('role', $r))
            ->when($request->query('is_active'), fn ($q, $v) => $q->where('is_active', filter_var($v, FILTER_VALIDATE_BOOLEAN)))
            ->when($request->query('q'), function ($q, $s) {
                $like = SearchOperator::like();
                $term = SearchOperator::wrap($s);

                return $q->where(function ($sub) use ($like, $term) {
                    $sub->where('name', $like, $term)->orWhere('email', $like, $term);
                });
            })
            ->latest()
            ->paginate(20);

        return response()->json(OrganizationUserResource::collection($users)->response()->getData(true));
    }

    public function store(InviteUserRequest $request, Organization $organization): JsonResponse
    {
        $user = $this->service->inviteUser($organization, $request->validated());

        $this->audit->log($request->user(), 'assign', User::class, $user->id, [
            'organization_id' => $organization->id,
            'role'            => $user->role,
        ]);

        return response()->json(new OrganizationUserResource($user), 201);
    }

    public function update(UpdateUserRoleRequest $request, Organization $organization, User $user): JsonResponse
    {
        abort_if($user->organization_id !== $organization->id, 404);

        $user = $this->service->updateUser($user, $request->validated());

        $this->audit->log($request->user(), 'update', User::class, $user->id);

        return response()->json(new OrganizationUserResource($user));
    }

    public function destroy(Request $request, Organization $organization, User $user): JsonResponse
    {
        $this->authorize('delete', $user);
        abort_if($user->organization_id !== $organization->id, 404);

        $this->service->deactivateUser($user);

        $this->audit->log($request->user(), 'delete', User::class, $user->id);

        return response()->json(['message' => 'Usuario desactivado.']);
    }

    public function suspend(Request $request, Organization $organization, User $user): JsonResponse
    {
        $this->authorize('manageUsers', $organization);
        abort_if($user->organization_id !== $organization->id, 404);

        $this->service->suspendUser($user);

        $this->audit->log($request->user(), 'suspend', User::class, $user->id, [
            'organization_id' => $organization->id,
        ]);

        return response()->json(['message' => 'Usuario suspendido.']);
    }

    public function activate(Request $request, Organization $organization, User $user): JsonResponse
    {
        $this->authorize('manageUsers', $organization);
        abort_if($user->organization_id !== $organization->id, 404);

        $this->service->activateUser($user);

        $this->audit->log($request->user(), 'activate', User::class, $user->id, [
            'organization_id' => $organization->id,
        ]);

        return response()->json(['message' => 'Usuario activado.']);
    }

    public function makeAdmin(Request $request, Organization $organization, User $user): JsonResponse
    {
        abort_if(! $request->user()->isSysAdmin(), 403, 'Solo el sysadmin puede cambiar roles.');
        abort_if($user->organization_id !== $organization->id, 404);

        $this->service->changeRole($user, 'org_admin');

        $this->audit->log($request->user(), 'assign', User::class, $user->id, [
            'organization_id' => $organization->id,
            'role'            => 'org_admin',
        ]);

        return response()->json(['message' => 'Usuario promovido a org_admin.']);
    }

    public function makeUser(Request $request, Organization $organization, User $user): JsonResponse
    {
        abort_if(! $request->user()->isSysAdmin(), 403, 'Solo el sysadmin puede cambiar roles.');
        abort_if($user->organization_id !== $organization->id, 404);

        $this->service->changeRole($user, 'org_user');

        $this->audit->log($request->user(), 'assign', User::class, $user->id, [
            'organization_id' => $organization->id,
            'role'            => 'org_user',
        ]);

        return response()->json(['message' => 'Usuario degradado a org_user.']);
    }
}
