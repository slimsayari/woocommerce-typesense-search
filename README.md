# WooCommerce Typesense Search

Recherche instantanée et intelligente pour WooCommerce avec Typesense, incluant la recherche textuelle, vocale, visuelle et sémantique.

## Description

**WooCommerce Typesense Search** est un plugin WordPress premium qui transforme l'expérience de recherche de votre boutique WooCommerce en intégrant la puissance de Typesense. Ce plugin offre une recherche ultra-rapide, tolérante aux fautes de frappe, avec des fonctionnalités avancées comme la recherche vocale, la recherche par image et la recherche sémantique.

### Fonctionnalités principales

#### 🔍 Recherche avancée
- **Recherche textuelle** avec tolérance aux fautes de frappe
- **Recherche vocale** via Web Speech API
- **Recherche par image** avec analyse IA
- **Recherche sémantique** avec embeddings OpenAI
- **Auto-complétion** en temps réel
- **Suggestions intelligentes** basées sur les requêtes

#### ⚡ Performance optimale
- Résultats instantanés (< 50ms)
- Cache des résultats fréquents
- Lazy loading des images
- Debounce sur la frappe
- Pagination infinie

#### 🎯 Filtres et tri
- Filtrage par catégories
- Filtrage par plage de prix
- Filtrage par disponibilité
- Filtrage par promotions
- Tri par pertinence, prix, date, note

#### 📊 Analytics intégrés
- Dashboard des recherches populaires
- Termes sans résultats
- Taux de conversion par recherche
- Export des données en CSV

#### 🔄 Synchronisation automatique
- Indexation automatique des produits
- Synchronisation en temps réel
- Synchronisation en masse avec barre de progression
- Gestion des variations de produits

#### 🌐 Compatibilité
- Support WPML/Polylang
- Compatible avec les principaux thèmes WooCommerce
- Mode dégradé si Typesense indisponible
- API REST complète

## Installation

### Prérequis

- WordPress 5.8 ou supérieur
- WooCommerce 7.0 ou supérieur
- PHP 7.4 ou supérieur
- Un serveur Typesense (Cloud ou auto-hébergé)

### Installation du plugin

1. Téléchargez le plugin
2. Uploadez le dossier `woocommerce-typesense-search` dans `/wp-content/plugins/`
3. Activez le plugin via le menu 'Extensions' dans WordPress
4. Configurez vos paramètres Typesense dans WooCommerce > Réglages > Typesense

### Configuration de Typesense

