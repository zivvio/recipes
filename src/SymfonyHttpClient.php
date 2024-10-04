<?php

declare(strict_types=1);

namespace Ziphp\Recipes;

use Symfony\Component\HttpClient\CurlHttpClient;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\Response\CurlResponse;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class SymfonyHttpClient
{
    private array $initOptions = [
        'headers' => [
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36 Edg/128.0.0.0'
        ],
    ];

    public function __construct(string $base_uri = null)
    {
        if ($base_uri !== null) {
            $this->initOptions['base_uri'] = $base_uri;
        }
    }


    private bool $_enableDebugOutput = false;

    public function setEnableDebugOutput(bool $value): void
    {
        $this->_enableDebugOutput = $value;
    }


    private bool $_displayURL = false;

    public function setDisplayURL(bool $value): void
    {
        $this->_displayURL = $value;
    }


    private bool $_x_www_form_urlencoded = false;

    public function set_x_www_form_urlencoded(): void
    {
        $this->_x_www_form_urlencoded = true;
    }

    public function toArrayGET(string $url, array $query = [], array $options = [], bool $strict = true): array|null
    {
        if (count($query)) {
            $options['query'] = $query;
        }

        return $this->requestInternal('GET', $url, $options, true, $strict);
    }

    public function toArrayPOST(string $url, array $json = [], array $options = [], bool $strict = true): array|null
    {
        return $this->internalPOST('POST', $url, $json, $options, true, $strict);
    }

    public function toArrayPUT(string $url, array $json = [], array $options = [], bool $strict = true): array|null
    {
        return $this->internalPOST('PUT', $url, $json, $options, true, $strict);
    }

    public function toArrayPATCH(string $url, array $json = [], array $options = [], bool $strict = true): array|null
    {
        return $this->internalPOST('PATCH', $url, $json, $options, true, $strict);
    }

    public function toArrayDELETE(string $url, array $json = [], array $options = [], bool $strict = true): array|null
    {
        return $this->internalPOST('DELETE', $url, $json, $options, true, $strict);
    }

    public function toStringGET(string $url, array $query = [], array $options = [], bool $strict = true): string|null
    {
        if (count($query)) {
            $options['query'] = $query;
        }

        return $this->requestInternal('GET', $url, $options, false, $strict);
    }

    public function toStringPOST(string $url, array $json = [], array $options = [], bool $strict = true): string|null
    {
        return $this->internalPOST('POST', $url, $json, $options, false, $strict);
    }

    public function toStringPUT(string $url, array $json = [], array $options = [], bool $strict = true): string|null
    {
        return $this->internalPOST('PUT', $url, $json, $options, false, $strict);
    }

    public function toStringPATCH(string $url, array $json = [], array $options = [], bool $strict = true): string|null
    {
        return $this->internalPOST('PATCH', $url, $json, $options, false, $strict);
    }

    public function toStringDELETE(string $url, array $json = [], array $options = [], bool $strict = true): string|null
    {
        return $this->internalPOST('DELETE', $url, $json, $options, false, $strict);
    }

    private function internalPOST(string $method, string $url, array $json, array $options, bool $toArray, bool $strict): array|string|null
    {
        if (count($json)) {
            if ($this->_x_www_form_urlencoded) {
                $options['body'] = $json;
            } else {
                $options['json'] = $json;
            }
        }

        return $this->requestInternal($method, $url, $options, $toArray, $strict);
    }

    private function requestInternal(string $method, string $url, array $options, bool $toArray, bool $strict): array|string|null
    {
        // reset
        $this->_error = null;
        $this->_debug = null;

        /** @var CurlHttpClient $client */
        $client = HttpClient::create();

        try {
            /** @var CurlResponse $_response */
            $_response = $client->request($method, $url, array_merge($this->initOptions, $options));
        } catch (TransportExceptionInterface $e) {
            $this->_error = $e->getMessage();
            return null;
        }

        try {
            $statusCode = $_response->getStatusCode();
        } catch (TransportExceptionInterface $e) {
            $this->_error = $e->getMessage();
            $this->setDebug($_response->getInfo('debug'));
            return null;
        }

        $this->_response = $_response;

        if ($this->_displayURL) {
            dump(
                $_response->getInfo('http_method')
                . ' '
                . $_response->getInfo('http_code')
                . ' '
                . $_response->getInfo('url')
            );
        }

        if ($statusCode !== 200) {
            $this->_error = "Invalid HTTP status code: $statusCode";
            $this->setDebug($_response->getInfo('debug'));
            return null;
        }

        if ($toArray) {
            try {
                return $_response->toArray($strict);
            } catch (ClientExceptionInterface|RedirectionExceptionInterface|ServerExceptionInterface|TransportExceptionInterface|DecodingExceptionInterface $e) {
                $this->_error = $e->getMessage();
                $this->setDebug($_response->getInfo('debug'));
                return null;
            }
        } else {
            try {
                return $_response->getContent($strict);
            } catch (ClientExceptionInterface|RedirectionExceptionInterface|ServerExceptionInterface|TransportExceptionInterface $e) {
                $this->_error = $e->getMessage();
                $this->setDebug($_response->getInfo('debug'));
                return null;
            }
        }
    }


    private ?ResponseInterface $_response = null;

    public function getResponse(): ?ResponseInterface
    {
        return $this->_response;
    }


    private ?string $_error = null;

    public function hasError(): bool
    {
        return $this->_error !== null;
    }

    public function getError(): ?string
    {
        return $this->_error;
    }


    private ?string $_debug = null;

    public function getDebug(): ?string
    {
        return $this->_debug;
    }

    private function setDebug(?string $value): void
    {
        $this->_debug = $value;

        if ($this->_enableDebugOutput) {
            dump($value);
        }
    }
}
