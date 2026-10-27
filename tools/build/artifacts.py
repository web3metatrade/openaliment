"""Build all generated contracts in isolation; --check never rewrites the project."""
import argparse
import json
import os
import subprocess
import sys
import tempfile
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
GENERATORS = ['consumer_contracts.py', 'external_contracts.py', 'contracts.py', 'integration_contracts.py', 'home_contracts.py', 'api_contracts.py', 'api_documentation.py', 'operations_assets.py']


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--check', action='store_true', help='Fail on generated drift without writing contracts/')
    args = parser.parse_args()
    runtime = ROOT / 'tests/runtime'
    runtime.mkdir(parents=True, exist_ok=True)
    with tempfile.TemporaryDirectory(prefix='contracts-', dir=runtime) as directory:
        output = Path(directory)
        environment = dict(os.environ, OA_CONTRACTS_OUT=str(output))
        for script in GENERATORS:
            subprocess.run([sys.executable, str(ROOT / 'tools/build' / script)], env=environment, cwd=ROOT, check=True, stdout=subprocess.DEVNULL)
        changed = []
        for generated in sorted(output.rglob('*.json')):
            relative = generated.relative_to(output)
            target = ROOT / 'contracts' / relative
            if not target.exists() or json.loads(target.read_text(encoding='utf-8')) != json.loads(generated.read_text(encoding='utf-8')) or (relative.as_posix()=='platform-openapi.json' and target.stat().st_size>2097152):
                changed.append(relative.as_posix())
                if not args.check:
                    target.parent.mkdir(parents=True, exist_ok=True)
                    target.write_bytes(generated.read_bytes())
        print(json.dumps({'mode': 'check' if args.check else 'build', 'generated': len(list(output.rglob('*.json'))), 'changed': changed}))
        return 1 if args.check and changed else 0


if __name__ == '__main__':
    raise SystemExit(main())
