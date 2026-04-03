<section>
    <form wire:submit="save">
        <input wire:model="hostname" type="text" />
        <input wire:model="port" type="number" />
        <input wire:model="username" type="text" />
        <input wire:model="password" type="password" />
        <select wire:model="encryption">
            <option value="">None</option>
            <option value="ssl">SSL</option>
            <option value="tls">TLS</option>
        </select>
        <input wire:model="isActive" type="checkbox" />

        <button type="button" wire:click="testConnection">Test connection</button>
        <button type="submit">Save</button>
    </form>

    @if ($testMessage !== '')
        <div data-status="{{ $testStatus }}">{{ $testMessage }}</div>
    @endif

    @error('hostname')
        <div>{{ $message }}</div>
    @enderror
</section>
