#!/usr/bin/env bash
# Builds an UNSIGNED LIVE-configured APK for packaging prep only.
# UNVERIFIED against LIVE. NOT APPROVED FOR PRODUCTION DISTRIBUTION.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

export JAVA_HOME="${JAVA_HOME:-/home/d14098/jdk}"
export ANDROID_HOME="${ANDROID_HOME:-/home/d14098/Android/Sdk}"
export ANDROID_SDK_ROOT="$ANDROID_HOME"
export GRADLE_USER_HOME="${GRADLE_USER_HOME:-/home/d14098/.gradle}"
export PATH="$JAVA_HOME/bin:$PATH"

echo "==> Apply LIVE Capacitor config (refuses TEST URL)"
npm run env:live
npm run verify:config

ACTIVE_URL="$(node -e "console.log(JSON.parse(require('fs').readFileSync('capacitor.config.json','utf8')).server.url)")"
case "$ACTIVE_URL" in
  *system.genral.net*) ;;
  *) echo "Refusing LIVE build: server.url is not LIVE ($ACTIVE_URL)"; exit 1 ;;
esac
case "$ACTIVE_URL" in
  *generalsys.idigital-cs.com*) echo "Refusing LIVE build: TEST domain present"; exit 1 ;;
esac

echo "==> Generate GNRL launcher / splash branding assets"
npm run brand:assets

echo "==> Sync Android assets with LIVE server.url"
npx cap sync android
cp -f android/app/build.gradle.flavors android/app/build.gradle
# Cap sync can restore default splash; regenerate branding after sync.
npm run brand:assets

OUT_DIR="$ROOT/dist/live"
mkdir -p "$OUT_DIR"

echo "==> Gradle assembleProductionRelease (LIVE env, unsigned / no Play signing)"
cd android
./gradlew assembleProductionRelease --no-daemon
cd "$ROOT"

APK_SRC="android/app/build/outputs/apk/production/release/app-production-release-unsigned.apk"
if [[ ! -f "$APK_SRC" ]]; then
  APK_SRC="$(ls android/app/build/outputs/apk/production/release/*.apk | head -n1)"
fi
APK_DST="$OUT_DIR/GeneralHouse-LIVE-UNVERIFIED-unsigned.apk"
cp -f "$APK_SRC" "$APK_DST"

SHA="$(sha256sum "$APK_DST" | awk '{print $1}')"
{
  echo "artifact=$APK_DST"
  echo "flavor=productionRelease"
  echo "environment=LIVE"
  echo "applicationId=net.genral.generalhouse"
  echo "appName=GNRL Operations"
  echo "serverUrl=$ACTIVE_URL"
  echo "sha256=$SHA"
  echo "builtAt=$(date -u +%Y-%m-%dT%H:%M:%SZ)"
  echo "status=UNVERIFIED_NOT_APPROVED_FOR_PRODUCTION"
  echo "note=Built without accessing or modifying LIVE. Requires owner LIVE verification before release."
} | tee "$OUT_DIR/GeneralHouse-LIVE-UNVERIFIED.sha256.txt"

echo "LIVE UNVERIFIED APK ready: $APK_DST"
echo "SHA-256: $SHA"
echo "LABEL: UNVERIFIED — NOT APPROVED FOR PRODUCTION"
