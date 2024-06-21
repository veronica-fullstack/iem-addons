<?php

class FeedReader
{
    public $tags = [];

    public $output = [];

    public $retval_link = [];

    public $retval_header = [];

    public $retval_new = [];

    public $retval = '';

    public $errorlevel = 0;

    public function __construct($file)
    {
        $errorlevel = error_reporting();
        error_reporting($errorlevel & ~E_NOTICE);

        $xml_parser = xml_parser_create('');
        xml_set_object($xml_parser, $this);
        xml_set_element_handler($xml_parser, 'startElement', 'endElement');
        xml_set_character_data_handler($xml_parser, 'parseData');

        $fp = @fopen($file, 'r') or die("<b>FeedReader:</b> Could not open {$file} for input");
        while ($data = fread($fp, 4096)) {
            xml_parse($xml_parser, $data, feof($fp)) or die(
                sprintf(
                    'FeedReader: Error <b>%s</b> at line <b>%d</b><br>',
                    xml_error_string(xml_get_error_code($xml_parser)),
                    xml_get_current_line_number($xml_parser)
                )
            );
        }
        fclose($fp);

        xml_parser_free($xml_parser);

        error_reporting($errorlevel);
    }

    public function startElement($parser, $tagname, $attrs = [])
    {
        if ($tagname == 'ENCLOSURE' && $attrs) {
            $this->startElement($parser, 'ENCLOSURE');
            foreach ($attrs as $attr => $attrval) {
                $this->startElement($parser, $attr);
                $this->parseData($parser, $attrval);
                $this->endElement($parser, $attr);
            }
            $this->endElement($parser, 'ENCLOSURE');
        }

        // Yahoo! Media RSS - images
        if ($tagname == 'MEDIA:CONTENT' && $attrs['URL'] && $attrs['MEDIUM'] == 'image') {
            $this->startElement($parser, 'IMAGE');
            $this->parseData($parser, $attrs['URL']);
            $this->endElement($parser, 'IMAGE');
        }

        // check if this element can contain others - list may be edited
        if (preg_match('/^(RDF|RSS|CHANNEL|IMAGE|ITEM)/', (string) $tagname)) {
            if ($this->tags) {
                $depth = count($this->tags);
                if (is_array($tmp = end($this->tags))) {
                    $parent = key($tmp);
                    $num = current($tmp);
                    next($tmp);
                    if ($parent) {
                        $this->tags[$depth - 1][$parent][$tagname]++;
                    }
                }
            }
            array_push($this->tags, [
                $tagname => [],
            ]);
        } else {
            if (! preg_match('/^(A|B|I)$/', (string) $tagname)) {
                array_push($this->tags, $tagname);
            }
        }
    }

    public function endElement($parser, $tagname)
    {
        if (! preg_match('/^(A|B|I)$/', (string) $tagname)) {
            array_pop($this->tags);
        }
    }

    public function parseData($parser, $data)
    {
        // return if data contains no text
        if (! trim((string) $data)) {
            return;
        }

        $evalcode = '$this->output';
        foreach ($this->tags as $tag) {
            if (is_array($tag)) {
                $tagname = key($tag);
                $indexes = current($tag);
                next($tag);
                $evalcode .= "[\"{$tagname}\"]";
                if (${$tagname}) {
                    $evalcode .= '[' . (${$tagname} - 1) . ']';
                }
                if ($indexes) {
                    extract($indexes);
                }
            } else {
                if (preg_match('/^([A-Z]+):([A-Z]+)$/', (string) $tag, $matches)) {
                    $evalcode .= "[\"{$matches[1]}\"][\"{$matches[2]}\"]";
                } else {
                    $evalcode .= "[\"{$tag}\"]";
                }
            }
        }
        eval("{$evalcode} = {$evalcode} . '" . addslashes((string) $data) . "';");
    }

