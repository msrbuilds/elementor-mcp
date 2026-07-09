<?php

declare(strict_types=1);

namespace WP\McpSchema\Common\Core\DTO;

use WP\McpSchema\Common\AbstractDataTransferObject;
use WP\McpSchema\Common\Traits\ValidatesRequiredFields;

/**
 * An optionally-sized icon that can be displayed in a user interface.
 *
 * @since 2025-11-25
 *
 * @mcp-domain Common
 * @mcp-subdomain Core
 * @mcp-version 2025-11-25
 */
class Icon extends AbstractDataTransferObject
{
    use ValidatesRequiredFields;

    /**
     * A standard URI pointing to an icon resource. May be an HTTP/HTTPS URL or a
     * `data:` URI with Base64-encoded image data.
     *
     * Consumers SHOULD takes steps to ensure URLs serving icons are from the
     * same domain as the client/server or a trusted domain.
     *
     * Consumers SHOULD take appropriate precautions when consuming SVGs as they can contain
     * executable JavaScript.
     *
     * @since 2025-11-25
     *
     * @var string
     */
    protected string $src;

    /**
     * Optional MIME type override if the source MIME type is missing or generic.
     * For example: `"image/png"`, `"image/jpeg"`, or `"image/svg+xml"`.
     *
     * @since 2025-11-25
     *
     * @var string|null
     */
    protected ?string $mimeType;

    /**
     * Optional array of strings that specify sizes at which the icon can be used.
     * Each string should be in WxH format (e.g., `"48x48"`, `"96x96"`) or `"any"` for scalable formats like SVG.
     *
     * If not provided, the client should assume that the icon can be used at any size.
     *
     * @since 2025-11-25
     *
     * @var array<string>|null
     */
    protected ?array $sizes;

    /**
     * Optional specifier for the theme this icon is designed for. `light` indicates
     * the icon is designed to be used with a light background, and `dark` indicates
     * the icon is designed to be used with a dark background.
     *
     * If not provided, the client should assume the icon can be used with any theme.
     *
     * @since 2025-11-25
     *
     * @var 'light'|'dark'|null
     */
    protected ?string $theme;

    /**
     * @param string $src @since 2025-11-25
     * @param string|null $mimeType @since 2025-11-25
     * @param array<string>|null $sizes @since 2025-11-25
     * @param 'light'|'dark'|null $theme @since 2025-11-25
     */
    public function __construct(
        string $src,
        ?string $mimeType = null,
        ?array $sizes = null,
        ?string $theme = null
    ) {
        $this->src = $src;
        $this->mimeType = $mimeType;
        $this->sizes = $sizes;
        $this->theme = $theme;
    }

    /**
     * Creates an instance from an array.
     *
     * @param array{
     *     src: string,
     *     mimeType?: string|null,
     *     sizes?: array<string>|null,
     *     theme?: 'light'|'dark'|null
     * } $data
     * @phpstan-param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        self::assertRequired($data, ['src']);

        /** @var 'light'|'dark'|null $theme */
        $theme = isset($data['theme'])
            ? self::asStringOrNull($data['theme'])
            : null;

        return new self(
            self::asString($data['src']),
            self::asStringOrNull($data['mimeType'] ?? null),
            self::asStringArrayOrNull($data['sizes'] ?? null),
            $theme
        );
    }

    /**
     * Converts the instance to an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $result = [];

        $result['src'] = $this->src;
        if ($this->mimeType !== null) {
            $result['mimeType'] = $this->mimeType;
        }
        if ($this->sizes !== null) {
            $result['sizes'] = $this->sizes;
        }
        if ($this->theme !== null) {
            $result['theme'] = $this->theme;
        }

        return $result;
    }

    /**
     * @return string
     */
    public function getSrc(): string
    {
        return $this->src;
    }

    /**
     * @return string|null
     */
    public function getMimeType(): ?string
    {
        return $this->mimeType;
    }

    /**
     * @return array<string>|null
     */
    public function getSizes(): ?array
    {
        return $this->sizes;
    }

    /**
     * @return 'light'|'dark'|null
     */
    public function getTheme(): ?string
    {
        return $this->theme;
    }
}
