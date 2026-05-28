<?php

declare(strict_types=1);

namespace Maispace\MaiAccessibility\Check;

final class ContrastCheck implements CheckInterface
{
    /**
     * WCAG AA contrast ratio thresholds.
     */
    private const float NORMAL_TEXT_RATIO = 4.5;
    private const float LARGE_TEXT_RATIO = 3.0;

    /**
     * Font size thresholds for "large text" (WCAG definition).
     * 18pt ≈ 24px, 14pt ≈ 18.66px (when bold).
     */
    private const float LARGE_TEXT_PX = 24.0;
    private const float LARGE_TEXT_BOLD_PX = 18.66;

    /**
     * Common CSS named colours for quick lookup.
     */
    private const array NAMED_COLORS = [
        'aliceblue' => [240, 248, 255],
        'antiquewhite' => [250, 235, 215],
        'aqua' => [0, 255, 255],
        'aquamarine' => [127, 255, 212],
        'azure' => [240, 255, 255],
        'beige' => [245, 245, 220],
        'bisque' => [255, 228, 196],
        'black' => [0, 0, 0],
        'blanchedalmond' => [255, 235, 205],
        'blue' => [0, 0, 255],
        'blueviolet' => [138, 43, 226],
        'brown' => [165, 42, 42],
        'burlywood' => [222, 184, 135],
        'cadetblue' => [95, 158, 160],
        'chartreuse' => [127, 255, 0],
        'chocolate' => [210, 105, 30],
        'coral' => [255, 127, 80],
        'cornflowerblue' => [100, 149, 237],
        'cornsilk' => [255, 248, 220],
        'crimson' => [220, 20, 60],
        'cyan' => [0, 255, 255],
        'darkblue' => [0, 0, 139],
        'darkcyan' => [0, 139, 139],
        'darkgoldenrod' => [184, 134, 11],
        'darkgray' => [169, 169, 169],
        'darkgreen' => [0, 100, 0],
        'darkgrey' => [169, 169, 169],
        'darkkhaki' => [189, 183, 107],
        'darkmagenta' => [139, 0, 139],
        'darkolivegreen' => [85, 107, 47],
        'darkorange' => [255, 140, 0],
        'darkorchid' => [153, 50, 204],
        'darkred' => [139, 0, 0],
        'darksalmon' => [233, 150, 122],
        'darkseagreen' => [143, 188, 143],
        'darkslateblue' => [72, 61, 139],
        'darkslategray' => [47, 79, 79],
        'darkslategrey' => [47, 79, 79],
        'darkturquoise' => [0, 206, 209],
        'darkviolet' => [148, 0, 211],
        'deeppink' => [255, 20, 147],
        'deepskyblue' => [0, 191, 255],
        'dimgray' => [105, 105, 105],
        'dimgrey' => [105, 105, 105],
        'dodgerblue' => [30, 144, 255],
        'firebrick' => [178, 34, 34],
        'floralwhite' => [255, 250, 240],
        'forestgreen' => [34, 139, 34],
        'fuchsia' => [255, 0, 255],
        'gainsboro' => [220, 220, 220],
        'ghostwhite' => [248, 248, 255],
        'gold' => [255, 215, 0],
        'goldenrod' => [218, 165, 32],
        'gray' => [128, 128, 128],
        'green' => [0, 128, 0],
        'greenyellow' => [173, 255, 47],
        'grey' => [128, 128, 128],
        'honeydew' => [240, 255, 240],
        'hotpink' => [255, 105, 180],
        'indianred' => [205, 92, 92],
        'indigo' => [75, 0, 130],
        'ivory' => [255, 255, 240],
        'khaki' => [240, 230, 140],
        'lavender' => [230, 230, 250],
        'lavenderblush' => [255, 240, 245],
        'lawngreen' => [124, 252, 0],
        'lemonchiffon' => [255, 250, 205],
        'lightblue' => [173, 216, 230],
        'lightcoral' => [240, 128, 128],
        'lightcyan' => [224, 255, 255],
        'lightgoldenrodyellow' => [250, 250, 210],
        'lightgray' => [211, 211, 211],
        'lightgreen' => [144, 238, 144],
        'lightgrey' => [211, 211, 211],
        'lightpink' => [255, 182, 193],
        'lightsalmon' => [255, 160, 122],
        'lightseagreen' => [32, 178, 170],
        'lightskyblue' => [135, 206, 250],
        'lightslategray' => [119, 136, 153],
        'lightslategrey' => [119, 136, 153],
        'lightsteelblue' => [176, 196, 222],
        'lightyellow' => [255, 255, 224],
        'lime' => [0, 255, 0],
        'limegreen' => [50, 205, 50],
        'linen' => [250, 240, 230],
        'magenta' => [255, 0, 255],
        'maroon' => [128, 0, 0],
        'mediumaquamarine' => [102, 205, 170],
        'mediumblue' => [0, 0, 205],
        'mediumorchid' => [186, 85, 211],
        'mediumpurple' => [147, 112, 219],
        'mediumseagreen' => [60, 179, 113],
        'mediumslateblue' => [123, 104, 238],
        'mediumspringgreen' => [0, 250, 154],
        'mediumturquoise' => [72, 209, 204],
        'mediumvioletred' => [199, 21, 133],
        'midnightblue' => [25, 25, 112],
        'mintcream' => [245, 255, 250],
        'mistyrose' => [255, 228, 225],
        'moccasin' => [255, 228, 181],
        'navajowhite' => [255, 222, 173],
        'navy' => [0, 0, 128],
        'oldlace' => [253, 245, 230],
        'olive' => [128, 128, 0],
        'olivedrab' => [107, 142, 35],
        'orange' => [255, 165, 0],
        'orangered' => [255, 69, 0],
        'orchid' => [218, 112, 214],
        'palegoldenrod' => [238, 232, 170],
        'palegreen' => [152, 251, 152],
        'paleturquoise' => [175, 238, 238],
        'palevioletred' => [219, 112, 147],
        'papayawhip' => [255, 239, 213],
        'peachpuff' => [255, 218, 185],
        'peru' => [205, 133, 63],
        'pink' => [255, 192, 203],
        'plum' => [221, 160, 221],
        'powderblue' => [176, 224, 230],
        'purple' => [128, 0, 128],
        'rebeccapurple' => [102, 51, 153],
        'red' => [255, 0, 0],
        'rosybrown' => [188, 143, 143],
        'royalblue' => [65, 105, 225],
        'saddlebrown' => [139, 69, 19],
        'salmon' => [250, 128, 114],
        'sandybrown' => [244, 164, 96],
        'seagreen' => [46, 139, 87],
        'seashell' => [255, 245, 238],
        'sienna' => [160, 82, 45],
        'silver' => [192, 192, 192],
        'skyblue' => [135, 206, 235],
        'slateblue' => [106, 90, 205],
        'slategray' => [112, 128, 144],
        'slategrey' => [112, 128, 144],
        'snow' => [255, 250, 250],
        'springgreen' => [0, 255, 127],
        'steelblue' => [70, 130, 180],
        'tan' => [210, 180, 140],
        'teal' => [0, 128, 128],
        'thistle' => [216, 191, 216],
        'tomato' => [255, 99, 71],
        'turquoise' => [64, 224, 208],
        'violet' => [238, 130, 238],
        'wheat' => [245, 222, 179],
        'white' => [255, 255, 255],
        'whitesmoke' => [245, 245, 245],
        'yellow' => [255, 255, 0],
        'yellowgreen' => [154, 205, 50],
    ];

