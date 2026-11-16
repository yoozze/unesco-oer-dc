<?php

declare(strict_types=1);

namespace Drupal\news_ingestion\Service;

use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Image\ImageFactory;
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

    /**
     * Reject icons and other assets that break card layouts / image styles.
     */
    private const MIN_WIDTH = 200;

    private const MIN_HEIGHT = 120;

    private const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    public function __construct(
        private readonly ClientInterface $httpClient,
        private readonly FileSystemInterface $fileSystem,
        private readonly FileRepositoryInterface $fileRepository,
        private readonly LoggerChannelFactoryInterface $loggerFactory,
        private readonly ImageFactory $imageFactory,
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

        if ($this->isRejectedUrl($url)) {
            $this->loggerFactory->get('news_ingestion')->notice('Skipping non-article image URL @url', [
                '@url' => $url,
            ]);
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

        $content_type = $response->getHeaderLine('Content-Type');
        $ext_from_type = $this->extensionFromContentType($content_type);
        if ($content_type !== '' && $this->isRejectedContentType($content_type)) {
            $this->loggerFactory->get('news_ingestion')->notice('Skipping image with unsupported Content-Type @type for @url', [
                '@type' => $content_type,
                '@url' => $url,
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
        $ext_from_name = NULL;
        if (preg_match('/\.(' . implode('|', self::ALLOWED_EXTENSIONS) . ')$/i', $basename, $matches)) {
            $ext_from_name = strtolower($matches[1]);
            if ($ext_from_name === 'jpeg') {
                $ext_from_name = 'jpg';
            }
        }

        $ext = $ext_from_name ?? $ext_from_type;
        if ($ext === NULL) {
            $this->loggerFactory->get('news_ingestion')->notice('Skipping image with unknown type for @url', [
                '@url' => $url,
            ]);
            return NULL;
        }

        if ($ext_from_name === NULL) {
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

        $image = $this->imageFactory->get($file->getFileUri());
        if (
            !$image->isValid()
            || (int) $image->getWidth() < self::MIN_WIDTH
            || (int) $image->getHeight() < self::MIN_HEIGHT
        ) {
            $this->loggerFactory->get('news_ingestion')->notice(
                'Skipping unusable or too-small image for @url (@widthx@height)',
                [
                    '@url' => $url,
                    '@width' => (int) $image->getWidth(),
                    '@height' => (int) $image->getHeight(),
                ]
            );
            try {
                $file->delete();
            } catch (\Throwable) {
                // Best-effort cleanup.
            }

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

    /**
     * Whether a remote URL is clearly not a usable article image.
     */
    private function isRejectedUrl(string $url): bool {
        $path = strtolower((string) (parse_url($url, PHP_URL_PATH) ?: ''));
        if ($path === '') {
            return FALSE;
        }

        if (preg_match('#(^|/)favicon(\.|$|/)|(^|/)apple-touch-icon#', $path)) {
            return TRUE;
        }

        return (bool) preg_match('#\.(ico|svg|bmp|tif|tiff)(\?|#|$)#', $path);
    }

    /**
     * Map a Content-Type header to an allowed image extension.
     */
    private function extensionFromContentType(string $content_type): ?string {
        $content_type = strtolower(trim(explode(';', $content_type)[0]));
        return match ($content_type) {
            'image/jpeg', 'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            default => NULL,
        };
    }

    /**
     * Whether a response Content-Type is clearly not a usable raster image.
     */
    private function isRejectedContentType(string $content_type): bool {
        $mime = strtolower(trim(explode(';', $content_type)[0]));
        if ($mime === '' || in_array($mime, ['application/octet-stream', 'binary/octet-stream'], TRUE)) {
            return FALSE;
        }

        // Reject SVG/ICO and any other image/* we do not support.
        if (str_starts_with($mime, 'image/')) {
            return $this->extensionFromContentType($mime) === NULL;
        }

        // HTML error pages, JSON, etc.
        return TRUE;
    }
}
