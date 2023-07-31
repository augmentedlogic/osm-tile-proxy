<?php
/**
  Copyright (c) 2020 Wolfgang Hauptfleisch/augmentedlogic <dev@augmentedlogic.com>

  Permission is hereby granted, free of charge, to any person obtaining a copy
  of this software and associated documentation files (the "Software"), to deal
  in the Software without restriction, including without limitation the rights
  to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
  copies of the Software, and to permit persons to whom the Software is
  furnished to do so, subject to the following conditions:

  The above copyright notice and this permission notice shall be included in all
  copies or substantial portions of the Software.

  THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
  IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
  FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
  AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
  LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
  OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
  SOFTWARE.
 **/
namespace com\augmentedlogic\osmtileproxy;

use Imagick;

class MapStyle
{

    const IMAGE_FORMAT_JPEG = IMAGETYPE_JPEG;
    const IMAGE_FORMAT_PNG = IMAGETYPE_PNG;
    const IMAGE_FORMAT_WEBP = IMAGETYPE_WEBP;

    private $stylename;
    private $option_modulate = false;

    private $option_modulate_brightness = 100.;
    private $option_modulate_saturation = 100.;
    private $option_modulate_hue = 100.;

    private $option_sepia = false;
    private $option_sepia_value = 0.;

    private $option_negate = false;
    private $option_negate_gray_only = false;
    private $option_negate_channel;

    private $option_image_format = "png";
    private $option_image_mimetype = "image/png";
    private $option_image_checktype = self::IMAGE_FORMAT_PNG;

    private $mirrors = array();
    private $refresh = 14;

    private $option_lang = null;


    function __construct(string $stylename)
    {
        $this->stylename = $stylename;
    }


    public function setMirrors(array $mirrors): void
    {
        $this->mirrors = $mirrors;
    }

    public function setLang(string $lang): void
    {
        $this->option_lang = $lang;
    }

    public function setEffectModulate(float $brightness = 100., float $saturation = 50., float $hue = 100.): void
    {
        $this->option_modulate = true;
        $this->option_modulate_brightness = $brightness;
        $this->option_modulate_saturation = $saturation;
        $this->option_modulate_hue = $hue;
    }

    public function setEffectSepia(float $value = 90.): void
    {
        $this->option_sepia = true;
        $this->option_sepia_value = $value;
    }

    public function setEffectNegate(bool $gray_only = false, int $channel = Imagick::CHANNEL_DEFAULT): void
    {
        $this->option_negate = true;
        $this->option_negate_gray_only = $gray_only;
        $this->option_negate_channel = $channel;
    }


    public function setRefresh(int $refresh): void
    {
        $this->refresh = $refresh;
    }

    public function getRefresh(): int
    {
        return $this->refresh;
    }


    public function setImageFormat(int $image_format): void
    {
        if($image_format == self::IMAGE_FORMAT_JPEG) {
            $this->option_image_format = "jpg";
            $this->option_image_mimetype = "image/jpeg";
        }
        if($image_format == self::IMAGE_FORMAT_PNG) {
            $this->option_image_format = "png";
            $this->option_image_mimetype = "image/png";
        }
        if($image_format == self::IMAGE_FORMAT_WEBP) {
            $this->option_image_format = "webp";
            $this->option_image_mimetype = "image/webp";
        }
        $this->option_image_checktype = $image_format;
    }

    public function getImageFormat(): string
    {
        return $this->option_image_format;
    }

    public function getName(): string
    {
        return $this->stylename;
    }


    public function getMirrors(): array
    {
        return $this->mirrors;
    }

    public function getModulate(): bool
    {
        return $this->option_modulate;
    }

    public function getModulateBrightness(): float
    {
        return $this->option_modulate_brightness;
    }

    public function getModulateSaturation(): float
    {
        return $this->option_modulate_saturation;
    }

    public function getModulateHue(): float
    {
        return $this->option_modulate_hue;
    }

    public function getSepia(): bool
    {
        return $this->option_sepia;
    }

    public function getSepiaValue(): float
    {
        return $this->option_sepia_value;
    }

    public function getNegate(): bool
    {
        return $this->option_negate;
    }

    public function getNegateGrayOnly(): bool
    {
        return $this->option_negate_gray_only;
    }

    public function getNegateChannel(): int
    {
        return $this->option_negate_channel;
    }

    public function getImageChecktype(): int
    {
        return $this->option_image_checktype;
    }

    public function getLang(): ?string
    {
        return $this->option_lang;
    }

}
