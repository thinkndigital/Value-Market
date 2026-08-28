#!/usr/bin/env python3
"""
Regenerate an app icon file in place, at its EXACT current pixel dimensions, from the
Value Market master square icon (white background, wordmark centered).

Usage: python3 regenerate_icon.py <path/to/existing/icon.png-or-jpg> [--master /path/to/icon_master.png]

Reads the target file's current size BEFORE overwriting (so Android/iOS/macOS icon slots that
require an exact pixel size stay correct), resizes the master down/up to match, and saves back
in the same format as the original file's extension (.png stays PNG with alpha removed to RGB;
.jpg/.jpeg saves as JPEG quality 92, matching how this codebase's iOS AppIcon.appiconset ships
PNG-content-as-.jpg already).
"""
import sys
import os
from PIL import Image

DEFAULT_MASTER = os.path.join(os.path.dirname(os.path.abspath(__file__)), "icon_master_1024.png")


def main():
    args = [a for a in sys.argv[1:] if not a.startswith("--master")]
    master_path = DEFAULT_MASTER
    for a in sys.argv[1:]:
        if a.startswith("--master="):
            master_path = a.split("=", 1)[1]

    if len(args) != 1:
        print("usage: regenerate_icon.py <target_icon_path> [--master=/path]", file=sys.stderr)
        sys.exit(1)

    target = args[0]
    if not os.path.exists(target):
        print(f"target does not exist: {target}", file=sys.stderr)
        sys.exit(1)

    with Image.open(target) as existing:
        size = existing.size

    master = Image.open(master_path).convert("RGB")
    resized = master.resize(size, Image.LANCZOS)

    ext = os.path.splitext(target)[1].lower()
    if ext in (".jpg", ".jpeg"):
        resized.save(target, "JPEG", quality=92)
    else:
        resized.save(target, "PNG")

    print(f"regenerated {target} at {size[0]}x{size[1]}")


if __name__ == "__main__":
    main()
