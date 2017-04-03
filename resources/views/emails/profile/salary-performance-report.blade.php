@extends('email-layout')

@section('content')

    @foreach($reports as $report)

        @include('emails.profile.salary-performance-report-html', $report)

    @endforeach

@endsection
