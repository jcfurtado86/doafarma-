<?php

declare(strict_types = 1);

use App\Models\User;

use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\postJson;
use function PHPUnit\Framework\assertTrue;

it('should be able to register a doctor', function (): void {
    postJson(route('register'), [
        'name'                  => 'John Doe',
        'email'                 => 'test@example.com',
        'phone_number'          => '(96) 98765-4321', // DDD + 9XXX-XXXX
        'crm'                   => '123456',
        'crm_uf'                => 'SP', // UF do CRM
        'password'              => 'password',
        'password_confirmation' => 'password',
        'addresses'             => [
            [
                'location_name' => 'Clínica X',
                'full_address'  => 'Rua A, 123, Bairro B, Cidade C, Estado D',
                'complement'    => 'Sala 1',
                'cep'           => '12345-678',
            ],
        ],
        'terms_accepted' => true,
        'user_type'      => 'doctor',
    ])->assertCreated();

    assertDatabaseHas('users', [
        'name'              => 'John Doe',
        'email'             => 'test@example.com',
        'phone_number'      => '96987654321',
        'user_type'         => 'doctor',
        'terms_accepted'    => 1,
        'terms_accepted_at' => now(),
    ]);

    assertTrue(password_verify('password', (string) User::whereEmail('test@example.com')->first()->password));

    assertDatabaseHas('doctors', [
        'user_id' => User::whereEmail('test@example.com')->first()->id,
        'crm'     => '123456',
        'crm_uf'  => 'SP',
    ]);

    assertDatabaseHas('addresses', [
        'location_name' => 'Clínica X',
        'full_address'  => 'Rua A, 123, Bairro B, Cidade C, Estado D',
        'complement'    => 'Sala 1',
        'cep'           => '12345678',
    ]);

    assertDatabaseCount('users', 1);
    assertDatabaseCount('doctors', 1);
    assertDatabaseCount('addresses', 1);
});

it('should ensure that there is a relationship between the user and their doctor profile', function (): void {
    postJson(route('register'), [
        'name'                  => 'John Doe',
        'email'                 => 'test@example.com',
        'phone_number'          => '(96) 98765-4321',
        'crm'                   => '123456',
        'crm_uf'                => 'SP',
        'password'              => 'password',
        'password_confirmation' => 'password',
        'addresses'             => [
            [
                'location_name' => 'Clínica X',
                'full_address'  => 'Rua A, 123',
                'complement'    => 'Sala 1',
                'cep'           => '12345-678',
            ],
        ],
        'terms_accepted' => true,
        'user_type'      => 'doctor',
    ])->assertCreated();

    $user   = User::whereEmail('test@example.com')->first();
    $doctor = $user->doctor;

    expect($doctor)
        ->not->toBeNull()
        ->and($doctor->user_id)->toBe($user->id)
        ->and($doctor->crm)->toBe('123456')
        ->and($doctor->crm_uf)->toBe('SP');
});

it('should ensure that there is a relationship between the user and their addresses', function (): void {
    postJson(route('register'), [
        'name'                  => 'John Doe',
        'email'                 => 'test@example.com',
        'phone_number'          => '(96) 98765-4321',
        'crm'                   => '123456',
        'crm_uf'                => 'SP',
        'password'              => 'password',
        'password_confirmation' => 'password',
        'addresses'             => [
            [
                'location_name' => 'Clínica X',
                'full_address'  => 'Rua A, 123, Bairro B, Cidade C, Estado D',
                'complement'    => 'Sala 1',
                'cep'           => '12345-678',
            ],
        ],
        'terms_accepted' => true,
        'user_type'      => 'doctor',
    ])->assertCreated();

    $user    = User::whereEmail('test@example.com')->first();
    $address = $user->addresses()->first();

    expect($address)
        ->not->toBeNull()
        ->and($address->user_id)->toBe($user->id)
        ->and($address->location_name)->toBe('Clínica X');
});

