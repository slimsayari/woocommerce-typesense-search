# URLs SEO - WooCommerce Typesense Search

Ce document liste toutes les URLs SEO-friendly créées par le plugin WooCommerce Typesense Search.

## Structure des URLs

### 1. Pages Catégories

**Format**: `/shop/categorie/[slug-categorie]/`

**Exemples**:
- `/shop/categorie/soins-capillaires/` - Page de la catégorie "Soins Capillaires"
- `/shop/categorie/colorations/` - Page de la catégorie "Colorations"
- `/shop/categorie/accessoires/` - Page de la catégorie "Accessoires"
- `/shop/categorie/shampoings/` - Page de la catégorie "Shampoings"
- `/shop/categorie/apres-shampoings/` - Page de la catégorie "Après-Shampoings"

**Utilisation en PHP**:
```php
// Générer une URL de catégorie
$url = WTS_URL_Manager::get_category_url('soins-capillaires');
// Résultat: https://votresite.com/shop/categorie/soins-capillaires/
```

---

### 2. Pages Attributs

**Format**: `/shop/attribut/[slug-attribut]/[slug-terme]/`

**Exemples**:
- `/shop/attribut/couleur/rouge/` - Produits de couleur rouge
- `/shop/attribut/couleur/bleu/` - Produits de couleur bleue
- `/shop/attribut/type-cheveux/lisses/` - Produits pour cheveux lisses
- `/shop/attribut/type-cheveux/boucles/` - Produits pour cheveux bouclés
- `/shop/attribut/type-cheveux/frises/` - Produits pour cheveux frisés
- `/shop/attribut/marque/loreal/` - Produits de la marque L'Oréal
- `/shop/attribut/taille/petit/` - Produits de petite taille
- `/shop/attribut/taille/moyen/` - Produits de taille moyenne
- `/shop/attribut/taille/grand/` - Produits de grande taille

**Utilisation en PHP**:
```php
// Générer une URL d'attribut
$url = WTS_URL_Manager::get_attribute_url('couleur', 'rouge');
// Résultat: https://votresite.com/shop/attribut/couleur/rouge/

$url = WTS_URL_Manager::get_attribute_url('type-cheveux', 'lisses');
// Résultat: https://votresite.com/shop/attribut/type-cheveux/lisses/
```

---

### 3. Pages Filtres Génériques

**Format**: `/shop/[slug-filtre]/`

Ces URLs fonctionnent pour n'importe quel terme de taxonomie de produit.

**Exemples**:
- `/shop/cheveux-lisses/` - Filtre automatique pour cheveux lisses
- `/shop/rouge/` - Filtre automatique pour la couleur rouge
- `/shop/bio/` - Filtre automatique pour les produits bio
- `/shop/vegan/` - Filtre automatique pour les produits vegan
- `/shop/sans-sulfate/` - Filtre automatique pour les produits sans sulfate

**Utilisation en PHP**:
```php
// Générer une URL de filtre générique
$url = WTS_URL_Manager::get_filter_url('cheveux-lisses');
// Résultat: https://votresite.com/shop/cheveux-lisses/
```

---

## Fonctionnalités SEO

### 1. **URLs Propres et Lisibles**
Toutes les URLs sont optimisées pour le SEO avec des slugs descriptifs en français.

### 2. **Canonical URLs**
Chaque page a une URL canonique unique pour éviter le contenu dupliqué.

### 3. **Meta Descriptions Automatiques**
Les pages de catégories et d'attributs génèrent automatiquement des meta descriptions basées sur:
- Le nom de la catégorie/attribut
- Le nombre de produits
- Les caractéristiques principales

### 4. **Breadcrumbs**
Navigation fil d'Ariane automatique:
```
Accueil > Shop > Catégorie > Soins Capillaires
Accueil > Shop > Attribut > Type de Cheveux > Lisses
```

### 5. **Pagination SEO**
Les pages paginées utilisent les balises `rel="next"` et `rel="prev"` pour le SEO.

---

## Configuration

### Activation des URLs SEO

Les URLs SEO sont automatiquement activées lors de l'installation du plugin. Pour régénérer les règles de réécriture:

