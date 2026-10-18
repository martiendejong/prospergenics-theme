#!/usr/bin/env python3
"""
Build the theme + team profile pages and upload them to the Vimexx hosting
(test.prospergenics.com moved there 2026-10-18; deploy-wp-theme-to-test.py
targeted the old IIS server and is superseded by this script).

    python deploy-wp-theme-vimexx.py

Server: web0168.zxcs.nl (Vimexx DA pakket u63291p434771, FTPS poort 21,
TLS session reuse verplicht). FTP root = DA home, so paths start at domains/.
Uploads:
  wp-theme/prospergenics-2026/  -> public_html/wp-content/themes/prospergenics-2026/
  team/<slug>/index.html        -> public_html/team/<slug>/index.html
                                   (../../assets/ rewritten to the theme assets URL)
"""
import ftplib, io, json, os, re, subprocess, sys

HERE   = os.path.dirname(os.path.abspath(__file__))
THEME  = os.path.normpath(os.path.join(HERE, "..", "..", "wp-theme", "prospergenics-2026"))
TEAM   = os.path.join(HERE, "team")
VAULT  = "E:/projects/jengo/jengo-system-private/tools/vault-api.py"

VAULT_PROJECT = 6
VAULT_CRED    = 266
FTP_HOST      = "web0168.zxcs.nl"
FTP_PORT      = 21
REMOTE_ROOT   = "domains/test.prospergenics.com/public_html"
REMOTE_THEME  = f"{REMOTE_ROOT}/wp-content/themes/prospergenics-2026"
THEME_ASSETS  = "/wp-content/themes/prospergenics-2026/assets/"


class FTPS(ftplib.FTP_TLS):
    """FTPS met TLS-session-reuse (web0168 vereist dit)."""
    def ntransfercmd(self, cmd, rest=None):
        conn, size = ftplib.FTP.ntransfercmd(self, cmd, rest)
        if self._prot_p:
            conn = self.context.wrap_socket(conn, server_hostname=self.host,
                                            session=self.sock.session)
        return conn, size


def vault_cred(project, cred_id, reason):
    r = subprocess.run([sys.executable, VAULT, "credential", str(project), str(cred_id),
                        "--reason", reason], capture_output=True, text=True)
    m = re.search(r"\{.*\}", r.stdout, re.S)
    if not m:
        sys.exit(f"vault gaf geen credential terug:\n{r.stdout}\n{r.stderr}")
    return json.loads(m.group(0))


def ensure_dir(ftp, path):
    parts = path.split("/")
    for i in range(1, len(parts) + 1):
        try:
            ftp.mkd("/".join(parts[:i]))
        except ftplib.error_perm:
            pass


def main():
    subprocess.run([sys.executable, os.path.join(TEAM, "build-profiles.py")], check=True)
    subprocess.run([sys.executable, os.path.join(HERE, "build-wp-theme.py")], check=True)

    cred = vault_cred(VAULT_PROJECT, VAULT_CRED,
                      "deploy prospergenics-2026 thema + team-profielen naar test.prospergenics.com")
    ftp = FTPS()
    ftp.connect(FTP_HOST, FTP_PORT, timeout=60)
    ftp.login(cred["username"], cred["password"])
    ftp.prot_p()

    count = 0
    # theme
    for root, _dirs, files in os.walk(THEME):
        rel = os.path.relpath(root, THEME).replace("\\", "/")
        remote_dir = REMOTE_THEME if rel == "." else f"{REMOTE_THEME}/{rel}"
        ensure_dir(ftp, remote_dir)
        for fn in files:
            with open(os.path.join(root, fn), "rb") as f:
                ftp.storbinary(f"STOR {remote_dir}/{fn}", f, blocksize=262144)
            count += 1
            if count % 10 == 0:
                print(f"  {count} bestanden...")

    # team profile pages (static, outside the theme); rewrite relative asset URLs
    for slug in sorted(os.listdir(TEAM)):
        page = os.path.join(TEAM, slug, "index.html")
        if not os.path.isfile(page):
            continue
        with open(page, encoding="utf-8") as f:
            html = f.read().replace("../../assets/", THEME_ASSETS)
        remote_dir = f"{REMOTE_ROOT}/team/{slug}"
        ensure_dir(ftp, remote_dir)
        ftp.storbinary(f"STOR {remote_dir}/index.html", io.BytesIO(html.encode("utf-8")))
        count += 1

    ftp.quit()
    print(f"deploy klaar: {count} bestanden naar {FTP_HOST}")


if __name__ == "__main__":
    main()