    public function display_channel($data, $limit, $maxDescriptionLimit = 0)
    {
        extract($data);
        /*if(isset($IMAGE) && $IMAGE) {
            // display channel image(s)
            foreach($IMAGE as $image) $this->display_image($image);
        }*/
        $this->retval .= '<div id="feedTitle">';
        if (isset($IMAGE) && $IMAGE) {
            // display channel image(s)
            foreach ($IMAGE as $image) {
                $this->display_image($image);
            }
        }
        if (isset($TITLE) && $TITLE) {
            $this->retval .= '<div id="feedTitleContainer"><h1 id="feedTitleText"';
            if (isset($LINK) && $LINK) {
                $this->retval .= "base=\"{$LINK}\"";
            }
            $this->retval .= '>';
            $this->retval .= stripslashes((string) $TITLE);
            if (isset($LINK) && $LINK) {
                $this->retval .= '';
            }
            $this->retval .= '</h1>';
            $this->retval .= '</div>';

            if (isset($DESCRIPTION) && $DESCRIPTION) {
                $this->retval .= "<p>{$DESCRIPTION}</p>\n\n";
            }
            $tmp = [];
            if (isset($PUBDATE) && $PUBDATE) {
                $tmp[] = "<small>Published: {$PUBDATE}</small>";
            }
            if (isset($COPYRIGHT) && $COPYRIGHT) {
                $tmp[] = "<small>Copyright: {$COPYRIGHT}</small>";
            }
            if ($tmp) {
                $this->retval .= '<p>' . implode("<br>\n", $tmp) . "</p>\n\n";
            }
            $this->retval .= '</div>';
        }
        if (isset($ITEM) && $ITEM) {
            // display channel item(s)
            $this->retval .= '<div id="feedContent">';
            foreach ($ITEM as $item) {
                $this->display_item($item, 'CHANNEL', $maxDescriptionLimit);
                if (is_int($limit) && --$limit <= 0) {
                    break;
                }
            }
            $this->retval .= '</div>';
        }
    }

    public function display_channel_header($data, $limit, $newArray)
    {
        extract($data);

        if (isset($TITLE) && $TITLE) {
            $this->retval_header .= stripslashes((string) $TITLE);
        }
    }

    public function display_channel_new($data, $limit, $newArray, $maxDescriptionLimit = 0)
    {
        extract($data);
        /*if(isset($IMAGE) && $IMAGE) {
            // display channel image(s)
            foreach($IMAGE as $image) $this->display_image($image);
        }*/
        $this->retval_new .= '<div id="feedTitle">';
        if (isset($IMAGE) && $IMAGE) {
            // display channel image(s)
            foreach ($IMAGE as $image) {
                $this->display_image($image);
            }
        }
        if (isset($TITLE) && $TITLE) {
            $this->retval_new .= '<div id="feedTitleContainer"><h1 id="feedTitleText"';
            if (isset($LINK) && $LINK) {
                $this->retval_new .= "base=\"{$LINK}\"";
            }
            $this->retval_new .= '>';
            $this->retval_new .= stripslashes((string) $TITLE);
            if (isset($LINK) && $LINK) {
                $this->retval_new .= '';
            }
            $this->retval_new .= '</h1>';
            $this->retval_new .= '</div>';

            if (isset($DESCRIPTION) && $DESCRIPTION) {
                $this->retval_new .= "<p>{$DESCRIPTION}</p>\n\n";
            }
            $tmp = [];
            if (isset($PUBDATE) && $PUBDATE) {
                $tmp[] = "<small>Published: {$PUBDATE}</small>";
            }
            if (isset($COPYRIGHT) && $COPYRIGHT) {
                $tmp[] = "<small>Copyright: {$COPYRIGHT}</small>";
            }
            if ($tmp) {
                $this->retval_new .= '<p>' . implode("<br>\n", $tmp) . "</p>\n\n";
            }
            $this->retval_new .= '</div>';
        }
        if (isset($ITEM) && $ITEM) {
            // display channel item(s)
            $this->retval_new .= '<div id="feedContent">';
            foreach ($ITEM as $item) {
                if ((isset($item['LINK']) && $item['LINK'] != '' && in_array(md5((string) $item['LINK']), $newArray)) || (isset($item['TITLE']) && $item['TITLE'] != '' && in_array(md5((string) $item['TITLE']), $newArray))) {
                    $this->display_item_new($item, 'CHANNEL', $maxDescriptionLimit);
                    if (is_int($limit) && --$limit <= 0) {
                        break;
                    }
                }
            }
            $this->retval_new .= '</div>';
        }
    }

