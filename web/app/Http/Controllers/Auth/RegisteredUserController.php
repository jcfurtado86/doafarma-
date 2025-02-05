<?php

declare(strict_types = 1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterUserRequest;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class RegisteredUserController extends Controller
{
    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(RegisterUserRequest $request): Response
    {
        return DB::transaction(function () use ($request) {
            $user = User::create([
                'name'              => $request->name,
                'email'             => $request->email,
                'password'          => Hash::make($request->string('password')->toString()),
                'phone_number'      => $request->phone_number,
                'user_type'         => $request->user_type,
                'terms_accepted'    => $request->terms_accepted,
                'terms_accepted_at' => now(),
            ]);

            if ($request->user_type === 'doctor') {
                $user->doctor()->create([
                    'crm'    => $request->crm,
                    'crm_uf' => $request->crm_uf,
                ]);
            }

            $addresses = collect(is_array($request->addresses) ? $request->addresses : [])
                ->filter(fn ($address): bool => is_array($address))
                ->map(fn ($address): array => [
                    'location_name' => $address['location_name'] ?? '',
                    'full_address'  => $address['full_address'] ?? '',
                    'complement'    => $address['complement'] ?? null,
                    'cep'           => $address['cep'] ?? '',
                ]);

            if ($addresses->isNotEmpty()) {
                $user->addresses()->createMany($addresses);
            }

            event(new Registered($user));

            Auth::login($user);

            return response()->noContent(Response::HTTP_CREATED);
        });
    }
}