1. Créez un compte Typesense Cloud ou installez Typesense sur votre serveur
2. Récupérez vos identifiants de connexion :
   - Host (ex: `xxx.a1.typesense.net`)
   - Port (généralement `443` pour HTTPS)
   - Protocol (`https` ou `http`)
   - API Key (clé d'administration)
3. Entrez ces informations dans les réglages du plugin
4. Testez la connexion
5. Lancez la synchronisation initiale des produits

## Configuration

### Paramètres de base

Accédez à **WooCommerce > Réglages > Typesense** pour configurer :

#### Configuration Typesense
- **Enable Typesense Search** : Activer/désactiver la recherche
- **Host** : Adresse du serveur Typesense
- **Port** : Port du serveur (8108 par défaut)
- **Protocol** : HTTP ou HTTPS
- **API Key** : Clé d'API Typesense
- **Collection Name** : Nom de la collection (products par défaut)

#### Synchronisation
- **Auto Sync** : Synchronisation automatique des produits
- **Bulk Sync** : Synchronisation manuelle en masse

#### Fonctionnalités de recherche
- **Typo Tolerance** : Tolérance aux fautes de frappe
- **Voice Search** : Recherche vocale
- **Image Search** : Recherche par image
- **Semantic Search** : Recherche sémantique (nécessite OpenAI API)
- **OpenAI API Key** : Clé API OpenAI pour la recherche sémantique

#### Performance
- **Enable Cache** : Activer le cache des résultats
- **Cache TTL** : Durée de vie du cache en secondes

### Utilisation du shortcode

Ajoutez le formulaire de recherche n'importe où avec le shortcode :

```php
[wts_search]
```

#### Paramètres du shortcode

```php
[wts_search 
    placeholder="Rechercher des produits..." 
    show_filters="yes" 
    show_voice="yes" 
    show_image="yes"
    results_per_page="12"
]
```

- `placeholder` : Texte du placeholder
- `show_filters` : Afficher les filtres (yes/no)
- `show_voice` : Afficher le bouton de recherche vocale (yes/no)
- `show_image` : Afficher le bouton de recherche par image (yes/no)
- `results_per_page` : Nombre de résultats par page

### Widget

Le plugin ajoute un widget **Typesense Product Search** disponible dans Apparence > Widgets.

### Remplacement de la recherche WooCommerce

Le plugin remplace automatiquement le formulaire de recherche WooCommerce par défaut. Pour désactiver ce comportement, utilisez le filtre :

```php
add_filter('get_product_search_form', function($form) {
    // Retourner le formulaire original
    return $form;
}, 5);
```

## API REST

Le plugin expose plusieurs endpoints REST :

### Recherche de produits

```
GET /wp-json/wts/v1/search
```

**Paramètres :**
- `q` (requis) : Terme de recherche
- `per_page` : Résultats par page (défaut: 12)
- `page` : Numéro de page (défaut: 1)
- `categories` : Filtrer par catégories (séparées par virgules)
- `min_price` : Prix minimum
- `max_price` : Prix maximum
- `in_stock` : Produits en stock uniquement (true/false)
- `on_sale` : Produits en promotion (true/false)
- `sort_by` : Tri (relevance, price_asc, price_desc, date_desc, rating)

### Suggestions

```
GET /wp-json/wts/v1/suggest
```

**Paramètres :**
- `q` (requis) : Terme de recherche
- `limit` : Nombre de suggestions (défaut: 5)

### Recherche par image

```
POST /wp-json/wts/v1/image-search
```

**Paramètres :**
- `image` (requis) : Fichier image (multipart/form-data)

### Synchronisation

```
POST /wp-json/wts/v1/sync
```

Nécessite les permissions d'administration.

### Statistiques

```
GET /wp-json/wts/v1/stats
```

Nécessite les permissions d'administration.

## Hooks et filtres

### Filtres

#### Modifier le schéma de la collection

```php
add_filter('wts_collection_schema', function($schema) {
    // Ajouter des champs personnalisés
    $schema['fields'][] = array(
        'name' => 'custom_field',
        'type' => 'string',
        'facet' => true,
    );
    return $schema;
});
```

#### Modifier le document produit

```php
add_filter('wts_product_document', function($document, $product) {
    // Ajouter des données personnalisées
    $document['custom_field'] = get_post_meta($product->get_id(), 'custom_field', true);
    return $document;
}, 10, 2);
```

#### Modifier les paramètres

```php
add_filter('wts_settings', function($settings) {
    // Ajouter des paramètres personnalisés
    $settings[] = array(
        'title' => 'Custom Setting',
        'id' => 'wts_custom_setting',
        'type' => 'text',
    );
    return $settings;
});
```

### Actions

#### Après l'initialisation

```php
add_action('wts_init', function() {
    // Code personnalisé
});
```

## Développement

### Structure des fichiers

```
woocommerce-typesense-search/
├── woocommerce-typesense-search.php  # Fichier principal
├── includes/                          # Classes PHP
│   ├── class-typesense-client.php    # Client Typesense
│   ├── class-product-indexer.php     # Indexation produits
│   ├── class-search-widget.php       # Widget de recherche
│   ├── class-rest-api.php            # API REST
│   └── class-admin-settings.php      # Paramètres admin
├── assets/                            # Assets frontend
│   ├── js/
│   │   ├── search.js                 # Recherche principale
│   │   ├── voice-search.js           # Recherche vocale
│   │   ├── image-search.js           # Recherche par image
│   │   └── admin.js                  # Scripts admin
│   └── css/
│       └── search.css                # Styles
├── templates/                         # Templates
│   ├── search-form.php               # Formulaire de recherche
│   └── search-filters.php            # Filtres
└── languages/                         # Traductions
```

### Contribuer

Les contributions sont les bienvenues ! Pour contribuer :

1. Forkez le projet
2. Créez une branche pour votre fonctionnalité (`git checkout -b feature/AmazingFeature`)
3. Committez vos changements (`git commit -m 'Add some AmazingFeature'`)
4. Pushez vers la branche (`git push origin feature/AmazingFeature`)
5. Ouvrez une Pull Request

## Support

Pour obtenir de l'aide :

- Documentation : [https://webntricks.com/docs/woocommerce-typesense-search](https://webntricks.com/docs/woocommerce-typesense-search)
- Support : [https://webntricks.com/support](https://webntricks.com/support)
- Issues GitHub : [https://github.com/slimsayari/woocommerce-typesense-search/issues](https://github.com/slimsayari/woocommerce-typesense-search/issues)

## FAQ

### Comment obtenir une clé API Typesense ?

Créez un compte sur [Typesense Cloud](https://cloud.typesense.org/) ou installez Typesense sur votre serveur. La clé API est générée automatiquement.

### La recherche vocale fonctionne-t-elle sur tous les navigateurs ?

La recherche vocale utilise l'API Web Speech qui est supportée par Chrome, Edge et Safari. Firefox ne la supporte pas encore.

### Comment activer la recherche sémantique ?

1. Obtenez une clé API OpenAI
2. Entrez-la dans les paramètres du plugin
3. Activez "Semantic Search"
4. Recréez la collection pour inclure les embeddings

### Les variations de produits sont-elles indexées ?

Oui, les variations sont automatiquement indexées avec leurs attributs spécifiques.

### Puis-je personnaliser l'apparence ?

Oui, vous pouvez surcharger les templates en les copiant dans votre thème :
`votre-theme/woocommerce-typesense-search/search-form.php`

## Changelog

### 1.0.0 - 2024-01-15
- Version initiale
- Recherche textuelle avec Typesense
- Recherche vocale
- Recherche par image
- Recherche sémantique
- Synchronisation automatique
- Analytics intégrés
- API REST complète

## Licence

Ce plugin est distribué sous licence GPL v2 ou ultérieure.

## Crédits

- **Auteur** : Slim Sayari
- **Société** : WebNTricks
- **Site web** : [https://webntricks.com](https://webntricks.com)

## Technologies utilisées

- [Typesense](https://typesense.org/) - Moteur de recherche open-source
- [OpenAI API](https://openai.com/) - IA pour recherche sémantique et analyse d'images
- [Web Speech API](https://developer.mozilla.org/en-US/docs/Web/API/Web_Speech_API) - Reconnaissance vocale
- [WooCommerce](https://woocommerce.com/) - Plateforme e-commerce
- [WordPress](https://wordpress.org/) - CMS

---

Développé avec ❤️ par [WebNTricks](https://webntricks.com)
