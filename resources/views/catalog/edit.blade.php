@extends('layouts.app')

@section('title', 'Edit Catalog Metadata')

@section('content')
    @if ($readOnly)
        <div class="alert alert-warning" role="alert">Catalog metadata changes are blocked while RAKIT is in READ_ONLY mode.</div>
    @endif

    <form method="POST" action="{{ route('catalog.update', $catalogItem) }}" class="card">
        @csrf
        @method('PUT')
        <div class="card-body">
            @include('catalog._form')
        </div>
    </form>
@endsection
