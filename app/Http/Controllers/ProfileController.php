<?php

namespace App\Http\Controllers;

use App\DTO\ProfileUpdateDTO;
use App\Models\User;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Show the form for editing the authenticated user's profile.
     *
     * @param  Request  $request
     * @return Response
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('Profile/Edit', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Update the authenticated user's profile.
     *
     * @param  ProfileUpdateDTO  $request
     * @return RedirectResponse
     */
    public function update(ProfileUpdateDTO $request): RedirectResponse
    {
        try {
            /** @var User $user */
            $user = auth()->user();

            return $this->executeStoreWithLock("profile-update-{$user->id}", function () use ($request, $user) {
                try {
                    $data = [
                        'name' => $request->name,
                    ];

                    // Only update password if provided
                    if (! empty($request->password)) {
                        $data['password'] = Hash::make($request->password);
                    }

                    $user->update($data);

                    return redirect()->route('profile.edit')
                        ->with('success', 'Profile updated.');
                } catch (\Exception $e) {
                    DB::rollBack();
                    throw $e;
                }
            });
        } catch (ValidationException $e) {
            return redirect()
                ->back()
                ->withErrors($e->errors());
        } catch (LockTimeoutException $e) {
            logger()->error("Failed acquire lock when updating profile: {$e->getMessage()}", [
                'exception' => $e,
            ]);

            return redirect()
                ->back()
                ->withErrors('Failed to acquire lock. Please try again later.');
        } catch (\Throwable $e) {
            logger()->error("Failed to update profile: {$e->getMessage()}", [
                'exception' => $e,
            ]);

            return redirect()
                ->back()
                ->withErrors($e->getMessage());
        }
    }
}
