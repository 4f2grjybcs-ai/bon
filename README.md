# Bon Cadeau Generator — plugin WordPress

Génère des **bons cadeaux** à partir de **votre propre visuel**, avec des zones de texte
placées à la souris et remplies **automatiquement** (code unique, numéro, dates)
ou **par vous** (valeur, bénéficiaire, offert par, prestation, message, champs personnalisés).

Chaque bon peut être **téléchargé en PDF** (ou PNG), **imprimé** directement, et
**envoyé par e-mail** avec le PDF en pièce jointe.

## Installation

1. Téléchargez `bon-cadeau.zip` (à la racine de ce dépôt).
2. WordPress → **Extensions → Ajouter → Téléverser une extension** → choisissez le zip → **Activer**.
3. Un menu **Bons cadeaux** apparaît dans l'administration.

Prérequis : PHP 7.4+ avec l'extension **GD + FreeType** (présente chez quasiment tous les hébergeurs).

## Utilisation

### 1. Modèle du bon (à faire une fois)
*Bons cadeaux → Modèle du bon*

- **Choisir le visuel** : votre image (JPG/PNG/WebP), idéalement en haute résolution
  (ex. 2000 px de large pour une impression nette).
- **Ajouter une zone de texte**, puis la **déplacer à la souris** (poignée bleue à droite = largeur,
  flèches du clavier = ajustement fin, Maj+flèche = plus rapide).
- Dans chaque zone, tapez le texte et **insérez des champs** : `{valeur} €`, `Offert à {beneficiaire}`,
  `Code : {code}`, `Valable jusqu'au {date_expiration}`…
- Réglez police, taille, couleur, alignement, majuscules.
- **Aperçu final** : montre le rendu exact généré par le serveur.
- Réglages : préfixe du code, durée de validité par défaut, champs personnalisés, texte de l'e-mail.
- Vous pouvez ajouter **votre propre police** (.ttf/.otf) en bas de la page.

### 2. Créer un bon
*Bons cadeaux → Créer un bon*

Remplissez les champs utilisés par votre modèle. Les champs automatiques
(code, numéro, dates) peuvent être laissés vides ou forcés. Indiquez éventuellement
un e-mail pour l'envoyer directement.

### 3. Gérer les bons
*Bons cadeaux → Tous les bons*

Pour chaque bon : **Voir**, **PDF**, **PNG**, **Imprimer**, **E-mail**, **Marquer utilisé**,
**Supprimer**. Recherche par code, filtre valides / utilisés, statut « Expiré » automatique.

## Champs disponibles

| Champ | Type | Placeholder |
|---|---|---|
| Code unique | auto | `{code}` |
| Numéro du bon | auto | `{numero}` |
| Date d'émission | auto | `{date_emission}` |
| Date d'expiration | auto (émission + validité) | `{date_expiration}` |
| Valeur | à remplir | `{valeur}` |
| Bénéficiaire | à remplir | `{beneficiaire}` |
| Offert par | à remplir | `{offert_par}` |
| Prestation / produit | à remplir | `{prestation}` |
| Message personnalisé | à remplir | `{message}` |
| Champs personnalisés | à remplir | `{votre_champ}` |

## Notes techniques

- Les images des bons sont stockées dans `wp-content/uploads/bon-cadeau/vouchers/`
  (noms aléatoires, accès direct bloqué sous Apache) et servies uniquement aux administrateurs.
- L'e-mail utilise `wp_mail()` : si vos e-mails WordPress partent mal, installez un plugin SMTP
  (ex. WP Mail SMTP).
- Droits requis : `manage_options` (modifiable via le filtre `bon_cadeau_capability`).
- Polices incluses : DejaVu Sans / Serif (licence libre, voir `fonts/LICENSE-DejaVu.txt`).
