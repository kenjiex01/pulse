#!/usr/bin/env python3
"""Skip the work that blocks the People360 window on every launch.

NativePHP ran `php artisan optimize` before starting PHP, on every production
open. That rebuilds config, routes, and every Blade view before the window
exists. It also froze NATIVEPHP_SECRET into config cache, so the rebuild could
not be skipped safely.

People360 now:
- does not run artisan optimize before the window
- deletes leftover config/route caches from older builds
- reuses the native:config and native:php-ini JSON for the same app version
"""
from __future__ import annotations

import sys
from pathlib import Path


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


OLD_SHOULD_OPTIMIZE_TS = """function shouldOptimize(store) {
    /*
     * For some weird reason,
     * the cached config is not picked up on subsequent launches,
     * so we'll just rebuilt it every time for now
     */

    return process.env.NODE_ENV !== 'development';
    // return runningSecureBuild();
    // return runningSecureBuild() && store.get('optimized_version') !== app.getVersion();
}"""

NEW_SHOULD_OPTIMIZE_TS = """function shouldOptimize(_store) {
    // People360 does not rebuild config, routes, or views before the window.
    // artisan optimize also freezes the per-launch NATIVEPHP_SECRET into config cache.
    return false;
}

function clearStaleFrameworkCache() {
    for (const name of ['config.php', 'routes-v7.php', 'events.php', 'services.php', 'packages.php']) {
        const file = join(bootstrapCache, name);
        try {
            if (existsSync(file)) {
                unlinkSync(file);
            }
        } catch (error) {
            console.error('Could not clear framework cache', file, error);
        }
    }
}"""

OLD_SHOULD_OPTIMIZE_JS = """function shouldOptimize(store) {
    return process.env.NODE_ENV !== 'development';
}"""

NEW_SHOULD_OPTIMIZE_JS = """function shouldOptimize(_store) {
    // People360 does not rebuild config, routes, or views before the window.
    // artisan optimize also freezes the per-launch NATIVEPHP_SECRET into config cache.
    return false;
}
function clearStaleFrameworkCache() {
    for (const name of ['config.php', 'routes-v7.php', 'events.php', 'services.php', 'packages.php']) {
        const file = join(bootstrapCache, name);
        try {
            if (existsSync(file)) {
                unlinkSync(file);
            }
        }
        catch (error) {
            console.error('Could not clear framework cache', file, error);
        }
    }
}"""

OLD_SERVE_TS = """        const store = new Store({
            name: 'nativephp', // So it doesn't conflict with settings of the app
        });

        // Cache the project
        if (shouldOptimize(store)) {"""

NEW_SERVE_TS = """        const store = new Store({
            name: 'nativephp', // So it doesn't conflict with settings of the app
        });

        clearStaleFrameworkCache();

        // Cache the project
        if (shouldOptimize(store)) {"""

OLD_SERVE_JS = """        const store = new Store({
            name: 'nativephp',
        });
        if (shouldOptimize(store)) {"""

NEW_SERVE_JS = """        const store = new Store({
            name: 'nativephp',
        });
        clearStaleFrameworkCache();
        if (shouldOptimize(store)) {"""

OLD_IMPORT_TS = "import {mkdirSync, statSync, writeFileSync, existsSync} from 'fs'"
NEW_IMPORT_TS = "import {mkdirSync, statSync, writeFileSync, existsSync, unlinkSync} from 'fs'"

OLD_IMPORT_JS = "import { mkdirSync, statSync, writeFileSync, existsSync } from 'fs';"
NEW_IMPORT_JS = "import { mkdirSync, statSync, writeFileSync, existsSync, unlinkSync } from 'fs';"

OLD_INDEX_IMPORT_TS = 'import { resolve } from "path";'
NEW_INDEX_IMPORT_TS = '''import { existsSync, readFileSync, writeFileSync } from "fs";
import { join, resolve } from "path";'''

