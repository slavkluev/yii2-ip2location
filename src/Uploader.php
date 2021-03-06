<?php

declare(strict_types=1);

namespace slavkluev\Ip2Location;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use ZipArchive;

class Uploader
{
    const BASE_URI = 'https://www.ip2location.com/download/';

    /**
     * Stores the HTTP Client.
     * @var Client
     */
    private $httpClient;

    /**
     * Uploader constructor.
     * @param Client|null $client
     */
    public function __construct(?Client $client = null)
    {
        if ($client) {
            $this->httpClient = $client;
        } else {
            $this->httpClient = new Client([
                'base_uri' => self::BASE_URI,
            ]);
        }
    }

    /**
     * @return Client
     */
    public function getHttpClient(): Client
    {
        return $this->httpClient;
    }

    /**
     * Updates the database.
     *
     * @param string $token
     * @param string $code
     * @param string $filePath
     *
     * @throws GuzzleException
     * @throws \Exception
     */
    public function update(string $token, string $code, string $filePath)
    {
        $tmpFile = $this->uploadUrl($token, $code);
        $this->extractBinFile((string)$tmpFile, $filePath);
    }

    /**
     * Extracts bin file from the zip archive to filepath.
     *
     * @param string $filename
     * @param string $filepath
     *
     * @throws \Exception
     */
    protected function extractBinFile(string $filename, string $filepath)
    {
        $zip = new ZipArchive();
        $zip->open($filename);
        $binFilename = $this->findBinFile($zip);
        $file = $zip->getFromName($binFilename);

        if (false === @file_put_contents($filepath, $file)) {
            throw new \Exception('Failure while storing: ' . error_get_last()['message']);
        }

        $zip->close();
    }

    /**
     * Finds bin file in the zip archive.
     *
     * @param ZipArchive $archive
     *
     * @return string
     *
     * @throws \Exception
     */
    protected function findBinFile(ZipArchive $archive): string
    {
        for ($i = 0; $i < $archive->numFiles; $i++) {
            $filename = $archive->getNameIndex($i);
            if (substr(strtolower($filename), -4, 4) == '.bin') {
                return $filename;
            }
        }

        throw new \Exception("bin file has not found");
    }

    /**
     * Uploads IP2Location database.
     *
     * @param string $token
     * @param string $code
     *
     * @return TmpFile
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Exception
     */
    protected function uploadUrl(string $token, string $code): TmpFile
    {
        $tmpFile = new TmpFile();
        $resource = fopen((string)$tmpFile, 'w+');
        $response = $this->httpClient->request(
            'GET',
            '',
            [
                'query' => [
                    'token' => $token,
                    'file' => $code,
                ],
                RequestOptions::SINK => $resource,
            ]
        );
        $contentType = $response->getHeaderLine('Content-Type');

        if ($contentType !== 'application/zip') {
            $response->getBody()->seek(0);
            $message = $response->getBody()->read(1024);
            throw new \Exception('Failure while uploading: ' . $message);
        }

        $response->getBody()->detach();
        fclose($resource);

        return $tmpFile;
    }
}
