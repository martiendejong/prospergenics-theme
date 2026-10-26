#!/usr/bin/env python3
"""
Build the theme + profile/list pages and upload them to the Vimexx hosting
(test.prospergenics.com moved there 2026-10-18; deploy-wp-theme-to-test.py
targeted the old IIS server and is superseded by this script).

    python deploy-wp-theme-vimexx.py

Credentials: PG_FTP_USER / PG_FTP_PASSWORD from the environment when set (GitHub Actions),
otherwise the Jengo vault (Martien's machine).

Server: web0168.zxcs.nl (Vimexx DA pakket u63291p434771, FTPS poort 21,
TLS session reuse verplicht). FTP root = DA home, so paths start at domains/.
Uploads:
  wp-theme/prospergenics-2026/  -> public_html/wp-content/themes/prospergenics-2026/
  people/<slug>/index.html      -> public_html/people/<slug>/index.html
  people/index.html             -> public_html/people/index.html (redirect to the people list)
  experts/index.html            -> public_html/experts/index.html
  community/index.html          -> public_html/community/index.html
                                   (relative asset URLs rewritten to the theme assets URL)
  team/<slug>/index.html        -> redirect stub to /people/<slug>/ (the profiles
                                   briefly lived under /team/ on 18-10)
"""
import ftplib, io, json, os, re, subprocess, sys

HERE   = os.path.dirname(os.path.abspath(__file__))
THEME  = os.path.normpath(os.path.join(HERE, "..", "..", "wp-theme", "prospergenics-2026"))
PEOPLE = os.path.join(HERE, "people")
LISTS  = ("experts", "community")
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


REDIRECT = """<!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8" />
<meta name="robots" content="noindex" />
<meta http-equiv="refresh" content="0; url=/people/{slug}/" />
<link rel="canonical" href="/people/{slug}/" />
<title>Moved · ProsperGenics</title></head>
<body><p>This profile moved to <a href="/people/{slug}/">/people/{slug}/</a>.</p></body></html>
"""


def main():
    subprocess.run([sys.executable, os.path.join(PEOPLE, "build-profiles.py")], check=True)
    subprocess.run([sys.executable, os.path.join(HERE, "build-wp-theme.py")], check=True)

    # CI (GitHub Actions, .github/workflows/deploy-test.yml) passes the FTPS login as
    # environment variables from repository secrets; on Martien's machine it comes from the vault.
    if os.environ.get("PG_FTP_USER") and os.environ.get("PG_FTP_PASSWORD"):
        cred = {"username": os.environ["PG_FTP_USER"], "password": os.environ["PG_FTP_PASSWORD"]}
    else:
        cred = vault_cred(VAULT_PROJECT, VAULT_CRED,
                          "deploy prospergenics-2026 thema + profielpagina's naar test.prospergenics.com")
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

    # personal profile pages (static, outside the theme); rewrite relative asset URLs
    for slug in sorted(os.listdir(PEOPLE)):
        page = os.path.join(PEOPLE, slug, "index.html")
        if not os.path.isfile(page):
            continue
        with open(page, encoding="utf-8") as f:
            html = f.read().replace("../../assets/", THEME_ASSETS)
        remote_dir = f"{REMOTE_ROOT}/people/{slug}"
        ensure_dir(ftp, remote_dir)
        ftp.storbinary(f"STOR {remote_dir}/index.html", io.BytesIO(html.encode("utf-8")))
        # the profiles briefly lived under /team/<slug>/ (18-10): leave a redirect
        redir_dir = f"{REMOTE_ROOT}/team/{slug}"
        ensure_dir(ftp, redir_dir)
        ftp.storbinary(f"STOR {redir_dir}/index.html",
                       io.BytesIO(REDIRECT.format(slug=slug).encode("utf-8")))
        count += 2

    # /people/ itself: a redirect to the people list (build-profiles.py), so it is no 403
    with open(os.path.join(PEOPLE, "index.html"), "rb") as f:
        ftp.storbinary(f"STOR {REMOTE_ROOT}/people/index.html", f)
    count += 1

    # list pages (experts/, community/)
    for folder in LISTS:
        with open(os.path.join(HERE, folder, "index.html"), encoding="utf-8") as f:
            html = f.read().replace("../assets/", THEME_ASSETS)
        remote_dir = f"{REMOTE_ROOT}/{folder}"
        ensure_dir(ftp, remote_dir)
        ftp.storbinary(f"STOR {remote_dir}/index.html", io.BytesIO(html.encode("utf-8")))
        count += 1

    ftp.quit()
    print(f"deploy klaar: {count} bestanden naar {FTP_HOST}")


if __name__ == "__main__":
    main()
