# Changelog

Toutes les modifications notables de ce projet seront documentées dans ce fichier.

Le format est basé sur [Keep a Changelog](https://keepachangelog.com/fr/1.0.0/),
et ce projet adhère au [Semantic Versioning](https://semver.org/lang/fr/).

## [1.0.0] - 2024-01-15

### Ajouté

#### Fonctionnalités principales
- Intégration complète avec Typesense pour la recherche de produits WooCommerce
- Recherche textuelle ultra-rapide avec tolérance aux fautes de frappe
- Recherche vocale via Web Speech API
- Recherche par image avec analyse IA (OpenAI Vision)
- Recherche sémantique avec embeddings OpenAI
- Auto-complétion et suggestions en temps réel

#### Interface utilisateur
- Formulaire de recherche personnalisable avec shortcode `[wts_search]`
- Widget WordPress pour zones de widgets
- Interface de résultats avec grille responsive
- Filtres dynamiques (catégories, prix, stock, promotions)
- Tri multiple (pertinence, prix, date, note)
- Pagination infinie avec bouton "Load More"
- Prévisualisation des images uploadées pour recherche

#### Administration
- Page de réglages dans WooCommerce > Réglages > Typesense
- Test de connexion Typesense avec feedback visuel
- Synchronisation automatique des produits (création, modification, suppression)
- Synchronisation manuelle en masse avec barre de progression
- Dashboard analytics avec recherches populaires
- Rapport des recherches sans résultats
- Export des analytics en CSV

#### API REST
- Endpoint `/wp-json/wts/v1/search` - Recherche de produits
- Endpoint `/wp-json/wts/v1/suggest` - Suggestions
- Endpoint `/wp-json/wts/v1/sync` - Synchronisation manuelle
- Endpoint `/wp-json/wts/v1/stats` - Statistiques
- Endpoint `/wp-json/wts/v1/image-search` - Recherche par image
- Endpoint `/wp-json/wts/v1/track-click` - Tracking des clics

#### Performance
- Cache des résultats de recherche avec TTL configurable
- Debounce sur la saisie pour réduire les requêtes
- Lazy loading des images
- Optimisation des requêtes Typesense
- Support de la pagination côté serveur

#### Développeur
- Hooks et filtres WordPress pour personnalisation
- Filtre `wts_collection_schema` pour modifier le schéma
- Filtre `wts_product_document` pour personnaliser les documents
- Filtre `wts_settings` pour ajouter des paramètres
- Action `wts_init` pour initialisation personnalisée
- Architecture orientée objet et modulaire
- Code documenté et commenté

#### Compatibilité
- Support WPML/Polylang pour sites multilingues
- Compatible avec les principaux thèmes WooCommerce
- Mode dégradé si Typesense indisponible
- Gestion des variations de produits
- Support des attributs et métadonnées personnalisées
- Compatible ACF (Advanced Custom Fields)

#### Documentation
- README.md complet avec exemples
- Guide d'installation détaillé
- Documentation des hooks et filtres
- Exemples de code pour développeurs
- FAQ avec solutions aux problèmes courants

#### Sécurité
- Validation et sanitization de toutes les entrées
- Nonces WordPress pour les requêtes AJAX
- Vérification des permissions utilisateur
- Protection contre les injections SQL
- Échappement des sorties

#### Internationalisation
- Support de la traduction via WordPress i18n
- Fichiers .pot pour traducteurs
- Textes en français par défaut
- Support des langues RTL

### Technique

#### Architecture
- Classe principale `WooCommerce_Typesense_Search`
- Client Typesense `WTS_Typesense_Client`
- Indexeur de produits `WTS_Product_Indexer`
- API REST `WTS_Rest_API`
- Widget de recherche `WTS_Search_Widget`
- Paramètres admin `WTS_Admin_Settings`

#### Base de données
- Table `wp_wts_analytics` pour le tracking
- Options WordPress pour les paramètres
- Pas de modification des tables WooCommerce

#### Assets
- JavaScript vanilla avec jQuery
- CSS responsive et moderne
- Animations et transitions fluides
- Support des navigateurs modernes
- Fallbacks pour anciens navigateurs

#### Tests
- Validation de connexion Typesense
- Test de synchronisation
- Vérification des permissions
- Gestion des erreurs

### Dépendances

#### Requises
- WordPress 5.8+
- WooCommerce 7.0+
- PHP 7.4+
- Serveur Typesense (Cloud ou auto-hébergé)

#### Optionnelles
- OpenAI API (pour recherche sémantique et image)
- WPML/Polylang (pour multilingue)

### Notes de version

Cette première version stable inclut toutes les fonctionnalités essentielles pour une recherche avancée de produits WooCommerce. Le plugin a été testé avec :

- WordPress 6.4
- WooCommerce 8.5
- PHP 8.2
- Typesense 0.25.1

### Problèmes connus

Aucun problème critique connu à ce jour.

### Roadmap

Les fonctionnalités suivantes sont prévues pour les prochaines versions :

#### Version 1.1.0 (Q2 2024)
- Support des synonymes Typesense
- Recherche géolocalisée
- Filtres personnalisés avancés
- Amélioration des analytics avec graphiques
- Support des produits groupés et composites

#### Version 1.2.0 (Q3 2024)
- A/B testing des résultats de recherche
- Personnalisation des résultats par utilisateur
- Recommandations de produits basées sur l'IA
- Support des recherches sauvegardées
- Historique de recherche utilisateur

#### Version 2.0.0 (Q4 2024)
- Interface de configuration visuelle
- Builder de formulaire de recherche
- Templates de résultats personnalisables
- Support des facettes dynamiques
- Intégration avec Google Analytics 4

### Contributeurs

- **Slim Sayari** - Développement initial - [slimsayari](https://github.com/slimsayari)

### Licence

GPL v2 ou ultérieure - voir [LICENSE](LICENSE)

### Liens

- [Repository GitHub](https://github.com/slimsayari/woocommerce-typesense-search)
- [Documentation](README.md)
- [WebNTricks](https://webntricks.com)
- [Typesense](https://typesense.org)

---

**Note** : Ce changelog suit les conventions de [Keep a Changelog](https://keepachangelog.com/fr/1.0.0/).
