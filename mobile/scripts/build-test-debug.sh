#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

export JAVA_HOME="${JAVA_HOME:-/home/d14098/jdk}"
export ANDROID_HOME="${ANDROID_HOME:-/home/d14098/Android/Sdk}"
export ANDROID_SDK_ROOT="$ANDROID_HOME"
export GRADLE_USER_HOME="${GRADLE_USER_HOME:-/home/d14098/.gradle}"
export PATH="$JAVA_HOME/bin:$PATH"

echo "==> Apply TEST Capacitor config (refuses LIVE URL)"
npm run env:test
npm run verify:config

echo "==> Generate GNRL launcher / splash branding assets"
npm run brand:assets

echo "==> Sync Android assets with TEST server.url"
npx cap sync android
cp -f android/app/build.gradle.flavors android/app/build.gradle
# Cap sync can restore default splash; regenerate branding after sync.
npm run brand:assets

ACTIVE_URL="$(node -e "console.log(JSON.parse(require('fs').readFileSync('capacitor.config.json','utf8')).server.url)")"
case "$ACTIVE_URL" in
  *generalsys.idigital-cs.com*) ;;
  *) echo "Refusing TEST build: server.url is not TEST ($ACTIVE_URL)"; exit 1 ;;
esac

OUT_DIR="$ROOT/dist/test"
mkdir -p "$OUT_DIR"

echo "==> Gradle assembleSandboxDebug (TEST environment)"
cd android
./gradlew assembleSandboxDebug --no-daemon
cd "$ROOT"

APK_SRC="android/app/build/outputs/apk/sandbox/debug/app-sandbox-debug.apk"
APK_DST="$OUT_DIR/GeneralHouse-TEST-debug.apk"
cp -f "$APK_SRC" "$APK_DST"

SHA="$(sha256sum "$APK_DST" | awk '{print $1}')"
{
  echo "artifact=$APK_DST"
  echo "flavor=sandboxDebug"
  echo "environment=TEST"
  echo "applicationId=net.genral.generalhouse.test"
  echo "appName=GNRL Operations TEST"
  echo "serverUrl=$ACTIVE_URL"
  echo "sha256=$SHA"
  echo "builtAt=$(date -u +%Y-%m-%dT%H:%M:%SZ)"
  echo "status=TEST_DEBUG"
} | tee "$OUT_DIR/GeneralHouse-TEST-debug.sha256.txt"

echo "TEST debug APK ready: $APK_DST"
echo "SHA-256: $SHA"
