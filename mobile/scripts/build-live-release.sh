#!/usr/bin/env bash
# Build the first official signed LIVE (production) GNRL Operations APK.
# Does NOT upload/publish to LIVE. Does NOT modify LIVE .env/DB/policies.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

export JAVA_HOME="${JAVA_HOME:-/home/d14098/jdk}"
export ANDROID_HOME="${ANDROID_HOME:-/home/d14098/Android/Sdk}"
export ANDROID_SDK_ROOT="$ANDROID_HOME"
export GRADLE_USER_HOME="${GRADLE_USER_HOME:-/home/d14098/.gradle}"
export PATH="$JAVA_HOME/bin:$PATH"
export GH_ANDROID_RELEASE_KEYSTORE_PROPS="${GH_ANDROID_RELEASE_KEYSTORE_PROPS:-$HOME/.gnrl-android-signing/keystore.properties}"

if [[ ! -f "$GH_ANDROID_RELEASE_KEYSTORE_PROPS" ]]; then
  echo "BLOCKED: missing release keystore properties at $GH_ANDROID_RELEASE_KEYSTORE_PROPS"
  exit 1
fi
STORE_FILE="$(awk -F= '/^storeFile=/{print substr($0,index($0,"=")+1)}' "$GH_ANDROID_RELEASE_KEYSTORE_PROPS")"
if [[ ! -f "$STORE_FILE" ]]; then
  echo "BLOCKED: keystore file missing (path from properties)"
  exit 1
fi

echo "==> Apply LIVE Capacitor config"
npm run env:live
npm run verify:config

ACTIVE_URL="$(node -e "console.log(JSON.parse(require('fs').readFileSync('capacitor.config.json','utf8')).server.url)")"
ACTIVE_ID="$(node -e "console.log(JSON.parse(require('fs').readFileSync('capacitor.config.json','utf8')).appId)")"
ACTIVE_NAME="$(node -e "console.log(JSON.parse(require('fs').readFileSync('capacitor.config.json','utf8')).appName)")"

case "$ACTIVE_URL" in
  *system.genral.net*) ;;
  *) echo "Refusing LIVE build: server.url is not LIVE ($ACTIVE_URL)"; exit 1 ;;
esac
case "$ACTIVE_URL" in
  *generalsys.idigital-cs.com*) echo "Refusing LIVE build: TEST domain present"; exit 1 ;;
esac
if [[ "$ACTIVE_ID" != "net.genral.generalhouse" ]]; then
  echo "Refusing LIVE build: unexpected appId $ACTIVE_ID"; exit 1
fi
if [[ "$ACTIVE_NAME" != "GNRL Operations" ]]; then
  echo "Refusing LIVE build: unexpected appName $ACTIVE_NAME"; exit 1
fi

echo "==> Branding + Capacitor sync (preserves flavors via build.gradle.flavors)"
npm run brand:assets
npx cap sync android
cp -f android/app/build.gradle.flavors android/app/build.gradle
npm run brand:assets

# Refuse TEST contamination inside the production {} flavor block only
PROD_BLOCK="$(awk '/production \{/,/^        \}/' android/app/build.gradle)"
if echo "$PROD_BLOCK" | grep -E 'applicationIdSuffix|generalsys\.idigital-cs\.com|GNRL Operations TEST|\.test"'; then
  echo "Refusing: production flavor contains TEST markers"
  echo "$PROD_BLOCK"
  exit 1
fi
if ! echo "$PROD_BLOCK" | grep -Fq '"LIVE"'; then
  echo "Refusing: production flavor missing LIVE GH_ENV"
  exit 1
fi
if ! echo "$PROD_BLOCK" | grep -Fq 'system.genral.net'; then
  echo "Refusing: production flavor missing LIVE host"
  exit 1
fi


OUT_DIR="$ROOT/dist/live"
mkdir -p "$OUT_DIR"

echo "==> Unit tests (AppUpdateDecision)"
cd android
./gradlew :app:testProductionReleaseUnitTest --no-daemon
echo "==> Assemble signed productionRelease"
./gradlew assembleProductionRelease --no-daemon
cd "$ROOT"

APK_SRC="android/app/build/outputs/apk/production/release/app-production-release.apk"
if [[ ! -f "$APK_SRC" ]]; then
  APK_SRC="$(ls android/app/build/outputs/apk/production/release/*.apk | head -n1)"
fi
if [[ "$APK_SRC" == *unsigned* ]]; then
  echo "BLOCKED: Gradle produced an unsigned APK — refusing to treat as official LIVE release"
  exit 1
fi

