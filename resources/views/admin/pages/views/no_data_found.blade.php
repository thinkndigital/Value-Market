@extends('admin/layout')
@section('title')
    {{ labels('admin_labels.no_data_found', 'No Data Found') }}
@endsection
@php
    use App\Services\MediaService;
@endphp
@section('content')
    <div class="d-flex justify-content-center align-items-center">
        <img alt="" src="{{ app(MediaService::class)->getImageUrl('system_images/no_data_found.png') }}" />
    </div>
@endsection
