@extends('layouts.app')

@section('title', $toernooi->naam)

@section('content')
@include('pages.toernooi.dashboard', ['toernooi' => $toernooi, 'statistieken' => $statistieken])
@endsection
