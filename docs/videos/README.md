# Test recordings (local only — not in git)

Video files here are **gitignored**. Do not commit or push them.

| File (local) | What it shows |
|------|----------------|
| `employee-profile-weekly-upload-test.webm` | Employee Profile → Upload: download CSV template (weekly shift columns) and upload round-trip to preview |

Regenerate:

```bash
cd pulse
chmod +x scripts/record-employee-profile-upload-video.sh
./scripts/record-employee-profile-upload-video.sh
```

Uses seeded login `superadmin@icct.edu.ph` / `Password123!` unless `PULSE_E2E_EMAIL` and `PULSE_E2E_PASSWORD` are set.
