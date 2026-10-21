@php($description = 'Détour calcule le gain net de chaque station-service : l\'économie sur le plein, moins le carburant brûlé pour y aller. Prix officiels du jour, itinéraires réels.')
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Détour · Le plein moins cher, détour compris')</title>
    <meta name="description" content="{{ $description }}">
    <meta name="theme-color" content="#14171c">
    <meta property="og:type" content="website">
    <meta property="og:locale" content="fr_FR">
    <meta property="og:site_name" content="Détour">
    <meta property="og:title" content="@yield('title', 'Détour · Le plein moins cher, détour compris')">
    <meta property="og:description" content="{{ $description }}">
    <meta property="og:url" content="{{ url()->current() }}">
    <link rel="canonical" href="{{ url()->current() }}">
    <meta property="og:image" content="{{ asset('og.png') }}">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:alt" content="Détour · aperçu du site">
    <meta name="twitter:card" content="summary_large_image">
    @if (request()->routeIs('home'))
    {{-- Données structurées pour les moteurs de recherche (@@ échappe le @ de Blade) --}}
    <script type="application/ld+json">
    {
      "@@context": "https://schema.org",
      "@@type": "WebApplication",
      "name": "Détour",
      "url": "https://detour.projectp.fr/",
      "description": "Le plein moins cher, détour compris : le gain net de chaque station-service, l'économie sur le plein moins le carburant du détour. Prix officiels du jour, itinéraires réels.",
      "applicationCategory": "TravelApplication",
      "operatingSystem": "Web",
      "inLanguage": "fr",
      "isAccessibleForFree": true,
      "offers": { "@@type": "Offer", "price": "0", "priceCurrency": "EUR" },
      "image": "https://detour.projectp.fr/og.png",
      "author": { "@@type": "Person", "name": "PierreWZK", "url": "https://github.com/PierreWZK" },
      "isPartOf": { "@@type": "WebSite", "name": "ProjectP", "url": "https://projectp.fr/" }
    }
    </script>
    @endif
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <header class="nav">
        <div class="container nav-inner">
            <a href="{{ route('home') }}" class="brand" aria-label="Détour, accueil">
                <img src="{{ asset('favicon.svg') }}" alt="" width="34" height="34">
                Détour
            </a>
            <nav class="nav-links" aria-label="Sections">
                <a href="{{ route('home') }}#comment">Comment ça marche</a>
                <a href="{{ route('home') }}#calcul">Le calcul</a>
                <a href="{{ route('home') }}#donnees">Les données</a>
            </nav>
            <a href="{{ route('game') }}" @class(['nav-game', 'active' => request()->routeIs('game')])>🔥 <span class="long">Jeu du jour</span><span class="short">Jeu</span></a>
            <a href="{{ route('search') }}" class="btn small"><span class="long">Trouver ma station</span><span class="short">Ma station</span></a>
        </div>
    </header>

    <main>
        @yield('content')
    </main>

    <footer class="footer container">
        <span>Détour · Jour 08 du défi <a href="https://projectp.fr/">30 jours, 30 projets</a></span>
        <span>Fait avec Laravel · <a href="https://github.com/PierreWZK/Detour" target="_blank" rel="noopener">Code sur GitHub ↗</a></span>
    </footer>
</body>
</html>
