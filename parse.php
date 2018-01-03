#!/usr/bin/php

<?php
if ($_SERVER['argc'] >= 3) {
    $parser = new Parser($argv[2]);
    switch ($argv[1]) {
        case "parse":
            $result = $parser->parse();
            echo "results saved in: " . "./" . parse_url($argv[2])['host'] . ".csv \n";
            break;
        case "report":
            $parser->report();
            break;
        case "help":
            echo "parser.php < parse|report|help > < URL >\n
      - parse - parse site and save result to file\n
      - report - show report\n
      - show this help\n";
            break;
        default:
            echo "Condition: \nparse.php < parse|report|help >  < URL >\n";
            break;
    }
} else {
    echo "Condition: \nparse.php < parse|report|help >  < URL >\n";
}


class Parser
{

    public $url;
    public $currentPath;
    public $visitedLinks;
    private $toFullUrl;
    private $level = 0;

    public function __construct($url)
    {
        $this->url = $url;
        //массмв посещенных страниц
        $this->visitedLinks = [$this->url];
        // принимает URL вида http://site.com/path/page | /path/page | path/page и приводитк виду http://site.com/path/page
        $this->toFullUrl = function ($el) {

            if (substr($el, 0, 1) === '/') {
                return $this->removeTrailSlashes($this->url) . $el;
            }

            if (strpos($el, 'http') === 0) {
                return $el;
            }

            return $this->currentPath . $el;
        };
    }

    public function parse()
    {

        $this->url = $this->withProtocol();

        $this->url = $this->removeTrailSlashes($this->url);

        $pictures = $this->parsePage($this->url);

        file_put_contents(parse_url($this->url)['host'] . '.csv', implode("\n", $pictures));
        return $pictures;
    }

    public function report()
    {
        $this->url = $this->withProtocol();

        if (is_file(parse_url($this->url)['host'] . ".csv")) {
            echo "results saved in: " . file_get_contents(parse_url($this->url)['host'] . ".csv") . " \n";
        } else {
            echo "The file: ".parse_url($this->url)['host'] . " does not exist\n";
        }
       // echo "results saved in: " . file_get_contents(parse_url($this->url)['host'] . ".csv") . " \n";
    }

    public function parsePage($url)
    {

        $url = $this->removeTrailSlashes($url);

        echo "URL: $url " . ($this->level++) . " \n";

        $parsedURL = parse_url($url);

        // полный путь к текущей странице чтобы подставлять к относмтельным ссылкам
        $this->currentPath = $parsedURL['scheme'] . '://' . $parsedURL['host'] . $parsedURL['path'];

        $this->currentPath = $this->removeTrailSlashes($this->currentPath);

        $contents = file_get_contents(trim($url));
        preg_match_all('%<a href="(' . $this->removeTrailSlashes($this->url) . ')?(/?[^#"]+)%', $contents, $links);
        preg_match_all('/<img src="([^"]+)"/', $contents, $images);

        $links = array_unique($links[2]);
        $images = array_unique($images[1]);
        //убираем не нужные протоколы
        $links = array_filter($links, function ($link) {
            return strpos($link, 'mailto:') !== 0 && strpos($link, 'javascript:') !== 0;
        });
        //приводит каждый эл. массива к полному пути
        $links = array_map($this->toFullUrl, $links);

        $images = array_map($this->toFullUrl, $images);

        //убирает ссылки других доменов
        $links = array_filter($links, function ($link) {
            return strpos($link, $this->url) === 0;
        });

        $links = $this->filterVisitedLinks($links);

        $this->visitedLinks = array_merge($this->visitedLinks, $links);
        $pic = [];
        foreach ($links as $link) {
            $newLinks = $this->parsePage($link);
            if (is_array($newLinks)) {
                $pic = array_merge($pic, $newLinks);
                echo '==' . count($pic) . "== \n";
            }
        }

        return array_unique(array_merge($images, $pic));
    }

    public function withProtocol()
    {
        if (strpos($this->url, 'http') !== 0) {
            $this->url = 'http://' . $this->url . '/';
        }
        return $this->url;
    }

    //возврашает еще не посещенные ссылки
    public function filterVisitedLinks($links)
    {
        $notVisitedLinks = array_diff($links, $this->visitedLinks);
        return $notVisitedLinks;
    }

    public function removeTrailSlashes($findTo)
    {
        $trailSlashSh = preg_replace('#/+$#', '/', $findTo);
        return $trailSlashSh;
    }


}

