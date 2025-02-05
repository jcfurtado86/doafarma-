<?php

declare(strict_types = 1);

use App\Models\User;

use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\postJson;
use function PHPUnit\Framework\assertTrue;

it('should be able to register', function (): void {
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
    ])->assertCreated();

    assertDatabaseHas('users', [
        'name'              => 'John Doe',
        'email'             => 'test@example.com',
        'phone_number'      => '96987654321',
        'crm'               => '123456',
        'crm_uf'            => 'SP',
        'terms_accepted'    => 1,
        'terms_accepted_at' => now(),
    ]);

    assertTrue(password_verify('password', (string) User::whereEmail('test@example.com')->first()->password));

    assertDatabaseHas('addresses', [
        'location_name' => 'Clínica X',
        'full_address'  => 'Rua A, 123, Bairro B, Cidade C, Estado D',
        'complement'    => 'Sala 1',
        'cep'           => '12345678',
    ]);

    assertDatabaseCount('users', 1);
    assertDatabaseCount('addresses', 1);
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
    ])->assertCreated();

    $user    = User::whereEmail('test@example.com')->first();
    $address = $user->addresses()->first();

    expect($address)
        ->not->toBeNull()
        ->and($address->user_id)->toBe($user->id)
        ->and($address->location_name)->toBe('Clínica X');
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
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['terms_accepted']);

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
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['addresses.0.full_address']);

    assertDatabaseCount('users', 0);
});

it('should not allow duplicate CRM numbers', function (): void {
    User::factory()->create([
        'crm'    => '123456',
        'crm_uf' => 'SP',
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
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['crm']);

    assertDatabaseCount('users', 1);
});
