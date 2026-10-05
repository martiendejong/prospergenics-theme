#!/usr/bin/env python3
"""
Build + deploy the "Prospergenics 2026" WP theme to the WordPress install
on https://test.prospergenics.com (one command).

    python deploy-wp-theme-to-test.py

What it does:
  - runs build-wp-theme.py (regenerates ../../wp-theme/prospergenics-2026
    from the static design in THIS folder)
  - uploads the theme folder to the prod server (85.215.217.154) at
    C:/stores/prospergenics-wp/wp-content/themes/prospergenics-2026

The WordPress system itself (PHP 8.3 FastCGI + WP core + SQLite drop-in,
IIS site "test.prospergenics.com" -> C:/stores/prospergenics-wp) was set up
2026-10-05 and stays in place; this only refreshes theme files.
The old static preview lives untouched in C:/stores/prospergenics-test
(deploy-to-test.py targeted that folder and is now superseded by this script).

Requirements: vault helper (prod SSH password, project 6 / credential 7) + paramiko.
WP admin login: user jengo-admin, password in vault (project 6, "WP test.prospergenics.com admin").
"""
import json, os, re, subprocess, sys

HERE = os.path.dirname(os.path.abspath(__file__))
THEME = os.path.normpath(os.path.join(HERE, "..", "..", "wp-theme", "prospergenics-2026"))
VAULT = r"E:\projects\jengo\jengo-system-private\tools\vault-api.py"
HOST, USER = "85.215.217.154", "Administrator"
REMOTE = "C:/stores/prospergenics-wp/wp-content/themes/prospergenics-2026"


def prod_password():
    out = subprocess.run([sys.executable, VAULT, "credential", "6", "7",
                          "--reason", "Deploy prospergenics-2026 WP theme to test.prospergenics.com"],
                         capture_output=True, text=True, cwd=os.path.dirname(VAULT)).stdout
    return json.loads(re.search(r"\{.*\}", out, re.S).group())["password"]


def main():
    subprocess.run([sys.executable, os.path.join(HERE, "build-wp-theme.py")], check=True)
    import paramiko
    ssh = paramiko.SSHClient()
    ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    ssh.connect(HOST, username=USER, password=prod_password(), timeout=20)
    sftp = ssh.open_sftp()

    def mkdirs(p):
        try:
            sftp.stat(p)
        except IOError:
            mkdirs(p.rsplit("/", 1)[0])
            sftp.mkdir(p)

    n = 0
    for root, _dirs, files in os.walk(THEME):
        rel = os.path.relpath(root, THEME).replace(os.sep, "/")
        rdir = REMOTE if rel == "." else REMOTE + "/" + rel
        mkdirs(rdir)
        for fn in files:
            sftp.put(os.path.join(root, fn), rdir + "/" + fn)
            n += 1
    sftp.close()
    _, so, _ = ssh.exec_command(
        'powershell -Command "try{(Invoke-WebRequest http://localhost -Headers @{Host=\'test.prospergenics.com\'} -UseBasicParsing).StatusCode}catch{$_.Exception.Message}"',
        timeout=60)
    print(f"uploaded {n} theme files; origin http:", so.read().decode(errors="replace").strip())
    ssh.close()
    print("DONE -> https://test.prospergenics.com (WordPress)")


if __name__ == "__main__":
    main()
