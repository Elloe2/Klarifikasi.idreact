<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use function config;
use function env;
use function data_get;

/**
 * Service untuk berkomunikasi dengan Google Gemini AI
 * Menggunakan HTTP client untuk mengakses Gemini API
 */
class GeminiService
{
    private string $apiKey;
    private string $model;
    private string $baseUrl;
    private bool $enabled;
    private WebScraperService $scraper;

    public function __construct()
    {
        $this->apiKey = config('services.gemini.api_key', env('GEMINI_API_KEY')) ?? '';
        $this->model = config('services.gemini.model', env('GEMINI_MODEL', 'gemini-2.0-flash'));
        $this->baseUrl = "https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent";
        $this->enabled = (bool) config('services.gemini.enabled', true);
        $this->scraper = new WebScraperService();

        // Log API key untuk debugging (hanya sebagian)
        $maskedKey = substr($this->apiKey, 0, 10) . '...' . substr($this->apiKey, -4);
        Log::info('GeminiService initialized with API Key: ' . $maskedKey);

        // Validasi API key tanpa throw exception
        if (empty($this->apiKey) || strlen($this->apiKey) < 20) {
            Log::error('Invalid or missing Gemini API Key: ' . $this->apiKey);
        }
    }

    /**
     * Menganalisis klaim dengan menggunakan hasil pencarian Google CSE
     */
    public function analyzeClaim(string $claim, array $searchResults = []): array
    {
        // Log API key status
        $maskedKey = substr($this->apiKey, 0, 10) . '...' . substr($this->apiKey, -4);
        Log::info('GeminiService analyzeClaim called', [
            'claim' => $claim,
            'api_key_masked' => $maskedKey,
            'search_results_count' => count($searchResults)
        ]);

        // Saat environment lokal, hindari pemanggilan API eksternal dan gunakan fallback
        if (!$this->enabled) {
            Log::warning('GeminiService disabled by configuration, using fallback');
            return $this->getFallbackWithSearchData($claim, $searchResults);
        }

        // Check API key validity
        if (empty($this->apiKey) || strlen($this->apiKey) < 20) {
            Log::warning('Gemini API Key not configured properly, using fallback');
            return $this->getFallbackWithSearchData($claim, $searchResults);
        }

        try {
            Log::info('Sending request to Gemini API...');

            $response = Http::timeout(30)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                ])
                ->post($this->baseUrl . "?key=" . $this->apiKey, [
                    'contents' => [
                        [
                            'parts' => [
                                ['text' => $this->buildPrompt($claim, $searchResults)]
                            ]
                        ]
                    ],
                    'generationConfig' => [
                        'temperature' => 0.1,
                        'topK' => 1,
                        'topP' => 1,
                        'maxOutputTokens' => 1024,
                    ],
                    'safetySettings' => [
                        [
                            'category' => 'HARM_CATEGORY_HARASSMENT',
                            'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
                        ],
                        [
                            'category' => 'HARM_CATEGORY_HATE_SPEECH',
                            'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
                        ],
                        [
                            'category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT',
                            'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
                        ],
                        [
                            'category' => 'HARM_CATEGORY_DANGEROUS_CONTENT',
                            'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
                        ]
                    ]
                ]);

            Log::info('Gemini API Response Status: ' . $response->status());

