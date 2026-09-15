@props(['label', 'name', 'opsi' => [], 'value' => null, 'bantuan' => null, 'wajib' => false, 'kosong' => null])

@php
    $id = $attributes->get('id', $name);
    $error = $errors->first($name);
    $terpilih = old($name, $value);
    $describedBy = trim(implode(' ', array_filter([
        $bantuan ? "{$id}-bantuan" : null,
        $error ? "{$id}-error" : null,
    ]))) ?: null;
@endphp

<div class="space-y-1.5">
    <label for="{{ $id }}" class="block text-sm font-semibold text-ink">
        {{ $label }}
        @if ($wajib)
            <span class="text-keluar" aria-hidden="true">*</span>
            <span class="sr-only">(wajib diisi)</span>
        @endif
    </label>

    <select id="{{ $id }}" name="{{ $name }}"
            @if ($wajib) required @endif
            @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
            @if ($error) aria-invalid="true" @endif
            {{ $attributes->merge([
                'class' => 'block w-full min-h-11 rounded-xl border bg-card px-3 py-2.5 text-base text-ink '
                    .'focus:outline-2 focus:outline-offset-2 focus:outline-ring-brand '
                    .($error ? 'border-keluar' : 'border-line-strong'),
            ]) }}>
        @if ($kosong)
            <option value="">{{ $kosong }}</option>
        @endif
        @foreach ($opsi as $nilai => $teks)
            <option value="{{ $nilai }}" @selected((string) $terpilih === (string) $nilai)>{{ $teks }}</option>
        @endforeach
    </select>

    @if ($bantuan)
        <p id="{{ $id }}-bantuan" class="text-sm text-ink-faint">{{ $bantuan }}</p>
    @endif

    @if ($error)
        <p id="{{ $id }}-error" role="alert" class="flex items-start gap-1.5 text-sm font-medium text-keluar">
            <x-icon name="peringatan" class="mt-0.5 size-4 shrink-0" />
            <span>{{ $error }}</span>
        </p>
    @endif
</div>
