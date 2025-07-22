<div class="space-y-4">
    @forelse ($documents as $index => $file)
        <div class="flex items-center gap-4 p-2 border rounded">
            @if(Str::contains($file, ['jpg', 'jpeg', 'png', 'gif']))
                <img src="{{ Storage::url($file) }}" alt="Preview" class="w-16 h-16 object-cover rounded">
            @elseif(Str::contains($file, ['pdf']))
                <a href="{{ Storage::url($file) }}" target="_blank" class="text-blue-600 underline">View PDF</a>
            @else
                <a href="{{ Storage::url($file) }}" target="_blank" class="text-gray-800">{{ basename($file) }}</a>
            @endif

            <button wire:click="removeDocument({{ $index }})"
                    class="text-red-600 hover:text-red-800 ml-auto">
                ✖
            </button>
        </div>
    @empty
        {{-- <div class="text-sm text-gray-500">No documents uploaded.</div> --}}
    @endforelse
</div>
