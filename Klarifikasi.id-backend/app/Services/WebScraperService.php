<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\DomCrawler\Crawler;

class WebScraperService
{
    /**
     * Scrape the main content from a URL
     * 
     * @param string $url
     * @return array|null
     */
    public function scrapeContent(string $url): ?array
    {
        try {
            // Set timeout and user agent to mimic browser
            $response = Http::timeout(10)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                    'Accept-Language' => 'id-ID,id;q=0.9,en-US;q=0.8,en;q=0.7',
                ])
                ->get($url);

            if (!$response->successful()) {
                Log::warning("WebScraperService: Failed to fetch {$url} - Status: " . $response->status());
                return null;
            }

            $html = $response->body();
            $crawler = new Crawler($html);

            // Extract article content using common selectors
            $content = $this->extractMainContent($crawler);
            $title = $this->extractTitle($crawler);
            $date = $this->extractDate($crawler);
            $author = $this->extractAuthor($crawler);

            if (empty($content)) {
                Log::info("WebScraperService: No content extracted from {$url}");
                return null;
            }

            return [
                'url' => $url,
                'title' => $title,
                'content' => $content,
                'date' => $date,
                'author' => $author,
                'word_count' => str_word_count($content),
            ];

        } catch (\Exception $e) {
            Log::error("WebScraperService: Error scraping {$url} - " . $e->getMessage());
            return null;
        }
    }

    /**
     * Extract the main article content
     */
    private function extractMainContent(Crawler $crawler): string
    {
        // Priority selectors for article content (in order of specificity)
        $contentSelectors = [
            // News website common patterns
            'article .content',
            'article .post-content',
            'article .entry-content',
            'article .article-content',
            'article .article-body',
            '.article-content',
            '.post-content',
            '.entry-content',
            '.content-article',
            '.detail-content',
            '.detail__body-text',
            '.read__content',
            // Fallback to article or main tag
            'article p',
            'main p',
            '.content p',
            // Generic fallback
            'body p',
        ];

        $content = '';

        foreach ($contentSelectors as $selector) {
            try {
                $nodes = $crawler->filter($selector);
                if ($nodes->count() > 0) {
                    $texts = [];
                    $nodes->each(function (Crawler $node) use (&$texts) {
                        $text = trim($node->text(''));
                        if (strlen($text) > 50) { // Only include substantial paragraphs
                            $texts[] = $text;
                        }
                    });

                    if (!empty($texts)) {
                        $content = implode("\n\n", $texts);
                        break;
                    }
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        // Clean up content
        $content = $this->cleanText($content);

        // Limit content length to prevent excessive data
        if (strlen($content) > 3000) {
            $content = mb_substr($content, 0, 3000) . '...';
        }

        return $content;
    }

    /**
     * Extract the article title
     */
    private function extractTitle(Crawler $crawler): string
    {
        $titleSelectors = [
            'h1.title',
            'h1.post-title',
            'h1.article-title',
            'h1.entry-title',
            '.article-title',
            'article h1',
            'h1',
            'title',
        ];

        foreach ($titleSelectors as $selector) {
            try {
                $node = $crawler->filter($selector)->first();
                if ($node->count() > 0) {
                    $title = trim($node->text(''));
                    if (!empty($title)) {
                        return $title;
                    }
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        return '';
    }

    /**
     * Extract the publication date
     */
    private function extractDate(Crawler $crawler): string
    {
        // Try meta tags first
        try {
            $metaDate = $crawler->filter('meta[property="article:published_time"]')->first();
            if ($metaDate->count() > 0) {
                return $metaDate->attr('content') ?? '';
            }
        } catch (\Exception $e) {
        }

        try {
            $metaDate = $crawler->filter('meta[name="pubdate"]')->first();
            if ($metaDate->count() > 0) {
                return $metaDate->attr('content') ?? '';
            }
        } catch (\Exception $e) {
        }

        // Try common date selectors
        $dateSelectors = [
            'time[datetime]',
            '.date',
            '.publish-date',
            '.article-date',
            '.post-date',
        ];

        foreach ($dateSelectors as $selector) {
            try {
                $node = $crawler->filter($selector)->first();
                if ($node->count() > 0) {
                    $date = $node->attr('datetime') ?? $node->text('');
                    if (!empty($date)) {
                        return trim($date);
                    }
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        return '';
    }

    /**
     * Extract the author name
     */
    private function extractAuthor(Crawler $crawler): string
    {
        try {
            $metaAuthor = $crawler->filter('meta[name="author"]')->first();
            if ($metaAuthor->count() > 0) {
                return $metaAuthor->attr('content') ?? '';
            }
        } catch (\Exception $e) {
        }

        $authorSelectors = [
            '.author',
            '.author-name',
            '.byline',
            '[rel="author"]',
        ];

        foreach ($authorSelectors as $selector) {
            try {
                $node = $crawler->filter($selector)->first();
                if ($node->count() > 0) {
                    $author = trim($node->text(''));
                    if (!empty($author)) {
                        return $author;
                    }
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        return '';
    }

    /**
     * Clean up extracted text
     */
    private function cleanText(string $text): string
    {
        // Remove excessive whitespace
        $text = preg_replace('/\s+/', ' ', $text);

        // Remove common unwanted phrases
        $unwantedPhrases = [
            'Baca juga:',
            'BACA JUGA:',
            'Baca Juga:',
            'Simak juga:',
            'Loading...',
            'Advertisement',
            'ADVERTISEMENT',
        ];

        foreach ($unwantedPhrases as $phrase) {
            $text = str_replace($phrase, '', $text);
        }

        return trim($text);
    }

    /**
     * Scrape multiple URLs and return contents
     * 
     * @param array $urls
     * @param int $limit Maximum number of URLs to scrape
     * @return array
     */
    public function scrapeMultiple(array $urls, int $limit = 3): array
    {
        $results = [];
        $scraped = 0;

        foreach ($urls as $url) {
            if ($scraped >= $limit) {
                break;
            }

            // Skip URLs that are likely to block scraping
            if ($this->shouldSkipUrl($url)) {
                continue;
            }

            $content = $this->scrapeContent($url);
            if ($content !== null) {
                $results[] = $content;
                $scraped++;
            }
        }

        return $results;
    }

    /**
     * Check if URL should be skipped (likely to block scraping)
     */
    private function shouldSkipUrl(string $url): bool
    {
        $blockedDomains = [
            'facebook.com',
            'twitter.com',
            'x.com',
            'instagram.com',
            'tiktok.com',
            'youtube.com',
            'linkedin.com',
        ];

        foreach ($blockedDomains as $domain) {
            if (str_contains($url, $domain)) {
                return true;
            }
        }

        return false;
    }
}