    public function getIdentifier(): string
    {
        return 'color_contrast';
    }

    public function check(string $html, int $pageUid): array
    {
        if (trim($html) === '') {
            return [];
        }

        $results = [];
        $doc = new \DOMDocument();
        @$doc->loadHTML('<meta charset="UTF-8">' . $html, \LIBXML_NOERROR);
        $xpath = new \DOMXPath($doc);

        $elementsWithStyle = $xpath->query('//*[@style]');
        if ($elementsWithStyle === false) {
            return [];
        }

        foreach ($elementsWithStyle as $element) {
            if (!$element instanceof \DOMElement) {
                continue;
            }

            $style = $element->getAttribute('style');
            $properties = $this->parseInlineStyle($style);

            $textColor = $this->extractColor($properties, 'color');
            $bgColor = $this->extractColor($properties, 'background-color')
                ?? $this->extractColorFromBackgroundShorthand($properties);

            if ($textColor === null && !$this->hasBackgroundProperty($properties)) {
                continue;
            }

            $fontSize = $properties['font-size'] ?? null;
            $fontWeight = $properties['font-weight'] ?? null;

            if ($textColor !== null && $bgColor !== null) {
                $ratio = $this->computeContrastRatio($textColor, $bgColor);
                $threshold = $this->isLargeText($fontSize, $fontWeight)
                    ? self::LARGE_TEXT_RATIO
                    : self::NORMAL_TEXT_RATIO;

                if ($ratio < $threshold) {
                    $tagInfo = sprintf(
                        '<%s style="%s">',
                        $element->nodeName,
                        htmlspecialchars(substr($style, 0, 120)),
                    );

                    if ($this->isLargeText($fontSize, $fontWeight)) {
                        $results[] = CheckResult::error(
                            $this->getIdentifier(),
                            sprintf(
                                'Insufficient colour contrast ratio of %.1f:1 (AA requires %.1f:1 for large text).',
                                $ratio,
                                $threshold,
                            ),
                            $tagInfo,
                            $pageUid,
                        );
                    } else {
                        $results[] = CheckResult::error(
                            $this->getIdentifier(),
                            sprintf(
                                'Insufficient colour contrast ratio of %.1f:1 (AA requires %.1f:1 for normal text).',
                                $ratio,
                                $threshold,
                            ),
                            $tagInfo,
                            $pageUid,
                        );
                    }
                }
            } elseif ($textColor !== null) {
                $results[] = CheckResult::warning(
                    $this->getIdentifier(),
                    'Inline text colour set without an explicit background colour — verify sufficient contrast against the page background.',
                    sprintf(
                        '<%s style="%s">',
                        $element->nodeName,
                        htmlspecialchars(substr($style, 0, 120)),
                    ),
                    $pageUid,
                );
            } elseif ($bgColor !== null) {
                $results[] = CheckResult::warning(
                    $this->getIdentifier(),
                    'Inline background colour set without an explicit text colour — verify sufficient contrast against the background.',
                    sprintf(
                        '<%s style="%s">',
                        $element->nodeName,
                        htmlspecialchars(substr($style, 0, 120)),
                    ),
                    $pageUid,
                );
            }
        }

        return $results;
    }

