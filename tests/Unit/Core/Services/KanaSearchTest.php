<?php

declare(strict_types=1);

namespace kintai\Tests\Unit\Core\Services;

use kintai\Core\Services\KanaSearch;
use PHPUnit\Framework\TestCase;

final class KanaSearchTest extends TestCase
{
    public function testRomajiToKatakanaConvertsBasicName(): void
    {
        $this->assertSame('ヤマダ', KanaSearch::romajiToKatakana('yamada'));
        $this->assertSame('タナカ', KanaSearch::romajiToKatakana('tanaka'));
    }

    public function testRomajiToKatakanaHandlesYouonAndSokuon(): void
    {
        $this->assertSame('キョウコ', KanaSearch::romajiToKatakana('kyouko'));
        $this->assertSame('リッカ', KanaSearch::romajiToKatakana('rikka'));
        $this->assertSame('ショウタ', KanaSearch::romajiToKatakana('shouta'));
    }

    public function testRomajiToKatakanaHandlesMoraicN(): void
    {
        // "annai" (案内) : ん isolé suivi de "na" + "i", pas un double-ん.
        $this->assertSame('アンナイ', KanaSearch::romajiToKatakana('annai'));
        $this->assertSame('ケンタ', KanaSearch::romajiToKatakana('kenta'));
    }

    public function testRomajiToKatakanaReturnsEmptyForNonRomajiInput(): void
    {
        $this->assertSame('', KanaSearch::romajiToKatakana('山田'));
        $this->assertSame('', KanaSearch::romajiToKatakana('yamada@example.com'));
        $this->assertSame('', KanaSearch::romajiToKatakana(''));
    }

    public function testToKatakanaConvertsHiraganaAndPassesOtherScriptsThrough(): void
    {
        $this->assertSame('ヤマダ', KanaSearch::toKatakana('やまだ'));
        $this->assertSame('ヤマダ', KanaSearch::toKatakana('ヤマダ'));
        $this->assertSame('山田', KanaSearch::toKatakana('山田'));
    }

    public function testMatchesFindsKanjiNameByExactText(): void
    {
        $this->assertTrue(KanaSearch::matches('山田', ['山田 太郎'], ['ヤマダタロウ']));
    }

    public function testMatchesFindsKanjiNameByRomajiReading(): void
    {
        $this->assertTrue(KanaSearch::matches('yamada', ['山田 太郎'], ['ヤマダタロウ']));
        $this->assertTrue(KanaSearch::matches('tanaka', ['田中 花子'], ['タナカハナコ']));
    }

    public function testMatchesFindsKanjiNameByHiraganaReading(): void
    {
        $this->assertTrue(KanaSearch::matches('やまだ', ['山田 太郎'], ['ヤマダタロウ']));
    }

    public function testMatchesFindsKanjiNameByKatakanaReading(): void
    {
        $this->assertTrue(KanaSearch::matches('ヤマダ', ['山田 太郎'], ['ヤマダタロウ']));
    }

    public function testMatchesReturnsFalseWhenNothingMatches(): void
    {
        $this->assertFalse(KanaSearch::matches('suzuki', ['山田 太郎'], ['ヤマダタロウ']));
    }

    public function testMatchesFallsBackToPlainTextWhenNoReadingProvided(): void
    {
        $this->assertTrue(KanaSearch::matches('Alice', ['Alice Dupont'], []));
        $this->assertFalse(KanaSearch::matches('yamada', ['Alice Dupont'], []));
    }

    public function testMatchesIsCaseInsensitiveOnPlainText(): void
    {
        $this->assertTrue(KanaSearch::matches('ALICE', ['Alice Dupont'], []));
    }

    public function testMatchesReturnsTrueForEmptyNeedle(): void
    {
        $this->assertTrue(KanaSearch::matches('', ['anything'], ['anything']));
    }
}
