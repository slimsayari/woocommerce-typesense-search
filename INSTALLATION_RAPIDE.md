# Guide d'Installation Rapide

## 🚀 Mise en route en 5 minutes

### Étape 1: Activer les URLs SEO

Exécutez le script d'activation :

```bash
cd /home/slim/Bureau/projects/fauvertprofessionnel/web/app/plugins/woocommerce-typesense-search
./activate-seo-urls.sh
```

**OU** manuellement dans WordPress :
1. Allez dans **Réglages > Permaliens**
2. Cliquez sur **Enregistrer les modifications**

### Étape 2: Activer les fonctionnalités de recherche

1. Allez dans **WooCommerce > Typesense Search**
2. Activez les options suivantes :
   - ✅ **Voice Search Enabled**
   - ✅ **Image Search Enabled**
3. Entrez votre **OpenAI API Key** (pour la recherche par image)
4. Cliquez sur **Enregistrer**

### Étape 3: Vérifier l'intégration du header

1. Allez sur la page d'accueil de votre site
2. Vérifiez que le formulaire de recherche apparaît dans le header
3. Testez l'autocomplete en tapant quelques lettres
4. Testez le bouton microphone (recherche vocale)
5. Testez le bouton caméra (recherche par image)

### Étape 4: Tester les URLs SEO

Visitez ces URLs pour vérifier qu'elles fonctionnent :

**Catégories** (remplacez `shampoings` par une vraie catégorie) :
```
https://votresite.com/shop/categorie/shampoings/
```

**Attributs** (remplacez par vos vrais attributs) :
```
https://votresite.com/shop/attribut/type-cheveux/lisses/
```

**Filtres génériques** :
```
https://votresite.com/shop/bio/
```

## ✅ Checklist de vérification

- [ ] Les permaliens ont été régénérés
- [ ] Le formulaire de recherche apparaît dans le header
- [ ] L'autocomplete fonctionne
- [ ] La recherche vocale est disponible (bouton microphone)
- [ ] La recherche par image est disponible (bouton caméra)
- [ ] Les URLs de catégories fonctionnent
- [ ] Les URLs d'attributs fonctionnent
- [ ] Le template `archive-product.php` est chargé depuis le plugin

## 🔧 Dépannage

### Le formulaire de recherche n'apparaît pas dans le header

**Solution** : Videz le cache du site et du navigateur

```bash
# Si vous utilisez WP-CLI
wp cache flush --path=/home/slim/Bureau/projects/fauvertprofessionnel/web --allow-root
```

### Les URLs SEO retournent 404

**Solution** : Régénérez les permaliens

```bash
wp rewrite flush --path=/home/slim/Bureau/projects/fauvertprofessionnel/web --allow-root
```

### La recherche vocale ne fonctionne pas

**Vérifications** :
1. Utilisez Chrome ou Edge (Firefox ne supporte pas Web Speech API)
2. Le site doit être en HTTPS
3. Autorisez l'accès au microphone dans le navigateur

### La recherche par image ne fonctionne pas

**Vérifications** :
1. Vérifiez que la clé API OpenAI est configurée
2. Vérifiez que l'option "Image Search Enabled" est activée
3. Vérifiez les logs d'erreur dans la console du navigateur

## 📚 Documentation complète

Pour plus d'informations, consultez :
- **README.md** - Documentation complète du plugin
- **URLS_SEO.md** - Guide des URLs SEO
- **MODIFICATIONS_2025-12-09.md** - Récapitulatif des modifications

## 🆘 Support

En cas de problème :
1. Vérifiez les logs WordPress : `wp-content/debug.log`
2. Vérifiez la console du navigateur (F12)
3. Contactez le support : support@webntricks.com
