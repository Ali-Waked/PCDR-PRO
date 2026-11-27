<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AssignUserRolesRequest;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Http\Requests\Admin\UpdateUserStatusRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\AccessManagementService;
use App\Services\AuditService;
use App\Services\RbacService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Inertia\Inertia;
use Inertia\Response;

final class UserController extends Controller
{
    public function __construct(
        private readonly AccessManagementService $access,
        private readonly AuditService $audit,
        private readonly RbacService $rbac,
    ) {}

    /** @return array<string, bool> */
    private function capabilities(): array
    {
        return [
            'create' => Gate::allows('create', User::class),
            'update' => Gate::allows('users.update'),
            'delete' => Gate::allows('users.delete'),
            'assignRoles' => Gate::allows('users.assign_roles'),
            'resetPassword' => Gate::allows('users.reset_password'),
        ];
    }

    /** @return array<string, bool> */
    private function targetState(User $user): array
    {
        return [
            'isSelf' => auth()->user()?->is($user) === true,
            'isProtected' => $user->isPlatformOwner(),
        ];
    }

    private function managementBlocker(User $user, ?User $actor): ?string
    {
        if ($actor?->is($user) === true) {
            return (string) __('ui.users_page.protected_self');
        }

        if ($user->isPlatformOwner() && $actor?->isPlatformOwner() !== true) {
            return (string) __('ui.users_page.protected_owner');
        }

        return null;
    }

    public function index(string $locale, Request $request): Response
    {
        $search = $request->string('search')->trim()->toString();

        $users = User::query()
            ->when($search !== '', fn ($query) => $query->where(function ($inner) use ($search): void {
                $inner->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            }))
            ->with('roles')
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'status' => $user->status,
                'locale' => $user->locale,
                'is_platform_owner' => $user->isPlatformOwner(),
                'is_self' => $user->is(auth()->user()),
                'roles' => $user->roles->pluck('key')->all(),
                'last_login_at' => $user->last_login_at?->toISOString(),
            ]);

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
            'filters' => ['search' => $search],
            'counts' => [
                'total' => User::query()->count(),
                'active' => User::query()->where('status', User::STATUS_ACTIVE)->count(),
                'suspended' => User::query()->where('status', User::STATUS_SUSPENDED)->count(),
                'owners' => User::query()->where('is_platform_owner', true)->count(),
            ],
            'can' => $this->capabilities(),
        ]);
    }

    public function show(string $locale, User $user): Response
    {
        return Inertia::render('Admin/Users/Show', [
            'target' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'status' => $user->status,
                'locale' => $user->locale,
                'is_platform_owner' => $user->isPlatformOwner(),
                'created_at' => $user->created_at?->toISOString(),
                'last_login_at' => $user->last_login_at?->toISOString(),
                'roles' => $user->roles()->get(['roles.id', 'roles.key', 'roles.name'])->all(),
            ],
            'availableRoles' => Role::orderBy('name')->get(['id', 'key', 'name']),
            'can' => $this->capabilities(),
            ...$this->targetState($user),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Users/Create', [
            'availableRoles' => Role::orderBy('name')->get(['id', 'key', 'name']),
            'can' => $this->capabilities(),
        ]);
    }

    public function store(string $locale, StoreUserRequest $request): RedirectResponse
    {
        $data = $request->validated();

        try {
            $user = DB::transaction(function () use ($data, $request): User {
                $user = User::create([
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'password' => Hash::make($data['password']),
                    'status' => $data['status'],
                    'locale' => $data['locale'],
                    'email_verified_at' => now(),
                ]);

                if (! empty($data['roles'])) {
                    $this->access->syncRoles($user, $data['roles'], $request->user());
                }

                return $user;
            });
        } catch (\RuntimeException $exception) {
            return back()->with('flash', ['error' => $exception->getMessage()])->withInput();
        }

        $this->rbac->invalidate();
        $this->audit->record('user.created', 'users', (string) $user->id, null, [
            'name' => $user->name,
            'status' => $user->status,
        ]);

        return redirect()->route('admin.users.index', ['locale' => $request->route('locale')])
            ->with('success', __('ui.user_created'));
    }

    public function edit(string $locale, User $user): Response
    {
        return Inertia::render('Admin/Users/Edit', [
            'target' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'status' => $user->status,
                'locale' => $user->locale,
                'roleIds' => $user->roles()->pluck('roles.id')->all(),
            ],
            'availableRoles' => Role::orderBy('name')->get(['id', 'key', 'name']),
            'can' => $this->capabilities(),
            ...$this->targetState($user),
        ]);
    }

    public function update(string $locale, UpdateUserRequest $request, User $user): RedirectResponse
    {
        $blocker = $this->managementBlocker($user, $request->user());
        if ($blocker !== null) {
            return back()->with('flash', ['error' => $blocker]);
        }

        $data = $request->validated();
        $before = ['name' => $user->name, 'email' => $user->email, 'status' => $user->status];

        if ($data['status'] !== $user->status) {
            try {
                $this->access->setStatus($user, $data['status'], $request->user());
            } catch (\RuntimeException $exception) {
                return back()->with('flash', ['error' => $exception->getMessage()])->withInput();
            }
        }

        $user->update([
            'name' => $data['name'],
            'email' => $data['email'],
            'locale' => $data['locale'],
        ]);

        $this->audit->record('user.updated', 'users', (string) $user->id, $before, [
            'name' => $user->name,
            'email' => $user->email,
            'status' => $user->status,
        ]);

        return redirect()->route('admin.users.index', ['locale' => $request->route('locale')])
            ->with('success', __('ui.user_updated'));
    }

    public function destroy(string $locale, Request $request, User $user): RedirectResponse
    {
        try {
            $this->access->delete($user, $request->user());
        } catch (\RuntimeException $exception) {
            return back()->with('flash', ['error' => $exception->getMessage()]);
        }

        return redirect()->route('admin.users.index', ['locale' => $request->route('locale')])
            ->with('success', __('ui.user_deleted'));
    }

    public function updateStatus(string $locale, UpdateUserStatusRequest $request, User $user): RedirectResponse
    {
        $blocker = $this->managementBlocker($user, $request->user());
        if ($blocker !== null) {
            return back()->with('flash', ['error' => $blocker]);
        }

        try {
            $this->access->setStatus($user, $request->string('status')->toString(), $request->user());
        } catch (\RuntimeException $exception) {
            return back()->with('flash', ['error' => $exception->getMessage()]);
        }

        return back()->with('success', __('ui.status_updated'));
    }

    public function syncRoles(string $locale, AssignUserRolesRequest $request, User $user): RedirectResponse
    {
        $blocker = $this->managementBlocker($user, $request->user());
        if ($blocker !== null) {
            return back()->with('flash', ['error' => $blocker]);
        }

        try {
            $this->access->syncRoles($user, $request->input('roles', []), $request->user());
        } catch (\RuntimeException $exception) {
            return back()->with('flash', ['error' => $exception->getMessage()]);
        }

        return back()->with('success', __('ui.roles_updated'));
    }

    public function sendPasswordReset(string $locale, Request $request, User $user): RedirectResponse
    {
        $blocker = $this->managementBlocker($user, $request->user());
        if ($blocker !== null) {
            return back()->with('flash', ['error' => $blocker]);
        }

        $status = Password::broker()->sendResetLink(['email' => $user->email]);

        if ($status !== Password::RESET_LINK_SENT) {
            return back()->with('flash', ['error' => __($status)]);
        }

        $this->audit->record('user.password_reset_requested', 'users', (string) $user->id);

        return back()->with('success', __('ui.reset_link_sent'));
    }
}