    public function display_channel_link($data, $limit)
    {
        extract($data);
        if (isset($ITEM) && $ITEM) {
            // display channel item(s)
            foreach ($ITEM as $item) {
                $this->display_item_link($item, 'CHANNEL');
                if (is_int($limit) && --$limit <= 0) {
                    break;
                }
            }
        }
    }

    public function display_recent_channel_link($data, $limit, $today)
    {
        extract($data);
        if (isset($ITEM) && $ITEM) {
            // display channel item(s)
            foreach ($ITEM as $item) {
                $this->display_recent_item_link($item, 'CHANNEL', $today);
                if (is_int($limit) && --$limit <= 0) {
                    break;
                }
            }
        }
    }

    public function display_image($data, $parent = '')
    {
        extract($data);
        if (! $URL) {
            return;
        }

        //$this->retval .= "<p>";
        if ($LINK) {
            $this->retval .= '<a id="feedTitleLink" href="' . $LINK . '" target="_blank">';
        }
        $this->retval .= '<img id="feedTitleImage" style="max-width:150px;max-height:300;" src="' . $URL . '"';
        if (isset($WIDTH) && isset($HEIGHT) && $WIDTH && $HEIGHT) {
            $this->retval .= " width=\"{$WIDTH}\" height=\"{$HEIGHT}\"";
        }
        $this->retval .= " border=\"0\" alt=\"{$TITLE}\">";
        if ($LINK) {
            $this->retval .= '</a>';
        }
        //$this->retval .= "</p>\n\n";
    }

    public function display_item($data, $parent, $maxDescriptionLimit = 0)
    {
        extract($data);
        if (! $TITLE) {
            return;
        }

        $this->retval .= '<div class="entry"><h3>';
        if ($LINK) {
            $this->retval .= "<a href=\"{$LINK}\" target=\"_blank\">";
        }
        $this->retval .= stripslashes((string) $TITLE);
        if ($LINK) {
            $this->retval .= '</a>';
        }

        if (! $PUBDATE && $DC['DATE']) {
            $PUBDATE = $DC['DATE'];
        }
        if ($PUBDATE) {
            $this->retval .= '<div class="lastUpdated"><small>(' . $PUBDATE . ')</small></div>';
        }
        $this->retval .= '</h3>';

        // use feed-formatted HTML if provided
        $nonRemovableTag = '<b><li><ul>';
        if (isset($CONTENT['ENCODED']) && $CONTENT['ENCODED']) {
            $newContent = strip_tags(stripslashes((string) $CONTENT['ENCODED']), $nonRemovableTag);
            if (strlen($newContent) > $maxDescriptionLimit && $maxDescriptionLimit > 0) {
                $this->retval .= "<div class='feedEntryContent'>" . substr($newContent, 0, $maxDescriptionLimit) . '[...]</div>';
            } else {
                if ($maxDescriptionLimit == 0) {
                    $newContent = stripslashes((string) $CONTENT['ENCODED']);
                    $this->retval .= "<div class='feedEntryContent'>" . $newContent . '</div>';
                } else {
                    $this->retval .= "<div class='feedEntryContent'>" . $newContent . '</div>';
                }
            }
        } elseif (isset($DESCRIPTION) && $DESCRIPTION) {
            if (isset($IMAGE) && $IMAGE) {
                foreach ($IMAGE as $IMG) {
                    $this->retval .= "<img style='max-width:150px;max-height:150px;' src=\"{$IMG}\">\n";
                }
            }
            $newContentSecond = strip_tags(stripslashes((string) $DESCRIPTION), $nonRemovableTag);
            if (strlen($newContentSecond) > $maxDescriptionLimit && $maxDescriptionLimit > 0) {
                $this->retval .= "<div class='feedEntryContent'>" . substr($newContentSecond, 0, $maxDescriptionLimit) . '</div>';
            } else {
                if ($maxDescriptionLimit == 0) {
                    $newContentSecond = stripslashes((string) $DESCRIPTION);
                    $this->retval .= "<div class='feedEntryContent'>" . $newContentSecond . '</div>';
                } else {
                    $this->retval .= "<div class='feedEntryContent'>" . $newContentSecond . '</div>';
                }
            }
        }

        // RSS 2.0 - ENCLOSURE
        if (isset($ENCLOSURE) && $ENCLOSURE) {
            $this->retval .= "<div class='enclosures'><small><b>Media File:</b><div class=\"enclosure\"><img class=\"type-icon\" src=\"moz-icon://goat?size=16&contentType=audio/mpeg\"/> <a href=\"{$ENCLOSURE['URL']}\">";
            $this->retval .= @(string) end(explode('/', (string) $ENCLOSURE['URL']));
            $totalLength = round((($ENCLOSURE['LENGTH'] / 1024) / 1024), 1);
            $this->retval .= "</a> ({$totalLength} Mb)</small> Type: {$ENCLOSURE['TYPE']}</div></div>";
        }

        if (isset($COMMENTS) && $COMMENTS) {
            $this->retval .= "<div class='feedEntryComment'><small>";
            $this->retval .= "<a href=\"{$COMMENTS}\">Comments</a>";
            $this->retval .= '</small></div>';
        }
    }

