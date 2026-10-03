#!/usr/bin/env python3
"""
Redeploy this hero preview to https://test.prospergenics.com (one command).

    python deploy-to-test.py

What it does:
  - uploads index.html + assets/ from THIS folder to the prod server
    (85.215.217.154) at C:/stores/prospergenics-test
  - the IIS site "test.prospergenics.com" + Let's Encrypt cert + Cloudflare DNS
    were created once on 2026-10-04 and stay in place; this only refreshes files.

Requirements (Martien's machine): the Jengo vault helper at
E:/projects/jengo/jengo-system-private/tools/vault-api.py (prod SSH password,
project 6 / credential 7) and paramiko. No secrets are stored in this repo.
"""
import json, os, re, subprocess, sys

HERE = os.path.dirname(os.path.abspath(__file__))
VAULT = r"E:\projects\jengo\jengo-system-private\tools\vault-api.py"
HOST, USER = "85.215.217.154", "Administrator"
REMOTE = "C:/stores/prospergenics-test"
ASSETS_DIR = os.path.join(HERE, "assets")


def prod_password():
    out = subprocess.run([sys.executable, VAULT, "credential", "6", "7",
                          "--reason", "Redeploy prospergenics hero preview to test.prospergenics.com"],
                         capture_output=True, text=True, cwd=os.path.dirname(VAULT)).stdout
    return json.loads(re.search(r"\{.*\}", out, re.S).group())["password"]


def main():
    import paramiko
    ssh = paramiko.SSHClient()
    ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    ssh.connect(HOST, username=USER, password=prod_password(), timeout=20)
    sftp = ssh.open_sftp()
    for d in (REMOTE, REMOTE + "/assets"):
        try: sftp.mkdir(d)
        except IOError: pass
    sftp.put(os.path.join(HERE, "index.html"), REMOTE + "/index.html")
    print("uploaded index.html")
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
