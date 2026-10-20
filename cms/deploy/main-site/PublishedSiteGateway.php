<?php

namespace External\Publishing;

/** Read-only HTTPS consumer. It can update its signed cache, never arbitrary site files. */
final class PublishedSiteGateway
{
    public const PAGES = ['admission.html', 'chinese.html', 'consent.html', 'english.html', 'format.html', 'french.html', 'german.html', 'index.html', 'interview.html', 'italian.html', 'korean.html', 'languages.html', 'privacy.html', 'schedule.html', 'spanish.html', 'tuition.html'];

    private const MAX_BYTES = 16777216;

    private \Closure $fetcher;

    private string $cache;

    private bool $verified = false;

    public function servedFromCms(): bool
    {
        return $this->verified;
    }

    public function __construct(private string $root, private string $source, private string $publicKey, private string $site, ?callable $fetcher = null)
    {
        $url = parse_url($source);
        if (($url['scheme'] ?? '') !== 'https' || empty($url['host']) || isset($url['user']) || isset($url['pass']) || isset($url['fragment']) || (isset($url['port']) && $url['port'] !== 443)) {
            throw new \RuntimeException('Publication source must use HTTPS.');
        }
        $this->cache = $root.'/cms-cache';
        $this->fetcher = $fetcher ? \Closure::fromCallable($fetcher) : fn () => $this->download();
    }

    public function page(string $file): string
    {
        if (! in_array($file, self::PAGES, true)) {
            throw new \InvalidArgumentException('Unknown public page.');
        }
        $cached = $this->readCache();
        if (! is_dir($this->cache) && ! @mkdir($this->cache, 0700, true) && ! is_dir($this->cache)) {
            return $this->fallback($file, $cached);
        }
        if ($this->recentAttempt()) {
            return $this->fallback($file, $cached);
        }
        $lock = @fopen($this->cache.'/refresh.lock', 'c+b');
        if (! $lock || ! flock($lock, LOCK_EX | LOCK_NB)) {
            if ($lock) {
                fclose($lock);
            }

            return $this->fallback($file, $cached);
        }
        try {
            // Another request may have refreshed the cache before this lock was acquired.
            clearstatcache(true, $this->cache.'/attempt');
            if ($this->recentAttempt()) {
                return $this->fallback($file, $this->readCache() ?? $cached);
            }
            touch($this->cache.'/attempt');
            $body = ($this->fetcher)();
            $snapshot = $this->decode($body, true);
            $temp = $this->cache.'/'.bin2hex(random_bytes(8)).'.tmp';
            try {
                if (file_put_contents($temp, $body, LOCK_EX) !== strlen($body)) {
                    throw new \RuntimeException('Incomplete publication cache write.');
                }
                chmod($temp, 0600);
                if (! rename($temp, $this->cache.'/snapshot.json')) {
                    throw new \RuntimeException('Publication cache replacement failed.');
                }
            } finally {
                if (is_file($temp)) {
                    unlink($temp);
                }
            }

            $this->verified = true;

            return $snapshot['pages'][$file];
        } catch (\Throwable $error) {
            error_log('External publication refresh: '.$error->getMessage());

            return $this->fallback($file, $cached);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function recentAttempt(): bool
    {
        $path = $this->cache.'/attempt';
        if (! is_file($path)) {
            return false;
        }
        $time = filemtime($path);

        return $time !== false && $time <= time() && time() - $time < 15;
    }

    private function readCache(): ?array
    {
        $path = $this->cache.'/snapshot.json';
        if (! is_file($path) || filesize($path) > self::MAX_BYTES) {
            return null;
        }
        try {
            return $this->decode(file_get_contents($path), false);
        } catch (\Throwable) {
            return null;
        }
    }

    private function decode(string $body, bool $fresh): array
    {
        if (strlen($body) > self::MAX_BYTES) {
            throw new \RuntimeException('Publication exceeds the size limit.');
        }
        $envelope = json_decode($body, true, 32, JSON_THROW_ON_ERROR);
        if (! is_array($envelope) || ! is_string($envelope['payload'] ?? null) || ! is_string($envelope['signature'] ?? null)) {
            throw new \RuntimeException('Invalid publication envelope.');
        }
        $payload = base64_decode($envelope['payload'], true);
        $signature = base64_decode($envelope['signature'], true);
        if ($payload === false || $signature === false || openssl_verify($payload, $signature, $this->publicKey, OPENSSL_ALGO_SHA256) !== 1) {
            throw new \RuntimeException('Publication signature is invalid.');
        }
        $snapshot = json_decode($payload, true, 32, JSON_THROW_ON_ERROR);
        if (($snapshot['schema'] ?? null) !== 1 || ($snapshot['site'] ?? null) !== $this->site || ! is_int($snapshot['issued_at'] ?? null) || ! is_array($snapshot['pages'] ?? null)) {
            throw new \RuntimeException('Publication belongs to a different site or schema.');
        }
        if ($snapshot['issued_at'] > time() + 300 || ($fresh && $snapshot['issued_at'] < time() - 300)) {
            throw new \RuntimeException('Publication timestamp is outside the allowed window.');
        }
        $files = array_keys($snapshot['pages']);
        sort($files);
        if ($files !== self::PAGES) {
            throw new \RuntimeException('Publication page list is incomplete or unexpected.');
        }
        foreach ($snapshot['pages'] as $html) {
            if (! is_string($html) || $html === '' || strlen($html) > 1048576) {
                throw new \RuntimeException('Invalid public HTML page.');
            }
        }

        return $snapshot;
    }

    private function fallback(string $file, ?array $cached): string
    {
        if ($cached !== null) {
            $this->verified = true;

            return $cached['pages'][$file];
        }
        $fallback = $this->root.'/cms-publishing/fallback/'.$file;
        $html = @file_get_contents(is_file($fallback) ? $fallback : $this->root.'/'.$file);
        if ($html === false || $html === '') {
            throw new \RuntimeException('No public page is available.');
        }

        return $html;
    }

    private function download(): string
    {
        if (! function_exists('curl_init')) {
            throw new \RuntimeException('HTTPS publication requires the PHP cURL extension.');
        }
        $body = '';
        $curl = curl_init($this->source);
        curl_setopt_array($curl, [
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
            CURLOPT_USERAGENT => 'External-Published-Site/1.0',
            CURLOPT_WRITEFUNCTION => function ($handle, string $chunk) use (&$body) {
                if (strlen($body) + strlen($chunk) > self::MAX_BYTES) {
                    return 0;
                }
                $body .= $chunk;

                return strlen($chunk);
            },
        ]);
        try {
            $ok = curl_exec($curl);
            if ($ok === false || curl_getinfo($curl, CURLINFO_RESPONSE_CODE) !== 200) {
                throw new \RuntimeException('Publication source is temporarily unavailable.');
            }

            return $body;
        } finally {
            curl_close($curl);
        }
    }
}
