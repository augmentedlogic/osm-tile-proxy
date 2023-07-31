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

    private $option_modulate_brightness = 100;
    private $option_modulate_saturation = 100;
    private $option_modulate_hue = 100;

    private $option_sepia = false;
    private $option_sepia_value;

    private $option_negate = false;
    private $option_negate_gray_only = false;
    private $option_negate_channel;

    private $option_image_format = "png";
    private $option_image_mimetype = "image/png";
    private $option_image_checktype = self::IMAGE_FORMAT_PNG;

    private $mirrors = array();
    private $refresh = 14;

    private $option_lang = null;


    function __construct($stylename)
    {
        $this->stylename = $stylename;
    }


    public function setMirrors($mirrors)
    {
        $this->mirrors = $mirrors;
    }

    public function setLang($lang)
    {
        $this->option_lang = $lang;
    }

    public function setEffectModulate($brightness = 100, $saturation = 50, $hue = 100)
    {
        $this->option_modulate = true;
        $this->option_modulate_brightness = $brightness;
        $this->option_modulate_saturation = $saturation;
        $this->option_modulate_hue = $hue;
    }

    public function setEffectSepia($value = 90)
    {
        $this->option_sepia = true;
        $this->option_sepia_value = $value;
    }

    public function setEffectNegate($gray_only = false, $channel = Imagick::CHANNEL_DEFAULT)
    {
        $this->option_negate = true;
        $this->option_negate_gray_only = $gray_only;
        $this->option_negate_channel = $channel;
    }


    public function setRefresh($refresh)
    {
        $this->refresh = $refresh;
    }

    public function getRefresh()
    {
        return $this->refresh;
    }


    public function setImageFormat($image_format)
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

    public function getImageFormat()
    {
        return $this->option_image_format;
    }

    public function getName()
    {
        return $this->stylename;
    }


    public function getMirrors()
    {
        return $this->mirrors;
    }

    public function getModulate()
    {
        return $this->option_modulate;
    }

    public function getModulateBrightness()
    {
        return $this->option_modulate_brightness;
    }

    public function getModulateSaturation()
    {
        return $this->option_modulate_saturation;
    }

    public function getModulateHue()
    {
        return $this->option_modulate_hue;
    }

    public function getSepia()
    {
        return $this->option_sepia;
    }

    public function getSepiaValue()
    {
        return $this->option_sepia_value;
    }

    public function getNegate()
    {
        return $this->option_negate;
    }

    public function getNegateGrayOnly()
    {
        return $this->option_negate_gray_only;
    }

    public function getNegateChannel()
    {
        return $this->option_negate_channel;
    }

    public function getImageChecktype()
    {
        return $this->option_image_checktype;
    }

    public function getLang()
    {
        return $this->option_lang;
    }

}