APK_DST="$OUT_DIR/GNRL-Operations-LIVE-1.0.0-release.apk"
cp -f "$APK_SRC" "$APK_DST"
chmod 640 "$APK_DST"

SHA="$(sha256sum "$APK_DST" | awk '{print $1}')"
SIZE="$(wc -c < "$APK_DST" | tr -d ' ')"

BT="$(ls -d "$ANDROID_HOME"/build-tools/*/ 2>/dev/null | sort -V | tail -n1)"
AAPT="${BT}aapt"
APKSIGNER="${BT}apksigner"

echo "==> Verify APK identity"
DUMP="$("$AAPT" dump badging "$APK_DST")"
echo "$DUMP" | head -n 5
PACKAGE="$(echo "$DUMP" | sed -n "s/.*package: name='\([^']*\)'.*/\1/p" | head -1)"
VNAME="$(echo "$DUMP" | sed -n "s/.*versionName='\([^']*\)'.*/\1/p" | head -1)"
VCODE="$(echo "$DUMP" | sed -n "s/.*versionCode='\([^']*\)'.*/\1/p" | head -1)"
LABEL="$(echo "$DUMP" | sed -n "s/.*application-label:'\([^']*\)'.*/\1/p" | head -1)"

if [[ "$PACKAGE" != "net.genral.generalhouse" ]]; then
  echo "BLOCKED: package=$PACKAGE"; exit 1
fi
if [[ "$VNAME" != "1.0.0" || "$VCODE" != "1" ]]; then
  echo "BLOCKED: versionName=$VNAME versionCode=$VCODE"; exit 1
fi
if [[ "$LABEL" != "GNRL Operations" ]]; then
  echo "BLOCKED: label=$LABEL"; exit 1
fi

echo "==> Verify signing"
"$APKSIGNER" verify --verbose "$APK_DST" | tee "$OUT_DIR/GNRL-Operations-LIVE-1.0.0-apksigner.txt"
if ! "$APKSIGNER" verify "$APK_DST"; then
  echo "BLOCKED: apksigner verify failed"; exit 1
fi

# Embedded Capacitor config must be LIVE
CFG_TMP="$(mktemp -d)"
unzip -p "$APK_DST" assets/capacitor.config.json > "$CFG_TMP/capacitor.config.json"
CFG_URL="$(node -e "console.log(JSON.parse(require('fs').readFileSync('$CFG_TMP/capacitor.config.json','utf8')).server.url)")"
CFG_ID="$(node -e "console.log(JSON.parse(require('fs').readFileSync('$CFG_TMP/capacitor.config.json','utf8')).appId)")"
rm -rf "$CFG_TMP"
if [[ "$CFG_URL" != "https://system.genral.net/login" ]]; then
  echo "BLOCKED: embedded server.url=$CFG_URL"; exit 1
fi
if [[ "$CFG_ID" != "net.genral.generalhouse" ]]; then
  echo "BLOCKED: embedded appId=$CFG_ID"; exit 1
fi
case "$CFG_URL" in
  *generalsys.idigital-cs.com*|*TEST*) echo "BLOCKED: TEST markers in embedded config"; exit 1 ;;
esac

{
  echo "artifact=$APK_DST"
  echo "flavor=productionRelease"
  echo "environment=LIVE"
  echo "applicationId=$PACKAGE"
  echo "appName=$LABEL"
  echo "versionName=$VNAME"
  echo "versionCode=$VCODE"
  echo "serverUrl=$CFG_URL"
  echo "sha256=$SHA"
  echo "sizeBytes=$SIZE"
  echo "builtAt=$(date -u +%Y-%m-%dT%H:%M:%SZ)"
  echo "signed=true"
  echo "status=SIGNED_NOT_PUBLISHED"
  echo "note=Official first LIVE APK built on TEST host. Not uploaded to LIVE. Not published via version policy."
} | tee "$OUT_DIR/GNRL-Operations-LIVE-1.0.0.sha256.txt"

# Public fingerprint copy next to APK (no secrets)
if [[ -f "$HOME/.gnrl-android-signing/certificate-fingerprints.txt" ]]; then
  cp -f "$HOME/.gnrl-android-signing/certificate-fingerprints.txt" \
    "$OUT_DIR/GNRL-Operations-LIVE-1.0.0-cert-fingerprints.txt"
  chmod 644 "$OUT_DIR/GNRL-Operations-LIVE-1.0.0-cert-fingerprints.txt"
fi

echo "SIGNED LIVE APK ready: $APK_DST"
echo "SHA-256: $SHA"
echo "SIZE: $SIZE"
echo "NOT PUBLISHED"
