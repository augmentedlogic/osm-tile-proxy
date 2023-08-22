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
use ImagickException;
use RuntimeException;

require_once 'MapStyle.php';

class TileProxy
{

    const LOGLEVEL_OFF = 0;
    const LOGLEVEL_INFO = 1;
    const LOGLEVEL_DEBUG = 2;

    private $user_agent = "Mozilla/5.0 (X11; Linux i586; rv:31.0) Gecko/20100101 Firefox/74.0";

    private $option_storage_dir = "../cache/";
    private $option_log_dir = "../log/";
    private $option_loglevel = 0;
    private $option_ttl = 86400; // default browser expiry time
    private $option_refresh = null; // refresh after n days, null = ignored

    private $allow_referrer = null;
    /**
     * @var MapStyle[]
     */
    private $styles = array();


    public function addStyle(MapStyle $style): void
    {
        $this->styles[$style->getName()] = $style;
    }

    public function setBrowserCacheExpire(float $hours): void
    {
        $this->option_ttl = (int)($hours * 3600);
    }

    public function setUserAgent(string $userAgent): void
    {
        $this->user_agent = $userAgent;
    }

    /**
     * Set the number of days after which this tile proxy should automatically clean up old cached tiles.
     * Setting it to null will disable the automatic cleanup.
     * @param float|null $days
     * @return void
     */
    public function setRefresh(?float $days): void
    {
        $this->option_refresh = $days;
    }

    public function setCacheDir(string $option_storage_dir): void
    {
        $this->option_storage_dir = $option_storage_dir;
    }

    public function setLogDir(string $option_log_dir): void
    {
        $this->option_log_dir = $option_log_dir;
    }

    public function setReferrer(?string $referrer = null): void
    {
        $this->allow_referrer = $referrer;
    }

    public function setLogLevel(int $loglevel): void
    {
        $this->option_loglevel = $loglevel;
    }

    protected function log(string $msg, int $level = self::LOGLEVEL_OFF): void
    {
        if($level <= $this->option_loglevel) {
            file_put_contents($this->option_log_dir."/proxy.log", date("Y M j G:i:s", time())." ".$msg."\n", FILE_APPEND);
        }
    }

    private function is_valid_image(string $path, MapStyle $current_style): bool
    {
        if(file_exists($path)) {
            $a = getimagesize($path);
            if($a) {
               return true;
            }
        }
        return false;
    }

    /**
     * @throws ImagickException|RuntimeException
     */
    private function fetchTile(MapStyle $current_style, string $filepath, string $target_dir, string $save_to): void
    {
        // choose a random mirror
        $random = rand(0, count($current_style->getMirrors()) -1);
        $domain = $current_style->getMirrors()[$random];
        $filepath = str_replace(".webp", ".png", $filepath);

        $url = $domain . $filepath;
        if(!is_null($current_style->getLang())) {
            $url = $domain . $filepath. "?lang=".$current_style->getLang();
        }
        $this->log("Downloading ".$url, self::LOGLEVEL_DEBUG);
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
        curl_setopt($ch, CURLOPT_USERAGENT, $this->user_agent);
        curl_exec($ch);
        curl_close($ch);
        fflush($fp);
        fclose($fp);

        // check if image downloaded successfully
        if (curl_errno($ch) !== 0) {
            throw new RuntimeException(curl_error($ch));
        } elseif ($this->is_valid_image($save_to, $current_style)) {

            if($current_style->getImageChecktype() !== MapStyle::IMAGE_FORMAT_PNG) {
                $this->log("to ". $current_style->getImageFormat(), self::LOGLEVEL_DEBUG);
                $save_to = $this->changeImageFormat($save_to, $current_style->getImageFormat());
            } else {
                $this->log("noconv " .$current_style->getImageFormat(), self::LOGLEVEL_DEBUG);
            }

            // TODO: dont save image in between
            if ($current_style->getModulate() || $current_style->getSepia() || $current_style->getNegate()) {
                $image_obj = new Imagick(realpath($save_to));
                if ($current_style->getModulate()) {
                    $this->log("Modulating Image", self::LOGLEVEL_DEBUG);
                    $this->modulateImage($image_obj, $current_style->getModulateBrightness(), $current_style->getModulateSaturation(), $current_style->getModulateHue());
                }

                if ($current_style->getSepia()) {
                    $this->log("Sepia Image", self::LOGLEVEL_DEBUG);
                    $this->sepiaToneImage($image_obj, $current_style->getSepiaValue());
                }

                if ($current_style->getNegate()) {
                    $this->log("Negate Image", self::LOGLEVEL_DEBUG);
                    $this->negateImage($image_obj, $current_style->getNegateGrayOnly(), $current_style->getNegateChannel());
                }
                $image_obj->writeImage($save_to);
            }

        } else {
            $content = file_get_contents($save_to);
            unlink($save_to);
            throw new RuntimeException($content);
        }
    }

