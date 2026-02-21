#!/bin/bash
# dev-setup.sh
# Downloads MediaWiki core for LSP indexing (Intelephense).
# Run once after cloning the repo. Not used by Docker.

set -e

MW_VERSION="REL1_45"
TARGET="extensions/AEPedia/.mediawiki-core"

if [ -d "$TARGET" ]; then
  echo "MediaWiki core already present at $TARGET, skipping."
  exit 0
fi

echo "Cloning MediaWiki core ($MW_VERSION) for IDE support..."
git clone --depth 1 --branch "$MW_VERSION" \
  https://gerrit.wikimedia.org/r/mediawiki/core \
  "$TARGET"

echo "Done. Point your LSP includePaths at: $TARGET"
