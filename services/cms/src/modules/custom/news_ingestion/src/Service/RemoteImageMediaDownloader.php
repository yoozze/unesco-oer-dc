<?php

declare(strict_types=1);

namespace Drupal\news_ingestion\Service;

use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\file\FileRepositoryInterface;
use Drupal\media\Entity\Media;
use Drupal\media\MediaInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Downloads a remote image URL into a permanent Media (image) entity.
 */
final class RemoteImageMediaDownloader {

    private const DIRECTORY = 'public://news_ingestion';

    public function __construct(
        private readonly ClientInterface $httpClient,
        private readonly FileSystemInterface $fileSystem,
        private readonly FileRepositoryInterface $fileRepository,
        private readonly LoggerChannelFactoryInterface $loggerFactory,
    ) {
    }

    /**
     * Create (or fail softly) an image media entity from a remote URL.
     */
    public function createFromUrl(string $url, string $alt = ''): ?MediaInterface {
        $url = trim($url);
        if ($url === '' || !preg_match('#^https?://#i', $url)) {
            return NULL;
        }

        try {
            $response = $this->httpClient->request('GET', $url, [
                'timeout' => 30,
                'http_errors' => TRUE,
                'headers' => [
                    'User-Agent' => 'UNESCO-OER-DC-NewsIngestion/1.0',
                ],
            ]);
        } catch (GuzzleException $e) {
            $this->loggerFactory->get('news_ingestion')->warning('Image download failed for @url: @msg', [
                '@url' => $url,
                '@msg' => $e->getMessage(),
            ]);
            return NULL;
        }

        $data = (string) $response->getBody();
        if ($data === '') {
            return NULL;
        }

        $path = parse_url($url, PHP_URL_PATH) ?: '';
        $basename = basename((string) $path);
        $basename = preg_replace('/[^A-Za-z0-9._-]+/', '_', $basename) ?: 'image';
        if (!preg_match('/\.(jpe?g|png|gif|webp)$/i', $basename)) {
            $content_type = $response->getHeaderLine('Content-Type');
            $ext = match (TRUE) {
                str_contains($content_type, 'png') => 'png',
                str_contains($content_type, 'gif') => 'gif',
                str_contains($content_type, 'webp') => 'webp',
                default => 'jpg',
            };
            $basename .= '.' . $ext;
        }

        // Avoid collisions between different remote URLs with the same filename.
        $hash = substr(hash('sha256', $url), 0, 12);
        $filename = $hash . '_' . $basename;

        $directory = self::DIRECTORY;
        $this->fileSystem->prepareDirectory(
            $directory,
            FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS
        );

        try {
            $file = $this->fileRepository->writeData(
                $data,
                $directory . '/' . $filename,
                FileExists::Replace
            );
        } catch (\Throwable $e) {
            $this->loggerFactory->get('news_ingestion')->warning('Could not save image file for @url: @msg', [
                '@url' => $url,
                '@msg' => $e->getMessage(),
            ]);
            return NULL;
        }

        $file->setPermanent();
        $file->save();

        $media = Media::create([
            'bundle' => 'image',
            'name' => mb_substr($alt !== '' ? $alt : $basename, 0, 255),
            'status' => 1,
            'field_media_image' => [
                'target_id' => $file->id(),
                'alt' => mb_substr($alt !== '' ? $alt : 'News image', 0, 512),
            ],
        ]);
        $media->save();
        return $media;
    }
}
