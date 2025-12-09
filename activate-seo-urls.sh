#!/bin/bash

# Script d'activation des URLs SEO
# Ce script flush les permaliens WordPress pour activer les nouvelles règles de réécriture

echo "🔄 Activation des URLs SEO pour WooCommerce Typesense Search..."
echo ""

# Vérifier que WP-CLI est installé
if ! command -v wp &> /dev/null; then
    echo "❌ WP-CLI n'est pas installé."
    echo "📝 Veuillez aller dans Réglages > Permaliens et cliquer sur 'Enregistrer les modifications'"
    exit 1
fi

# Obtenir le chemin WordPress
WP_PATH="/home/slim/Bureau/projects/fauvertprofessionnel/web"

# Vérifier que le chemin existe
if [ ! -d "$WP_PATH" ]; then
    echo "❌ Le chemin WordPress n'existe pas: $WP_PATH"
    exit 1
fi

echo "📂 Chemin WordPress: $WP_PATH"
echo ""

# Flush les permaliens
echo "🔄 Flush des permaliens..."
wp rewrite flush --path="$WP_PATH" --allow-root

if [ $? -eq 0 ]; then
    echo "✅ Permaliens régénérés avec succès!"
    echo ""
    echo "📋 Les URLs SEO suivantes sont maintenant actives:"
    echo ""
    echo "   Catégories:"
    echo "   └─ /shop/categorie/[slug]/"
    echo ""
    echo "   Attributs:"
    echo "   └─ /shop/attribut/[attribut]/[valeur]/"
    echo ""
    echo "   Filtres génériques:"
    echo "   └─ /shop/[slug]/"
    echo ""
    echo "🎉 Configuration terminée!"
else
    echo "❌ Erreur lors du flush des permaliens"
    echo "📝 Veuillez aller dans Réglages > Permaliens et cliquer sur 'Enregistrer les modifications'"
    exit 1
fi
