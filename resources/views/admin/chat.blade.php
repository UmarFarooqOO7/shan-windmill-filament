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

        .bg-default-app,
        .btn-outline-success-app,
        .user-container:hover {
            background-color: #10B981 !important;
            color: white;
            transition: 0.3s ease-in-out;
        }

        @media (max-width: 768px) {
            #chat-box {
                min-height: 50vh;
                height: auto !important;
            }

            .user-container img {
                width: 35px;
                height: 35px;
            }

            .modal-content {
                border-radius: 0;
            }
        }
    </style>
@endpush

@section('content')
    @livewire('chat-panel')
@endsection
