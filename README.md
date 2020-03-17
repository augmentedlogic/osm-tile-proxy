# OSM Tile Proxy

### Description

OSM Tile Proxy is a proxy/tile cache (written in PHP) for openstreetmap tiles which also can modify the tiles on the fly to create custom colorful maps. 

### Prerequisites

You will need apache or nginx with PHP support and php-imagick (imagmagick) support.

### Installing

* Clone the repository

* copy example/index.php-dist to public/index.php

* make sure your webserver has write permissions on cache/ and log/ directories

* redirect all requests to index.php, e.g. in apache with mod_rewrite


```
RewriteEngine on
RewriteCond %{REQUEST_FILENAME} !-d
RewriteCond %{REQUEST_FILENAME} !-f
RewriteRule . /index.php [L]
```
or for nginx

```
 location /  {
            include /etc/nginx/fastcgi_params;
            fastcgi_param   SCRIPT_FILENAME  $document_root/index.php;
            fastcgi_pass unix:/var/run/php/php7.3-fpm.sock;
}
```

your tiles will now be served at

```
http://<mydomain>/<stylename>/${z}/${x}/${y}.png
```

## Further Documentation

For more configuration options and styling examples, see [OSM Tile Proxy on augmentedlogic developer](https://developer.augmentedlogic.com/project/osm-tile-proxy) 

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details

