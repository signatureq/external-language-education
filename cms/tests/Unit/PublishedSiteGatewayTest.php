<?php

namespace Tests\Unit;

use External\Publishing\PublishedSiteGateway;
use PHPUnit\Framework\TestCase;

require_once __DIR__.'/../../deploy/main-site/PublishedSiteGateway.php';

class PublishedSiteGatewayTest extends TestCase
{
    private static string $privateKey = '';

    private static string $publicKey;

    private string $root;

    public static function setUpBeforeClass(): void
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048]);
        openssl_pkey_export($key, self::$privateKey);
        self::$publicKey = openssl_pkey_get_details($key)['key'];
    }

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir().'/ele-gateway-'.bin2hex(random_bytes(6));
        mkdir($this->root);
        file_put_contents($this->root.'/index.html', 'Original static page');
    }

    protected function tearDown(): void
    {
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($this->root);
    }

    private function snapshot(array $patch = []): array
    {
        return array_replace([
            'schema' => 1, 'site' => 'externlangschool.ru', 'issued_at' => time(),
            'pages' => array_combine(PublishedSiteGateway::PAGES, array_map(fn ($file) => '<title>Published '.$file.'</title>', PublishedSiteGateway::PAGES)),
        ], $patch);
    }

    private function envelope(array $snapshot): string
    {
        $payload = json_encode($snapshot, JSON_THROW_ON_ERROR);
        openssl_sign($payload, $signature, self::$privateKey, OPENSSL_ALGO_SHA256);

        return json_encode(['payload' => base64_encode($payload), 'signature' => base64_encode($signature)]);
    }

    private function gateway(callable $fetcher, ?string $root = null): PublishedSiteGateway
    {
        return new PublishedSiteGateway($root ?? $this->root, 'https://cms.example.test/published-site', self::$publicKey, 'externlangschool.ru', $fetcher);
    }

    public function test_verified_snapshot_is_shared_by_all_pages_and_requests_are_coalesced(): void
    {
        $calls = 0;
        $gateway = $this->gateway(function () use (&$calls) {
            $calls++;

            return $this->envelope($this->snapshot());
        });
        $this->assertStringContainsString('Published index.html', $gateway->page('index.html'));
        $this->assertTrue($gateway->servedFromCms());
        $this->assertStringContainsString('Published english.html', $gateway->page('english.html'));
        $this->assertSame(1, $calls);
        $this->assertFileExists($this->root.'/cms-cache/snapshot.json');
    }

    public function test_invalid_or_unexpected_snapshots_never_replace_the_public_page(): void
    {
        $missing = $this->snapshot();
        unset($missing['pages']['index.html']);
        $extra = $this->snapshot();
        $extra['pages']['../.env'] = 'must not be accepted';
        $valid = json_decode($this->envelope($this->snapshot()), true);
        $valid['payload'] = base64_encode(json_encode($this->snapshot(['site' => 'tampered.test'])));
        $cases = [
            json_encode($valid),
            $this->envelope($missing),
            $this->envelope($extra),
            $this->envelope($this->snapshot(['site' => 'another.example'])),
            $this->envelope($this->snapshot(['issued_at' => time() - 3600])),
            $this->envelope($this->snapshot(['issued_at' => time() + 3600])),
            '{"payload":"bad base64","signature":"bad base64"}',
        ];
        foreach ($cases as $i => $body) {
            $root = $this->root.'/case-'.$i;
            mkdir($root);
            file_put_contents($root.'/index.html', 'Original static page');
            $this->assertSame('Original static page', $this->gateway(fn () => $body, $root)->page('index.html'));
            $this->assertFileDoesNotExist($root.'/cms-cache/snapshot.json');
        }
    }

    public function test_a_cms_outage_preserves_the_last_verified_publication(): void
    {
        $gateway = $this->gateway(fn () => $this->envelope($this->snapshot()));
        $first = $gateway->page('index.html');
        $before = hash_file('sha256', $this->root.'/cms-cache/snapshot.json');
        touch($this->root.'/cms-cache/attempt', time() - 60);
        clearstatcache();
        $failed = $this->gateway(fn () => throw new \RuntimeException('CMS offline'));
        $this->assertSame($first, $failed->page('index.html'));
        $this->assertSame($before, hash_file('sha256', $this->root.'/cms-cache/snapshot.json'));
    }

    public function test_paths_and_unencrypted_publication_sources_are_rejected(): void
    {
        try {
            $this->gateway(fn () => '')->page('../.env');
            $this->fail('Arbitrary paths must not be read.');
        } catch (\InvalidArgumentException) {
            $this->assertFileDoesNotExist($this->root.'/cms-cache/snapshot.json');
        }
        $this->expectException(\RuntimeException::class);
        new PublishedSiteGateway($this->root, 'http://cms.example.test/published-site', self::$publicKey, 'externlangschool.ru');
    }
}
