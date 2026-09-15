@php
    $pesan = collect([
        'sukses' => session('sukses'),
        'peringatan' => session('peringatan'),
        'galat' => session('galat'),
    ])->filter();
@endphp

@if ($pesan->isNotEmpty())
    {{-- aria-live polite: dibacakan pembaca layar tanpa merebut fokus pengguna. --}}
    <div aria-live="polite" class="space-y-2">
        @foreach ($pesan as $tipe => $isi)
            <x-ui.alert :tipe="$tipe">{{ $isi }}</x-ui.alert>
        @endforeach
    </div>
@endif
