#!/usr/bin/env bash
# Installe Kintai sans git ni composer : télécharge l'archive d'une Release GitHub (code + vendor/, construite
# par .github/workflows/release.yml), vérifie son empreinte SHA-256 (SHA256SUMS publié avec elle) et la
# décompresse dans un dossier neuf. L'installation se termine ensuite comme d'habitude : installateur web
# (/install.php) ou scripts/provision.php.
#
# Usage en une ligne :
#   curl -fsSL https://raw.githubusercontent.com/AudricSan/Kintai/main/scripts/install.sh | bash
#   curl -fsSL https://raw.githubusercontent.com/AudricSan/Kintai/main/scripts/install.sh | bash -s -- --channel beta --dir /var/www/kintai
#
# Options (ou variables d'environnement équivalentes, pratiques avec « | bash ») :
#   --channel stable|beta|alpha   KINTAI_CHANNEL   canal de release (défaut : stable) — même règle que /admin/update :
#                                                  stable = releases de main, beta = tout sauf alpha, alpha = tout
#   --version vX.Y.Z              KINTAI_VERSION   version précise (ignore --channel)
#   --dir CHEMIN                  KINTAI_DIR       dossier d'installation (défaut : ./kintai), absent ou vide
#
# Prérequis : curl, unzip, sha256sum (ou shasum), et php ou jq pour lire la liste des releases (inutile avec --version).
# Variable optionnelle : GITHUB_TOKEN, pour éviter la limite de 60 requêtes/heure de l'API GitHub anonyme.

set -euo pipefail

REPO="AudricSan/Kintai"
CHANNEL="${KINTAI_CHANNEL:-stable}"
VERSION="${KINTAI_VERSION:-}"
TARGET="${KINTAI_DIR:-kintai}"

fail() { printf 'Erreur : %s\n' "$1" >&2; exit 1; }
info() { printf '==> %s\n' "$1"; }

while [ $# -gt 0 ]; do
    case "$1" in
        --channel) CHANNEL="${2:-}"; shift 2 ;;
        --channel=*) CHANNEL="${1#*=}"; shift ;;
        --version) VERSION="${2:-}"; shift 2 ;;
        --version=*) VERSION="${1#*=}"; shift ;;
        --dir) TARGET="${2:-}"; shift 2 ;;
        --dir=*) TARGET="${1#*=}"; shift ;;
        -h|--help) sed -n '2,20p' "$0" 2>/dev/null | sed 's/^# \{0,1\}//'; exit 0 ;;
        *) fail "option inconnue : $1 (voir --help)" ;;
    esac
done

case "$CHANNEL" in stable|beta|alpha) ;; *) fail "canal invalide « $CHANNEL » (stable, beta ou alpha)" ;; esac
[ -n "$TARGET" ] || fail "--dir ne peut pas être vide"

for cmd in curl unzip; do
    command -v "$cmd" >/dev/null 2>&1 || fail "commande requise introuvable : $cmd"
done
if command -v sha256sum >/dev/null 2>&1; then
    SHA_CMD="sha256sum"
elif command -v shasum >/dev/null 2>&1; then
    SHA_CMD="shasum -a 256"
else
    fail "commande requise introuvable : sha256sum (ou shasum)"
fi

# Jamais d'écrasement : une instance existante se met à jour depuis /admin/update, pas avec ce script.
if [ -e "$TARGET" ] && [ -n "$(ls -A "$TARGET" 2>/dev/null)" ]; then
    fail "le dossier « $TARGET » existe déjà et n'est pas vide (une instance existante se met à jour depuis /admin/update)"
fi

# PHP CLI sans avertissements de démarrage : certaines installations (XAMPP : « Module "openssl" is already
# loaded ») les écrivent sur stdout, ce qui polluerait les valeurs lues ci-dessous.
php_cli() { php -d display_startup_errors=0 -d display_errors=stderr "$@"; }

# PHP n'est pas indispensable au téléchargement, mais Kintai ne tournera pas sans PHP 8.3+ : on prévient tôt.
if command -v php >/dev/null 2>&1; then
    if ! php_cli -r 'exit(version_compare(PHP_VERSION, "8.3.0", ">=") ? 0 : 1);' 2>/dev/null; then
        printf 'Attention : PHP %s détecté, Kintai exige PHP 8.3 ou plus.\n' "$(php_cli -r 'echo PHP_VERSION;' 2>/dev/null)" >&2
    fi
else
    printf 'Attention : php introuvable dans le PATH — Kintai exige PHP 8.3+ côté serveur web.\n' >&2
fi

CURL_AUTH=()
if [ -n "${GITHUB_TOKEN:-}" ]; then
    CURL_AUTH=(-H "Authorization: Bearer ${GITHUB_TOKEN}")
fi

