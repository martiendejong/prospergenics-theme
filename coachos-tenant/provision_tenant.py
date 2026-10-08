#!/usr/bin/env python3
"""Create and configure the CoachOS tenant behind the Port of Giethoorn chat widget.

    COACHOS_ADMIN_USER=... COACHOS_ADMIN_PASSWORD=... \\
    COACHOS_COACH_EMAIL=... COACHOS_COACH_PASSWORD=... \\
      python provision_tenant.py --url https://coaching.martiendejong.nl/coaching

Reads ./tenant.json (slug, persona, welcome message, suggestions, languages) and:

  1. platform admin login -> creates the tenant when it does not exist yet
     (needs COACHOS_ADMIN_*; skipped when the tenant is already there);
  2. coach login of that tenant -> PUT /api/instellingen/teksten with the config;
  3. --verify (also run after every apply): reads the public /api/branding/{slug}
     back and checks that the welcome message, languages and suggestions are what
     tenant.json says.

Idempotent: PUT /teksten sets the same values every time, and an existing tenant is
never re-created or its coach account touched.  The knowledge base is a different
step (../coachos-kb/load_coachos.py, task 3872).  Credentials only come from the
environment, never from a file.  Standard library only.  Task 3871.
"""
from __future__ import annotations

import argparse
import json
import os
import re
import sys
import urllib.error
import urllib.request
from pathlib import Path

HERE = Path(__file__).resolve().parent
DEFAULT_URL = "https://coaching.martiendejong.nl/coaching"


class CoachOS:
    def __init__(self, url: str, tenant: str) -> None:
        self.url = url.rstrip("/")
        self.tenant = tenant
        self.token = ""

    def call(self, method: str, path: str, payload=None, tenant_header: bool = True):
        headers = {"Accept": "application/json"}
        if tenant_header:
            headers["X-Tenant"] = self.tenant
        body = None
        if payload is not None:
            body = json.dumps(payload).encode("utf-8")
            headers["Content-Type"] = "application/json"
        if self.token:
            headers["Authorization"] = f"Bearer {self.token}"
        req = urllib.request.Request(f"{self.url}{path}", data=body, method=method, headers=headers)
        try:
            with urllib.request.urlopen(req, timeout=60) as resp:
                raw = resp.read()
        except urllib.error.HTTPError as exc:
            detail = exc.read().decode("utf-8", "replace")[:300]
            raise SystemExit(f"{method} {path} -> HTTP {exc.code}: {detail}") from None
        return json.loads(raw) if raw else None


def load_config(path: Path) -> dict:
    cfg = json.loads(path.read_text(encoding="utf-8"))
    problems = validate(cfg)
    if problems:
        raise SystemExit("tenant.json invalid:\n  " + "\n  ".join(problems))
    return cfg


def validate(cfg: dict) -> list[str]:
    problems: list[str] = []
    slug = cfg.get("slug", "")
    if not (isinstance(slug, str) and re.fullmatch(r"[a-z0-9-]{2,}", slug)):
        problems.append("slug must be 2+ chars of a-z, 0-9 and '-' (CoachOS rule)")
    talen = cfg.get("talen") or []
    if cfg.get("taal") not in talen:
        problems.append("'taal' (default language) must be one of 'talen'")
    if not str(cfg.get("aiPersona", "")).strip():
        problems.append("aiPersona is empty")
    if not str(cfg.get("basis", {}).get("assistantIntro", "")).strip():
        problems.append("basis.assistantIntro (default-language welcome message) is empty")
    for lang in talen:
        if lang == cfg.get("taal"):
            continue
        if not str(cfg.get("vertalingen", {}).get(lang, {}).get("assistantIntro", "")).strip():
            problems.append(f"vertalingen.{lang}.assistantIntro is empty")
    for lang in talen:
        ui = cfg.get("uiTeksten", {}).get(lang, {})
        # chat_disclaimer mag bewust leeg zijn ("": verbergt de regel in de chat — Martien
        # 08-10: clutter); hij moet alleen als sleutel AANWEZIG zijn, anders valt de widget
        # terug op de platform-standaardtekst.
        if "chat_disclaimer" not in ui:
            problems.append(f"uiTeksten.{lang}.chat_disclaimer ontbreekt (leeg mag, afwezig niet)")
        if not str(ui.get("chat_suggestie_1", "")).strip():
            problems.append(f"uiTeksten.{lang}.chat_suggestie_1 is empty")
    return problems


def teksten_payload(cfg: dict) -> dict:
    """The body of PUT /api/instellingen/teksten (TekstenVerzoek)."""
    return {
        "taal": cfg["taal"],
        "talen": cfg["talen"],
        "aiPersona": cfg["aiPersona"],
        "basis": cfg["basis"],
        "vertalingen": cfg.get("vertalingen", {}),
        "uiTeksten": cfg.get("uiTeksten", {}),
    }


