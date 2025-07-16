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
            background-color: #F4F4F5 !important;
            color: #059669;
            transition: 0.3s ease-in-out;
        }

        .btn-outline-success-app:hover {
            color: #059669;
            transition: 0.4s ease-in-out;
        }

        .btn-outline-success-app,.user-container{
            font-weight: 600;
        }


        @media (min-width: 768px) {
            /* .col-md-3 {
                width: 20% !important;
            }
             .col-md-9 {
                width: 80% !important;
            } */
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
