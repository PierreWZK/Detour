# Détour · Le plein moins cher, détour compris

> Jour 08 du défi **30 jours, 30 projets** · Laravel · Leaflet · Open data · 🟢 **En ligne : [detour.projectp.fr](https://detour.projectp.fr)**

![Page d'accueil de Détour](docs/preview.png)

Les applis de carburant montrent la station la moins chère… sans dire si le détour vaut le coup. **Détour** calcule le **gain net** de chaque station : l'économie sur le plein, moins le carburant brûlé (et, si on veut, le temps perdu) pour y aller. Il répond simplement : « Allez à 18 bd de la Rocade, Annecy : +2,93 € net pour 10 minutes de détour ».

📄 Les règles détaillées sont dans le [cahier des charges](CAHIER_DES_CHARGES.md).

## Fonctionnalités

- Point de départ (position ou adresse) et, en option, une destination : le trajet prévu.
- Profil du plein : carburant (gazole, SP95-E10, SP98, E85, GPL), litres à mettre, consommation de la voiture (L/100 km).
- Stations autour du trajet avec leur prix du jour (données officielles, sans clé).
- Pour chaque station : **détour en km et en minutes** (itinéraire réel) et **gain net** par rapport à la station de référence.
- Classement par gain net, carte (Leaflet + OpenStreetMap) et lien d'itinéraire vers la station choisie.
- Prix périmés (plus de 48 h) signalés, stations en rupture écartées.
- Fiche de chaque station : détail du calcul, prix de tous les carburants, horaires, automate 24/24, services.
- Suggestions d'adresses pendant la frappe, bouton « Ma position », profil du plein retenu sur l'appareil.
- Sans destination, chaque station est comparée à la plus proche : seul le trajet **en plus** de l'aller-retour vers la plus proche est compté.
- La carte ne montre que les meilleures stations (celles qui rapportent et la référence) ; la liste les garde toutes.
- Une API en panne donne un message clair, jamais une page blanche.

Le flux officiel ne donne pas le **nom** des stations (ni l'enseigne) : elles sont désignées par leur adresse.

## 🔥 Jeu du jour (`/jeu`)

Deux défis par jour, les mêmes pour tout le monde, une tentative chacun, sans compte (série retenue sur l'appareil) :

- **⛽ Le juste prix** : deviner le prix moyen du gazole en France aujourd'hui. La réponse est la moyenne des stations mises à jour depuis 48 h, figée pour la journée. Appréciation selon l'écart : 🎯 Dans le mille (≤ 0,5 ct), 🔥 Brûlant (≤ 2 ct), ♨️ Chaud (≤ 5 ct), 🙂 Tiède (≤ 10 ct), 🧊 Froid.
- **🚗 Quiz auto** : une question sur la voiture, la conduite ou le carburant ([`app/Detour/Game/questions.php`](app/Detour/Game/questions.php), 30 questions), avec l'explication après la réponse.

Les réponses restent sur le serveur : la page ne les contient pas, on les obtient en jouant. Le résultat se partage façon Wordle.

## Lancer en local

```bash
composer install
cp .env.example .env && php artisan key:generate
npm install && npm run dev   # dans un second terminal
php artisan serve            # http://127.0.0.1:8000
php artisan test             # tests : calcul du gain, recherche (API simulées), pages
```

Pas de base de données : le cache et les sessions sont des fichiers (`storage/`).

Sous Windows, si PHP n'a pas de liste de certificats (erreur cURL 60), renseigner `HTTP_CA_BUNDLE` dans `.env`, par exemple avec celle de Git : `"C:/Program Files/Git/mingw64/etc/ssl/certs/ca-bundle.crt"`.

## La recherche

1. **Les stations candidates** : celles qui vendent le carburant choisi dans un rayon de 10 km (sans destination), ou autour du trajet (destination à 150 km au plus), via le flux des prix, mis en cache 10 minutes.
2. **Une présélection** de 40 stations (meilleur coût estimé à vol d'oiseau, plus les plus proches), pour rester sous les limites du serveur d'itinéraires.
3. **Le détour réel** en une requête OSRM `table` : (départ → station → arrivée) − (départ → arrivée), ou l'aller-retour sans destination. Mis en cache une journée.
4. **La référence** : la moins chère des stations « sur le trajet » (détour de 1 km au plus), à défaut la plus proche.
5. **Le classement** par gain net (à gain égal, la référence puis le plus petit détour).

## Le calcul

Le gain se mesure par rapport à la station de référence (la moins chère sur le trajet, à défaut la plus proche) :

```
économie            = (prix de référence − prix de la station) × litres
carburant du détour = km de détour × consommation ÷ 100 × prix de la station
temps (facultatif)  = minutes de détour ÷ 60 × valeur de l'heure
gain net            = économie − carburant du détour − temps
```

**Exemple vérifiable à la main** : 40 L, 6,5 L/100 km, station de référence à 1,799 €/L, station à 1,749 €/L à 3 km de détour.
Économie = 0,05 × 40 = 2,00 € ; carburant du détour = 3 × 6,5 ÷ 100 = 0,195 L × 1,749 = 0,34 € ; **gain net = 1,66 €**.
Rentable dès 0,34 ÷ 0,05 = 6,8 → **7 litres**.

Le calcul vit dans [`app/Detour/GainCalculator.php`](app/Detour/GainCalculator.php), testé dans [`tests/Unit/GainCalculatorTest.php`](tests/Unit/GainCalculatorTest.php). La page vitrine affiche des exemples calculés par cette même classe.

## Structure

```
app/Detour/              GainCalculator (le calcul, sans dépendance), Gain, Station, Fuel
app/Detour/Api/          Clients des trois API (prix, adresses, itinéraires), avec cache et messages d'erreur
app/Detour/Search/       DetourSearch (candidates, détours, référence, classement) et ses objets
app/Detour/Game/         Jeu du jour : DailyPrice (le juste prix), DailyQuiz et ses questions
app/Http/Controllers/    HomeController (vitrine), SearchController (recherche, adresses), GameController (jeu du jour)
app/Support/Format.php   Montants et prix à la française
resources/views/         layouts/app (en-tête, aperçu de lien, pied de page), home, search
resources/js/search.js   Suggestions d'adresses, position, profil retenu, carte Leaflet
resources/js/game.js     Jeu du jour : tentative, série, partage
resources/css/app.css    Les styles (sans framework CSS)
tests/                   Unit : le calcul ; Feature : la vitrine, la recherche et le jeu (API simulées)
```

## Mise en ligne

Sur Coolify : **https://detour.projectp.fr**, redéployé à chaque push sur `master`.

| Réglage | Valeur |
| --- | --- |
| Build pack | Nixpacks (PHP 8.4 + Node 22), commande de build `npm run build` |
| nginx | [`nginx.template.conf`](nginx.template.conf), modèle lu par Nixpacks |
| Après chaque déploiement | `php artisan optimize` |
| Variables | `APP_KEY`, `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL`, `SESSION_DRIVER=file`, `CACHE_STORE=file`, `LOG_CHANNEL=stderr`, `NIXPACKS_PHP_ROOT_DIR=/app/public`, `NIXPACKS_PHP_FALLBACK_PATH=/index.php`, `NIXPACKS_NODE_VERSION=22` |

Pas de base de données : le cache (prix, itinéraires, juste prix du jour) est en fichiers et repart de zéro à chaque déploiement.

## Stack

Laravel · Leaflet · Open data.
