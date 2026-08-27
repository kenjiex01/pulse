import { app } from 'electron'
import { existsSync, statSync } from 'fs'
import { join } from 'path'

/**
 * People360 was rebranded from Pulse. Electron stores SQLite under userData,
 * which follows the product name — so a People360 build would otherwise open
 * a new empty folder instead of the existing Pulse database.
 *
 * If the old Pulse userData folder already has pulse.sqlite, keep using it.
 */
function hasExistingSqlite(userDataDir) {
    const file = join(userDataDir, 'storage', 'app', 'pulse.sqlite')

    try {
        return existsSync(file) && statSync(file).size > 0
    } catch {
        return false
    }
}

const appData = app.getPath('appData')

for (const folderName of ['Pulse', 'pulse']) {
    const legacyDir = join(appData, folderName)

    if (hasExistingSqlite(legacyDir)) {
        app.setPath('userData', legacyDir)
        break
    }
}
