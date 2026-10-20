<?php

declare(strict_types=1);

/**
 * Parcours GET d'une instance de démonstration, un compte par rôle, en suivant les liens internes.
 *
 * Usage : php scripts/ci/crawl-smoke.php <url-de-base> <e-mail> <mot-de-passe> [<e-mail> <mot-de-passe> ...]
 *
 * Échoue si une page répond 5xx, ou affiche une erreur PHP (Fatal error, Warning, Notice, Stack trace). Les tests
 * unitaires simulent les dépôts et ne voient pas ces pannes (schéma, vue, droit) : seul un vrai parcours les trouve.
 * Une page par « motif » d'URL (identifiants remplacés) ne suffit pas, d'où MAX_PER_PATTERN exemplaires.
 */

const MAX_PER_PATTERN = 3;
const MAX_DEPTH       = 4;
// Actions sans page à vérifier, flux continus, exports binaires, ressources statiques et routes à effet de bord.
const SKIP = '#^/(logout|cron/|api/|ical/|bundle-assets/|sw\.js|manifest\.json|lang/|assets/|storage/)|/stream$|/export|/download|\.ics$|/pdf|/print|/backup|/docs/#';

[$script, $base] = $argv + [null, null];
$accounts = array_chunk(array_slice($argv, 2), 2);
if ($base === null || $accounts === [] || count($accounts[count($accounts) - 1]) !== 2) {
    fwrite(STDERR, "Usage : crawl-smoke.php <url-de-base> <e-mail> <mot-de-passe> [...]\n");
    exit(2);
}
$base = rtrim($base, '/');

/** @return array{0:int,1:string,2:string} statut, corps, en-tête Location */
function request(string $url, string $jar, ?array $post = null): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER         => true,
        CURLOPT_COOKIEFILE     => $jar,
        CURLOPT_COOKIEJAR      => $jar,
        CURLOPT_TIMEOUT        => 60,
    ]);
    if ($post !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
    }
    $raw    = (string) curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $size   = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    $head = substr($raw, 0, $size);
    $loc  = preg_match('/^Location:\s*(\S+)/mi', $head, $m) ? $m[1] : '';
    return [$status, substr($raw, $size), $loc];
}

function pathOf(string $base, string $url): ?string
{
    if (str_starts_with($url, $base)) {
        $url = substr($url, strlen($base));
    }
    return str_starts_with($url, '/') && !str_starts_with($url, '//') ? $url : null;
}

$failures = [];
foreach ($accounts as [$email, $password]) {
    $jar = tempnam(sys_get_temp_dir(), 'crawl');
    [, $page] = request($base . '/login', $jar);
    if (!preg_match('/name="(_token|csrf_token|_csrf)"[^>]*value="([^"]+)"/', $page, $t)) {
        fwrite(STDERR, "Jeton CSRF introuvable sur /login\n");
        exit(1);
    }
    request($base . '/login', $jar, [$t[1] => $t[2], 'login_mode' => 'email', 'email' => $email, 'password' => $password]);

    $seen = $perPattern = [];
    $frontier = ['/'];
    $count = 0;
    for ($depth = 0; $depth < MAX_DEPTH && $frontier !== []; $depth++) {
        $next = [];
        foreach ($frontier as $path) {
            $pattern = preg_replace('#/\d+#', '/{n}', explode('?', $path)[0]);
            if (isset($seen[$path]) || preg_match(SKIP, $path) || ($perPattern[$pattern] ?? 0) >= MAX_PER_PATTERN) {
                continue;
            }
            $seen[$path] = true;
            $perPattern[$pattern] = ($perPattern[$pattern] ?? 0) + 1;
            [$status, $body, $loc] = request($base . $path, $jar);
            $count++;

            if ($status >= 500 || $status === 0) {
                $failures[] = "[$email] $path → $status";
            } elseif (preg_match('/(Fatal error|Parse error|Warning|Notice|Deprecated):|Stack trace:/', $body, $m)) {
                $failures[] = "[$email] $path → erreur PHP affichée ({$m[0]})";
            }
            if ($status >= 300 && $status < 400 && ($p = pathOf($base, $loc)) !== null) {
                $next[] = $p;
            }
            if ($status === 200 && preg_match_all('/href="([^"#]*)"/', $body, $links)) {
                foreach ($links[1] as $href) {
                    if (($p = pathOf($base, html_entity_decode($href))) !== null) {
                        $next[] = $p;
                    }
                }
            }
        }
        $frontier = $next;
    }
    echo "$email : $count pages parcourues\n";
    @unlink($jar);
}

if ($failures !== []) {
    fwrite(STDERR, "ÉCHEC : " . count($failures) . " page(s) en erreur\n  " . implode("\n  ", array_unique($failures)) . "\n");
    exit(1);
}
echo "OK : aucune page en erreur.\n";