    public function display_item_new($data, $parent, $maxDescriptionLimit = 0)
    {
        extract($data);
        if (! $TITLE) {
            return;
        }

        $this->retval_new .= '<div class="entry"><h3>';
        if ($LINK) {
            $this->retval_new .= "<a href=\"{$LINK}\" target=\"_blank\">";
        }
        $this->retval_new .= stripslashes((string) $TITLE);
        if ($LINK) {
            $this->retval_new .= '</a>';
        }

        if (! $PUBDATE && $DC['DATE']) {
            $PUBDATE = $DC['DATE'];
        }
        if ($PUBDATE) {
            $this->retval_new .= '<div class="lastUpdated"><small>(' . $PUBDATE . ')</small></div>';
        }
        $this->retval_new .= '</h3>';

        // use feed-formatted HTML if provided
        $nonRemovableTag = '<b><li><ul>';
        if (isset($CONTENT['ENCODED']) && $CONTENT['ENCODED']) {
            $newContent = strip_tags(stripslashes((string) $CONTENT['ENCODED']), $nonRemovableTag);
            if (strlen($newContent) > $maxDescriptionLimit && $maxDescriptionLimit > 0) {
                $this->retval_new .= "<div class='feedEntryContent'>" . substr($newContent, 0, $maxDescriptionLimit) . '[...]</div>';
            } else {
                if ($maxDescriptionLimit == 0) {
                    $newContent = stripslashes((string) $CONTENT['ENCODED']);
                    $this->retval_new .= "<div class='feedEntryContent'>" . $newContent . '</div>';
                } else {
                    $this->retval_new .= "<div class='feedEntryContent'>" . $newContent . '</div>';
                }
            }
        } elseif (isset($DESCRIPTION) && $DESCRIPTION) {
            if (isset($IMAGE) && $IMAGE) {
                foreach ($IMAGE as $IMG) {
                    $this->retval_new .= "<img style='max-width:150px;max-height:150px;' src=\"{$IMG}\">\n";
                }
            }
            $newContentSecond = strip_tags(stripslashes((string) $DESCRIPTION), $nonRemovableTag);
            if (strlen($newContentSecond) > $maxDescriptionLimit && $maxDescriptionLimit > 0) {
                $this->retval_new .= "<div class='feedEntryContent'>" . substr($newContentSecond, 0, $maxDescriptionLimit) . '</div>';
            } else {
                if ($maxDescriptionLimit == 0) {
                    $newContentSecond = stripslashes((string) $DESCRIPTION);
                    $this->retval_new .= "<div class='feedEntryContent'>" . $newContentSecond . '</div>';
                } else {
                    $this->retval_new .= "<div class='feedEntryContent'>" . $newContentSecond . '</div>';
                }
            }
        }

        // RSS 2.0 - ENCLOSURE
        if (isset($ENCLOSURE) && $ENCLOSURE) {
            $this->retval_new .= "<div class='enclosures'><small><b>Media File:</b><div class=\"enclosure\"><img class=\"type-icon\" src=\"moz-icon://goat?size=16&contentType=audio/mpeg\"/> <a href=\"{$ENCLOSURE['URL']}\">";
            $this->retval_new .= @(string) end(explode('/', (string) $ENCLOSURE['URL']));
            $totalLength = round((($ENCLOSURE['LENGTH'] / 1024) / 1024), 1);
            $this->retval_new .= "</a> ({$totalLength} Mb)</small> Type: {$ENCLOSURE['TYPE']}</div></div>";
        }

        if (isset($COMMENTS) && $COMMENTS) {
            $this->retval_new .= "<div class='feedEntryComment'><small>";
            $this->retval_new .= "<a href=\"{$COMMENTS}\">Comments</a>";
            $this->retval_new .= '</small></div>';
        }
    }