it('should be able to register without a complement and with multiple addresses', function (): void {
    postJson(route('register'), [
        'name'                  => 'John Doe',
        'email'                 => 'test@example.com',
        'phone_number'          => '(96) 98765-4321',
        'crm'                   => '123456',
        'crm_uf'                => 'SP',
        'password'              => 'password',
        'password_confirmation' => 'password',
        'addresses'             => [
            [
                'location_name' => 'Clínica X',
                'full_address'  => 'Rua A, 123, Bairro B, Cidade C, Estado D',
                'cep'           => '12345-678',
            ],
            [
                'location_name' => 'Clínica Y',
                'full_address'  => 'Rua B, 456, Bairro C, Cidade D, Estado E',
                'complement'    => 'Sala 2',
                'cep'           => '98765-432',
            ],
        ],
        'terms_accepted' => true,
        'user_type'      => 'doctor',
    ])->assertCreated();

    $user = User::whereEmail('test@example.com')->first();

    expect($user->addresses)
        ->toHaveCount(2)
        ->and($user->addresses[0]->complement)->toBeNull()
        ->and($user->addresses[1]->complement)->toBe('Sala 2');

    assertDatabaseCount('users', 1);
    assertDatabaseCount('addresses', 2);
});

it('should validate required fields', function (): void {
    postJson(route('register'), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'name',
            'email',
            'phone_number',
            'crm',
            'crm_uf',
            'password',
            'addresses',
            'terms_accepted',
            'user_type',
        ]);

    postJson(route('register'), [
        'addresses' => [
            [
                // Empty address fields
            ],
        ],
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'addresses.0.location_name',
            'addresses.0.full_address',
            'addresses.0.cep',
        ]);

    assertDatabaseCount('users', 0);
    assertDatabaseCount('doctors', 0);
    assertDatabaseCount('addresses', 0);
});