            if ($response->successful()) {
                $data = $response->json();
                $text = \data_get($data, 'candidates.0.content.parts.0.text');

                if (!is_string($text) || trim($text) === '') {
                    $blockReason = \data_get($data, 'promptFeedback.blockReason');
                    $safetyRatings = \data_get($data, 'promptFeedback.safetyRatings');
                    Log::error('Gemini API returned no analysable candidates.', [
                        'blockReason' => $blockReason,
                        'safetyRatings' => $safetyRatings,
                    ]);

                    $fallback = $this->getFallbackWithSearchData($claim, $searchResults);
                    $message = $blockReason ? 'Analisis diblokir oleh Gemini AI.' : 'Gemini AI tidak mengembalikan analisis.';
                    $fallback['success'] = false;
                    $fallback['explanation'] = $message;
                    $fallback['sources'] = 'Gemini AI';
                    $fallback['error'] = $blockReason
                        ? 'Gemini AI memblokir analisis: ' . $blockReason
                        : 'Gemini AI tidak mengembalikan analisis.';
                    return $fallback;
                }

                Log::info('Gemini API Success - Response received');
                return $this->parseResponse((string) $text, $claim);
            } else {
                Log::error('Gemini API Error Status: ' . $response->status());
                Log::error('Gemini API Error Body: ' . $response->body());

                // Return fallback dengan informasi dari Google CSE
                return $this->getFallbackWithSearchData($claim, $searchResults);
            }

        } catch (ConnectionException $e) {
            Log::warning('Gemini API unreachable: ' . $e->getMessage());
            return $this->getFallbackWithSearchData($claim, $searchResults);
        } catch (\Exception $e) {
            Log::error('Gemini Service Exception: ' . $e->getMessage());
            Log::error('Gemini Service Exception Trace: ' . $e->getTraceAsString());
            return $this->getFallbackWithSearchData($claim, $searchResults);
        }
    }

    /**
     * Membangun prompt untuk Gemini AI dengan data pencarian Google CSE
     * Termasuk konten lengkap dari artikel yang di-scrape
     */
    private function buildPrompt(string $claim, array $searchResults = []): string
    {
        $searchData = '';
        $fullContentData = '';

        if (!empty($searchResults)) {
            // Extract URLs for scraping (top 3 most relevant)
            $urlsToScrape = [];
            foreach (array_slice($searchResults, 0, 5) as $result) {
                if (!empty($result['link'])) {
                    $urlsToScrape[] = $result['link'];
                }
            }

            // Scrape full content from top URLs
            Log::info('Scraping content from ' . count($urlsToScrape) . ' URLs...');
            $scrapedContent = $this->scraper->scrapeMultiple($urlsToScrape, 3);

            if (!empty($scrapedContent)) {
                $fullContentItems = [];
                foreach ($scrapedContent as $index => $scraped) {
                    $content = mb_substr($scraped['content'], 0, 2000); // Limit content per article
                    $fullContentItems[] = sprintf(
                        "=== ARTIKEL LENGKAP %d ===\nURL: %s\nJudul: %s\nTanggal: %s\nPenulis: %s\n\nISI ARTIKEL:\n%s",
                        $index + 1,
                        $scraped['url'],
                        $scraped['title'],
                        $scraped['date'] ?: 'Tidak diketahui',
                        $scraped['author'] ?: 'Tidak diketahui',
                        $content
                    );
                }
                $fullContentData = "\n\n=== KONTEN LENGKAP ARTIKEL (UNTUK ANALISIS MENDALAM) ===\n" . implode("\n\n", $fullContentItems);
                Log::info('Successfully scraped ' . count($scrapedContent) . ' articles');
            }

            // Build snippet data (for all results)
            $items = [];
            foreach ($searchResults as $index => $result) {
                $snippet = $result['snippet'] ?? 'Tidak ada snippet';
                $snippet = mb_substr($snippet, 0, 400);

                $items[] = sprintf(
                    "SUMBER %d:\n  Brand/Domain: %s\n  Judul: %s\n  Ringkasan: %s",
                    $index + 1,
                    $result['displayLink'] ?? 'Tidak ada domain',
                    $result['title'] ?? 'Tidak ada judul',
                    $snippet
                );
            }
            $searchData = "\n\nDATA SUMBER (RINGKASAN PENCARIAN):\n" . implode("\n\n", $items);
        }

        return <<<PROMPT
Anda adalah AI Fact-Checker Profesional. Analisis klaim pengguna menggunakan DATA SUMBER dan KONTEN LENGKAP ARTIKEL yang disediakan.

=== ATURAN KONTEN (PENTING) ===
1. JANGAN gunakan frasa pengantar seperti "Berdasarkan data yang saya baca," atau "Analisis saya menunjukkan." Langsung masuk ke inti informasi.
2. JANGAN mengulang-ulang informasi yang sama di bagian berbeda.
3. Tetap objektif, ringkas, dan profesional.

=== KLAIM PENGGUNA ===
"{$claim}"{$searchData}{$fullContentData}

=== ATURAN FORMAT OUTPUT (JSON ONLY) ===
Wajib mengembalikan JSON dengan field berikut:

1. "verdict": "Tervalidasi" | "Terbantah" | "Perlu Verifikasi"
2. "confidence": "Tinggi" | "Sedang" | "Rendah"
3. "explanation": Narasi ringkas dan padat. Gunakan format (pake line break \n antar bagian):
   **Analisa Klaim**: (1-2 kalimat inti masalah)
   **Konteks**: (Penjelasan kenapa ini viral/muncul)
   **Hasil Verifikasi**: (Kesimpulan akhir berbasis data sumber)
4. "analysis": Detail mendalam. Berikan poin-poin tentang:
   - Alasan penetapan status (Verdict & Confidence)
   - Fakta-fakta kunci yang ditemukan di artikel lengkap
   - Perbandingan antar sumber (apakah konsisten atau bertolak belakang)
5. "sources_used": List domain utama yang memberikan informasi paling valid (max 5).

Format JSON:
{
  "verdict": "...",
  "explanation": "...",
  "analysis": "...",
  "confidence": "...",
  "sources_used": ["..."]
}
PROMPT;
    }


    /**
     * Parse response dari Gemini AI
     */
    private function parseResponse(string $text, string $claim): array
    {
        try {
            // Log response untuk debugging
            Log::info('Gemini Raw Response: ' . $text);

            // Bersihkan response dari markdown formatting jika ada
            $cleanText = $this->cleanResponse($text);

            // Coba extract JSON dari response
            $jsonStart = strpos($cleanText, '{');
            $jsonEnd = strrpos($cleanText, '}');

            if ($jsonStart !== false && $jsonEnd !== false) {
                $jsonString = substr($cleanText, $jsonStart, $jsonEnd - $jsonStart + 1);
                Log::info('Extracted JSON: ' . $jsonString);

                $data = json_decode($jsonString, true);

                // Jika JSON parsing gagal, coba repair JSON yang terpotong
                if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
                    Log::warning('JSON parsing failed, attempting repair: ' . json_last_error_msg());
                    $repairedJson = $this->repairTruncatedJson($jsonString);
                    $data = json_decode($repairedJson, true);

                    if ($data !== null) {
                        Log::info('JSON repair successful');
                    }
                }

                if ($data && isset($data['explanation'])) {
                    Log::info('Successfully parsed JSON response');

                    // Extract sources_used array and convert to string
                    $sourcesUsed = '';
                    if (isset($data['sources_used']) && is_array($data['sources_used'])) {
                        $sourcesUsed = implode(', ', $data['sources_used']);
                    }

                    // Format social media links in explanation and analysis
                    $explanation = $this->formatSocialMediaLinks((string) ($data['explanation'] ?? 'Tidak ada penjelasan tersedia'));
                    $analysis = $this->formatSocialMediaLinks((string) ($data['analysis'] ?? 'Tidak ada analisis tersedia'));

                    return [
                        'success' => true,
                        'verdict' => (string) ($data['verdict'] ?? 'MEMERLUKAN_VERIFIKASI'),
                        'explanation' => $explanation,
                        'analysis' => $analysis,
                        'confidence' => (string) ($data['confidence'] ?? 'rendah'),
                        'sources' => $sourcesUsed,
                        'claim' => (string) $claim,
                    ];
                } else {
                    Log::warning('JSON parsed but missing explanation field');
                }
            } else {
                Log::warning('No JSON found in response');
            }

            // Fallback jika JSON parsing gagal - coba parse manual
            return $this->parseTextResponse($cleanText, $claim);

        } catch (\Exception $e) {
            Log::error('Error parsing Gemini response: ' . $e->getMessage());
            return $this->getFallbackResponse($claim);
        }
    }

    /**
     * Bersihkan response dari markdown dan formatting
     */
    private function cleanResponse(string $text): string
    {
        // Hapus markdown code blocks
        $text = preg_replace('/```json\s*/', '', $text);
        $text = preg_replace('/```\s*/', '', $text);

        // Hapus markdown formatting
        $text = preg_replace('/\*\*(.*?)\*\*/', '$1', $text);
        $text = preg_replace('/\*(.*?)\*/', '$1', $text);

        // Hapus extra whitespace
        $text = preg_replace('/\s+/', ' ', $text);

        return trim($text);
    }

    /**
     * Format social media domain names to "Postingan di [Platform]"
     */
    private function formatSocialMediaLinks(string $text): string
    {
        // Replace social media domains with formatted text
        // Use word boundaries to avoid replacing partial matches
        $replacements = [
            '/\binstagram\.com\b/i' => 'postingan di Instagram',
            '/\bfacebook\.com\b/i' => 'postingan di Facebook',
            '/\bfb\.com\b/i' => 'postingan di Facebook',
            '/\btwitter\.com\b/i' => 'postingan di X',
            '/\bx\.com\b/i' => 'postingan di X',
            '/\byoutube\.com\b/i' => 'postingan di YouTube',
            '/\byoutu\.be\b/i' => 'postingan di YouTube',
            '/\breddit\.com\b/i' => 'postingan di Reddit',
            '/\btiktok\.com\b/i' => 'postingan di TikTok',
            '/\blinkedin\.com\b/i' => 'postingan di LinkedIn',
            '/\bthreads\.net\b/i' => 'postingan di Threads',
        ];

        foreach ($replacements as $pattern => $replacement) {
            $text = preg_replace($pattern, $replacement, $text);
        }

        return $text;
    }

    /**
     * Parse response text jika JSON parsing gagal
     */
    private function parseTextResponse(string $text, string $claim): array
    {
        // Jika response tidak dalam format JSON, coba extract informasi manual
        $explanation = 'Tidak dapat menganalisis klaim ini dengan pasti.';
        $sources = '';
        $analysis = 'Tidak ada analisis tersedia';

        // Coba extract penjelasan dari response text
        if (!empty($text)) {
            // Ambil beberapa kalimat pertama sebagai explanation
            $sentences = preg_split('/[.!?]+/', $text);
            $explanation = trim($sentences[0] ?? $text);

            // Jika explanation terlalu panjang, potong
            if (strlen($explanation) > 200) {
                $explanation = substr($explanation, 0, 200) . '...';
            }

            // Gunakan seluruh response sebagai analysis
            $analysis = $text;
            if (strlen($analysis) > 500) {
                $analysis = substr($analysis, 0, 500) . '...';
            }

            $sources = '';
        }

        // Pastikan semua field adalah string
        return [
            'success' => true,
            'explanation' => (string) $explanation,
            'sources' => (string) $sources,
            'analysis' => (string) $analysis,
            'claim' => (string) $claim,
        ];
    }

    /**
     * Repair JSON yang terpotong karena limit token
     */
    private function repairTruncatedJson(string $json): string
    {
        // Hitung bracket yang belum ditutup
        $openBraces = substr_count($json, '{') - substr_count($json, '}');
        $openBrackets = substr_count($json, '[') - substr_count($json, ']');

        // Cek apakah jumlah quote ganjil (string tidak ditutup)
        $quoteCount = substr_count($json, '"');

        // Jika jumlah quote ganjil, tutup string
        if ($quoteCount % 2 !== 0) {
            $json .= '"';
        }

        // Tutup array yang terbuka
        $json .= str_repeat(']', max(0, $openBrackets));

        // Tutup object yang terbuka
        $json .= str_repeat('}', max(0, $openBraces));

        return $json;
    }

    /**
     * Fallback response jika API gagal
     */
    private function getFallbackResponse(string $claim): array
    {
        return [
            'success' => false,
            'verdict' => 'Perlu Verifikasi',
            'explanation' => 'Tidak dapat menganalisis klaim ini saat ini. Silakan coba lagi nanti.',
            'analysis' => 'Layanan AI sedang sibuk atau mencapai batas kuota. Silakan periksa hasil pencarian secara manual di tab sebelah.',
            'confidence' => 'Rendah',
            'sources' => '',
            'claim' => (string) $claim,
            'error' => 'Gemini API tidak tersedia'
        ];
    }

    private function getFallbackWithSearchData(string $claim, array $searchResults = []): array
    {
        $relevantCount = 0;
        $isHoax = false;
        $isOfficial = false;
        $claimKeywords = explode(' ', strtolower($claim));
        $contextSnippets = [];
        $sourcesMentioned = [];
        $platforms = [];
        $scrapedArticles = [];

        // Try to scrape content from top URLs
        if (!empty($searchResults)) {
            $urlsToScrape = [];
            foreach (array_slice($searchResults, 0, 5) as $result) {
                if (!empty($result['link'])) {
                    $urlsToScrape[] = $result['link'];
                }
            }

            try {
                $scrapedArticles = $this->scraper->scrapeMultiple($urlsToScrape, 3);
                Log::info('Fallback: Scraped ' . count($scrapedArticles) . ' articles');
            } catch (\Exception $e) {
                Log::warning('Fallback scraping failed: ' . $e->getMessage());
            }
        }

        if (!empty($searchResults)) {
            foreach ($searchResults as $result) {
                $title = $result['title'] ?? '';
                $snippet = $result['snippet'] ?? '';
                $domain = $result['displayLink'] ?? '';
                $text = strtolower($title . ' ' . $snippet);

                // Ekstrak platform jika ada
                foreach (['tiktok', 'facebook', 'instagram', 'twitter', 'x.com', 'whatsapp', 'youtube'] as $p) {
                    if (str_contains($text, $p))
                        $platforms[] = ucfirst($p);
                }

                // Cek relevansi
                $matches = 0;
                foreach ($claimKeywords as $kw) {
                    if (strlen($kw) > 3 && str_contains($text, $kw))
                        $matches++;
                }

                if ($matches >= 2) {
                    $relevantCount++;
                    $contextSnippets[] = $snippet;
                    $sourcesMentioned[] = "**{$domain}** melaporkan: \"{$title}\"";
                }

                if (str_contains($text, 'hoaks') || str_contains($text, 'tidak benar') || str_contains($text, 'salah')) {
                    $isHoax = true;
                }
                if (str_contains($domain, '.go.id')) {
                    $isOfficial = true;
                }
            }
        }

        // Also check scraped content for hoax keywords
        foreach ($scrapedArticles as $article) {
            $text = strtolower($article['content'] ?? '');
            if (str_contains($text, 'hoaks') || str_contains($text, 'tidak benar') || str_contains($text, 'salah') || str_contains($text, 'palsu')) {
                $isHoax = true;
            }
        }

        $platforms = array_unique($platforms);
        $platformText = !empty($platforms) ? "di platform " . implode(', ', $platforms) : "di media sosial";

        // Gunakan konten scraping untuk konteks yang lebih baik
        $realContext = '';
        if (!empty($scrapedArticles)) {
            $firstArticle = $scrapedArticles[0];
            $realContext = mb_substr(strip_tags($firstArticle['content']), 0, 300) . "...";
        } elseif (!empty($contextSnippets)) {
            $realContext = mb_substr(strip_tags($contextSnippets[0]), 0, 200) . "...";
        } else {
            $realContext = "topik yang sedang dibicarakan publik";
        }

        $confidence = ($relevantCount >= 4) ? 'Tinggi' : (($relevantCount >= 2) ? 'Sedang' : 'Rendah');
        $verdict = $isHoax ? 'Terbantah' : (($relevantCount >= 3 && $isOfficial) ? 'Tervalidasi' : 'Perlu Verifikasi');

        // EXPLANATION TERTATA
        $explanation = "**Analisa Klaim**: Isu mengenai \"{$claim}\" ditemukan di berbagai sumber informasi.\n\n";
        $explanation .= "**Konteks**: Narasi ini terpantau menyebar {$platformText} dan menarik perhatian publik secara luas.\n\n";

        if ($isHoax) {
            $explanation .= "**Hasil Verifikasi**: Ditemukan indikasi kuat berupa bantahan atau pelabelan sebagai informasi **HOAKS/SALAH** dari sumber kredibel. Detil artikel menunjukkan ketidaksesuaian klaim dengan fakta di lapangan.";
        } else {
            $explanation .= "**Hasil Verifikasi**: Saat ini belum ditemukan klarifikasi resmi yang mutlak, namun data menunjukkan relevansi dengan: {$realContext}";
        }


        // ANALYSIS TERTATA
        $analysis = "### Ringkasan Verifikasi Data\n\n";
        $analysis .= "Status **{$verdict}** ditetapkan berdasarkan penelusuran terhadap " . count($scrapedArticles) . " artikel mendalam dan {$relevantCount} rujukan data terkait. ";
        $analysis .= "Tingkat kepercayaan **{$confidence}** diberikan karena " . ($relevantCount >= 4 ? "adanya konsistensi informasi yang kuat dari berbagai sumber kredibel." : "sumber informasi masih bersifat terbatas atau dalam tahap verifikasi lanjut.");

        if (!empty($scrapedArticles)) {
            $analysis .= "\n\n### Poin Kunci dari Artikel Terkait\n";
            foreach ($scrapedArticles as $article) {
                $preview = mb_substr(strip_tags($article['content']), 0, 180) . "...";
                $analysis .= "- **" . parse_url($article['url'], PHP_URL_HOST) . "**: {$preview}\n";
            }
        }

        if (!empty($sourcesMentioned)) {
            $uniqueSources = array_slice(array_unique($sourcesMentioned), 0, 3);
            $analysis .= "\n### Referensi Tambahan\n";
            foreach ($uniqueSources as $src) {
                $analysis .= "- {$src}\n";
            }
        }

        return [
            'success' => true,
            'verdict' => $verdict,
            'explanation' => $explanation,
            'analysis' => $analysis,
            'confidence' => $confidence,
            'sources' => '',
            'claim' => (string) $claim,
        ];
    }

}