    /**
     * image processing
     *
     * @throws ImagickException
     */
    private function changeImageFormat(string $target_file, string $format): string {
        $imagick = new Imagick($target_file);
        $imagick->setImageFormat($format);
        $new_target_file = str_replace(".png", ".".$format, $target_file);
        $this->log("Converting to " . $new_target_file, self::LOGLEVEL_DEBUG);
        unlink($target_file);
        $imagick->writeImage($new_target_file);
        return $new_target_file;
    }

    /**
     * @throws ImagickException
     */
    private function sepiaToneImage(Imagick $image_obj, float $sepia): void
    {
        //$imagick = new Imagick(realpath($imagePath));
        $image_obj->sepiaToneImage($sepia);
        //$imagick->writeImage($imagePath);
    }

    /**
     * @throws ImagickException
     */
    private function modulateImage(Imagick $image_obj, float $brightness, float $saturation, float $hue): void
    {
        //$imagick = new Imagick($target_file);
        $image_obj->modulateImage($brightness, $saturation, $hue);
        //$imagick->writeImage($target_file);
    }

    /**
     * @throws ImagickException
     */
    private function negateImage(Imagick $image_obj, bool $grayOnly, int $channel = Imagick::CHANNEL_DEFAULT): void
    {
        //$imagick = new Imagick(realpath($imagePath));
        $image_obj->negateImage($grayOnly, $channel);
        //$imagick->writeImage($imagePath);
    }

    /**
     *
     **/
    private function validate(array $parts): bool
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

    public function handle(): void
    {
        $parts = $this->getParameters();

        if($this->validate($parts)) {

            $current_style = $this->styles[$parts[0]];
            $current_style_name = $current_style->getName();

            $target_file = "/$parts[1]/$parts[2]/$parts[3]";
            $target_dir = "$this->option_storage_dir/$current_style_name/$parts[1]/$parts[2]/";
            $check_file = "$this->option_storage_dir/$current_style_name/$parts[1]/$parts[2]/$parts[3]";

            $this->log("Checking ". $check_file, self::LOGLEVEL_DEBUG);

            try {
                if (!file_exists($check_file)) {
                    $this->fetchTile($current_style, $target_file, $target_dir, $check_file);
                } else {
                    // check if refresh is needed
                    // if no refresh is set, we can skip this operation
                    if (!is_null($this->option_refresh)) {
                        if (filemtime($check_file) > time() - (86400 * $this->option_refresh)) {
                            $this->log("refresh needed.", self::LOGLEVEL_DEBUG);
                            $this->fetchTile($current_style, $target_file, $target_dir, $check_file);
                        } else {
                            $this->log("file found in cache.", self::LOGLEVEL_DEBUG);
                        }
                    }
                }
            } catch (ImagickException $e) {
                header ('Content-Type: text/html');
                echo 'Imagick exception ' . $e->getMessage();
                http_response_code(500);
                return;
            } catch (RuntimeException $e) {
                header ('Content-Type: text/html');
                echo $e->getMessage();
                http_response_code(500);
                return;
            }


            // we set browser cache options
            $exp_gmt = gmdate("D, d M Y H:i:s", time() + $this->option_ttl) ." GMT";
            $mod_gmt = gmdate("D, d M Y H:i:s", filemtime($check_file)) ." GMT";
            header("Expires: " . $exp_gmt);
            header("Last-Modified: " . $mod_gmt);
            header("Cache-Control: public, max-age=" . $this->option_ttl);
            header ('Content-Type: image/'.$current_style->getImageFormat());
            readfile($check_file);
            flush();

        } else {
            // image not found
            header ('Content-Type: text/html');
            echo 'Invalid request URL';
            http_response_code(400);
        }

    }

    protected function getParameters(): array
    {
        $path_only = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $parts = explode("/", $path_only);
        array_shift($parts);
        return $parts;
    }
}


