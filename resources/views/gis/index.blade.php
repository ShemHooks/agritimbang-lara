@extends('layouts.app')

@section('content')
    <main class="min-h-screen bg-gray-50 p-6">
        <div class="mx-auto max-w-7xl">
            <h1 class="mb-2 text-3xl font-bold text-gray-900">
                AgriTimbang GIS Monitoring
            </h1>

            <p class="mb-6 text-gray-600">
                Cebu Province Livestock Monitoring
            </p>

            <div id="map" style="height: 600px; width: 100%;" class="rounded-xl border border-gray-200"></div>
        </div>
    </main>
@endsection