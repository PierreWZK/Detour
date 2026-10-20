# Détour · Le plein moins cher, détour compris

> Jour 08 du défi **30 jours, 30 projets** · Laravel · Leaflet · Open data · 🚧 **En construction** (page vitrine prête, recherche à venir)

Les applis de carburant montrent la station la moins chère… sans dire si le détour vaut le coup. **Détour** calcule le **gain net** de chaque station : l'économie sur le plein, moins le carburant brûlé (et, si on veut, le temps perdu) pour y aller. Il répond simplement : « Allez à Intermarché Seynod : +3,40 € net pour 6 minutes de détour ».

📄 Les règles détaillées sont dans le [cahier des charges](CAHIER_DES_CHARGES.md).

## Au programme

- Point de départ (position ou adresse) et, en option, une destination : le trajet prévu.
- Profil du plein : carburant (gazole, SP95-E10, SP98, E85, GPL), litres à mettre, consommation de la voiture (L/100 km).
- Stations autour du trajet avec leur prix du jour (données officielles, sans clé).
- Pour chaque station : **détour en km et en minutes** (itinéraire réel) et **gain net** par rapport à la station de référence.
- Classement par gain net, carte (Leaflet + OpenStreetMap) et lien d'itinéraire vers la station choisie.
- Prix périmés (plus de 48 h) signalés, stations fermées ou en rupture écartées.

## Lancer en local

```bash
composer install
cp .env.example .env && php artisan key:generate
npm install && npm run dev   # dans un second terminal
php artisan serve            # http://127.0.0.1:8000
php artisan test             # tests : calcul du gain, page vitrine
```

Pas de base de données : le cache et les sessions sont des fichiers (`storage/`).

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
app/Detour/              GainCalculator (le calcul, sans dépendance) et Gain (le résultat)
app/Http/Controllers/    HomeController : la page vitrine et ses exemples
app/Support/Format.php   Montants et prix à la française
resources/views/         layouts/app (en-tête, aperçu de lien, pied de page), home
resources/css/app.css    Les styles (sans framework CSS)
tests/                   Unit : le calcul ; Feature : la page vitrine
```

## Mise en ligne

Prévue sur Coolify : **https://detour.projectp.fr**.

## Stack

Laravel · Leaflet · Open data.
