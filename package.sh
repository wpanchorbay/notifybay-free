#!/bin/bash

# NotifyBay Packaging Script
# This script builds the project and creates distributable zip files.

# Exit on error
set -e

# Define the zip filename
PLUGIN_SLUG="notifybay-waitlist-and-stock-alert-woo"
ZIP_NAME="$PLUGIN_SLUG.zip"

echo "------------------------------------------------------"
echo "  Packaging $PLUGIN_SLUG..."
echo "------------------------------------------------------"

# 1. Run the build script
echo "Step 1: Running build process..."
bash build.sh

# 2. Cleanup previous distribution if exists
echo "Step 2: Cleaning up old files..."
rm -rf dist
rm -f $ZIP_NAME

# 3. Create staging directories
mkdir -p "dist/wordpress/$PLUGIN_SLUG"
mkdir -p "dist/woocommerce/$PLUGIN_SLUG"

# Helper function to copy plugin files to a target path
copy_plugin_files() {
    local DEST=$1
    echo "Copying files to $DEST..."
    
    # Copy folders
    cp -r app "$DEST/"
    cp -r languages "$DEST/"
    cp -r assets "$DEST/"
    cp -r build "$DEST/"
    cp -r vendor "$DEST/"
    # The plugin-update-checker library is only used by NotifyBay Pro (self-hosted
    # updates). The free wp.org build must not ship it — repo plugins update via
    # wordpress.org, not a bundled updater.
    rm -rf "$DEST/vendor/plugin-update-checker"
    cp -r config "$DEST/"
    cp -r templates "$DEST/"

    # Copy files
    cp index.php "$DEST/"
    cp readme.txt "$DEST/"
    cp uninstall.php "$DEST/"
    cp notifybay-waitlist-and-stock-alert-woo.php "$DEST/"
    cp composer.json "$DEST/"
}

# 4. Copy files to both staging areas
echo "Step 3: Copying folders and files..."
copy_plugin_files "dist/wordpress/$PLUGIN_SLUG"
copy_plugin_files "dist/woocommerce/$PLUGIN_SLUG"

# 5. Create WordPress Zip
echo "Step 4: Creating WordPress zip..."
cd dist/wordpress
zip -r "$ZIP_NAME" "$PLUGIN_SLUG" > /dev/null
cd ../..
# Remove the staging folder after zipping to leave only the zip in the subfolder
rm -rf "dist/wordpress/$PLUGIN_SLUG"

# 6. Create WooCommerce Zip
echo "Step 5: Creating WooCommerce zip..."
cd dist/woocommerce
zip -r "$ZIP_NAME" "$PLUGIN_SLUG" > /dev/null
# Remove the folder after zipping to leave only the zip in the subfolder
rm -rf "$PLUGIN_SLUG"
cd ../..

echo "------------------------------------------------------"
echo "Done! Packages created successfully:"
echo "- dist/wordpress/$ZIP_NAME"
echo "- dist/woocommerce/$ZIP_NAME"
echo ""
echo "Note: The zip files now contain the required parent directory '$PLUGIN_SLUG'."
echo "------------------------------------------------------"
