"""Fixed operations payload for hosts with an existing restricted HTTPS bridge."""
import base64
import hashlib
from common import ROOT, save

PATHS=['tools/ops/backup.php','tools/ops/restore.php','tools/ops/reconcile-erasure.php','tools/ops/monitor.py','config/backup.local.example.php','config/migrations.local.example.php','config/restore.local.example.php','config/external.local.example.php','config/anaf.local.example.php','app/Vendor/WebAuthn/LICENSE','THIRD_PARTY_NOTICES.md','release.json','LICENSING.md','tools/standards/fetch.py','tools/external-worker.php']
files={}
for name in PATHS:
    content=(ROOT/name).read_bytes()
    files[name]={'sha256':hashlib.sha256(content).hexdigest(),'content_base64':base64.b64encode(content).decode()}
save('operations-assets.json',{'schema_version':'openaliment.operations-assets.v1','files':files})
