<div>
    @if (session()->has('message'))
        <div class="alert alert-success">{{ session('message') }}</div>
    @endif

    <form wire:submit.prevent="upload">
        <input type="file" wire:model="documents" multiple>
        @error('documents.*') <span class="text-danger">{{ $message }}</span> @enderror

        <button class="btn btn-success mt-2" type="submit">Upload</button>
    </form>

    <hr>

    <h5>Uploaded Documents</h5>
    <ul class="list-group">
    </ul>
</div>
