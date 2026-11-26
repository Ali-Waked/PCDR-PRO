<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\SocialLinkNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SocialLinkNormalizerTest extends TestCase
{
    private SocialLinkNormalizer $normalizer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->normalizer = new SocialLinkNormalizer;
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function acceptedInputProvider(): array
    {
        return [
            'full facebook url' => ['social.facebook', 'https://www.facebook.com/pcdr/', 'https://www.facebook.com/pcdr'],
            'facebook scheme-less path' => ['social.facebook', 'facebook.com/pcdr', 'https://www.facebook.com/pcdr'],
            'facebook handle' => ['social.facebook', '@pcdr', 'https://www.facebook.com/pcdr'],
            'x handle' => ['social.x', 'pcdr', 'https://x.com/pcdr'],
            'legacy twitter url' => ['social.x', 'http://twitter.com/pcdr', 'https://x.com/pcdr'],
            'instagram handle' => ['social.instagram', '@pcdr', 'https://www.instagram.com/pcdr/'],
            'linkedin handle' => ['social.linkedin', 'pcdr', 'https://www.linkedin.com/in/pcdr/'],
            'linkedin company path' => ['social.linkedin', 'www.linkedin.com/company/pcdr', 'https://www.linkedin.com/company/pcdr/'],
            'youtube handle' => ['social.youtube', '@pcdr', 'https://www.youtube.com/@pcdr'],
            'youtube short url' => ['social.youtube', 'https://youtu.be/abc123XYZ', 'https://www.youtube.com/watch?v=abc123XYZ'],
            'youtube watch url' => ['social.youtube', 'youtube.com/watch?v=abc123XYZ', 'https://www.youtube.com/watch?v=abc123XYZ'],
            'tiktok handle' => ['social.tiktok', '@pcdr', 'https://www.tiktok.com/@pcdr'],
            'snapchat handle' => ['social.snapchat', 'pcdr', 'https://www.snapchat.com/add/pcdr'],
            'snapchat add path' => ['social.snapchat', 'snapchat.com/add/pcdr', 'https://www.snapchat.com/add/pcdr'],
            'whatsapp number' => ['social.whatsapp', '+970 59 123 4567', 'https://wa.me/970591234567'],
            'whatsapp wa.me url' => ['social.whatsapp', 'https://wa.me/970591234567', 'https://wa.me/970591234567'],
        ];
    }

    #[DataProvider('acceptedInputProvider')]
    public function test_it_normalizes_supported_input_forms(string $key, string $input, string $expected): void
    {
        $this->assertSame($expected, $this->normalizer->normalize($key, $input));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function rejectedInputProvider(): array
    {
        return [
            'javascript scheme' => ['social.facebook', 'javascript:alert(1)'],
            'data scheme' => ['social.instagram', 'data:text/html;base64,AA'],
            'relative path' => ['social.facebook', '/facebook/pcdr'],
            'unexpected host' => ['social.facebook', 'https://example.com/pcdr'],
            'missing handle' => ['social.facebook', 'https://www.facebook.com/'],
            'invalid handle' => ['social.x', 'bad handle!'],
            'short whatsapp number' => ['social.whatsapp', '12345'],
            'whatsapp letters' => ['social.whatsapp', 'call-me-970591234567'],
        ];
    }

    #[DataProvider('rejectedInputProvider')]
    public function test_it_rejects_unsafe_or_unrecognized_values(string $key, string $input): void
    {
        $this->assertNull($this->normalizer->normalize($key, $input));
    }

    public function test_it_returns_null_for_blank_values_and_unknown_keys(): void
    {
        $this->assertNull($this->normalizer->normalize('social.facebook', null));
        $this->assertNull($this->normalizer->normalize('social.facebook', '   '));
        $this->assertNull($this->normalizer->normalize('contact.email', 'pcdr'));
    }

    public function test_output_is_deterministic_and_https_only(): void
    {
        $first = $this->normalizer->normalize('social.facebook', 'facebook.com/pcdr');
        $second = $this->normalizer->normalize('social.facebook', 'facebook.com/pcdr');

        $this->assertSame($first, $second);
        $this->assertNotNull($first);
        $this->assertStringStartsWith('https://', $first);
    }

    public function test_it_exposes_all_supported_platforms(): void
    {
        $this->assertSame([
            'facebook', 'x', 'instagram', 'linkedin', 'youtube', 'tiktok', 'snapchat', 'whatsapp',
        ], $this->normalizer->platforms());

        foreach ($this->normalizer->platforms() as $platform) {
            $this->assertSame($platform, $this->normalizer->platformFor('social.'.$platform));
        }
    }
}
