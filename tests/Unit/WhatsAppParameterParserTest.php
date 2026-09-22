<?php

namespace Tests\Unit;

use App\Support\WhatsApp\WhatsAppParameterParser;
use PHPUnit\Framework\TestCase;

class WhatsAppParameterParserTest extends TestCase
{
    public function test_parses_two_parameters(): void
    {
        $this->assertSame([1, 2], WhatsAppParameterParser::positions('Halo {{1}}, klinik {{2}}'));
    }

    public function test_parses_four_parameters(): void
    {
        $this->assertSame([1, 2, 3, 4], WhatsAppParameterParser::positions('{{1}} {{2}} {{3}} {{4}}'));
    }

    public function test_parses_six_parameters(): void
    {
        $this->assertSame([1, 2, 3, 4, 5, 6], WhatsAppParameterParser::positions('{{1}}{{2}}{{3}}{{4}}{{5}}{{6}}'));
    }

    public function test_parses_ten_parameters(): void
    {
        $body = collect(range(1, 10))->map(fn ($n) => '{{'.$n.'}}')->implode(' ');
        $this->assertSame(range(1, 10), WhatsAppParameterParser::positions($body));
    }

    public function test_sorts_unordered_parameters(): void
    {
        $this->assertSame([1, 3, 5], WhatsAppParameterParser::positions('{{5}} lalu {{1}} dan {{3}}'));
    }

    public function test_removes_duplicate_parameters(): void
    {
        $this->assertSame([1, 2], WhatsAppParameterParser::positions('{{1}} {{2}} {{1}} {{2}}'));
    }

    public function test_empty_body_has_no_parameters(): void
    {
        $this->assertSame([], WhatsAppParameterParser::positions('Halo tanpa parameter'));
    }

    public function test_missing_mapping_detected(): void
    {
        $mapping = WhatsAppParameterParser::mergeMapping('{{1}} {{2}}', [
            ['position' => 1, 'label' => 'Nama', 'source' => 'patient.name'],
        ]);

        $this->assertSame(['{{2}}'], WhatsAppParameterParser::missing($mapping));
    }

    public function test_from_remote_converts_numeric_placeholders(): void
    {
        $parsed = WhatsAppParameterParser::fromRemote(
            'Halo 1, pesanan 2 telah dikirim ke 3.',
            ['1', '2', '3'],
        );

        $this->assertSame('Halo {{1}}, pesanan {{2}} telah dikirim ke {{3}}.', $parsed['preview']);
        $this->assertSame([1, 2, 3], $parsed['positions']);
    }

    public function test_from_remote_keeps_existing_braces(): void
    {
        $parsed = WhatsAppParameterParser::fromRemote('Halo {{1}} di {{2}}', ['1', '2']);

        $this->assertSame('Halo {{1}} di {{2}}', $parsed['preview']);
        $this->assertSame([1, 2], $parsed['positions']);
    }

    public function test_to_remote_body_strips_braces(): void
    {
        $this->assertSame(
            'Halo 1, pesanan 2 telah dikirim.',
            WhatsAppParameterParser::toRemoteBody('Halo {{1}}, pesanan {{2}} telah dikirim.'),
        );
    }

    public function test_import_placeholders_generate_mapping_rows(): void
    {
        $parsed = WhatsAppParameterParser::fromRemote('A 1 B 2 C 3', ['1', '2', '3']);
        $mapping = WhatsAppParameterParser::mergeMapping($parsed['preview']);

        $this->assertCount(3, $mapping);
        $this->assertSame([1, 2, 3], array_column($mapping, 'position'));
    }
}