1. Allez dans **Réglages > Permaliens**
2. Cliquez sur **Enregistrer les modifications** (sans rien changer)
3. Les règles de réécriture seront régénérées

### Vérification des URLs

Pour vérifier que les URLs fonctionnent correctement:

```bash
# Tester une URL de catégorie
curl -I https://votresite.com/shop/categorie/soins-capillaires/

# Tester une URL d'attribut
curl -I https://votresite.com/shop/attribut/couleur/rouge/

# Tester une URL de filtre générique
curl -I https://votresite.com/shop/cheveux-lisses/
```

Toutes devraient retourner un code HTTP 200.

---

## Intégration dans les Templates

### Afficher un lien vers une catégorie

```php
<?php
$categories = get_terms('product_cat', array('hide_empty' => true));
foreach ($categories as $category) {
    $url = WTS_URL_Manager::get_category_url($category->slug);
    echo '<a href="' . esc_url($url) . '">' . esc_html($category->name) . '</a>';
}
?>
```

### Afficher un lien vers un attribut

```php
<?php
$terms = get_terms('pa_couleur', array('hide_empty' => true));
foreach ($terms as $term) {
    $url = WTS_URL_Manager::get_attribute_url('couleur', $term->slug);
    echo '<a href="' . esc_url($url) . '">' . esc_html($term->name) . '</a>';
}
?>
```

---

## Exemples d'URLs Complètes pour Fauvert Professionnel

Basé sur votre catalogue de produits capillaires:

### Catégories
- `https://fauvertprofessionnel.com/shop/categorie/shampoings/`
- `https://fauvertprofessionnel.com/shop/categorie/apres-shampoings/`
- `https://fauvertprofessionnel.com/shop/categorie/masques/`
- `https://fauvertprofessionnel.com/shop/categorie/colorations/`
- `https://fauvertprofessionnel.com/shop/categorie/soins-professionnels/`

### Attributs - Type de Cheveux
- `https://fauvertprofessionnel.com/shop/attribut/type-cheveux/lisses/`
- `https://fauvertprofessionnel.com/shop/attribut/type-cheveux/boucles/`
- `https://fauvertprofessionnel.com/shop/attribut/type-cheveux/frises/`
- `https://fauvertprofessionnel.com/shop/attribut/type-cheveux/secs/`
- `https://fauvertprofessionnel.com/shop/attribut/type-cheveux/gras/`
- `https://fauvertprofessionnel.com/shop/attribut/type-cheveux/normaux/`
- `https://fauvertprofessionnel.com/shop/attribut/type-cheveux/abimes/`
- `https://fauvertprofessionnel.com/shop/attribut/type-cheveux/colores/`

### Attributs - Couleur
- `https://fauvertprofessionnel.com/shop/attribut/couleur/blond/`
- `https://fauvertprofessionnel.com/shop/attribut/couleur/brun/`
- `https://fauvertprofessionnel.com/shop/attribut/couleur/roux/`
- `https://fauvertprofessionnel.com/shop/attribut/couleur/noir/`
- `https://fauvertprofessionnel.com/shop/attribut/couleur/chatain/`

### Filtres Génériques
- `https://fauvertprofessionnel.com/shop/bio/`
- `https://fauvertprofessionnel.com/shop/vegan/`
- `https://fauvertprofessionnel.com/shop/sans-sulfate/`
- `https://fauvertprofessionnel.com/shop/sans-paraben/`
- `https://fauvertprofessionnel.com/shop/professionnel/`

---

## Notes Techniques

### Priorité des Règles de Réécriture

Les règles sont ajoutées avec la priorité `'top'` pour s'assurer qu'elles sont évaluées avant les règles WordPress par défaut.

### Gestion des 404

Si une URL ne correspond à aucune catégorie ou attribut existant, WordPress retourne automatiquement une page 404.

### Performance

Les URLs sont gérées via les règles de réécriture WordPress natives, ce qui garantit:
- Pas de redirection supplémentaire
- Pas de requête de base de données supplémentaire
- Performance optimale

---

## Support et Maintenance

Pour toute question ou problème avec les URLs SEO, contactez l'équipe de développement.

**Version**: 1.0.0  
**Dernière mise à jour**: 2025-12-09
