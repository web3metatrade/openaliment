"""Shared deterministic output for contract generators, including check-only builds."""
import json
import os
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
OUT = Path(os.environ.get('OA_CONTRACTS_OUT', ROOT / 'contracts'))


def save(name, value):
    path = OUT / name
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(json.dumps(value, ensure_ascii=False, indent=2) + '\n', encoding='utf-8', newline='\n')
