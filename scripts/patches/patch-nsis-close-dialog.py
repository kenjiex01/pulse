#!/usr/bin/env python3
"""Stop the Windows installer from asking to close People360 when the old uninstall fails."""

from pathlib import Path
import sys

ROOT = Path(sys.argv[1] if len(sys.argv) > 1 else ".").resolve()
NSIS = ROOT / "vendor/nativephp/electron/resources/js/node_modules/app-builder-lib/templates/nsis"

UNINSTALL_OLD = """    ${if} $R5 > 5
      MessageBox MB_RETRYCANCEL|MB_ICONEXCLAMATION "$(appCannotBeClosed)" /SD IDCANCEL IDRETRY OneMoreAttempt
      Return
    ${endIf}"""

UNINSTALL_NEW = """    ${if} $R5 > 1
      ; People360: a failed old uninstaller is not "the app is open".
      ; Remove the program folder and continue. Payroll data stays in AppData.
      DetailPrint `Old uninstaller failed ($R0). Removing "$installationDir" and continuing.`
      ${if} $installationDir != ""
        RMDir /r "$installationDir"
      ${endIf}
      StrCpy $R0 0
      ClearErrors
      Return
    ${endIf}"""

EXTRACT_OLD = """      MessageBox MB_RETRYCANCEL|MB_ICONEXCLAMATION "$(appCannotBeClosed)" /SD IDRETRY IDCANCEL AbortExtract7za"""

EXTRACT_NEW = """      DetailPrint `Copy failed. Overwriting "${PRODUCT_NAME}" files.`"""


def replace_once(path: Path, old: str, new: str, label: str) -> None:
    text = path.read_text()
    if new in text:
        print(f"already patched: {path}")
        return
    if old not in text:
        raise SystemExit(f"{label} block not found in {path}")
    path.write_text(text.replace(old, new, 1))
    print(f"patched: {path}")


def main() -> None:
    replace_once(
        NSIS / "include/installUtil.nsh",
        UNINSTALL_OLD,
        UNINSTALL_NEW,
        "uninstallOldVersion",
    )
    replace_once(
        NSIS / "include/extractAppPackage.nsh",
        EXTRACT_OLD,
        EXTRACT_NEW,
        "extractUsing7za",
    )


if __name__ == "__main__":
    main()
