<?php
// 
// This code scrapes Blekko's HTML search result pages for information about a given
// domain
//
// By Pete Warden <pete@petewarden.com>, freely reusable, see http://petewarden.typepad.com for more

require_once('parallelcurl.php');

define('INBOUND_LINK_RE', 
'@'.
'\s*<tr>\s*'.
'\s*<td\s+class="number">[^<]+</td>\s*'.
'\s*<td class="urlInfo" title="[^"]+">\s*'.
'\s*<a href="[^"]+">([^<]+)</a>\s*'.
'\s*</td>\s*'.
'\s*<td\s+class="number" title="[^"]+">\s*'.
'\s*(\S+)\s*'.
'\s*</td>\s*'.
'\s*<td\s+class="number"\s+title="[^"]+"><a href="javascript:;" onclick="[^"]+">(\S+)</a></td>\s*'.
'@');

define('DOMAIN_STATS_RE',
'@'.
'\s*<li>\s*'.
'\s*<a href="javascript:;" onclick="[^"]+" title="see host stats" class="linkNr">(\S+)</a>\s*'.
'\s*hostrank\s*'.
'\s*</li>\s*'.
'\s*<li>\s*'.
'\s*<a href="javascript:;" class="linkNr" onclick="[^"]+">(\S+)</a>\s*'.
'\s*inbound links\s*'.
'\s*</li>\s*'.
'\s*<li>\s*'.
'\s*<a href="javascript:;" onclick="[^"]+" title="see outbound links" class="linkNr">(\S+)</a>\s*'.
'@');

$g_domain_info = array();
$g_retry_count = 0;

// This function gets called back once the main domain's result page has been fetched
function on_main_request_done($content, $url, $ch, $domain) {
    
    global $g_domain_info;
    global $g_parallel_curl;
    global $g_retry_count;

    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);    
    if ($httpcode !== 200) 
    {
        if (($httpcode>=500)&&($httpcode<600)&&($g_retry_count<100))
        {
            error_log("Temporary error $httpcode for '$url' - pausing and retrying\n");
            sleep(2);
            $g_parallel_curl->startRequest($url, 'on_main_request_done', $domain);
            $g_retry_count += 1;
        }
        else
        {
            error_log("Fetch error $httpcode for '$url'\n");
        }
        return;
    }

    $g_domain_info = scrape_domain_info_from_html($content);
    $g_domain_info['domain'] = $domain;
    
    $inbound_links = $g_domain_info['inbound_links'];
    
    foreach ($inbound_links as $link_domain => $inbound_link)
    {
        $page_rank = $inbound_link['page_rank'];
        $link_count = $inbound_link['link_count'];
        
        $g_parallel_curl->startRequest(blekko_seo_url($link_domain), 'on_link_request_done', $link_domain);
    }    
}

function on_link_request_done($content, $url, $ch, $link_domain) {
    
    global $g_domain_info;
    global $g_parallel_curl;
    global $g_retry_count;
    
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);    
    if ($httpcode !== 200) {
        if (($httpcode>=500)&&($httpcode<600)&&($g_retry_count<100))
        {
            error_log("Temporary error $httpcode for '$url' - pausing and retrying\n");
            sleep(2);
            $g_parallel_curl->startRequest($url, 'on_link_request_done', $link_domain);
            $g_retry_count += 1;
        }
        else
        {
            error_log("Fetch error $httpcode for '$url'\n");
        }
        return;
    }

    $g_domain_info['inbound_links'][$link_domain]['domain_info'] = 
        scrape_domain_info_from_html($content);
}

// Takes Blekko's raw HTML and extracts the statistics we're interested in
function scrape_domain_info_from_html($content)
{
    $content = str_replace("\n", ' ', $content);

    preg_match_all(INBOUND_LINK_RE, $content, $inbound_matches, PREG_SET_ORDER);

    preg_match(DOMAIN_STATS_RE, $content, $domain_stats);

    if (empty($domain_stats))
        die("Couldn't find domain stats on page\n");

    $main_page_rank = pete_as_numeric($domain_stats[1]);
    $inbound_link_count = pete_as_numeric($domain_stats[2]);
    $site_page_count = pete_as_numeric($domain_stats[3]);
    $inbound_links = array();
        
    foreach ($inbound_matches as $inbound_match)
    {
        $domain = $inbound_match[1];
        $page_rank = pete_as_numeric($inbound_match[2]);
        $link_count = pete_as_numeric($inbound_match[3]);
     
        $inbound_links[$domain] = array(
            'page_rank' => $page_rank,
            'link_count' => $link_count,
        );
    }

    $result = array(
        'page_rank' => $main_page_rank,
        'inbound_link_count' => $inbound_link_count,
        'page_count' => $site_page_count,
        'inbound_links' => $inbound_links,
    );
    
    return $result;
}

function blekko_seo_url($domain)
{
    return 'http://blekko.com/ws/'.urlencode($domain).'+/seo';
}

// Take a string with extra cruft in it, and attempt to strip it out and return a number
function pete_as_numeric($input_value)
{
    $clean_value = trim($input_value);
    $clean_value = str_replace(',', '', $clean_value);
    $clean_value = str_replace('%', '', $clean_value);
    $clean_value = str_replace('+', '', $clean_value);
    $clean_value = str_replace('$', '', $clean_value);
    if (is_numeric($clean_value))
        $result= $clean_value;
    else
        $result = null;
        
    return $result;
}

set_time_limit(0);

$domain = $_GET['domain'];

$curl_options = array(
    CURLOPT_USERAGENT, 'PageRankGraph - contact pete@petewarden.com',
);

$max_requests = 3;

$main_url = blekko_seo_url($domain);

$g_parallel_curl = new ParallelCurl($max_requests, $curl_options);

$g_parallel_curl->startRequest($main_url, 'on_main_request_done', $domain);

$g_parallel_curl->finishAllRequests();

print json_encode($g_domain_info);

?>