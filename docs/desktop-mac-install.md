# People360 — macOS install (other computers)

Unsigned desktop builds often show **“People360 is damaged and can’t be opened”** after download (Edge, Chrome, Google Drive). The app is not corrupt; macOS **Gatekeeper** and the download **quarantine** flag block it.

## Before you install

1. Confirm the file size matches the release (~267 MB ZIP, ~330 MB DMG for recent versions).
2. Prefer **Safari** or copy via USB if Drive keeps failing the download.

## Option A — ZIP (recommended for sharing)

1. Unzip `People360-x.y.z-arm64.zip`.
2. Open **Terminal** and run (adjust the path if needed):

```bash
cd ~/Downloads
chmod +x /path/to/pulse/scripts/install-pulse-mac.sh
./install-pulse-mac.sh ~/Downloads/People360.app
```

Or manually:

```bash
xattr -cr ~/Downloads/People360.app
codesign --force --deep --sign - ~/Downloads/People360.app
cp -R ~/Downloads/People360.app /Applications/
xattr -cr /Applications/People360.app
```

3. **First launch:** Finder → Applications → **right-click People360 → Open** → **Open** again.

## Option A2 — DMG with install helper (recent builds)

1. Open `People360-x.y.z-arm64.dmg`.
2. Double-click **Install People360.command** (if blocked: right-click → Open).
3. **First launch:** Applications → right-click People360 → Open → Open.

## Option B — DMG

1. After download:

```bash
xattr -cr ~/Downloads/People360-*-arm64.dmg
```

2. Open the DMG and drag **People360** to **Applications**.
3. Then:

```bash
xattr -cr /Applications/People360.app
```

4. **Right-click → Open** on first launch (do not double-click the first time).

## If it still fails

- **System Settings → Privacy & Security** → scroll down → **Open Anyway** (appears after one blocked launch).
- Remove quarantine again: `xattr -cr /Applications/People360.app`
- Apple Silicon Mac only (`arm64`). Intel Macs need an x64 build if provided.

## For IT / developers

### Production builds (no Gatekeeper warning)

1. Enroll in [Apple Developer Program](https://developer.apple.com/programs/) ($99/year).
2. Create **Developer ID Application** certificate and install in Keychain.
3. From `pulse/` run:

```bash
chmod +x scripts/setup-mac-notarization.sh
./scripts/setup-mac-notarization.sh
```

4. Rebuild Mac installer:

```bash
./scripts/build-desktop.sh mac
```

Build output should log notarization + stapler validate. Shipped DMG/ZIP opens on other Macs without Terminal or right-click workarounds.

Env vars (also in `.env.example`):

| Variable | Purpose |
|----------|---------|
| `NATIVEPHP_NOTARIZE=1` | Enable notarization during build |
| `NATIVEPHP_APPLE_ID` | Apple ID email |
| `NATIVEPHP_APPLE_ID_PASS` | App-specific password |
| `NATIVEPHP_APPLE_TEAM_ID` | 10-char Team ID |
| `NATIVEPHP_MAC_IDENTITY` | Optional explicit Developer ID identity name |

Local/testing builds without these vars use adhoc sign via `./scripts/fix-mac-pulse-codesign.sh` during `build-desktop.sh`. DMG includes **Install People360.command** for one-click client install (still requires first right-click Open — Gatekeeper limit on unsigned apps).

### Free vs paid

| Approach | Cost | Client experience |
|----------|------|-------------------|
| **Unsigned + install helper** | Free | One-time right-click Open; use `Install People360.command` in DMG |
| **Apple Developer + notarization** | $99/year | Double-click install, no workaround |
