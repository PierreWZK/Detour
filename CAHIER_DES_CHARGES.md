# Jour 8/30 — Détour, le plein moins cher, détour compris

> Stack : **Laravel**, **Leaflet**, **Open data** · hébergement **Coolify**, sous-domaine `detour.projectp.fr`.
> Durée visée : **2 h 45** pour le MVP, les bonus ensuite.

Ce document est notre contrat : on le suit étape par étape, et toute décision qui s'en écarte est d'abord notée ici. Il s'ajuste au début de la journée.

## 1. L'idée

Les applis de carburant montrent la station la moins chère… sans dire si le détour vaut le coup. **Détour** calcule le **gain net** de chaque station : l'économie sur le plein, moins le carburant brûlé (et, si on veut, le temps perdu) pour y aller. Il répond simplement : « Allez à Intermarché Seynod : +3,40 € net pour 6 minutes de détour ».

## 2. MVP — ce qui doit fonctionner à la fin

1. Point de départ (position ou adresse) et, en option, une destination : le trajet prévu.
2. Profil du plein : carburant (gazole, SP95-E10, SP98, E85, GPL), litres à mettre, consommation de la voiture (L/100 km).
3. Stations autour du trajet avec leur prix du jour (données officielles, sans clé).
4. Pour chaque station : **détour en km et en minutes** (itinéraire réel) et **gain net** par rapport à la station de référence.
5. Classement par gain net, carte (Leaflet + OpenStreetMap) et lien d'itinéraire vers la station choisie.
6. Prix périmés (plus de 48 h) signalés, stations fermées ou en rupture écartées.
7. En ligne sur Coolify, avec un README à jour et un aperçu de lien (Open Graph).

### Périmètre volontairement limité

- France uniquement (données prix-carburants du ministère).
- Pas de compte : le profil (carburant, consommation) reste dans le navigateur.

## 3. Cahier des charges fonctionnel

### Le calcul

- Station de référence : la moins chère **sans détour** (moins de 500 m du trajet), à défaut la plus proche.
- Gain net = (prix de référence − prix de la station) × litres − litres brûlés pour le détour × prix de la station.
- Option « mon temps vaut X €/h » : le temps du détour est retiré du gain.
- Seuil de rentabilité affiché : « ce détour devient rentable à partir de 28 litres ».

### Le trajet

- Sans destination : aller-retour depuis le point de départ.
- Avec destination : détour = (départ → station → arrivée) − (départ → arrivée).

### La lecture

- Fiche station : prix de chaque carburant, date de mise à jour, horaires, automate 24/24, services.
- Verdict en une phrase en haut de page, carte et liste en dessous.

## 4. Cahier des charges technique

- Prix : API Explore de data.economie.gouv.fr (jeu `prix-des-carburants-en-france-flux-instantane-v2`), filtre géographique `within_distance`, réponses mises en cache 10 minutes côté serveur.
- Adresses : API Adresse (api-adresse.data.gouv.fr), sans clé.
- Itinéraires : OSRM ; le service `table` donne en une requête les durées et distances vers toutes les stations candidates. Le serveur public d'OSRM est un serveur de démo à usage limité : mettre en cache, et prévoir OpenRouteService (clé gratuite) ou son propre OSRM si besoin.
- Calcul du gain dans une classe PHP pure, testée (cas rentable, pas rentable, prix égaux, détour nul).
- Laravel sert de relais et de cache aux trois API ; la carte en Leaflet + tuiles OpenStreetMap (attribution affichée).
- Mise en ligne sur Coolify à chaque push sur la branche principale.

### Décisions prises pendant la construction

- **Pas de nom de station** : le flux officiel ne donne ni le nom ni l'enseigne ; les stations sont désignées par leur adresse.
- **Adresses** : l'API Adresse de data.gouv.fr redirige désormais vers la Géoplateforme de l'IGN (`data.geopf.fr/geocodage`), appelée directement.
- **Référence** : « sur le trajet » = détour de 1 km au plus (500 m de chaque côté).
- **Trajet** : 150 km au plus à vol d'oiseau ; au-delà, c'est le bonus « long trajet ».
- **Itinéraires** : 40 stations au plus envoyées à OSRM (présélection par coût estimé, plus les plus proches).
- **Cache** : les données brutes des API sont mises en cache (Laravel 13 refuse de relire des objets PHP depuis le cache).

## 5. Critères de réussite

- [ ] Le gain net affiché se retrouve à la main sur un exemple (calcul documenté dans le README).
- [ ] Une station moins chère mais trop loin est classée en dessous d'une station proche un peu plus chère.
- [ ] La page répond en moins de 2 secondes grâce au cache.
- [ ] Une API en panne donne un message clair, pas une page blanche.

## 6. Bonus facultatifs

- Alerte de prix : prévenir quand le gazole passe sous un seuil près de chez soi.
- Historique des prix d'une station (graphique sur 30 jours).
- Le long d'un long trajet : meilleure station où s'arrêter sur 400 km.
- Coût du trajet complet et comparaison avec le train.

## 7. Définition du « terminé »

Le MVP fonctionne, les critères de la section 5 sont cochés, le site est en ligne sur `detour.projectp.fr`, le README a son lien et sa capture, et la carte du projet passe à « terminé » sur la vitrine.
