# Pulse — Mac Install Guide (for end users)

> **Note for IT:** This guide applies to **unsigned** builds. For installs with **no Gatekeeper prompt at all**, the organization must use Apple Developer notarization ($99/year). See `docs/desktop-mac-install.md`.

## Install from DMG

1. Open `Pulse-x.y.z-arm64.dmg`
2. Double-click **Install Pulse.command**
   - If macOS blocks it: **right-click → Open → Open**
3. Wait for the alert: *"Pulse copied to Applications..."*
4. **First launch (required once per Mac):**
   - Finder → **Applications**
   - **Right-click Pulse → Open → Open**
   - Do **not** double-click the first time

## If Pulse still won't open

1. Open **System Settings → Privacy & Security**
2. Scroll down → click **Open Anyway** (appears after one blocked launch)
3. Try **right-click → Open** again

## Install from ZIP

1. Unzip the file
2. Open **Terminal** and run:

```bash
xattr -cr ~/Downloads/Pulse.app
codesign --force --deep --sign - ~/Downloads/Pulse.app
cp -R ~/Downloads/Pulse.app /Applications/
```

3. **Right-click Pulse → Open → Open** (first launch only)

## Why this happens

macOS Gatekeeper blocks apps that are not signed by a registered Apple developer. This is an **Apple security rule**, not a Pulse bug. The steps above are the standard workaround when not using Apple notarization.
