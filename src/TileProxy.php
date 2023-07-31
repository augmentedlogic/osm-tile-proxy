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
use ImagickPixel;

require_once 'MapStyle.php';

class TileProxy
{

    const LOGLEVEL_OFF = 0;
    const LOGLEVEL_INFO = 1;
    const LOGLEVEL_DEBUG = 2;

    private $z = 0;
    private $x = 0;
    private $y = 0;
    private $user_agent = "Mozilla/5.0 (X11; Linux i586; rv:31.0) Gecko/20100101 Firefox/74.0";

    private $option_storage_dir = "../cache/";
    private $option_log_dir = "../log/";
    private $option_loglevel = 0;
    private $option_ttl = 86400; // default browser expiry time
    private $option_refresh = null; // refresh after n days, null = ignored

    private $allow_referrer = NULL;
    /**
     * @var MapStyle[]
     */
    private $styles = array();


    public function addStyle(MapStyle $style)
    {
        $this->styles[$style->getName()] = $style;
    }

    public function setBrowserCacheExpire($hours)
    {
        $this->option_ttl = $hours * 3600;
    }

    public function setRefresh($days)
    {
        $this->option_refresh = $days;
    }

    public function setCacheDir($option_storage_dir)
    {
        $this->option_storage_dir = $option_storage_dir;
    }

    public function setLogDir($option_log_dir)
    {
        $this->option_log_dir = $option_log_dir;
    }

    public function setReferrer($referrer)
    {
        $this->allow_referrer = $referrer;
    }

    public function setLogLevel($loglevel)
    {
        $this->option_loglevel = $loglevel;
    }


    protected function log($msg, $level = 0)
    {
        if($level <= $this->option_loglevel) {
            file_put_contents($this->option_log_dir."/proxy.log", date("Y M j G:i:s", time())." ".$msg."\n", FILE_APPEND);
        }
    }

    private function is_valid_image($path, $current_style)
    {
        $a = getimagesize($path);
        $image_type = $a[2];
        if(in_array($image_type , array($current_style->getImageChecktype())))
        {
            return true;
        }
        return false;
    }


    private function fetchTile($current_style_name, $filepath, $target_dir)
    {
        $success = false;
        // choose a random mirror
        $current_style = $this->styles[$current_style_name];
        $random = rand(0, count($this->styles[$current_style_name]->getMirrors()) -1);
        $domain = $this->styles[$current_style_name]->getMirrors()[$random];
        $filepath = str_replace(".webp", ".png", $filepath);

        $url = $domain . $filepath;
        if(!is_null($current_style->getLang())) {
            $url = $domain . $filepath. "?lang=".$current_style->getLang();
        }
        $this->log("Downloading ".$url, self::LOGLEVEL_DEBUG);
        $save_to = "{$this->option_storage_dir}/{$current_style_name}{$filepath}";
        $this->log("Saving to ".$save_to, self::LOGLEVEL_DEBUG);
        $this->log("mkdir ".$target_dir, self::LOGLEVEL_DEBUG);
        if(!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        // tile download
        $ch = curl_init($url);
        $fp = fopen($save_to, "w");
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch,CURLOPT_USERAGENT, $this->user_agent);
        curl_exec($ch);
        curl_close($ch);
        fflush($fp);
        fclose($fp);

        // check if image downloaded successfully
        if($this->is_valid_image($save_to, $current_style)) {
            $success = true;

            if($current_style->getImageChecktype() !== MapStyle::IMAGE_FORMAT_PNG) {
                $this->log("to ". $current_style->getImageFormat(), self::LOGLEVEL_DEBUG);
                $save_to = $this->changeImageFormat($save_to, $current_style->getImageFormat());
            }

            // TODO: dont save image in between
            if($current_style->getModulate() || $current_style->getSepia() || $current_style->getNegate()) {
                $image_obj = new Imagick(realpath($save_to));
                if($current_style->getModulate()) {
                    $this->log("Modulating Image", self::LOGLEVEL_DEBUG);
                    $this->modulateImage($image_obj, $current_style->getModulateBrightness(), $current_style->getModulateSaturation(), $current_style->getModulateHue());
                }

                if($current_style->getSepia()) {
                    $this->log("Sepia Image", self::LOGLEVEL_DEBUG);
                    $this->sepiaToneImage($image_obj, $current_style->getSepiaValue());
                }

                if($current_style->getNegate()) {
                    $this->log("Negate Image", self::LOGLEVEL_DEBUG);
                    $this->negateImage($image_obj, $current_style->getNegateGrayOnly(), $current_style->getNegateChannel());
                }
                $image_obj->writeImage($save_to);
            }

        } else {
            unlink($save_to);
        }


        return $success;
    }

