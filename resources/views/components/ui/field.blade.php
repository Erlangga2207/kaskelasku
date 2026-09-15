@props([
    'label',
    'name',
    'type' => 'text',
    'value' => null,
    'bantuan' => null,
    'wajib' => false,
    'prefix' => null,
])

@php
    $id = $attributes->get('id', $name);
    $error = $errors->first($name);
    $bantuanId = $bantuan ? "{$id}-bantuan" : null;
    $errorId = $error ? "{$id}-error" : null;
    $describedBy = trim(implode(' ', array_filter([$bantuanId, $errorId]))) ?: null;
@endphp

<div class="space-y-1.5">
    {{-- Label selalu terlihat. Placeholder bukan pengganti label. --}}
    <label for="{{ $id }}" class="block text-sm font-semibold text-ink">
        {{ $label }}
        @if ($wajib)
            <span class="text-keluar" aria-hidden="true">*</span>
            <span class="sr-only">(wajib diisi)</span>
        @endif
    </label>

    <div class="relative">
        @if ($prefix)
            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-sm text-ink-faint">
                {{ $prefix }}
            </span>
        @endif

        <input
            type="{{ $type }}"
            id="{{ $id }}"
            name="{{ $name }}"
            value="{{ old($name, $value) }}"
            @if ($wajib) required @endif
            @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
            @if ($error) aria-invalid="true" @endif
            {{ $attributes->merge([
                'class' => 'block w-full min-h-11 rounded-xl border bg-card px-3.5 py-2.5 text-base text-ink '
                    .'placeholder:text-ink-faint transition-colors '
                    .'focus:outline-2 focus:outline-offset-2 focus:outline-ring-brand '
                    .($prefix ? 'pl-11 ' : '')
                    .($error ? 'border-keluar' : 'border-line-strong'),
            ]) }}
        >
    </div>

    @if ($bantuan)
        {{-- Keterangan permanen, bukan hanya placeholder yang hilang saat diketik. --}}
        <p id="{{ $bantuanId }}" class="text-sm text-ink-faint">{{ $bantuan }}</p>
    @endif

    @if ($error)
        {{-- Pesan galat menempel di bawah field-nya, bukan menumpuk di atas halaman. --}}
        <p id="{{ $errorId }}" role="alert" class="flex items-start gap-1.5 text-sm font-medium text-keluar">
            <x-icon name="peringatan" class="mt-0.5 size-4 shrink-0" />
            <span>{{ $error }}</span>
        </p>
    @endif
</div>
