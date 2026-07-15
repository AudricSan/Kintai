<?php

declare(strict_types=1);

namespace kintai\Core\Services;

/**
 * Recherche de noms tolérante à la langue : un texte tapé en romaji ou en
 * hiragana doit pouvoir retrouver un nom stocké en kanji, à condition que sa
 * lecture (furigana, saisie en katakana) soit connue.
 */
final class KanaSearch
{
    // Romaji (Hepburn) → hiragana. Les combinaisons les plus longues sont
    // testées en premier lors du parsing (voir romajiToHiragana()).
    private const ROMAJI_TO_HIRAGANA = [
        'kya' => 'きゃ', 'kyu' => 'きゅ', 'kyo' => 'きょ',
        'gya' => 'ぎゃ', 'gyu' => 'ぎゅ', 'gyo' => 'ぎょ',
        'sha' => 'しゃ', 'shu' => 'しゅ', 'sho' => 'しょ',
        'sya' => 'しゃ', 'syu' => 'しゅ', 'syo' => 'しょ',
        'ja'  => 'じゃ', 'ju'  => 'じゅ', 'jo'  => 'じょ',
        'zya' => 'じゃ', 'zyu' => 'じゅ', 'zyo' => 'じょ',
        'cha' => 'ちゃ', 'chu' => 'ちゅ', 'cho' => 'ちょ',
        'tya' => 'ちゃ', 'tyu' => 'ちゅ', 'tyo' => 'ちょ',
        'nya' => 'にゃ', 'nyu' => 'にゅ', 'nyo' => 'にょ',
        'hya' => 'ひゃ', 'hyu' => 'ひゅ', 'hyo' => 'ひょ',
        'bya' => 'びゃ', 'byu' => 'びゅ', 'byo' => 'びょ',
        'pya' => 'ぴゃ', 'pyu' => 'ぴゅ', 'pyo' => 'ぴょ',
        'mya' => 'みゃ', 'myu' => 'みゅ', 'myo' => 'みょ',
        'rya' => 'りゃ', 'ryu' => 'りゅ', 'ryo' => 'りょ',

        'shi' => 'し', 'chi' => 'ち', 'tsu' => 'つ',

        'ka' => 'か', 'ki' => 'き', 'ku' => 'く', 'ke' => 'け', 'ko' => 'こ',
        'ga' => 'が', 'gi' => 'ぎ', 'gu' => 'ぐ', 'ge' => 'げ', 'go' => 'ご',
        'sa' => 'さ', 'si' => 'し', 'su' => 'す', 'se' => 'せ', 'so' => 'そ',
        'za' => 'ざ', 'ji' => 'じ', 'zi' => 'じ', 'zu' => 'ず', 'ze' => 'ぜ', 'zo' => 'ぞ',
        'ta' => 'た', 'ti' => 'ち', 'tu' => 'つ', 'te' => 'て', 'to' => 'と',
        'da' => 'だ', 'di' => 'ぢ', 'du' => 'づ', 'de' => 'で', 'do' => 'ど',
        'na' => 'な', 'ni' => 'に', 'nu' => 'ぬ', 'ne' => 'ね', 'no' => 'の',
        'ha' => 'は', 'hi' => 'ひ', 'fu' => 'ふ', 'hu' => 'ふ', 'he' => 'へ', 'ho' => 'ほ',
        'ba' => 'ば', 'bi' => 'び', 'bu' => 'ぶ', 'be' => 'べ', 'bo' => 'ぼ',
        'pa' => 'ぱ', 'pi' => 'ぴ', 'pu' => 'ぷ', 'pe' => 'ぺ', 'po' => 'ぽ',
        'ma' => 'ま', 'mi' => 'み', 'mu' => 'む', 'me' => 'め', 'mo' => 'も',
        'ya' => 'や', 'yu' => 'ゆ', 'yo' => 'よ',
        'ra' => 'ら', 'ri' => 'り', 'ru' => 'る', 're' => 'れ', 'ro' => 'ろ',
        'wa' => 'わ', 'wo' => 'を',

        'a' => 'あ', 'i' => 'い', 'u' => 'う', 'e' => 'え', 'o' => 'お',
        'n' => 'ん',
    ];

