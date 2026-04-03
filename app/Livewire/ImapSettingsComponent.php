<?php

namespace App\Livewire;

use App\Exceptions\Imap\ImapConnectionException;
use App\Models\ImapSetting;
use App\Services\ImapConnectionService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class ImapSettingsComponent extends Component
{
    public string $hostname = '';

    public int $port = 993;

    public string $username = '';

    public string $password = '';

    public ?string $encryption = 'ssl';

    public bool $isActive = true;

    public string $testMessage = '';

    public string $testStatus = '';

    protected function rules(): array
    {
        return [
            'hostname' => ['required', 'string', 'max:255'],
            'port' => ['required', 'integer', 'min:1', 'max:65535'],
            'username' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
            'encryption' => ['nullable', 'in:ssl,tls'],
            'isActive' => ['boolean'],
        ];
    }

    public function mount(): void
    {
        $settings = Auth::user()?->imapSetting;

        if (! $settings instanceof ImapSetting) {
            return;
        }

        $this->hostname = $settings->hostname;
        $this->port = $settings->port;
        $this->username = $settings->username;
        $this->password = $settings->decrypted_password ?? '';
        $this->encryption = $settings->encryption;
        $this->isActive = $settings->is_active;
    }

    public function testConnection(): void
    {
        $validated = $this->validate();

        try {
            app(ImapConnectionService::class)->testConnection(
                $validated['hostname'],
                $validated['port'],
                $validated['username'],
                $validated['password'],
                $validated['encryption'],
            );

            $this->testStatus = 'success';
            $this->testMessage = 'Connection successful.';
        } catch (ImapConnectionException $exception) {
            $this->testStatus = 'error';
            $this->testMessage = $exception->getMessage();
            $this->addError('hostname', $exception->getMessage());
        }
    }

    public function save(): void
    {
        $validated = $this->validate();

        try {
            app(ImapConnectionService::class)->testConnection(
                $validated['hostname'],
                $validated['port'],
                $validated['username'],
                $validated['password'],
                $validated['encryption'],
            );
        } catch (ImapConnectionException $exception) {
            $this->testStatus = 'error';
            $this->testMessage = $exception->getMessage();
            $this->addError('hostname', $exception->getMessage());

            return;
        }

        Auth::user()?->imapSetting()->updateOrCreate(
            [],
            [
                'hostname' => $validated['hostname'],
                'port' => $validated['port'],
                'username' => $validated['username'],
                'password' => $validated['password'],
                'encryption' => $validated['encryption'],
                'is_active' => $validated['isActive'],
            ],
        );

        $this->testStatus = 'success';
        $this->testMessage = 'IMAP settings saved.';

        $this->dispatch('imap-settings-saved');
    }

    public function render()
    {
        return view('livewire.imap-settings-component');
    }
}
