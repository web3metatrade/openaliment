"""Compatibility entry point for the manifest-based release builder."""
import runpy
from pathlib import Path

runpy.run_path(str(Path(__file__).resolve().parent / "build/package.py"), run_name="__main__")
