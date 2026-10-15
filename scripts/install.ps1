<#
Installe Kintai sans git ni composer (Windows / PowerShell 5.1+) : télécharge l'archive d'une Release GitHub
(code + vendor/, construite par .github/workflows/release.yml), vérifie son empreinte SHA-256 (SHA256SUMS publié
avec elle) et la décompresse dans un dossier neuf. L'installation se termine ensuite comme d'habitude :
installateur web (/install.php) ou scripts/provision.php. Équivalent de scripts/install.sh.

Usage en une ligne :
  irm https://raw.githubusercontent.com/AudricSan/Kintai/main/scripts/install.ps1 | iex
  & ([scriptblock]::Create((irm https://raw.githubusercontent.com/AudricSan/Kintai/main/scripts/install.ps1))) -Channel beta -Dir C:\xampp\htdocs\kintai

Paramètres (ou variables d'environnement équivalentes, pratiques avec « | iex ») :
  -Channel stable|beta|alpha   KINTAI_CHANNEL   canal de release (défaut : stable) — même règle que /admin/update :
                                                stable = releases de main, beta = tout sauf alpha, alpha = tout
  -Version vX.Y.Z              KINTAI_VERSION   version précise (ignore -Channel)
  -Dir CHEMIN                  KINTAI_DIR       dossier d'installation (défaut : .\kintai), absent ou vide
Variable optionnelle : GITHUB_TOKEN, pour éviter la limite de 60 requêtes/heure de l'API GitHub anonyme.
#>
param(
    [string] $Channel = $(if ($env:KINTAI_CHANNEL) { $env:KINTAI_CHANNEL } else { 'stable' }),
    [string] $Version = $env:KINTAI_VERSION,
    [string] $Dir = $(if ($env:KINTAI_DIR) { $env:KINTAI_DIR } else { 'kintai' })
)

$ErrorActionPreference = 'Stop'
# Windows PowerShell 5.1 ne négocie pas TLS 1.2 par défaut : GitHub refuserait la connexion.
[Net.ServicePointManager]::SecurityProtocol = [Net.ServicePointManager]::SecurityProtocol -bor [Net.SecurityProtocolType]::Tls12
# La barre de progression d'Invoke-WebRequest ralentit énormément les téléchargements sous 5.1.
$ProgressPreference = 'SilentlyContinue'

$Repo = 'AudricSan/Kintai'

# throw plutôt qu'exit : lancé via « | iex », exit fermerait la fenêtre PowerShell de l'utilisateur.
function Fail([string] $Message) { throw "Erreur : $Message" }
function Info([string] $Message) { Write-Host "==> $Message" }

if ($Channel -notin @('stable', 'beta', 'alpha')) { Fail "canal invalide « $Channel » (stable, beta ou alpha)" }
if (-not $Dir) { Fail '-Dir ne peut pas être vide' }

# Jamais d'écrasement : une instance existante se met à jour depuis /admin/update, pas avec ce script.
if ((Test-Path -LiteralPath $Dir) -and (Get-ChildItem -LiteralPath $Dir -Force | Select-Object -First 1)) {
    Fail "le dossier « $Dir » existe déjà et n'est pas vide (une instance existante se met à jour depuis /admin/update)"
}

# PHP n'est pas indispensable au téléchargement, mais Kintai ne tournera pas sans PHP 8.3+ : on prévient tôt.
$php = Get-Command php -ErrorAction SilentlyContinue
if ($php) {
    # php peut afficher des avertissements de démarrage (extension introuvable…) avant la version : on n'en garde que le motif X.Y.Z.
    $phpOutput = (& php -d display_startup_errors=0 -r 'echo PHP_VERSION;' 2>$null) -join "`n"
    if ($phpOutput -match '(\d+\.\d+\.\d+)') {
        if ([version] $Matches[1] -lt [version] '8.3.0') {
            Write-Warning "PHP $($Matches[1]) détecté, Kintai exige PHP 8.3 ou plus."
        }
    } else {
        Write-Warning 'version de PHP illisible — Kintai exige PHP 8.3+ côté serveur web.'
    }
} else {
    Write-Warning 'php introuvable dans le PATH — Kintai exige PHP 8.3+ côté serveur web (XAMPP : C:\xampp\php).'
}

$headers = @{ 'Accept' = 'application/vnd.github+json'; 'User-Agent' = 'kintai-installer' }
if ($env:GITHUB_TOKEN) { $headers['Authorization'] = "Bearer $($env:GITHUB_TOKEN)" }

if (-not $Version) {
    Info "Recherche de la dernière release du canal « $Channel »…"
    try {
        $releases = Invoke-RestMethod -Headers $headers -Uri "https://api.github.com/repos/$Repo/releases?per_page=100"
    } catch {
        Fail "impossible de lire la liste des releases GitHub (réseau ou limite de l'API : définir GITHUB_TOKEN) — $($_.Exception.Message)"
    }

    # Même règle que GithubUpdateService::filterReleasesForChannel().
    $candidates = @($releases | Where-Object {
        -not $_.draft -and $(switch ($Channel) {
            'alpha' { $true }
            'beta' { $_.target_commitish -ne 'alpha' }
            default { $_.target_commitish -eq 'main' -and -not $_.prerelease }
        })
    })
    $latest = $candidates | Sort-Object { [version] ($_.tag_name.TrimStart('v')) } -Descending | Select-Object -First 1
    if (-not $latest) { Fail "aucune release publiée sur le canal « $Channel » — essayer -Channel beta ou -Channel alpha" }
    $Version = $latest.tag_name
}
if (-not $Version.StartsWith('v')) { $Version = "v$Version" }

$name = "kintai-$Version"
$baseUrl = "https://github.com/$Repo/releases/download/$Version"
$tmp = Join-Path ([IO.Path]::GetTempPath()) ("kintai-install-" + [guid]::NewGuid().ToString('N'))
New-Item -ItemType Directory -Path $tmp | Out-Null

try {
    $zip = Join-Path $tmp "$name.zip"
    $sums = Join-Path $tmp 'SHA256SUMS'

    # WebClient plutôt qu'Invoke-WebRequest : sous Windows PowerShell 5.1, ce dernier peut rester bloqué sur la
    # redirection de GitHub vers son stockage d'assets (fichier à 0 octet qui ne progresse plus).
    $client = New-Object System.Net.WebClient
    $client.Headers['User-Agent'] = 'kintai-installer'

    Info "Téléchargement de Kintai $Version…"
    try {
        $client.DownloadFile("$baseUrl/$name.zip", $zip)
    } catch {
        Fail "archive $name.zip introuvable sur la release $Version (les releases antérieures à l'archive vérifiée n'en ont pas)"
    }
    try {
        $client.DownloadFile("$baseUrl/SHA256SUMS", $sums)
    } catch {
        Fail "SHA256SUMS introuvable sur la release ${Version} : archive non vérifiable, installation annulée"
    }

    Info 'Vérification de l''empreinte SHA-256…'
    $expected = Get-Content -LiteralPath $sums | ForEach-Object {
        $parts = $_ -split '\s+', 2
        if ($parts.Count -eq 2 -and $parts[1].TrimStart('*') -eq "$name.zip") { $parts[0].ToLowerInvariant() }
    } | Select-Object -First 1
    if (-not $expected) { Fail "aucune empreinte pour $name.zip dans SHA256SUMS" }
    $actual = (Get-FileHash -Algorithm SHA256 -LiteralPath $zip).Hash.ToLowerInvariant()
    if ($expected -ne $actual) {
        Fail "empreinte SHA-256 incorrecte (attendue $expected, obtenue $actual) : archive corrompue ou altérée"
    }

    Info "Décompression dans « $Dir »…"
    $extract = Join-Path $tmp 'extract'
    Expand-Archive -LiteralPath $zip -DestinationPath $extract
    $source = Join-Path $extract $name
    if (-not (Test-Path -LiteralPath $source -PathType Container)) { Fail "structure d'archive inattendue (dossier $name\ absent)" }

    New-Item -ItemType Directory -Path $Dir -Force | Out-Null
    # -Force : copie aussi les fichiers cachés (.htaccess racine, .env.example…).
    Get-ChildItem -LiteralPath $source -Force | Copy-Item -Destination $Dir -Recurse -Force
} finally {
    Remove-Item -LiteralPath $tmp -Recurse -Force -ErrorAction SilentlyContinue
}

$target = (Resolve-Path -LiteralPath $Dir).Path
Write-Host @"

Kintai $Version est prêt dans $target

Étapes suivantes :
  1. Faire pointer la racine web (DocumentRoot / root) sur $target\public
     (sur Apache/XAMPP, pointer sur $target fonctionne aussi grâce au .htaccess racine).
  2. Ouvrir http://<votre-hôte>/install.php pour terminer l'installation,
     ou, sans navigateur :
       php "$target\scripts\provision.php" --admin-email=… --admin-password=… --app-url=https://…

Test rapide en local :
  php -S 127.0.0.1:8000 -t "$target\public"   puis   http://127.0.0.1:8000/install.php
"@
