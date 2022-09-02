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
	private $option_refresh = 60; // refresh after n days

	private $allow_referrer = NULL;
	private $styles = array();
        private $lang = null;

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
		$this->$option_storage_dir = $option_storage_dir;
	}

	public function setLogDir($option_log_dir)
	{
		$this->$option_log_dir = $option_log_dir;
	}


        public function setLang($lang)
        {
                $this->$lang = $lang;
        }


	public function setReferrer($referrer)
	{
		$this->allow_referrer = $referrer;
	}

	public function setLogLevel($loglevel)
	{
		$this->option_loglevel = $loglevel;
	}


	private function log($msg, $level = 0)
	{
		if($level <= $this->option_loglevel) {
			file_put_contents($this->option_log_dir."/proxy.log", date("Y M j G:i:s", time())." ".$msg."\n", FILE_APPEND);
		}
	}

	private function is_valid_image($path)
	{
		$a = getimagesize($path);
		$image_type = $a[2];
		if(in_array($image_type , array(IMAGETYPE_PNG)))
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

		$url = $domain . $filepath;
                if(!is_null($current_style->getLang())) {
                   $url = $domain . $filepath. "?lang=".$current_style->getLang();
                }
		$this->log("Downloading ".$url, 2);
		$save_to = "{$this->option_storage_dir}/{$current_style_name}{$filepath}";
		$this->log("Saving to ".$save_to, 2);
		$this->log("mkdir ".$target_dir, 2);
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
		if($this->is_valid_image($save_to)) {
			$success = true;

			// TODO: dont save image in between
			if($current_style->getModulate()) {
				$this->log("Modulating Image", 2);
				$this->modulateImage($save_to, $current_style->getModulateBrightness(), $current_style->getModulateSaturation(), $current_style->getModulateHue());
			}

			if($current_style->getSepia()) {
				$this->log("Sepia Image", 2);
				$this->sepiaToneImage($save_to, $current_style->getSepiaValue());
			}

			if($current_style->getNegate()) {
				$this->log("Negate Image", 2);
				$this->negateImage($save_to, $current_style->getNegateGrayOnly(), $current_style->getNegateChannel());
			}

		} else {
			unlink($save_to);
		}


		return $success;
	}

	/**
	 *  image processing
	 **/
	function sepiaToneImage($imagePath, $sepia) {
		$imagick = new Imagick(realpath($imagePath));
		$imagick->sepiaToneImage($sepia);
		$imagick->writeImage($imagePath);
	}


	function modulateImage($target_file, $brightness, $saturation, $hue) {
		$imagick = new Imagick($target_file);
		$imagick->modulateImage($brightness, $saturation, $hue);
		$imagick->writeImage($target_file);
	}

	function negateImage($imagePath, $grayOnly, $channel = Imagick::CHANNEL_DEFAULT) {
		$imagick = new Imagick(realpath($imagePath));
		$imagick->negateImage($grayOnly, $channel);
		$imagick->writeImage($imagePath);
	}

	private function validate($parts)
	{
		$valid = true;
		if(!is_null($this->allow_referrer)) {
			if (!empty($_SERVER['HTTP_REFERER'])) {
				if($_SERVER['HTTP_REFERER'] != $this->allow_referrer) {
					$valid = false;
					$this->log("referrer_not_allowed: ". $_SERVER['HTTP_REFERER'], 1);
				}
			}
		}

		if(count($parts) != 5) {
			$this->log("invalid request url", 1);
			$valid = false;
		}

		if(!$this->styles[$parts[1]]) {
			$valid = false;
			$this->log("invalid style requested", 1);
		}

		return $valid;
	}

	public function handle()
	{
		$path_only = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
		$subfolder = dirname(parse_url($_SERVER['SCRIPT_NAME'], PHP_URL_PATH));
        	$path_only = substr($path_only, strlen($subfolder)-1);
		$parts = explode("/", $path_only);

		if($this->validate($parts)) {
			$succes = false;

			$current_style = $this->styles[$parts[1]];
			$current_style_name = $current_style->getName();

			$target_file = "/${parts[2]}/${parts[3]}/{$parts[4]}";
			$target_dir = "{$this->option_storage_dir}/{$current_style_name}/${parts[2]}/${parts[3]}/";
			$check_file = "{$this->option_storage_dir}/{$current_style_name}/${parts[2]}/${parts[3]}/{$parts[4]}";

			$this->log("Checking ". $check_file, 2);

			if (!is_file($check_file))
			{
				$success = $this->fetchTile($current_style_name, $target_file, $target_dir);
			} else {
				// check if refresh is needed
				if(filemtime($check_file) > time() - (86400 * $this->refresh) ) {
					$this->log("refresh needed.", 2);
					$success = $this->fetchTile($current_style_name, $target_file, $target_dir);
				} else {
					$this->log("file found in cache.", 2);
					$success = true;
				}
			}


			// we set broser cache options
			if($success) {
				$exp_gmt = gmdate("D, d M Y H:i:s", time() + $this->option_ttl) ." GMT";
				$mod_gmt = gmdate("D, d M Y H:i:s", filemtime($check_file)) ." GMT";
				header("Expires: " . $exp_gmt);
				header("Last-Modified: " . $mod_gmt);
				header("Cache-Control: public, max-age=" . $this->option_ttl);
				header ('Content-Type: image/png');
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


}


