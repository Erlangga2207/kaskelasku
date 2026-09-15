@php $baru = ! $siswa->exists; @endphp

<x-layouts.app :judul="$baru ? 'Tambah siswa' : 'Ubah siswa'">
    <x-ui.button :href="route('siswa.index')" variant="ghost" icon="kiri" size="sm">Kembali ke daftar siswa</x-ui.button>

    <x-ui.card
        :judul="$baru ? 'Data siswa baru' : 'Ubah data '.$siswa->nama"
        keterangan="Aplikasi ini sengaja hanya menyimpan nama, nomor absen, dan status. Jangan menuliskan NIS, nomor HP, atau alamat di kolom nama.">

        <form method="POST"
              action="{{ $baru ? route('siswa.store') : route('siswa.update', $siswa) }}"
              class="space-y-5">
            @csrf
            @unless ($baru) @method('PUT') @endunless

            <x-ui.field label="Nama lengkap" name="nama" :value="$siswa->nama" wajib autofocus
                        autocomplete="off" placeholder="Adinda Ayu Lestari" />

            <x-ui.field label="Nomor absen" name="no_absen" type="number" inputmode="numeric" min="1" max="200"
                        :value="$siswa->no_absen ?? $nomorBerikutnya"
                        bantuan="Boleh dikosongkan bila kelasmu tidak memakai nomor absen." />

            <div class="grid gap-5 sm:grid-cols-2">
                <x-ui.field label="Tanggal mulai aktif" name="tgl_mulai_aktif" type="date" wajib
                            :value="$siswa->tgl_mulai_aktif?->toDateString()"
                            bantuan="Tagihan hanya dibuat untuk periode sejak tanggal ini." />

                <x-ui.field label="Tanggal berhenti" name="tgl_berhenti" type="date"
                            :value="$siswa->tgl_berhenti?->toDateString()"
                            bantuan="Isi bila siswa pindah atau keluar di tengah tahun." />
            </div>

            <div class="flex flex-col gap-2 border-t border-line pt-5 sm:flex-row-reverse sm:justify-start">
                <x-ui.button type="submit" size="lg">{{ $baru ? 'Simpan siswa' : 'Simpan perubahan' }}</x-ui.button>
                <x-ui.button :href="route('siswa.index')" variant="secondary" size="lg">Batal</x-ui.button>
            </div>
        </form>
    </x-ui.card>
</x-layouts.app>