    /**
     *  image processing
     **/
    private function changeImageFormat($target_file, $format) {
        $imagick = new Imagick($target_file);
        $imagick->setImageFormat($format);
        $new_target_file = str_replace(".png", ".".$format, $target_file);
        $this->log("Converting to " . $new_target_file, self::LOGLEVEL_DEBUG);
        unlink($target_file);
        $imagick->writeImage($new_target_file);
        return $new_target_file;
    }

    private function sepiaToneImage($image_obj, $sepia) {
        //$imagick = new Imagick(realpath($imagePath));
        return $image_obj->sepiaToneImage($sepia);
        //$imagick->writeImage($imagePath);
    }

    private function modulateImage($image_obj, $brightness, $saturation, $hue) {
        //$imagick = new Imagick($target_file);
        return $image_obj->modulateImage($brightness, $saturation, $hue);
        //$imagick->writeImage($target_file);
    }

    private function negateImage($image_obj, $grayOnly, $channel = Imagick::CHANNEL_DEFAULT) {
        //$imagick = new Imagick(realpath($imagePath));
        return $image_obj->negateImage($grayOnly, $channel);
        //$imagick->writeImage($imagePath);
    }

    /**
     *
     **/
    private function validate($parts)
    {
        $valid = true;
        if(!is_null($this->allow_referrer)) {
            if (!empty($_SERVER['HTTP_REFERER'])) {
                if($_SERVER['HTTP_REFERER'] != $this->allow_referrer) {
                    $valid = false;
                    $this->log("referrer_not_allowed: ". $_SERVER['HTTP_REFERER'], self::LOGLEVEL_INFO);
                }
            }
        }

        if(count($parts) != 4) {
            $this->log("invalid request url", self::LOGLEVEL_INFO);
            $valid = false;
        }

        if(!$this->styles[$parts[0]]) {
            $valid = false;
            $this->log("invalid style requested", self::LOGLEVEL_INFO);
        }

        return $valid;
    }

    public function handle()
    {
        $parts = $this->getParameters();

        if($this->validate($parts)) {

            $current_style = $this->styles[$parts[0]];
            $current_style_name = $current_style->getName();

            $target_file = "/$parts[1]/$parts[2]/$parts[3]";
            $target_dir = "$this->option_storage_dir/$current_style_name/$parts[1]/$parts[2]/";
            $check_file = "$this->option_storage_dir/$current_style_name/$parts[1]/$parts[2]/$parts[3]";

            $this->log("Checking ". $check_file, self::LOGLEVEL_DEBUG);

            if (!is_file($check_file))
            {
                $success = $this->fetchTile($current_style_name, $target_file, $target_dir);
            } else {
                // check if refresh is needed
                // if no refresh is set, we can skip this operation
                if(!is_null($this->option_refresh)) {
                    if(filemtime($check_file) > time() - (86400 * $this->option_refresh) ) {
                        $this->log("refresh needed.", self::LOGLEVEL_DEBUG);
                        $success = $this->fetchTile($current_style_name, $target_file, $target_dir);
                    } else {
                        $this->log("file found in cache.", self::LOGLEVEL_DEBUG);
                        $success = true;
                    }
                } else {
                    $success = true;
                }
            }


            // we set browser cache options
            if($success) {
                $exp_gmt = gmdate("D, d M Y H:i:s", time() + $this->option_ttl) ." GMT";
                $mod_gmt = gmdate("D, d M Y H:i:s", filemtime($check_file)) ." GMT";
                header("Expires: " . $exp_gmt);
                header("Last-Modified: " . $mod_gmt);
                header("Cache-Control: public, max-age=" . $this->option_ttl);
                header ('Content-Type: image/'.$current_style->getImageFormat());
                readfile($check_file);
                flush();
            } else {
                // something else went wrong
                header ('Content-Type: text/html');
                http_response_code(404);
            }

        } else {
            // image not found
            header ('Content-Type: text/html');
            http_response_code(404);
        }


    }

    protected function getParameters(): array
    {
        $path_only = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $parts = explode("/", $path_only);

        return array_shift($parts);
    }
}


