<!DOCTYPE html>
@php
    $isDesktopApp = (bool) config('nativephp-internal.running', env('NATIVEPHP_RUNNING', false));
@endphp
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-pulse-desktop="{{ $isDesktopApp ? '1' : '0' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name'))</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    @include('partials.full-screen-loader')
    @include('partials.full-screen-loader-fallback')
    @include('partials.desktop-installer-update-modal')
    @yield('content')
</body>
</html>
