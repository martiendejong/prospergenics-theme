#!/usr/bin/env python3
"""Load the ProsperGenics knowledge base (./kb/) into the CoachOS tenant.

    COACHOS_EMAIL=... COACHOS_PASSWORD=... \\
      python load_kb.py

Idempotent: all chunks land in the "ProsperGenics (automatisch)" kennismap.
A re-run empties that map first, then re-uploads the current set.
Documents the coach uploaded by hand (other maps) are never touched.

Standard library only. Adapted from portofgiethoorn/coachos-kb/load_coachos.py.
"""
from __future__ import annotations

import argparse
import json
import os
import re
import sys
import urllib.error
import urllib.request
import uuid
from pathlib import Path

HERE = Path(__file__).resolve().parent
DEFAULT_URL = "https://coaching.martiendejong.nl/coaching"
DEFAULT_TENANT = "prospergenics"
DEFAULT_FOLDER = "ProsperGenics (automatisch)"


def upload_name(title: str) -> str:
    name = re.sub(r"\s*[:/\\?*\"<>|]+\s*", " - ", title).strip(" .-")
    return f"{name}.md"


def multipart(fields: dict[str, str], file_name: str, content: bytes) -> tuple[bytes, str]:
    boundary = f"----pgkb{uuid.uuid4().hex}"
    out = bytearray()
    for key, value in fields.items():
        out += f'--{boundary}\r\nContent-Disposition: form-data; name="{key}"\r\n\r\n{value}\r\n'.encode()
    out += (f'--{boundary}\r\nContent-Disposition: form-data; name="file"; filename="{file_name}"\r\n'
            "Content-Type: text/markdown\r\n\r\n").encode("utf-8")
    out += content + b"\r\n" + f"--{boundary}--\r\n".encode()
    return bytes(out), f"multipart/form-data; boundary={boundary}"


class CoachOS:
    def __init__(self, url: str, tenant: str) -> None:
        self.url = url.rstrip("/")
        self.tenant = tenant
        self.token = ""

    def call(self, method: str, path: str, body: bytes | None = None, content_type: str = "application/json"):
        headers = {"X-Tenant": self.tenant, "Accept": "application/json"}
        if body is not None:
            headers["Content-Type"] = content_type
        if self.token:
            headers["Authorization"] = f"Bearer {self.token}"
        req = urllib.request.Request(f"{self.url}{path}", data=body, method=method, headers=headers)
        try:
            with urllib.request.urlopen(req, timeout=120) as resp:
                raw = resp.read()
        except urllib.error.HTTPError as exc:
            detail = exc.read().decode("utf-8", "replace")[:300]
            raise SystemExit(f"{method} {path} -> HTTP {exc.code}: {detail}") from None
        return json.loads(raw) if raw else None

    def login(self, email: str, password: str) -> None:
        body = json.dumps({"gebruikersnaam": email, "wachtwoord": password, "tenant": self.tenant}).encode()
        self.token = self.call("POST", "/api/auth/login", body)["token"]

    def folder_id(self, name: str, create: bool = True) -> int | None:
        for folder in self.call("GET", "/api/kennismappen/") or []:
            if folder["naam"] == name:
                return folder["id"]
        if not create:
            return None
        return self.call("POST", "/api/kennismappen/", json.dumps({"naam": name}).encode())["id"]

    def documents(self) -> list[dict]:
        return self.call("GET", "/api/kennisbank/") or []


def main(argv: list[str] | None = None) -> int:
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument("--url", default=DEFAULT_URL)
    ap.add_argument("--tenant", default=DEFAULT_TENANT)
    ap.add_argument("--folder", default=DEFAULT_FOLDER)
    ap.add_argument("--kb", default=str(HERE / "kb"))
    ap.add_argument("--dry-run", action="store_true")
    args = ap.parse_args(argv)

    kb = Path(args.kb)
    manifest = json.loads((kb / "manifest.json").read_text(encoding="utf-8"))
    chunks = manifest["chunks"]
    names = [upload_name(c["title"]) for c in chunks]

    if args.dry_run:
        print(f"would load {len(chunks)} chunks into tenant '{args.tenant}', folder '{args.folder}'")
        for i, (c, n) in enumerate(zip(chunks, names)):
            print(f"  {i+1}. {n}")
        return 0

    email = os.environ.get("COACHOS_EMAIL", "")
    password = os.environ.get("COACHOS_PASSWORD", "")
    if not email or not password:
        raise SystemExit("set COACHOS_EMAIL and COACHOS_PASSWORD (a coach account for the tenant)")

    api = CoachOS(args.url, args.tenant)
    api.login(email, password)
    print(f"logged in as {email} on tenant '{args.tenant}'")

    folder = api.folder_id(args.folder, create=True)
    stale = [d for d in api.documents() if d.get("kennisMapId") == folder]
    for doc in stale:
        api.call("DELETE", f"/api/kennisbank/{doc['id']}")
    print(f"removed {len(stale)} previous documents from '{args.folder}'")

    for chunk, name in zip(chunks, names):
        body, ctype = multipart({"mapId": str(folder)}, name, (kb / chunk["file"]).read_bytes())
        result = api.call("POST", "/api/kennisbank/upload", body, ctype)
        ndoc = (result or {}).get("documenten", 0)
        print(f"  uploaded '{name}' -> {ndoc} document(s) in CoachOS")

    loaded = [d for d in api.documents() if d.get("kennisMapId") == folder]
    print(f"\ndone. '{args.folder}' now holds {len(loaded)} documents.")
    return 0


if __name__ == "__main__":
    sys.exit(main())