OLD_INDEX_IMPORT_JS = 'import { resolve } from "path";'
NEW_INDEX_IMPORT_JS = '''import { existsSync, readFileSync, writeFileSync } from "fs";
import { join, resolve } from "path";'''

OLD_LOAD_CONFIG_TS = '''  private async loadConfig() {
    let config = {};

    try {
      const result = await retrieveNativePHPConfig();

      config = JSON.parse(result.stdout);
    } catch (error) {
      console.error(error);
    }

    return config;
  }'''

NEW_LOAD_CONFIG_TS = '''  private startupProbeCache = null;
  private pendingStartupConfig = null;

  private startupProbeCachePath() {
    return join(app.getPath("userData"), "startup-probe-cache.json");
  }

  private readStartupProbeCache() {
    if (this.startupProbeCache) {
      return this.startupProbeCache;
    }

    try {
      if (!existsSync(this.startupProbeCachePath())) {
        return null;
      }

      const parsed = JSON.parse(readFileSync(this.startupProbeCachePath(), "utf8"));

      if (parsed?.version === app.getVersion() && parsed.config && Object.keys(parsed.config).length > 0 && parsed.phpIni) {
        this.startupProbeCache = parsed;
        return parsed;
      }
    } catch (error) {
      console.error(error);
    }

    return null;
  }

  private writeStartupProbeCache(config, phpIni) {
    if (!config || Object.keys(config).length === 0 || phpIni == null) {
      return;
    }

    const payload = {
      version: app.getVersion(),
      config,
      phpIni,
    };

    try {
      writeFileSync(this.startupProbeCachePath(), JSON.stringify(payload));
      this.startupProbeCache = payload;
    } catch (error) {
      console.error(error);
    }
  }

  private async loadConfig() {
    const cached = this.readStartupProbeCache();

    if (cached) {
      console.log("Using cached native:config for", app.getVersion());
      return cached.config;
    }

    let config = {};

    try {
      const result = await retrieveNativePHPConfig();

      config = JSON.parse(result.stdout);
    } catch (error) {
      console.error(error);
    }

    this.pendingStartupConfig = config;

    return config;
  }'''

OLD_LOAD_PHP_INI_TS = '''  private async loadPhpIni() {
    let config = {};

    try {
      const result = await retrievePhpIniSettings();

      config = JSON.parse(result.stdout);
    } catch (error) {
      console.error(error);
    }

    return config;
  }'''

NEW_LOAD_PHP_INI_TS = '''  private async loadPhpIni() {
    const cached = this.readStartupProbeCache();

    if (cached) {
      console.log("Using cached native:php-ini for", app.getVersion());
      return cached.phpIni;
    }

    let config = {};
    let loaded = false;

    try {
      const result = await retrievePhpIniSettings();

      config = JSON.parse(result.stdout);
      loaded = true;
    } catch (error) {
      console.error(error);
    }

    if (loaded) {
      this.writeStartupProbeCache(this.pendingStartupConfig, config);
    }

    return config;
  }'''

OLD_LOAD_CONFIG_JS = '''    loadConfig() {
        return __awaiter(this, void 0, void 0, function* () {
            let config = {};
            try {
                const result = yield retrieveNativePHPConfig();
                config = JSON.parse(result.stdout);
            }
            catch (error) {
                console.error(error);
            }
            return config;
        });
    }'''