    /** Consonnes pouvant être doublées pour marquer un petit "tsu" (っ). */
    private const SOKUON_CONSONANTS = ['k', 'g', 's', 'z', 't', 'd', 'h', 'b', 'p', 'm', 'r', 'y', 'w', 'j', 'c', 'f'];

    /**
     * Convertit hiragana → katakana ; le katakana, le kanji et le latin
     * traversent inchangés. Sert de forme normalisée commune pour comparer
     * une lecture furigana (saisie en katakana) à une saisie utilisateur.
     */
    public static function toKatakana(string $text): string
    {
        return mb_convert_kana($text, 'C', 'UTF-8');
    }

    /**
     * Convertit une saisie romaji (ex. "yamada") en katakana (ex. "ヤマダ").
     * Renvoie '' si le texte n'est pas purement romaji (rien à convertir).
     */
    public static function romajiToKatakana(string $text): string
    {
        $text = strtolower(trim($text));
        if ($text === '' || !preg_match('/^[a-z\s\'-]+$/', $text)) {
            return '';
        }

        $hiragana = self::romajiToHiragana($text);
        return $hiragana === '' ? '' : self::toKatakana($hiragana);
    }

    private static function romajiToHiragana(string $romaji): string
    {
        $romaji = preg_replace('/[\s\'-]+/', '', $romaji) ?? '';
        $length = strlen($romaji);
        $result = '';
        $i = 0;

        while ($i < $length) {
            if (
                $i + 1 < $length
                && $romaji[$i] === $romaji[$i + 1]
                && in_array($romaji[$i], self::SOKUON_CONSONANTS, true)
            ) {
                $result .= 'っ';
                $i++;
                continue;
            }

            $matched = false;
            foreach ([3, 2, 1] as $chunkLength) {
                if ($i + $chunkLength > $length) {
                    continue;
                }
                $chunk = substr($romaji, $i, $chunkLength);
                if (isset(self::ROMAJI_TO_HIRAGANA[$chunk])) {
                    $result .= self::ROMAJI_TO_HIRAGANA[$chunk];
                    $i += $chunkLength;
                    $matched = true;
                    break;
                }
            }

            if (!$matched) {
                // Caractère non reconnu (ponctuation, chiffre...) : on l'ignore
                // pour rester tolérant plutôt que d'abandonner toute la recherche.
                $i++;
            }
        }

        return $result;
    }

    /**
     * @param string   $needle    Texte tapé par l'utilisateur.
     * @param string[] $texts     Champs "littéraux" (nom kanji, email, code...).
     * @param string[] $readings  Champs de lecture (furigana, en katakana).
     */
    public static function matches(string $needle, array $texts, array $readings = []): bool
    {
        $needle = trim($needle);
        if ($needle === '') {
            return true;
        }

        $needleLower = mb_strtolower($needle);
        foreach ($texts as $text) {
            if ($text !== '' && str_contains(mb_strtolower((string) $text), $needleLower)) {
                return true;
            }
        }

        if ($readings === []) {
            return false;
        }

        // La saisie peut déjà être en kana (hiragana/katakana) : on la
        // normalise directement. Elle peut aussi être en romaji : on la
        // convertit. Les deux formes sont comparées à chaque lecture.
        $needleKatakana      = self::toKatakana($needle);
        $needleFromRomaji    = self::romajiToKatakana($needle);

        foreach ($readings as $reading) {
            if ($reading === '') {
                continue;
            }
            $readingKatakana = self::toKatakana((string) $reading);
            if (str_contains($readingKatakana, $needleKatakana)) {
                return true;
            }
            if ($needleFromRomaji !== '' && str_contains($readingKatakana, $needleFromRomaji)) {
                return true;
            }
        }

        return false;
    }
}
