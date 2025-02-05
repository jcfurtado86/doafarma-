<?php

declare(strict_types = 1);

namespace App\Http\Requests;

use App\Models\Doctor;
use App\Models\User;
use App\Rules\ValidUF;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules;

class RegisterUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name'                      => ['required', 'string', 'max:255'],
            'email'                     => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:' . User::class],
            'phone_number'              => ['required', 'string', 'min:10', 'max:11'],
            'crm'                       => ['required', 'string', 'size:6', 'regex:/^[0-9]{6}$/', 'unique:' . Doctor::class],
            'crm_uf'                    => ['required', 'string', 'size:2', new ValidUF()],
            'password'                  => ['required', 'confirmed', Rules\Password::defaults()],
            'user_type'                 => ['required', 'string', 'in:doctor,patient'],
            'addresses'                 => ['array', 'min:1'],
            'addresses.*.location_name' => ['required', 'string', 'max:255'],
            'addresses.*.full_address'  => ['required', 'string', 'max:255'],
            'addresses.*.complement'    => ['nullable', 'string', 'max:255'],
            'addresses.*.cep'           => ['required', 'string', 'size:8'],
            'terms_accepted'            => ['required', 'accepted'],
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'phone_number' => $this->cleanNumeric($this->input('phone_number')),
            'addresses'    => is_array($addresses = $this->input('addresses', []))
                ? array_map(function ($address) {
                    if (is_array($address) && isset($address['cep'])) {
                        $address['cep'] = $this->cleanNumeric($address['cep']);
                    }

                    return $address;
                }, $addresses)
                : [],
        ]);
    }

    /**
     * Clean numeric values.
     */
    private function cleanNumeric(mixed $value): string
    {
        if (! is_string($value)) {
            return '';
        }

        return preg_replace('/\D/', '', $value) ?? '';
    }
}
