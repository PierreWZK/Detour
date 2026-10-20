# Détour · Le plein moins cher, détour compris

> Jour 08 du défi **30 jours, 30 projets** · Laravel · Leaflet · Open data · 🚧 **En construction** (recherche fonctionnelle en local, mise en ligne à venir)

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
- Une API en panne donne un message clair, jamais une page blanche.

Le flux officiel ne donne pas le **nom** des stations (ni l'enseigne) : elles sont désignées par leur adresse.

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
app/Http/Controllers/    HomeController (vitrine), SearchController (recherche, suggestions d'adresses)
app/Support/Format.php   Montants et prix à la française
resources/views/         layouts/app (en-tête, aperçu de lien, pied de page), home, search
resources/js/search.js   Suggestions d'adresses, position, profil retenu, carte Leaflet
resources/css/app.css    Les styles (sans framework CSS)
tests/                   Unit : le calcul ; Feature : la vitrine et la recherche (API simulées)
```

## Mise en ligne

Prévue sur Coolify : **https://detour.projectp.fr**.

## Stack

Laravel · Leaflet · Open data.
