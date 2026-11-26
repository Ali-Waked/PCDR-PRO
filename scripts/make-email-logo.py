#!/usr/bin/env python3
"""Generate the deterministic, opaque email logo from the committed master logo."""
import hashlib
import importlib.util
import struct
import zlib
from collections import Counter
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
HELPERS = ROOT / "scripts" / "make-icons.py"
MASTER = ROOT / "public" / "assets" / "pcdr-homepage" / "logo" / "pcdr-logo.png"
OUTPUTS = [
    ROOT / "resources" / "mail" / "email-logo.png",
    ROOT / "public" / "assets" / "email" / "email-logo.png",
]
SIZE = 240
MAX_COLORS = 256
MAX_BYTES = 40 * 1024
WHITE = (255, 255, 255)


def load_helpers():
    spec = importlib.util.spec_from_file_location("pcdr_make_icons", HELPERS)
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    return module


def chunk(chunk_type, payload):
    return (
        struct.pack(">I", len(payload))
        + chunk_type
        + payload
        + struct.pack(">I", zlib.crc32(chunk_type + payload) & 0xFFFFFFFF)
    )


def opaque_logo(helpers):
    width, height, master = helpers.png_read(MASTER)
    resized = helpers.resize(master, width, height, SIZE, SIZE)
    canvas = helpers.blank(SIZE, SIZE, WHITE)
    helpers.composite(canvas, SIZE, resized, 0, 0, SIZE)

    for index in range(0, len(canvas), 4):
        canvas[index + 3] = 255

    return bytes(canvas)


def indexed_png(rgba):
    pixels = [tuple(rgba[index:index + 3]) for index in range(0, len(rgba), 4)]
    counts = Counter(pixels)
    palette = [color for color, _ in counts.most_common(MAX_COLORS)]
    lookup = {color: index for index, color in enumerate(palette)}

    for color in counts:
        if color not in lookup:
            lookup[color] = min(
                range(len(palette)),
                key=lambda index: sum((color[channel] - palette[index][channel]) ** 2 for channel in range(3)),
            )

    raw = bytearray()
    for row in range(SIZE):
        offset = row * SIZE
        raw.append(0)
        for color in pixels[offset:offset + SIZE]:
            raw.append(lookup[color])

    return (
        b"\x89PNG\r\n\x1a\n"
        + chunk(b"IHDR", struct.pack(">IIBBBBB", SIZE, SIZE, 8, 3, 0, 0, 0))
        + chunk(b"PLTE", bytes(channel for color in palette for channel in color))
        + chunk(b"IDAT", zlib.compress(bytes(raw), 9))
        + chunk(b"IEND", b"")
    )


def main():
    data = indexed_png(opaque_logo(load_helpers()))
    if len(data) >= MAX_BYTES:
        raise SystemExit(f"email logo exceeds {MAX_BYTES} bytes: {len(data)}")

    digest = hashlib.sha256(data).hexdigest()
    for path in OUTPUTS:
        path.parent.mkdir(parents=True, exist_ok=True)
        path.write_bytes(data)
        print(f"{path.relative_to(ROOT)}: {len(data)} bytes sha256:{digest}")


if __name__ == "__main__":
    main()
