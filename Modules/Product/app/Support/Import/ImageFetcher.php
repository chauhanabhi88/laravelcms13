<?php

declare(strict_types=1);

namespace Modules\Product\Support\Import;

use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Modules\Product\DTO\ImageData;
use Modules\Product\Exceptions\ProductException;
use Modules\Product\Exceptions\ProductValidationException;
use Modules\Product\Services\ProductImageService;

/**
 * Downloads an image URL from a bulk upload and stores it like an uploaded image
 * (ProductImageService::store(): type, size, 500 px minimum, re-encoding, resized copies).
 *
 * SSRF guard: only http(s) on ports 80 / 443 / 8080 / 8443, the host must resolve to public
 * addresses only (no private, loopback, link-local or reserved ranges), the connection is
 * pinned to the checked address (no DNS rebinding) and redirects are followed by hand, each
 * hop checked the same way. Timeout and size cap from config product.image_fetch_*.
 */
class ImageFetcher
{
    protected const ALLOWED_PORTS = [80, 443, 8080, 8443];

    protected const EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/jpg' => 'jpg',
        'image/pjpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    /**
     * @var Closure(string): list<string>
     */
    protected Closure $resolver;

    /**
     * @param  (Closure(string): list<string>)|null  $resolver  host => IP addresses (tests replace DNS)
     */
    public function __construct(
        protected ProductImageService $images,
        ?Closure $resolver = null,
    ) {
        $this->resolver = $resolver ?? fn (string $host): array => $this->resolve($host);
    }

    /**
     * @throws ProductException with a message safe to show in the error report
     */
    public function fetch(string $url, ?int $attributeOptionId = null): ImageData
    {
        $url = trim($url);
        $maxBytes = (int) config('product.image_fetch_max_kb', 5120) * 1024;
        $hops = (int) config('product.image_fetch_max_redirects', 3);

        for ($hop = 0; $hop <= $hops; $hop++) {
            [$host, $port, $address] = $this->checkUrl($url);

            try {
                $response = Http::timeout((int) config('product.image_fetch_timeout', 10))
                    ->connectTimeout(5)
                    ->withOptions([
                        'allow_redirects' => false,
                        'curl' => [CURLOPT_RESOLVE => ["{$host}:{$port}:{$address}"]],
                        'progress' => function ($total, $downloaded) use ($maxBytes): void {
                            if ($total > $maxBytes || $downloaded > $maxBytes) {
                                throw new \RuntimeException('too_large');
                            }
                        },
                    ])
                    ->accept('image/*')
                    ->get($url);
            } catch (ConnectionException) {
                throw $this->failure('unreachable', $url);
            } catch (\Throwable $e) {
                throw $this->failure(str_contains($e->getMessage(), 'too_large') ? 'too_large' : 'unreachable', $url);
            }

            if ($response->redirect()) {
                $location = (string) $response->header('Location');

                if ($location === '') {
                    throw $this->failure('unreachable', $url);
                }

                $url = $this->absolute($location, $url);

                continue;
            }

            if (! $response->successful()) {
                throw $this->failure('http_status', $url, ['status' => $response->status()]);
            }

            $type = strtolower(trim(explode(';', (string) $response->header('Content-Type'))[0]));
            $extension = self::EXTENSIONS[$type] ?? null;

            if ($extension === null) {
                throw $this->failure('not_image', $url);
            }

            $body = $response->body();

            if ((int) $response->header('Content-Length') > $maxBytes || strlen($body) > $maxBytes) {
                throw $this->failure('too_large', $url);
            }

            return $this->store($body, $extension, $attributeOptionId, $url);
        }

        throw $this->failure('too_many_redirects', $url);
    }