    /**
     * @return array<string, string>
     */
    private function parseInlineStyle(string $style): array
    {
        $properties = [];
        $declarations = explode(';', $style);

        foreach ($declarations as $declaration) {
            $declaration = trim($declaration);
            if ($declaration === '') {
                continue;
            }

            $colonPos = strpos($declaration, ':');
            if ($colonPos === false) {
                continue;
            }

            $property = strtolower(trim(substr($declaration, 0, $colonPos)));
            $value = trim(substr($declaration, $colonPos + 1));

            if ($property !== '' && $value !== '') {
                $properties[$property] = $value;
            }
        }

        return $properties;
    }

    /**
     * @param array<string, string> $properties
     * @return array{int, int, int}|null
     */
    private function extractColor(array $properties, string $property): ?array
    {
        $value = $properties[$property] ?? null;
        if ($value === null) {
            return null;
        }

        return $this->parseCssColor($value);
    }

    /**
     * @param array<string, string> $properties
     * @return array{int, int, int}|null
     */
    private function extractColorFromBackgroundShorthand(array $properties): ?array
    {
        if (isset($properties['background-color'])) {
            return null;
        }

        $background = $properties['background'] ?? null;
        if ($background === null || $background === 'none' || $background === 'transparent') {
            return null;
        }

        $tokens = preg_split('/\s+/', $background);
        if ($tokens === false || $tokens === []) {
            return null;
        }

        foreach ($tokens as $token) {
            $token = trim($token);
            if (str_starts_with($token, 'url(')
                || str_starts_with($token, 'linear-gradient')
                || str_starts_with($token, 'radial-gradient')
                || str_starts_with($token, 'conic-gradient')
                || str_starts_with($token, 'repeating-linear-gradient')
                || str_starts_with($token, 'repeating-radial-gradient')
                || $token === 'no-repeat'
                || $token === 'repeat'
                || $token === 'repeat-x'
                || $token === 'repeat-y'
                || $token === 'scroll'
                || $token === 'fixed'
                || $token === 'local'
                || $token === 'cover'
                || $token === 'contain'
                || $token === 'auto'
                || $token === 'inherit'
                || $token === 'initial'
                || $token === 'unset'
                || is_numeric($token)
                || str_ends_with($token, '%')
                || str_ends_with($token, 'px')
                || str_ends_with($token, 'em')
                || str_ends_with($token, 'rem')
                || str_ends_with($token, 'pt')
                || str_ends_with($token, 'cm')
                || str_ends_with($token, 'mm')
                || str_ends_with($token, 'in')
            ) {
                continue;
            }

            $color = $this->parseCssColor($token);
            if ($color !== null) {
                return $color;
            }
        }

        return null;
    }

