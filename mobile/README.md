# General House — Capacitor Android

See `docs/general-house/CAPACITOR_ANDROID.md` for full build / release instructions.

Quick TEST build:

```bash
export JAVA_HOME=/home/d14098/jdk ANDROID_HOME=/home/d14098/Android/Sdk ANDROID_SDK_ROOT=/home/d14098/Android/Sdk GRADLE_USER_HOME=/home/d14098/.gradle
export PATH="$JAVA_HOME/bin:$PATH"
npm install
npm run build:test:debug
```

LIVE unsigned (UNVERIFIED): `npm run build:live:unsigned`