def check_branding(cfg: dict, branding: dict) -> list[str]:
    """Differences between tenant.json and what the public branding endpoint serves."""
    diffs: list[str] = []
    if branding.get("talen") != cfg["talen"]:
        diffs.append(f"talen: {branding.get('talen')} != {cfg['talen']}")
    if branding.get("assistantIntro") != cfg["basis"]["assistantIntro"]:
        diffs.append("assistantIntro (default language) differs")
    for lang, vert in cfg.get("vertalingen", {}).items():
        served = (branding.get("vertalingen") or {}).get(lang) or {}
        if served.get("assistantIntro") != vert.get("assistantIntro"):
            diffs.append(f"vertalingen.{lang}.assistantIntro differs")
    for lang, teksten in cfg.get("uiTeksten", {}).items():
        served = (branding.get("uiTeksten") or {}).get(lang) or {}
        for key, value in teksten.items():
            if served.get(key) != value:
                diffs.append(f"uiTeksten.{lang}.{key} differs: {served.get(key)!r}")
    return diffs


def ensure_tenant(api: CoachOS, cfg: dict, dry_run: bool) -> bool:
    """Create the tenant when missing.  True when it was (or would be) created."""
    user = os.environ.get("COACHOS_ADMIN_USER", "")
    password = os.environ.get("COACHOS_ADMIN_PASSWORD", "")
    if not (user and password):
        print("admin credentials not set: assuming the tenant exists")
        return False
    admin = CoachOS(api.url, api.tenant)
    admin.token = admin.call("POST", "/api/admin/login", {"gebruikersnaam": user, "wachtwoord": password}, tenant_header=False)["token"]
    known = {t["slug"] for t in admin.call("GET", "/api/admin/tenants", tenant_header=False) or []}
    if cfg["slug"] in known:
        print(f"tenant '{cfg['slug']}' exists")
        return False
    email = os.environ.get("COACHOS_COACH_EMAIL", "")
    coach_password = os.environ.get("COACHOS_COACH_PASSWORD", "")
    if not (email and coach_password):
        raise SystemExit("tenant is missing: set COACHOS_COACH_EMAIL and COACHOS_COACH_PASSWORD to create it")
    if dry_run:
        print(f"[dry-run] would create tenant '{cfg['slug']}' ({cfg['naam']}) with coach {email}")
        return True
    admin.call("POST", "/api/admin/tenants", {
        "slug": cfg["slug"], "naam": cfg["naam"], "coachEmail": email,
        "coachWachtwoord": coach_password, "coachNaam": cfg["coachNaam"],
    }, tenant_header=False)
    print(f"tenant '{cfg['slug']}' created")
    return True


def main(argv: list[str] | None = None) -> int:
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument("--url", default=DEFAULT_URL, help="CoachOS base URL (default: %(default)s)")
    ap.add_argument("--config", type=Path, default=HERE / "tenant.json")
    ap.add_argument("--dry-run", action="store_true", help="validate and print the plan, change nothing")
    ap.add_argument("--verify", action="store_true", help="only read the public branding back and compare")
    args = ap.parse_args(argv)

    cfg = load_config(args.config)
    api = CoachOS(args.url, cfg["slug"])

    if args.verify:
        diffs = check_branding(cfg, api.call("GET", f"/api/branding/{cfg['slug']}", tenant_header=False))
        print("branding matches tenant.json" if not diffs else "\n".join(diffs))
        return 1 if diffs else 0

    if args.dry_run and not os.environ.get("COACHOS_ADMIN_USER"):
        print(f"[dry-run] config ok; would PUT /api/instellingen/teksten for '{cfg['slug']}': "
              f"languages {cfg['talen']}, persona {len(cfg['aiPersona'])} chars")
        return 0

    created = ensure_tenant(api, cfg, args.dry_run)
    if args.dry_run:
        print("[dry-run] would apply persona/welcome/suggestions")
        return 0

    email = os.environ.get("COACHOS_COACH_EMAIL", "")
    coach_password = os.environ.get("COACHOS_COACH_PASSWORD", "")
    if not (email and coach_password):
        raise SystemExit("set COACHOS_COACH_EMAIL and COACHOS_COACH_PASSWORD (the tenant's coach account)")
    api.token = api.call("POST", "/api/auth/login", {"gebruikersnaam": email, "wachtwoord": coach_password, "tenant": cfg["slug"]})["token"]
    api.call("PUT", "/api/instellingen/teksten", teksten_payload(cfg))
    print(f"persona, welcome messages ({', '.join(cfg['talen'])}) and suggestions applied"
          + (" to the new tenant" if created else ""))

    diffs = check_branding(cfg, api.call("GET", f"/api/branding/{cfg['slug']}", tenant_header=False))
    if diffs:
        print("branding does not match after applying:\n  " + "\n  ".join(diffs))
        return 1
    print("verified: public branding matches tenant.json")
    return 0


if __name__ == "__main__":
    sys.exit(main())
