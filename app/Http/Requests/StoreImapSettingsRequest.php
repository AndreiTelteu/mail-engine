<?php

namespace App\Http\Requests;

use App\Models\ImapSetting;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreImapSettingsRequest extends FormRequest
{
    private ?ImapSetting $stored = null;

    /**
     * Every user configures their own single mailbox, so authentication is the
     * only requirement; the `auth` middleware on the route enforces it.
     */
    public function authorize(): bool
    {
        return $this->user() instanceof User;
    }

    /**
     * "No encryption" arrives from the form as an empty string.
     */
    protected function prepareForValidation(): void
    {
        if ($this->input('encryption') === '') {
            $this->merge(['encryption' => null]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'hostname' => ['required', 'string', 'max:255'],
            'port' => ['required', 'integer', 'between:1,65535'],
            'username' => ['required', 'string', 'max:255'],
            'password' => [$this->hasStoredPassword() ? 'nullable' : 'required', 'string', 'max:255'],
            'encryption' => ['nullable', Rule::in(['ssl', 'tls'])],
            'isActive' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'password.required' => 'Enter the mailbox password so the connection can be tested.',
        ];
    }

    /**
     * The stored password, kept when the form is submitted without a new one.
     *
     * @return array{hostname: string, port: int, username: string, password: string, encryption: ?string, isActive: bool}
     */
    public function settings(): array
    {
        /** @var array{hostname: string, port: int, username: string, password: ?string, encryption: ?string, isActive: bool} $validated */
        $validated = $this->validated();

        return [
            ...$validated,
            'encryption' => $validated['encryption'] ?: null,
            'password' => filled($validated['password'])
                ? $validated['password']
                : (string) $this->storedSetting()?->decrypted_password,
        ];
    }

    private function hasStoredPassword(): bool
    {
        return filled($this->storedSetting()?->decrypted_password);
    }

    private function storedSetting(): ?ImapSetting
    {
        /** @var User|null $user */
        $user = $this->user();

        return $this->stored ??= $user?->imapSetting()->first();
    }
}
