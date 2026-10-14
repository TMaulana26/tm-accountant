# Production VPS Read-Only Telemetry & Log Inspection

When the user asks to inspect real production data, transaction history, or application logs on the production VPS, follow these strict rules without exception:

## 1. SSH Connection & Fail2ban Protection
- The SSH host alias `vps-accountant-audit` is defined in `C:\Users\dimen\.ssh\config\hosts` (`User agy-reader`, `IdentityFile ~/.ssh/id_vps_reader`).
- Always pass `-F 'C:\Users\dimen\.ssh\config\hosts' -o BatchMode=yes -o IdentitiesOnly=yes -o ConnectTimeout=10` when invoking `ssh` so OpenSSH only offers the single `id_vps_reader` key and never triggers a Fail2ban IP ban:
  ```powershell
  ssh -F 'C:\Users\dimen\.ssh\config\hosts' -o BatchMode=yes -o IdentitiesOnly=yes -o ConnectTimeout=10 vps-accountant-audit '<read-only-command>'
  ```

## 2. Strict Repository & Directory Confinement
- You MUST operate **exclusively** within `/home/agy-reader/tm-accountant` (symlink to `/home/ubuntu/tm-accountant`).
- **STRICTLY FORBIDDEN**: Never `cd`, `ls`, `find`, or read any files, user directories, or other repositories outside `/home/agy-reader/tm-accountant`.
- **STRICTLY FORBIDDEN**: Never read `/home/agy-reader/tm-accountant/.env` or expose production API keys/tokens in chat output.

## 3. Strictly Read-Only Database & Log Operations
- All access to the VPS is **100% READ-ONLY**. Never execute `INSERT`, `UPDATE`, `DELETE`, `ALTER`, `DROP`, `php artisan migrate`, file modifications, or service restarts on the VPS.
- When querying the production SQLite database (`/home/agy-reader/tm-accountant/database/database.sqlite`), ALWAYS open it in read-only URI mode (`?mode=ro`) to prevent locking or mutating the production database:
  ```powershell
  @'
  import sqlite3
  conn = sqlite3.connect("file:/home/agy-reader/tm-accountant/database/database.sqlite?mode=ro", uri=True)
  conn.row_factory = sqlite3.Row
  cur = conn.cursor()
  # Execute SELECT queries only
  '@ | ssh -F 'C:\Users\dimen\.ssh\config\hosts' -o BatchMode=yes -o IdentitiesOnly=yes vps-accountant-audit 'python3 -'
  ```
- Production logs are located at `/home/agy-reader/tm-accountant/storage/logs/laravel.log` and must only be inspected with read-only tools (`tail`, `grep`, or Python read-only file streams).
