#!/usr/bin/env python3
"""
SUPERSEDED 2026-10-05: test.prospergenics.com now runs WordPress
(IIS site points at C:/stores/prospergenics-wp). Use deploy-wp-theme-to-test.py
instead; this script still uploads to the old static folder
C:/stores/prospergenics-test, which is kept but no longer served.

Redeploy this hero preview to https://test.prospergenics.com (one command).

    python deploy-to-test.py

What it does:
  - uploads index.html, about/index.html (the About page, /about/) and assets/
    from THIS folder to the prod server (85.215.217.154) at
    C:/stores/prospergenics-test
  - the IIS site "test.prospergenics.com" + Let's Encrypt cert + Cloudflare DNS
    were created once on 2026-10-04 and stay in place; this only refreshes files.

Requirements: the Jengo vault helper vault-api.py (prod SSH password, project 32 /
credential 142, fallback project 6 / credential 7) and paramiko. The helper is looked up at $PG_VAULT_API, then
E:/projects/jengo/... (Martien's machine), then C:/projects/jengo/... (dev/orchestration
host). No secrets are stored in this repo.
"""
import json, os, re, subprocess, sys

HERE = os.path.dirname(os.path.abspath(__file__))
_VAULT_CANDIDATES = (os.environ.get("PG_VAULT_API"),
                     r"E:\projects\jengo\jengo-system-private\tools\vault-api.py",
                     r"C:\projects\jengo\jengo-system-private\tools\vault-api.py")
VAULT = next((p for p in _VAULT_CANDIDATES if p and os.path.exists(p)), _VAULT_CANDIDATES[1])
HOST, USER = "85.215.217.154", "Administrator"
REMOTE = "C:/stores/prospergenics-test"
ASSETS_DIR = os.path.join(HERE, "assets")
# extra single-file pages: (local path relative to HERE, remote path relative to REMOTE)
PAGES = [("about/index.html", "about/index.html")]


# (vault project, credential) holding the prod Administrator SSH login. 32/142 is the
# deploy-agent copy (kept current for unattended agents); 6/7 is the gated original.
CREDENTIALS = [("32", "142"), ("6", "7")]


def vault_password(project, cred):
    out = subprocess.run([sys.executable, VAULT, "credential", project, cred,
                          "--reason", "Redeploy prospergenics hero preview to test.prospergenics.com"],
                         capture_output=True, text=True, cwd=os.path.dirname(VAULT)).stdout
    m = re.search(r"\{.*\}", out, re.S)
    return json.loads(m.group()).get("password") if m else None


def connect():
    import paramiko
    for project, cred in CREDENTIALS:
        password = vault_password(project, cred)
        if not password:
            continue
        ssh = paramiko.SSHClient()
        ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
        try:
            ssh.connect(HOST, username=USER, password=password, timeout=20)
            return ssh
        except paramiko.AuthenticationException:
            print("vault %s/%s: authentication failed, trying next" % (project, cred))
    sys.exit("no vault credential could log in to " + HOST)


def main():
    ssh = connect()
    sftp = ssh.open_sftp()
    for d in (REMOTE, REMOTE + "/assets", REMOTE + "/about"):
        try: sftp.mkdir(d)
        except IOError: pass
    sftp.put(os.path.join(HERE, "index.html"), REMOTE + "/index.html")
    print("uploaded index.html")
    for local, remote in PAGES:
        sftp.put(os.path.join(HERE, local), REMOTE + "/" + remote)
        print("uploaded " + remote)
    for a in sorted(os.listdir(ASSETS_DIR)):
        sftp.put(os.path.join(ASSETS_DIR, a), REMOTE + "/assets/" + a)
        print("uploaded assets/" + a)
    sftp.close()
    # health check via the origin
    _, so, _ = ssh.exec_command(
        'powershell -Command "try{(Invoke-WebRequest http://localhost -Headers @{Host=\'test.prospergenics.com\'} -UseBasicParsing).StatusCode}catch{$_.Exception.Message}"',
        timeout=60)
    print("origin http:", so.read().decode(errors="replace").strip())
    ssh.close()
    print("DONE -> https://test.prospergenics.com")


if __name__ == "__main__":
    main()
