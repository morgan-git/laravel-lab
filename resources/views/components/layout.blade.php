@props([
    'title' => "Skoopski Potatoes!"
])

<!DOCTYPE html>
<html lang="en" data-theme="darkwave">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="bg-background text-foreground">

<x-nav/>
@if (0 && $errors->any())
    <div class="mb-4 rounded border border-red-500 bg-red-50 p-3 text-red-700">
        <ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
<!-- MAIN CONTENT -->
<main class="max-w-6xl mx-auto mt-6 pb-10 px-6">
    <div class="prose prose-invert">
        {{ $slot }}
    </div>
</main>
{{--
<div x-data="{ greeting: 'Hello, World!', show: false }" class="max-w-md mx-auto mt-10">
    <p x-show="show" x-text="greeting"></p>
    <input type="text" x-model="greeting" class="input input-bordered w-full max-w-xs" placeholder="Type a greeting...">
    <button @click="show = !show" class="btn btn-primary mt-2">Set Greeting</button>
</div>
--}}

@session('success')
    <div
    x-data="{ show: true }"
    x-init="setTimeout(() => show = false, 3000)"
    x-show="show"
    x-transition.opacity.duration.1000ms
    class="fixed bottom-4 right-4 bg-green-500 text-white px-4 py-2 rounded shadow">
        {{ $value }}
    </div>
@endsession

<!-- Error/Failure Message (Red) -->
@if (session('error'))
    <div x-data="{ show: true }" x-init="setTimeout(() => show = false, 4000)" x-show="show" x-transition.opacity.duration.1000ms class="fixed bottom-4 right-4 bg-red-500 text-white px-4 py-2 rounded shadow">
        {{ session('error') }}
    </div>
@endif

<!-- Warning Message (Orange) -->
@if (session('warning'))
    <div x-data="{ show: true }" x-init="setTimeout(() => show = false, 4000)" x-show="show" x-transition.opacity.duration.1000ms class="fixed bottom-4 right-4 bg-orange-500 text-white px-4 py-2 rounded shadow">
        {{ session('warning') }}
    </div>
@endif
</body>
</html>
