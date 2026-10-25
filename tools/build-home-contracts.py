"""Compatibility entry point; implementation lives in tools/build/."""
import runpy
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent / "build"))
runpy.run_path(str(Path(__file__).resolve().parent / "build" / "home_contracts.py"), run_name="__main__")
