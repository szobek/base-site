<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'site_name' => ['required', 'string', 'max:80'],
            'contact_address' => ['nullable', 'string', 'max:200'],
            'contact_phone' => ['nullable', 'string', 'max:40'],
            'contact_email' => ['nullable', 'string', 'email', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'site_name.required' => 'Az oldal nevének megadása kötelező.',
            'site_name.max' => 'Az oldal neve legfeljebb 80 karakter lehet.',
            'contact_address.max' => 'A cím legfeljebb 200 karakter lehet.',
            'contact_phone.max' => 'A telefonszám legfeljebb 40 karakter lehet.',
            'contact_email.email' => 'Az e-mail cím formátuma érvénytelen.',
            'contact_email.max' => 'Az e-mail cím legfeljebb 255 karakter lehet.',
        ];
    }
}
