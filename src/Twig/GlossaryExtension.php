<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class GlossaryExtension extends AbstractExtension
{

    /**
     * Hardcoded for now: term => link target.
     * Later these entries will be loaded from sulu.
     * 
     * @var array<string , string>
     */
    private array $terms = [
        'Symfony' => '/glossary#symfony',
        'Twig' => '/glossar#twig',
        'Git' => '/glossar#git',
        'PHP' => '/glossar#php',
        'Sulu' => '/glossar#sulu',
    ];

    public function getFilters(): array
    {
        return [
            // is_safe: tells Twig not to escape the returned HTML
            new TwigFilter('glossary_links', [$this, 'linkTerms'], ['is_safe' => ['html']]),
        ];
    }

    /**
     * Wraps the frist occurence of each glossary term in a link.
     *Only text is changd; HTML tags, attributes and existing links stay untouched.
     */

    public function linkTerms(?string $html): string
    {
        if ($html === null || trim($html) === '') {
            return '';
        }


        //Lowercase term => URL, so matching is case-insensitive
        $lookup = [];
        foreach ($this->terms as $term => $url) {
            $lookup[mb_strtolower($term)] = $url;
        }

        //Longer terms first, so "Sulu CMS" is matched before "Sulu"
        $termList = array_keys($this->terms);
        usort($termList, fn($a, $b) => mb_strlen($b) <=> mb_strlen($a));

        // Regex: whole words only (\nb), case-insensitive (i), UTF-8 (u)
        $escaped =array_map(fn ($t) => preg_quote($t, '/'), $termList);
        $pattern = '/\b(' . implode('|', $escaped) . ')\b/iu';


        //Load the HTML into a DOM tree so we can work on text nodes only. 
        //The XML declaration forces UTF-8; the wrapper div gives us a single root.
        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML(
            '<?xml encoding="UTF-8"><div id="glossary-root">' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();

        $xpath = new \DOMXPath($dom);

        //All text nodes that are NOT already inside a link, script or style
        $textNodes = $xpath->query('//text()[not(ancestor::a) and not(ancestor::script) and not(ancestor::style)]');

        // Remembers which terms were already linked (only the first occurrence gets a link)
        $alreadyLinked = [];

        foreach(iterator_to_array($textNodes) as $node){
            //split the text at each matc; with DELIM_CAPTURE the matches end up at odd indexes
            $parts = preg_split($pattern, $node->nodeValue, -1, PREG_SPLIT_DELIM_CAPTURE);

            if(count($parts) < 2) {
                continue; // no matches in this text node
            }

            //Build the replaceemt: plain text pieces plus <a> elements
            $fragment = $dom->createDocumentFragment();

            foreach ($parts as $index => $part) {
                $key = mb_strtolower($part);
                $isMatch = $index % 2 === 1;

                if ($isMatch && !isset($alreadyLinked[$key])) {
                    $alreadyLinked[$key] = true;

                    $link = $dom->createElement('a');
                    $link->setAttribute('href', $lookup[$key]);
                    $link->setAttribute('class', 'glossary-link');
                    $link->appendChild($dom->createTextNode($part));

                    $fragment->appendChild($link);
                } else {
                    $fragment->appendChild($dom->createTextNode($part));
                }
            }

            //Swap the original text node for the new fragment
            $node->parentNode->replaceChild($fragment, $node);

        }

        // Return only the content of the helper <div>, not the div itself 
        $root = $xpath->query('//div[@id="glossary-root"]')->item(0);
        $output = '';
        foreach($root->childNodes as $child) {
            $output .= $dom->saveHTML($child);
        }

        return $output;
    }   
    
}