    public function display_item_link($data, $parent)
    {
        extract($data);
        if (! $TITLE) {
            return;
        }

        if ($LINK) {
            $this->retval_link['Link'][] = $LINK;
        }
        if (! $LINK) {
            $this->retval_link['Link'][] = $TITLE;
        }
    }

    public function display_recent_item_link($data, $parent, $todayDate)
    {
        extract($data);
        if (! $TITLE) {
            return;
        }
        if (! $PUBDATE && $DC['DATE']) {
            $PUBDATE = $DC['DATE'];
        }
        if (isset($PUBDATE)) {
            if ($todayDate == date('Y-m-d', strtotime((string) $PUBDATE))) {
                if ($LINK) {
                    $this->retval_link['Link'][] = $LINK;
                }
                if (! $LINK) {
                    $this->retval_link['Link'][] = $TITLE;
                }
            }
        } else {
            if ($LINK) {
                $this->retval_link['Link'][] = $LINK;
            }
            if (! $LINK) {
                $this->retval_link['Link'][] = $TITLE;
            }
        }
    }

    public function fixEncoding(&$input, $key, $output_encoding)
    {
        if (! function_exists('mb_detect_encoding')) {
            return $input;
        }

        $encoding = mb_detect_encoding((string) $input);
        switch ($encoding) {
            case 'ASCII':
            case $output_encoding:
                break;
            case '':
                $input = mb_convert_encoding((string) $input, $output_encoding);
                break;
            default:
                $input = mb_convert_encoding((string) $input, $output_encoding, $encoding);
        }
    }

    public function getOutput($limit = false, $output_encoding = 'UTF-8', $maxDescriptionLimit = 0)
    {
        $this->retval = '';
        $start_tag = key($this->output);

        switch ($start_tag) {
            case 'RSS':
                // new format - channel contains all
                foreach ($this->output[$start_tag]['CHANNEL'] as $channel) {
                    $this->display_channel($channel, $limit, $maxDescriptionLimit);
                }
                break;

            case 'RDF:RDF':
                // old format - channel and items are separate
                if (isset($this->output[$start_tag]['IMAGE'])) {
                    foreach ($this->output[$start_tag]['IMAGE'] as $image) {
                        $this->display_image($image);
                    }
                }
                foreach ($this->output[$start_tag]['CHANNEL'] as $channel) {
                    $this->display_channel($channel, $limit, $maxDescriptionLimit);
                }
                foreach ($this->output[$start_tag]['ITEM'] as $item) {
                    $this->display_item($item, $start_tag, $maxDescriptionLimit);
                }
                break;

            case 'HTML':
                die('Error: cannot parse HTML document as RSS');

            default:
                die("Error: unrecognized start tag '{$start_tag}' in getOutput()");
        }

        if ($this->retval && is_array($this->retval)) {
            array_walk_recursive($this->retval, 'FeedReader::fixEncoding', $output_encoding);
        }
        return $this->retval;
    }

