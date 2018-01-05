#!/usr/bin/php

<?php
/*
 * if page not found (404)
 * replace php warning with: "fail (red color)"
 */
set_error_handler(function(){ echo "\033[0;31mfail \033[0m \n";}, E_WARNING);

if(empty($argv[1])) {
    showHelp();
    exit;
}

switch ($argv[1]) {
    case "parse":
        checkURLArg();
        $parser = new Parser($argv[2]);
        $parser->setLogger(function($msg) {
            echo "$msg\n";
        });
        $file = Report::save( $parser->parse(), $argv[2] );
        echo "results saved in: ./" . $file . "\n";
        break;
    case "report":
        try {
            checkURLArg();
            echo Report::get($argv[2]);
        } catch (Exception $e) {
            echo $e->getMessage() . "\n";
        }
        break;
    case "help":
    default:
        showHelp();
        break;
}

function checkURLArg() {
    global $argv;
    if(empty($argv[2])) {
        echo "wrung url argument\n";
        exit;
    }
}

function showHelp() {
    echo "parser.php < parse|report|help > < URL >\n
      - parse - parse site and save result to file\n
      - report - show report\n
      - show this help\n";
}


class Report
{
    public static function save($images, $url) {
        $filename = sprintf("%s.csv", parse_url(URL::withProtocol($url))['host']);
        file_put_contents($filename, implode(";", $images));
        return $filename;
    }

    public static function get($url) {
        $filename = sprintf("%s.csv", parse_url(URL::withProtocol($url))['host']);
        if (file_exists($filename)) {
            return file_get_contents($filename);
        } else {
            throw new Exception("The file: $filename does not exist");
        }
    }
}

class URL
{
    public static function withProtocol($url)
    {
        if (strpos($url, 'http') !== 0) {
            $url = 'http://' . $url . '/';
        }
        return $url;
    }
}

class Parser
{

    private $url;
    private $currentPath;
    private $visitedLinks;
    private $toFullUrl;
    private $numLink = 0;
    private $logger;

    public function __construct($url)
    {
        $this->url = $url;
        /*
         * masses of visited pages
         * */
        $this->visitedLinks = [$this->url];
        /*
         * A URL of the form http://site.com/path/page | / path / page | path / page and results in the form http://site.com/path/page
         * */
        $this->toFullUrl = function ($el) {

            if (substr($el, 0, 2) === '//') {
                return $this->removeTrailSlashes($this->url) . substr($el, 1, strlen($el));
            }

            if (substr($el, 0, 1) === '/') {
                return $this->removeTrailSlashes($this->url) . $el;
            }

            if (strpos($el, 'http') === 0) {
                return $el;
            }

            return $this->currentPath . $el;
        };

        $this->logger = function(){};
    }

    public function setLogger($logger) {
        $this->logger = $logger;
    }

    public function getURL() {
        return $this->url;
    }

    public function parse()
    {
        $this->url = URL::withProtocol($this->url);

        $this->url = $this->removeTrailSlashes($this->url);

        $pictures = $this->parsePage($this->url);

        return $pictures;
    }

    private function parsePage($url)
    {
        $url = $this->removeTrailSlashes($url);

        ($this->logger)("URL: $url " . ($this->numLink++));

        $parsedURL = parse_url($url);

        /*
         * full path to the current page to substitute for relative references
         */
        $this->currentPath = $parsedURL['scheme'] . '://' . $parsedURL['host'] . $parsedURL['path'];

        $this->currentPath = $this->removeTrailSlashes($this->currentPath);

        $contents = file_get_contents(trim($url) );
        preg_match_all('%<a href="(' . $this->removeTrailSlashes($this->url) . ')?(/?[^#"]+)%', $contents, $links);
        preg_match_all('/<img src="([^"]+)"/', $contents, $images);

        $links = array_unique($links[2]);
        $images = array_unique($images[1]);
        /*
         * we remove unnecessary protocols
         */
        $links = array_filter($links, function ($link) {
            return strpos($link, 'mailto:') !== 0 && strpos($link, 'javascript:') !== 0;
        });
        /*
         * leads each email. array to the full path
         */
        $links = array_map($this->toFullUrl, $links);
        $images = array_map($this->toFullUrl, $images);

        /*
         * removes links from other domains
        */
        $links = array_filter($links, function ($link) {
            return strpos($link, $this->url) === 0;
        });

        $links = $this->filterVisitedLinks($links);

        $this->visitedLinks = array_merge($this->visitedLinks, $links);
        $picture = [];
        foreach ($links as $link) {
            $newLinks = $this->parsePage($link);
            if (is_array($newLinks)) {
                $picture = array_merge($picture, $newLinks);
                ($this->logger)('==' . count($picture) . '==');
            }
        }

        return array_unique(array_merge($images, $picture));
    }

    /*
     * returns unreachable links
     */
    private function filterVisitedLinks($links)
    {
        $notVisitedLinks = array_diff($links, $this->visitedLinks);
        return $notVisitedLinks;
    }

    private function removeTrailSlashes($findTo)
    {
        $trailSlashSh = preg_replace('#/+$#', '', $findTo);
        return $trailSlashSh;
    }
}