# Tag de la release la plus récente du canal, selon la même règle que GithubUpdateService::filterReleasesForChannel().
latest_tag_for_channel() {
    local json
    # Forme ${t[@]+…} : un tableau vide sous « set -u » plante sur le bash 3.2 de macOS.
    json=$(curl -fsSL ${CURL_AUTH[@]+"${CURL_AUTH[@]}"} -H "Accept: application/vnd.github+json" \
        "https://api.github.com/repos/${REPO}/releases?per_page=100") \
        || fail "impossible de lire la liste des releases GitHub (réseau ou limite de l'API : définir GITHUB_TOKEN)"

    if command -v php >/dev/null 2>&1; then
        printf '%s' "$json" | php_cli -r '
            $channel = $argv[1];
            $releases = json_decode(stream_get_contents(STDIN), true) ?: [];
            $ok = array_filter($releases, function ($r) use ($channel) {
                if (!empty($r["draft"])) return false;
                $branch = (string) ($r["target_commitish"] ?? "");
                return match ($channel) {
                    "alpha" => true,
                    "beta"  => $branch !== "alpha",
                    default => $branch === "main" && empty($r["prerelease"]),
                };
            });
            usort($ok, fn ($a, $b) => version_compare(ltrim($b["tag_name"], "v"), ltrim($a["tag_name"], "v")));
            echo $ok[0]["tag_name"] ?? "";
        ' "$CHANNEL"
    elif command -v jq >/dev/null 2>&1; then
        printf '%s' "$json" | jq -r --arg c "$CHANNEL" '
            map(select(.draft | not)
                | select(if $c == "alpha" then true
                         elif $c == "beta" then .target_commitish != "alpha"
                         else .target_commitish == "main" and (.prerelease | not) end))
            | .[].tag_name' | sort -V | tail -n 1
    else
        fail "php ou jq est requis pour choisir la release d'un canal (sinon, passer --version vX.Y.Z)"
    fi
}

if [ -z "$VERSION" ]; then
    info "Recherche de la dernière release du canal « $CHANNEL »…"
    # Filet de sécurité : seule une ligne de la forme vX.Y.Z est retenue, quoi qu'ait pu afficher d'autre l'outil.
    # (affectation séparée : sous « set -e », un échec de latest_tag_for_channel arrête le script avec son propre message).
    RAW=$(latest_tag_for_channel)
    VERSION=$(printf '%s\n' "$RAW" | grep -E '^v?[0-9]+\.[0-9]+\.[0-9]+$' | tail -n 1 || true)
    if [ -z "$VERSION" ]; then
        fail "aucune release publiée sur le canal « $CHANNEL » — essayer --channel beta ou --channel alpha"
    fi
fi
case "$VERSION" in v*) ;; *) VERSION="v${VERSION}" ;; esac

NAME="kintai-${VERSION}"
BASE_URL="https://github.com/${REPO}/releases/download/${VERSION}"
TMP=$(mktemp -d)
trap 'rm -rf "$TMP"' EXIT

info "Téléchargement de Kintai ${VERSION}…"
curl -fSL --progress-bar -o "${TMP}/${NAME}.zip" "${BASE_URL}/${NAME}.zip" \
    || fail "archive ${NAME}.zip introuvable sur la release ${VERSION} (les releases antérieures à l'archive vérifiée n'en ont pas)"
curl -fsSL -o "${TMP}/SHA256SUMS" "${BASE_URL}/SHA256SUMS" \
    || fail "SHA256SUMS introuvable sur la release ${VERSION} : archive non vérifiable, installation annulée"

info "Vérification de l'empreinte SHA-256…"
EXPECTED=$(awk -v f="${NAME}.zip" '$2 == f || $2 == "*" f { print $1 }' "${TMP}/SHA256SUMS")
ACTUAL=$(cd "$TMP" && $SHA_CMD "${NAME}.zip" | awk '{ print $1 }')
[ -n "$EXPECTED" ] || fail "aucune empreinte pour ${NAME}.zip dans SHA256SUMS"
[ "$EXPECTED" = "$ACTUAL" ] || fail "empreinte SHA-256 incorrecte (attendue ${EXPECTED}, obtenue ${ACTUAL}) : archive corrompue ou altérée"

info "Décompression dans « ${TARGET} »…"
unzip -q "${TMP}/${NAME}.zip" -d "${TMP}/extract"
[ -d "${TMP}/extract/${NAME}" ] || fail "structure d'archive inattendue (dossier ${NAME}/ absent)"
mkdir -p "$TARGET"
# Copie fichiers cachés compris (.htaccess racine, .env.example…).
cp -R "${TMP}/extract/${NAME}/." "$TARGET/"

# storage/ doit rester inscriptible par le serveur web (base SQLite, journaux, bundles, téléversements).
chmod -R u+rwX "${TARGET}/storage" 2>/dev/null || true

TARGET_ABS=$(cd "$TARGET" && pwd)
cat <<EOF

Kintai ${VERSION} est prêt dans ${TARGET_ABS}

Étapes suivantes :
  1. Faire pointer la racine web (DocumentRoot / root) sur ${TARGET_ABS}/public
     (sur Apache, pointer sur ${TARGET_ABS} fonctionne aussi grâce au .htaccess racine).
     Le serveur web doit pouvoir écrire dans ${TARGET_ABS}/storage.
  2. Ouvrir http://<votre-hôte>/install.php pour terminer l'installation,
     ou, sans navigateur :
       php ${TARGET_ABS}/scripts/provision.php --admin-email=… --admin-password=… --app-url=https://…

Test rapide en local :
  php -S 127.0.0.1:8000 -t ${TARGET_ABS}/public   puis   http://127.0.0.1:8000/install.php
EOF