    /**
     * @return array{0: string, 1: int, 2: string} host, port, the checked address to connect to
     *
     * @throws ProductException
     */
    protected function checkUrl(string $url): array
    {
        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower(trim((string) ($parts['host'] ?? ''), '[]'));

        if (! in_array($scheme, ['http', 'https'], true) || $host === '' || isset($parts['user']) || isset($parts['pass'])) {
            throw $this->failure('invalid_url', $url);
        }

        $port = (int) ($parts['port'] ?? ($scheme === 'https' ? 443 : 80));
        if (! in_array($port, self::ALLOWED_PORTS, true)) {
            throw $this->failure('blocked_host', $url);
        }

        $addresses = filter_var($host, FILTER_VALIDATE_IP) !== false ? [$host] : ($this->resolver)($host);

        if ($addresses === []) {
            throw $this->failure('unresolvable', $url);
        }

        foreach ($addresses as $address) {
            if (! $this->isPublicAddress($address)) {
                throw $this->failure('blocked_host', $url);
            }
        }

        return [$host, $port, $addresses[0]];
    }

    public function isPublicAddress(string $address): bool
    {
        // IPv4-mapped IPv6 (::ffff:10.0.0.1) is checked as the IPv4 address.
        if (preg_match('/^::ffff:(\d{1,3}(?:\.\d{1,3}){3})$/i', $address, $match)) {
            $address = $match[1];
        }

        if (filter_var($address, FILTER_VALIDATE_IP) === false) {
            return false;
        }

        if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return false;
        }

        // Ranges PHP's flags leave open: carrier-grade NAT, benchmarking, IPv6 unique-local / link-local.
        foreach (['100.64.0.0/10', '198.18.0.0/15', '192.0.0.0/24'] as $cidr) {
            if ($this->inIpv4Range($address, $cidr)) {
                return false;
            }
        }

        $lower = strtolower($address);

        return ! (str_starts_with($lower, 'fc') || str_starts_with($lower, 'fd') || str_starts_with($lower, 'fe8') || str_starts_with($lower, 'fe9') || str_starts_with($lower, 'fea') || str_starts_with($lower, 'feb'));
    }

    /**
     * @return list<string>
     */
    protected function resolve(string $host): array
    {
        $records = @dns_get_record($host, DNS_A | DNS_AAAA) ?: [];
        $addresses = [];

        foreach ($records as $record) {
            $addresses[] = (string) ($record['ip'] ?? $record['ipv6'] ?? '');
        }

        return array_values(array_filter(array_unique($addresses)));
    }

    protected function store(string $body, string $extension, ?int $attributeOptionId, string $url): ImageData
    {
        $path = tempnam(sys_get_temp_dir(), 'img');

        if ($path === false) {
            throw $this->failure('unreachable', $url);
        }

        try {
            file_put_contents($path, $body);
            $file = new UploadedFile($path, 'image.'.$extension, null, null, true);

            return $this->images->store($file, $attributeOptionId, null, 'image_url');
        } catch (ProductValidationException $e) {
            throw new ProductException(trans('product::seller.uploads.image_errors.rejected', ['url' => $this->shortUrl($url), 'reason' => $e->getMessage()]));
        } finally {
            @unlink($path);
        }
    }

    protected function absolute(string $location, string $base): string
    {
        if (preg_match('#^https?://#i', $location)) {
            return $location;
        }

        $parts = parse_url($base);
        $origin = ($parts['scheme'] ?? 'http').'://'.($parts['host'] ?? '').(isset($parts['port']) ? ':'.$parts['port'] : '');

        return str_starts_with($location, '/') ? $origin.$location : $origin.'/'.$location;
    }

    protected function inIpv4Range(string $address, string $cidr): bool
    {
        if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
            return false;
        }

        [$subnet, $bits] = explode('/', $cidr);
        $mask = -1 << (32 - (int) $bits);

        return (ip2long($address) & $mask) === (ip2long($subnet) & $mask);
    }

    /**
     * @param  array<string, mixed>  $replace
     */
    protected function failure(string $reason, string $url, array $replace = []): ProductException
    {
        return new ProductException(trans('product::seller.uploads.image_errors.'.$reason, $replace + ['url' => $this->shortUrl($url)]));
    }

    protected function shortUrl(string $url): string
    {
        return mb_strimwidth($url, 0, 120, '…');
    }
}