    public function getOutputHeader($limit = false, $output_encoding = 'UTF-8')
    {
        $this->retval_header = '';
        $start_tag = key($this->output);

        switch ($start_tag) {
            case 'RSS':

            case 'RDF:RDF':
                foreach ($this->output[$start_tag]['CHANNEL'] as $channel) {
                    $this->display_channel_header($channel, $limit);
                }
                break;

            case 'HTML':
                die('Error: cannot parse HTML document as RSS');

            default:
                die("Error: unrecognized start tag '{$start_tag}' in getOutput()");
        }

        if ($this->retval_header && is_array($this->retval_header)) {
            array_walk_recursive($this->retval_header, 'FeedReader::fixEncoding', $output_encoding);
        }

        return $this->retval_header;
    }

    public function getOutputNew($newArray, $today, $limit = false, $output_encoding = 'UTF-8', $maxDescriptionLimit = 0)
    {
        $this->retval_new = '';
        $start_tag = key($this->output);

        switch ($start_tag) {
            case 'RSS':
                // new format - channel contains all
                foreach ($this->output[$start_tag]['CHANNEL'] as $channel) {
                    $this->display_channel_new($channel, $limit, $newArray);
                }
                break;

            case 'RDF:RDF':
                // old format - channel and items are separate
                if (isset($this->output[$start_tag]['IMAGE'])) {
                    foreach ($this->output[$start_tag]['IMAGE'] as $image) {
                        $this->display_image($image);
                    }
                }
                foreach ($this->output[$start_tag]['CHANNEL'] as $channel) {
                    $this->display_channel_new($channel, $limit, $newArray);
                }
                foreach ($this->output[$start_tag]['ITEM'] as $item) {
                    $this->display_item($item, $start_tag, $maxDescriptionLimit);
                }
                break;

            case 'HTML':
                die('Error: cannot parse HTML document as RSS');

            default:
                die("Error: unrecognized start tag '{$start_tag}' in getOutput()");
        }

        if ($this->retval_new && is_array($this->retval_new)) {
            array_walk_recursive($this->retval_new, 'FeedReader::fixEncoding', $output_encoding);
        }
        return $this->retval_new;
    }

    public function getOutputLinks($limit = false, $output_encoding = 'UTF-8')
    {
        $this->retval_link = '';
        $start_tag = key($this->output);

        switch ($start_tag) {
            case 'RSS':
                // new format - channel contains all
                foreach ($this->output[$start_tag]['CHANNEL'] as $channel) {
                    $this->display_channel_link($channel, $limit);
                }
                break;

            case 'RDF:RDF':
                // old format - channel and items are separate
                foreach ($this->output[$start_tag]['ITEM'] as $item) {
                    $this->display_item_link($item, $start_tag);
                }
                break;

            case 'HTML':
                die('Error: cannot parse HTML document as RSS');

            default:
                die("Error: unrecognized start tag '{$start_tag}' in getOutput()");
        }

        return $this->retval_link;
    }

    public function getRecentOnlyOutputLinks($today, $limit = false, $output_encoding = 'UTF-8')
    {
        $this->retval_link = '';
        $start_tag = key($this->output);

        switch ($start_tag) {
            case 'RSS':
                // new format - channel contains all
                foreach ($this->output[$start_tag]['CHANNEL'] as $channel) {
                    $this->display_recent_channel_link($channel, $limit, $today);
                }
                break;

            case 'RDF:RDF':
                // old format - channel and items are separate
                foreach ($this->output[$start_tag]['ITEM'] as $item) {
                    $this->display_recent_item_link($item, $start_tag, $today);
                }
                break;

            case 'HTML':
                die('Error: cannot parse HTML document as RSS');

            default:
                die("Error: unrecognized start tag '{$start_tag}' in getOutput()");
        }

        return $this->retval_link;
    }

    public function getRawOutput($output_encoding = 'UTF-8')
    {
        array_walk_recursive($this->output, 'FeedReader::fixEncoding', $output_encoding);
        return $this->output;
    }
}
