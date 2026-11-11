# GNRL Operations — Android LIVE release 1.0.0

First official production APK identity (built on TEST host; **not published** to LIVE in this step).

| Field | Value |
|-------|--------|
| App name | GNRL Operations |
| Package ID | `net.genral.generalhouse` |
| Version name | `1.0.0` |
| Version code | `1` |
| Flavor / build type | `productionRelease` |
| Server URL | `https://system.genral.net/login` |
| Artifact (local) | `mobile/dist/live/GNRL-Operations-LIVE-1.0.0-release.apk` |
| APK SHA-256 | `1053d73ad2307ed0c680023b9a1e1218328149d351eaa2179126788828fb17b8` |
| APK size | 4,989,738 bytes (~4.76 MiB) |
| Signing | v1 + v2 verified (`apksigner verify`) |
| Publish status | **Not published** to LIVE |

## Signing certificate fingerprints (public)

Alias: `gnrl-operations-live`

- **SHA-1:** `1F:43:3B:F0:94:9C:BA:C4:C3:BC:80:90:3C:AC:52:52:41:5D:5D:AF`
- **SHA-256:** `10:50:5A:33:6D:35:0F:FF:DC:E6:9C:28:4D:FA:2F:EE:DC:1D:96:1F:8C:54:B4:13:84:95:EA:86:69:F3:6A:8E`

Private keystore and passwords live **outside Git** under `~/.gnrl-android-signing/` (with a local backup copy). Never commit them. Future LIVE updates must reuse this signer.

## Build command

```bash
cd mobile
npm run build:live:release
```

## Publication

Not performed here. LIVE `.env`, database, and version policies remain unchanged until a separate owner-approved publish step.