    /**
     * @param array<string, string> $properties
     */
    private function hasBackgroundProperty(array $properties): bool
    {
        return isset($properties['background-color']) || isset($properties['background']);
    }

    /**
     * @return array{int, int, int}|null
     */
    private function parseCssColor(string $value): ?array
    {
        $value = strtolower(trim($value));

        if ($value === 'transparent' || $value === 'currentcolor' || $value === 'inherit' || $value === 'initial' || $value === 'unset') {
            return null;
        }

        if (isset(self::NAMED_COLORS[$value])) {
            return self::NAMED_COLORS[$value];
        }

        if ($value[0] === '#') {
            return $this->parseHexColor($value);
        }

        if (str_starts_with($value, 'rgba')) {
            return $this->parseRgbaColor($value);
        }
        if (str_starts_with($value, 'rgb')) {
            return $this->parseRgbColor($value);
        }

        return null;
    }

    /**
     * @return array{int, int, int}|null
     */
    private function parseHexColor(string $hex): ?array
    {
        $hex = ltrim($hex, '#');

        if (strlen($hex) === 3) {
            $r = hexdec(str_repeat($hex[0], 2));
            $g = hexdec(str_repeat($hex[1], 2));
            $b = hexdec(str_repeat($hex[2], 2));
            return [$r, $g, $b];
        }

        if (strlen($hex) >= 6) {
            $r = hexdec(substr($hex, 0, 2));
            $g = hexdec(substr($hex, 2, 2));
            $b = hexdec(substr($hex, 4, 2));
            return [$r, $g, $b];
        }

        return null;
    }

    /**
     * @return array{int, int, int}|null
     */
    private function parseRgbColor(string $value): ?array
    {
        $value = str_replace(["\r", "\n", "\t"], '', $value);
        $value = str_replace(' ', '', $value);
        // Remove rgb( and )
        $content = substr($value, 4, -1);
        $parts = explode(',', (string) $content);

        if (count($parts) < 3) {
            return null;
        }

        $r = $this->resolveColorChannel((string) $parts[0]);
        $g = $this->resolveColorChannel((string) $parts[1]);
        $b = $this->resolveColorChannel((string) $parts[2]);

        if ($r === null || $g === null || $b === null) {
            return null;
        }

        return [$r, $g, $b];
    }

