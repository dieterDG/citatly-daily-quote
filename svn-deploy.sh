#!/bin/bash
PLUGIN_DIR="$(cd "$(dirname "$0")" && pwd)"
SVN_DIR="/c/Users/Dieter Geiling/svn-citatly/trunk"
SVN_ASSETS_DIR="/c/Users/Dieter Geiling/svn-citatly/assets"
LOCAL_ASSETS_DIR="/n/Dieter/Entwicklung/citatly-svn-assets"

# Version aus Plugin-Header lesen
VERSION=$(grep -i "^ \* Version:" "$PLUGIN_DIR/citatly-daily-quote.php" | awk '{print $NF}')
if [ -z "$VERSION" ]; then
  echo "❌ Version nicht gefunden in citatly-daily-quote.php – Abbruch."
  exit 1
fi

echo "🔄 Deploye Version $VERSION nach SVN trunk..."

# Plugin-Dateien synchronisieren
rsync -a --delete \
  --include="citatly-daily-quote.php" \
  --include="citatly.js" \
  --include="citatly.css" \
  --include="admin-export.js" \
  --include="uninstall.php" \
  --include="readme.txt" \
  --include="LICENSE" \
  --include="languages/" \
  --include="languages/**" \
  --include="build/" \
  --include="build/**" \
  --exclude="*" \
  "$PLUGIN_DIR/" "$SVN_DIR/"

if [ $? -ne 0 ]; then
  echo "❌ SVN trunk sync fehlgeschlagen!"
  exit 1
fi
echo "✅ SVN trunk aktualisiert"

# Assets synchronisieren
echo "🔄 Assets werden synchronisiert..."
rsync -a --delete \
  "$LOCAL_ASSETS_DIR/" "$SVN_ASSETS_DIR/"

if [ $? -ne 0 ]; then
  echo "❌ Assets sync fehlgeschlagen!"
  exit 1
fi
echo "✅ SVN assets aktualisiert"
echo ""

# SVN Status anzeigen
cd "/c/Users/Dieter Geiling/svn-citatly" || exit 1
echo "📋 SVN Status:"
svn status

echo ""
read -p "🚀 Jetzt committen? (j/n): " CONFIRM
if [ "$CONFIRM" = "j" ]; then
  svn add --force trunk/* 2>/dev/null
  svn add --force assets/* 2>/dev/null
  svn commit -m "Version $VERSION" --username dieter93
  if [ $? -eq 0 ]; then
    echo "✅ Version $VERSION erfolgreich committed!"
  else
    echo "❌ Commit fehlgeschlagen!"
    exit 1
  fi
else
  echo "⏸ Abgebrochen. SVN wurde synchronisiert, aber nicht committed."
fi