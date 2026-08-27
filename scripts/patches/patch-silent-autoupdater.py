#!/usr/bin/env python3
"""Patch NativePHP electron-updater for silent auto-download + auto-install.

NativePHP default is checkForUpdatesAndNotify(), which shows an OS prompt.
People360/Pulse must download and install with no user click.
"""
from __future__ import annotations

import sys
from pathlib import Path

OLD_TS = """  private startAutoUpdater(config) {
    if (config?.updater?.enabled === true) {
      autoUpdater.checkForUpdatesAndNotify();
    }
  }"""

NEW_TS = """  private startAutoUpdater(config) {
    if (config?.updater?.enabled === true) {
      autoUpdater.autoDownload = true;
      autoUpdater.autoInstallOnAppQuit = true;
      autoUpdater.on("update-downloaded", () => {
        try {
          autoUpdater.quitAndInstall(true, true);
        } catch (error) {
          console.error("Auto-install failed", error);
        }
      });
      autoUpdater.checkForUpdates();
    }
  }"""

OLD_DIST = """    startAutoUpdater(config) {
        var _a;
        if (((_a = config === null || config === void 0 ? void 0 : config.updater) === null || _a === void 0 ? void 0 : _a.enabled) === true) {
            autoUpdater.checkForUpdatesAndNotify();
        }
    }"""

NEW_DIST = """    startAutoUpdater(config) {
        var _a;
        if (((_a = config === null || config === void 0 ? void 0 : config.updater) === null || _a === void 0 ? void 0 : _a.enabled) === true) {
            autoUpdater.autoDownload = true;
            autoUpdater.autoInstallOnAppQuit = true;
            autoUpdater.on("update-downloaded", () => {
                try {
                    autoUpdater.quitAndInstall(true, true);
                }
                catch (error) {
                    console.error("Auto-install failed", error);
                }
            });
            autoUpdater.checkForUpdates();
        }
    }"""

OLD_QUIT = "autoUpdater.quitAndInstall();"
NEW_QUIT = "autoUpdater.quitAndInstall(true, true);"


def replace_once(path: Path, old: str, new: str) -> bool:
    if not path.is_file():
        print(f"    skip (missing): {path}")
        return False
    text = path.read_text()
    if new in text and old not in text:
        print(f"    already patched: {path}")
        return True
    if old not in text:
        print(f"    WARNING: pattern not found: {path}")
        return False
    path.write_text(text.replace(old, new, 1))
    print(f"    patched: {path}")
    return True


def main() -> int:
    root = Path(sys.argv[1] if len(sys.argv) > 1 else ".").resolve()
    plugin = root / "vendor/nativephp/electron/resources/js/electron-plugin"
    ok = True
    ok = replace_once(plugin / "src/index.ts", OLD_TS, NEW_TS) and ok
    ok = replace_once(plugin / "dist/index.js", OLD_DIST, NEW_DIST) and ok
    ok = replace_once(plugin / "src/server/api/autoUpdater.ts", OLD_QUIT, NEW_QUIT) and ok
    ok = replace_once(plugin / "dist/server/api/autoUpdater.js", OLD_QUIT, NEW_QUIT) and ok
    return 0 if ok else 1


if __name__ == "__main__":
    raise SystemExit(main())
