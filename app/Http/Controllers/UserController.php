<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\DataSource;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    use AuthorizesRequests;

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', User::class);
        $query = User::with('roles');

        // Search
        if ($request->filled('search')) {
            $searchTerm = $request->get('search');
            $query->where(function (Builder $q) use ($searchTerm) {
                $q->where('name', 'like', "%{$searchTerm}%")
                    ->orWhere('email', 'like', "%{$searchTerm}%");
            });
        }

        // Filter by role
        if ($request->filled('role')) {
            $query->whereHas('roles', function (Builder $q) use ($request) {
                $q->where('roles.id', $request->get('role'));
            });
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'name');
        $sortDirection = $request->get('sort_direction', 'asc');
        $query->orderBy($sortBy, $sortDirection);

        // Pagination
        $users = $query->customPaginate();

        return Inertia::render('Users/Index', [
            'users' => $users,
            'roles' => Role::all(),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request): Response
    {
        $this->authorize('create', User::class);

        return Inertia::render('Users/Create', [
            'roles' => Role::all(),
            'dataSources' => DataSource::all(['id', 'name', 'host', 'port', 'database']),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreUserRequest $request): RedirectResponse
    {
        try {
            $this->authorize('create', User::class);
            DB::beginTransaction();

            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
            ]);

            // Attach roles
            $user->roles()->sync($request->roles);

            // Attach data sources (only if not administrator)
            if ($request->filled('data_sources')) {
                $user->dataSources()->sync($request->data_sources);
            }

            DB::commit();

            return redirect()->route('user.index')
                ->with('success', 'User created successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            logger()->error("Failed to create user: {$e->getMessage()}", [
                'exception' => $e,
            ]);

            return redirect()
                ->back()
                ->withInput()
                ->withErrors('Failed to create user. Please try again.');
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, User $user): Response
    {
        $this->authorize('viewAny', $user);
        $user->load(['roles', 'dataSources']);

        return Inertia::render('Users/Show', [
            'user' => $user,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Request $request, User $user): Response
    {
        $user->load(['roles', 'dataSources']);
        $this->authorize('update', $user);

        return Inertia::render('Users/Edit', [
            'user' => $user,
            'roles' => Role::all(),
            'dataSources' => DataSource::all(['id', 'name', 'host', 'port', 'database']),
            'userRoles' => $user->roles->pluck('id')->toArray(),
            'userDataSources' => $user->dataSources->pluck('id')->toArray(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        try {
            $this->authorize('update', $user);
            DB::beginTransaction();

            $data = [
                'name' => $request->name,
                'email' => $request->email,
            ];

            // Only update password if provided
            if ($request->filled('password')) {
                $data['password'] = Hash::make($request->password);
            }

            $user->update($data);

            // Sync roles
            $user->roles()->sync($request->roles);

            // Sync data sources
            if ($request->filled('data_sources')) {
                $user->dataSources()->sync($request->data_sources);
            } else {
                $user->dataSources()->detach();
            }

            DB::commit();

            return redirect()->route('user.index')
                ->with('success', 'User updated successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            logger()->error("Failed to update user {$user->id}: {$e->getMessage()}", [
                'exception' => $e,
            ]);

            return redirect()
                ->back()
                ->withInput()
                ->withErrors('Failed to update user. Please try again.');
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, User $user): RedirectResponse
    {
        try {
            $this->authorize('delete', $user);
            DB::beginTransaction();

            $user->delete();

            DB::commit();

            return redirect()->route('user.index')
                ->with('success', 'User deleted successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            logger()->error("Failed to delete user {$user->id}: {$e->getMessage()}", [
                'exception' => $e,
            ]);

            return redirect()
                ->back()
                ->withErrors('Failed to delete user. Please try again.');
        }
    }
}