it('should validate email format', function (): void {
    postJson(route('register'), [
        'name'                  => 'John Doe',
        'email'                 => 'invalid-email',
        'phone_number'          => '(96) 98765-4321',
        'crm'                   => '123456',
        'crm_uf'                => 'SP',
        'password'              => 'password',
        'password_confirmation' => 'password',
        'addresses'             => [
            [
                'location_name' => 'Clínica X',
                'full_address'  => 'Rua A, 123',
                'complement'    => 'Sala 1',
                'cep'           => '12345-678',
            ],
        ],
        'terms_accepted' => true,
        'user_type'      => 'doctor',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);

    assertDatabaseCount('users', 0);
});

it('should validate unique email', function (): void {
    User::factory()->create([
        'email' => 'test@example.com',
    ]);

    postJson(route('register'), [
        'name'                  => 'John Doe',
        'email'                 => 'test@example.com',
        'phone_number'          => '(96) 98765-4321',
        'crm'                   => '123456',
        'crm_uf'                => 'SP',
        'password'              => 'password',
        'password_confirmation' => 'password',
        'addresses'             => [
            [
                'location_name' => 'Clínica X',
                'full_address'  => 'Rua A, 123',
                'complement'    => 'Sala 1',
                'cep'           => '12345-678',
            ],
        ],
        'terms_accepted' => true,
        'user_type'      => 'doctor',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);

    assertDatabaseCount('users', 1);
});

it('should validate phone number format', function (): void {
    postJson(route('register'), [
        'name'                  => 'John Doe',
        'email'                 => 'test@example.com',
        'phone_number'          => 'invalid-phone',
        'crm'                   => '123456',
        'crm_uf'                => 'SP',
        'password'              => 'password',
        'password_confirmation' => 'password',
        'addresses'             => [
            [
                'location_name' => 'Clínica X',
                'full_address'  => 'Rua A, 123',
                'complement'    => 'Sala 1',
                'cep'           => '12345-678',
            ],
        ],
        'terms_accepted' => true,
        'user_type'      => 'doctor',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['phone_number']);

    assertDatabaseCount('users', 0);
});

it('should validate password confirmation', function (): void {
    postJson(route('register'), [
        'name'                  => 'John Doe',
        'email'                 => 'test@example.com',
        'phone_number'          => '(96) 98765-4321',
        'crm'                   => '123456',
        'crm_uf'                => 'SP',
        'password'              => 'password',
        'password_confirmation' => 'different-password',
        'addresses'             => [
            [
                'location_name' => 'Clínica X',
                'full_address'  => 'Rua A, 123',
                'complement'    => 'Sala 1',
                'cep'           => '12345-678',
            ],
        ],
        'terms_accepted' => true,
        'user_type'      => 'doctor',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['password']);

    assertDatabaseCount('users', 0);
});

it('should validate addresses array format', function (): void {
    postJson(route('register'), [
        'name'                  => 'John Doe',
        'email'                 => 'test@example.com',
        'phone_number'          => '(96) 98765-4321',
        'crm'                   => '123456',
        'crm_uf'                => 'SP',
        'password'              => 'password',
        'password_confirmation' => 'password',
        'addresses'             => 'not-an-array',
        'terms_accepted'        => true,
        'user_type'             => 'doctor',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['addresses']);

    assertDatabaseCount('users', 0);
});

it('should validate required address fields', function (): void {
    postJson(route('register'), [
        'name'                  => 'John Doe',
        'email'                 => 'test@example.com',
        'phone_number'          => '(96) 98765-4321',
        'crm'                   => '123456',
        'crm_uf'                => 'SP',
        'password'              => 'password',
        'password_confirmation' => 'password',
        'addresses'             => [[]],
        'terms_accepted'        => true,
        'user_type'             => 'doctor',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'addresses.0.location_name',
            'addresses.0.full_address',
            'addresses.0.cep',
        ]);

    assertDatabaseCount('users', 0);
});

it('should validate CEP format', function (): void {
    postJson(route('register'), [
        'name'                  => 'John Doe',
        'email'                 => 'test@example.com',
        'phone_number'          => '(96) 98765-4321',
        'crm'                   => '123456',
        'crm_uf'                => 'SP',
        'password'              => 'password',
        'password_confirmation' => 'password',
        'addresses'             => [
            [
                'location_name' => 'Clínica X',
                'full_address'  => 'Rua A, 123',
                'complement'    => 'Sala 1',
                'cep'           => 'invalid-cep',
            ],
        ],
        'terms_accepted' => true,
        'user_type'      => 'doctor',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['addresses.0.cep']);

    assertDatabaseCount('users', 0);
});

it('should validate CRM format and length', function (): void {
    postJson(route('register'), [
        'name'                  => 'John Doe',
        'email'                 => 'test@example.com',
        'phone_number'          => '(96) 98765-4321',
        'crm'                   => 'ABC123',  // Invalid format
        'crm_uf'                => 'SP',
        'password'              => 'password',
        'password_confirmation' => 'password',
        'addresses'             => [
            [
                'location_name' => 'Clínica X',
                'full_address'  => 'Rua A, 123',
                'complement'    => 'Sala 1',
                'cep'           => '12345-678',
            ],
        ],
        'terms_accepted' => true,
        'user_type'      => 'doctor',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['crm']);

    postJson(route('register'), [
        'name'                  => 'John Doe',
        'email'                 => 'test@example.com',
        'phone_number'          => '(96) 98765-4321',
        'crm'                   => '12',  // Too short
        'crm_uf'                => 'SP',
        'password'              => 'password',
        'password_confirmation' => 'password',
        'addresses'             => [
            [
                'location_name' => 'Clínica X',
                'full_address'  => 'Rua A, 123',
                'complement'    => 'Sala 1',
                'cep'           => '12345-678',
            ],
        ],
        'terms_accepted' => true,
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['crm']);

    assertDatabaseCount('users', 0);
});

it('should validate CRM UF is valid state', function (): void {
    postJson(route('register'), [
        'name'                  => 'John Doe',
        'email'                 => 'test@example.com',
        'phone_number'          => '(96) 98765-4321',
        'crm'                   => '123456',
        'crm_uf'                => 'XX', // Invalid state
        'password'              => 'password',
        'password_confirmation' => 'password',
        'addresses'             => [
            [
                'location_name' => 'Clínica X',
                'full_address'  => 'Rua A, 123',
                'complement'    => 'Sala 1',
                'cep'           => '12345-678',
            ],
        ],
        'terms_accepted' => true,
        'user_type'      => 'doctor',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['crm_uf']);

    assertDatabaseCount('users', 0);
});

it('should validate terms_accepted is true', function (): void {
    postJson(route('register'), [
        'name'                  => 'John Doe',
        'email'                 => 'test@example.com',
        'phone_number'          => '(96) 98765-4321',
        'crm'                   => '123456',
        'crm_uf'                => 'SP',
        'password'              => 'password',
        'password_confirmation' => 'password',
        'addresses'             => [
            [
                'location_name' => 'Clínica X',
                'full_address'  => 'Rua A, 123',
                'complement'    => 'Sala 1',
                'cep'           => '12345-678',
            ],
        ],
        'terms_accepted' => false,
        'user_type'      => 'doctor',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['terms_accepted']);

    assertDatabaseCount('users', 0);
});

it('should validate user_type is doctor or patient', function (): void {
    postJson(route('register'), [
        'name'                  => 'John Doe',
        'email'                 => 'test@example.com',
        'phone_number'          => '(96) 98765-4321',
        'crm'                   => '123456',
        'crm_uf'                => 'SP',
        'password'              => 'password',
        'password_confirmation' => 'password',
        'addresses'             => [
            [
                'location_name' => 'Clínica X',
                'full_address'  => 'Rua A, 123',
                'complement'    => 'Sala 1',
                'cep'           => '12345-678',
            ],
        ],
        'terms_accepted' => true,
        'user_type'      => 'invalid_type',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['user_type']);

    assertDatabaseCount('users', 0);
});

it('should validate address has valid location name length', function (): void {
    postJson(route('register'), [
        'name'                  => 'John Doe',
        'email'                 => 'test@example.com',
        'phone_number'          => '(96) 98765-4321',
        'crm'                   => '123456',
        'crm_uf'                => 'SP',
        'password'              => 'password',
        'password_confirmation' => 'password',
        'addresses'             => [
            [
                'location_name' => str_repeat('a', 256), // Too long
                'full_address'  => 'Rua A, 123',
                'complement'    => 'Sala 1',
                'cep'           => '12345-678',
            ],
        ],
        'terms_accepted' => true,
        'user_type'      => 'doctor',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['addresses.0.location_name']);

    assertDatabaseCount('users', 0);
});

it('should validate full_address is not empty', function (): void {
    postJson(route('register'), [
        'name'                  => 'John Doe',
        'email'                 => 'test@example.com',
        'phone_number'          => '(96) 98765-4321',
        'crm'                   => '123456',
        'crm_uf'                => 'SP',
        'password'              => 'password',
        'password_confirmation' => 'password',
        'addresses'             => [
            [
                'location_name' => 'Clínica X',
                'full_address'  => '',
                'complement'    => 'Sala 1',
                'cep'           => '12345-678',
            ],
        ],
        'terms_accepted' => true,
        'user_type'      => 'doctor',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['addresses.0.full_address']);

    assertDatabaseCount('users', 0);
});

it('should not allow duplicate CRM numbers', function (): void {
    User::factory()->doctor(crm: '123456', crm_uf: 'SP')->create();

    postJson(route('register'), [
        'name'                  => 'John Doe',
        'email'                 => 'test@example.com',
        'phone_number'          => '(96) 98765-4321',
        'crm'                   => '123456',
        'crm_uf'                => 'SP',
        'password'              => 'password',
        'password_confirmation' => 'password',
        'addresses'             => [
            [
                'location_name' => 'Clínica X',
                'full_address'  => 'Rua A, 123',
                'complement'    => 'Sala 1',
                'cep'           => '12345-678',
            ],
        ],
        'terms_accepted' => true,
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['crm']);

    assertDatabaseCount('users', 1);
});