NEW_LOAD_CONFIG_JS = '''    startupProbeCachePath() {
        return join(app.getPath("userData"), "startup-probe-cache.json");
    }
    readStartupProbeCache() {
        if (this.startupProbeCache) {
            return this.startupProbeCache;
        }
        try {
            if (!existsSync(this.startupProbeCachePath())) {
                return null;
            }
            const parsed = JSON.parse(readFileSync(this.startupProbeCachePath(), "utf8"));
            if ((parsed === null || parsed === void 0 ? void 0 : parsed.version) === app.getVersion()
                && parsed.config
                && Object.keys(parsed.config).length > 0
                && parsed.phpIni) {
                this.startupProbeCache = parsed;
                return parsed;
            }
        }
        catch (error) {
            console.error(error);
        }
        return null;
    }
    writeStartupProbeCache(config, phpIni) {
        if (!config || Object.keys(config).length === 0 || phpIni == null) {
            return;
        }
        const payload = {
            version: app.getVersion(),
            config,
            phpIni,
        };
        try {
            writeFileSync(this.startupProbeCachePath(), JSON.stringify(payload));
            this.startupProbeCache = payload;
        }
        catch (error) {
            console.error(error);
        }
    }
    loadConfig() {
        return __awaiter(this, void 0, void 0, function* () {
            const cached = this.readStartupProbeCache();
            if (cached) {
                console.log("Using cached native:config for", app.getVersion());
                return cached.config;
            }
            let config = {};
            try {
                const result = yield retrieveNativePHPConfig();
                config = JSON.parse(result.stdout);
            }
            catch (error) {
                console.error(error);
            }
            this.pendingStartupConfig = config;
            return config;
        });
    }'''

OLD_LOAD_PHP_INI_JS = '''    loadPhpIni() {
        return __awaiter(this, void 0, void 0, function* () {
            let config = {};
            try {
                const result = yield retrievePhpIniSettings();
                config = JSON.parse(result.stdout);
            }
            catch (error) {
                console.error(error);
            }
            return config;
        });
    }'''

NEW_LOAD_PHP_INI_JS = '''    loadPhpIni() {
        return __awaiter(this, void 0, void 0, function* () {
            const cached = this.readStartupProbeCache();
            if (cached) {
                console.log("Using cached native:php-ini for", app.getVersion());
                return cached.phpIni;
            }
            let config = {};
            let loaded = false;
            try {
                const result = yield retrievePhpIniSettings();
                config = JSON.parse(result.stdout);
                loaded = true;
            }
            catch (error) {
                console.error(error);
            }
            if (loaded) {
                this.writeStartupProbeCache(this.pendingStartupConfig, config);
            }
            return config;
        });
    }'''


def main() -> int:
    root = Path(sys.argv[1] if len(sys.argv) > 1 else ".").resolve()
    plugin = root / "vendor/nativephp/electron/resources/js/electron-plugin"
    php_ts = plugin / "src/server/php.ts"
    php_js = plugin / "dist/server/php.js"
    index_ts = plugin / "src/index.ts"
    index_js = plugin / "dist/index.js"

    ok = True
    ok = replace_once(php_ts, OLD_IMPORT_TS, NEW_IMPORT_TS) and ok
    ok = replace_once(php_ts, OLD_SHOULD_OPTIMIZE_TS, NEW_SHOULD_OPTIMIZE_TS) and ok
    ok = replace_once(php_ts, OLD_SERVE_TS, NEW_SERVE_TS) and ok
    ok = replace_once(php_js, OLD_IMPORT_JS, NEW_IMPORT_JS) and ok
    ok = replace_once(php_js, OLD_SHOULD_OPTIMIZE_JS, NEW_SHOULD_OPTIMIZE_JS) and ok
    ok = replace_once(php_js, OLD_SERVE_JS, NEW_SERVE_JS) and ok
    ok = replace_once(index_ts, OLD_INDEX_IMPORT_TS, NEW_INDEX_IMPORT_TS) and ok
    ok = replace_once(index_ts, OLD_LOAD_CONFIG_TS, NEW_LOAD_CONFIG_TS) and ok
    ok = replace_once(index_ts, OLD_LOAD_PHP_INI_TS, NEW_LOAD_PHP_INI_TS) and ok
    ok = replace_once(index_js, OLD_INDEX_IMPORT_JS, NEW_INDEX_IMPORT_JS) and ok
    ok = replace_once(index_js, OLD_LOAD_CONFIG_JS, NEW_LOAD_CONFIG_JS) and ok
    ok = replace_once(index_js, OLD_LOAD_PHP_INI_JS, NEW_LOAD_PHP_INI_JS) and ok
    return 0 if ok else 1


if __name__ == "__main__":
    raise SystemExit(main())