    /**
     * @return array{int, int, int}|null
     */
    private function parseRgbaColor(string $value): ?array
    {
        $value = str_replace(["\r", "\n", "\t"], '', $value);
        $value = str_replace(' ', '', $value);
        // Remove rgba( and )
        $content = substr($value, 5, -1);
        $parts = explode(',', (string) $content);

        if (count($parts) < 3) {
            return null;
        }

        $r = $this->resolveColorChannel((string) $parts[0]);
        $g = $this->resolveColorChannel((string) $parts[1]);
        $b = $this->resolveColorChannel((string) $parts[2]);

        if ($r === null || $g === null || $b === null) {
            return null;
        }

        return [$r, $g, $b];
    }

    private function resolveColorChannel(string $value): ?int
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (str_ends_with($value, '%')) {
            $percent = (float) substr($value, 0, -1);
            return (int) round($percent * 2.55);
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        return null;
    }

    /**
     * Compute WCAG 2.1 relative luminance from sRGB values (0-255).
     */
    private function relativeLuminance(int $r, int $g, int $b): float
    {
        $rLinear = $this->linearizeSRgb($r / 255.0);
        $gLinear = $this->linearizeSRgb($g / 255.0);
        $bLinear = $this->linearizeSRgb($b / 255.0);

        return 0.2126 * $rLinear + 0.7152 * $gLinear + 0.0722 * $bLinear;
    }

    private function linearizeSRgb(float $channel): float
    {
        if ($channel <= 0.04045) {
            return $channel / 12.92;
        }

        return (($channel + 0.055) / 1.055) ** 2.4;
    }

    /**
     * @param array{int, int, int} $colorA
     * @param array{int, int, int} $colorB
     */
    private function computeContrastRatio(array $colorA, array $colorB): float
    {
        $lA = $this->relativeLuminance($colorA[0], $colorA[1], $colorA[2]);
        $lB = $this->relativeLuminance($colorB[0], $colorB[1], $colorB[2]);

        $lighter = max($lA, $lB);
        $darker = min($lA, $lB);

        return ($lighter + 0.05) / ($darker + 0.05);
    }

    /**
     * Determine if the text qualifies as "large text" per WCAG definition.
     * Large text is 18pt (≈24px) or larger, or 14pt (≈18.66px) or larger and bold.
     */
    private function isLargeText(?string $fontSize, ?string $fontWeight): bool
    {
        if ($fontSize === null) {
            return false;
        }

        $sizePx = $this->resolveFontSizeToPx($fontSize);
        if ($sizePx === null) {
            return false;
        }

        if ($sizePx >= self::LARGE_TEXT_PX) {
            return true;
        }

        if ($sizePx >= self::LARGE_TEXT_BOLD_PX && $this->isBold($fontWeight)) {
            return true;
        }

        return false;
    }

    /**
     * Convert a CSS font-size value to pixels.
     */
    private function resolveFontSizeToPx(string $fontSize): ?float
    {
        $fontSize = strtolower(trim($fontSize));

        if (str_ends_with($fontSize, 'px')) {
            return (float) substr($fontSize, 0, -2);
        }

        if (str_ends_with($fontSize, 'pt')) {
            $pt = (float) substr($fontSize, 0, -2);
            return $pt * (96.0 / 72.0);
        }

        return match ($fontSize) {
            'xx-small' => 7.5,
            'x-small' => 10.0,
            'small' => 13.3,
            'medium' => 16.0,
            'large' => 18.0,
            'x-large' => 24.0,
            'xx-large' => 32.0,
            'xxx-large' => 48.0,
            'smaller' => 13.3,
            'larger' => 19.2,
            default => null,
        };
    }

    private function isBold(?string $fontWeight): bool
    {
        if ($fontWeight === null) {
            return false;
        }

        $fontWeight = strtolower(trim($fontWeight));

        if (is_numeric($fontWeight)) {
            return (int) $fontWeight >= 700;
        }

        return $fontWeight === 'bold' || $fontWeight === 'bolder';
    }
}
