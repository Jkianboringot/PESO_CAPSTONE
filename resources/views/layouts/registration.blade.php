<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'PESO Catanduanes') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])

        <style>
            body { font-family: 'Figtree', sans-serif; }

            /* Left panel dots pattern */
            .hero-pattern {
                background-color: #1F4E79;
                background-image: radial-gradient(rgba(255,255,255,0.08) 1px, transparent 1px);
                background-size: 22px 22px;
            }

            /* Step connector line */
            .step-connector {
                flex: 1;
                height: 2px;
                background: #e2e8f0;
                margin: 0 4px;
                position: relative;
                top: -12px;
                transition: background 0.3s ease;
            }
            .step-connector.done { background: #1F4E79; }

            /* Input focus ring */
            .field-input:focus {
                outline: none;
                border-color: #1F4E79;
                box-shadow: 0 0 0 3px rgba(31,78,121,0.12);
            }

            /* Skill checkbox card */
            .skill-card {
                transition: all 0.15s ease;
                cursor: pointer;
            }
            .skill-card:hover { border-color: #1F4E79; background: #eff6ff; }
            .skill-card.selected {
                border-color: #1F4E79;
                background: #eff6ff;
            }

            /* Slide animation between steps */
            .step-body { animation: fadeSlide 0.25s ease; }
            @keyframes fadeSlide {
                from { opacity: 0; transform: translateY(8px); }
                to   { opacity: 1; transform: translateY(0); }
            }
        </style>
    </head>
    <body class="bg-slate-100 antialiased min-h-screen">
        {{ $slot }}
    </body>
</html>