@extends('layouts.app')

@section('title', 'Chat Panel')

@push('styles')
    <style>
        .btn-circle {
            border-radius: 50%;
            width: 22px;
            height: 22px;
            text-align: center;
            line-height: 16px;
            padding: 0;
        }
    </style>
@endpush

@section('content')
    <h2 class="mb-4">Chat Panel</h2>

    @livewire('chat-panel')
@endsection
