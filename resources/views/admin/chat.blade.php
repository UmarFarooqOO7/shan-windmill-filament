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
        .bg-default-app, .btn-outline-success-app, .user-container:hover{
            background-color: #10B981 !important;
            color: white;
            transition: 0.3s ease-in-out;
        }
    </style>
@endpush

@section('content')
    @livewire('chat-panel')
@endsection
