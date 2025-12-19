@extends('ecommerce::layouts.master')

@section('content')
    <h1>Ecommerce Module</h1>

    <p>
        This view is loaded from module: {!! config('ecommerce.name') !!}
    </p>
@endsection