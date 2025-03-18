<?php

declare(strict_types=1);

namespace App\Scraper\Simple;

use App\Exceptions\Reportable\ProfileNotFoundException;
use App\Exceptions\Reportable\ScraperException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Psr\SimpleCache\InvalidArgumentException;

class InstagramFlashScraper implements ISimpleIgScraper
{
    use ScraperErrorTrait;

    private const TIMEOUT = 20;

    private string $key;
    private string $host;
    private int $ttl;
    private string $errorCacheKeyPrefix;
    private int $errorCacheTtl;

    public function __construct()
    {
        $this->key = config('scrapers.instagram.flash.key');
        $this->host = config('scrapers.instagram.flash.host');
        $this->ttl = config('scrapers.instagram.flash.ttl');
        $this->errorCacheKeyPrefix = config('scrapers.instagram.errorCacheKeyPrefix');
        $this->errorCacheTtl = config('scrapers.instagram.errorCacheTtl');
    }

    private function headers(): array
    {
        return [
            'x-rapidapi-host' => $this->host,
            'x-rapidapi-key' => $this->key,
        ];
    }

    /**
     * Wrapper for caching HTTP requests.
     * 
     * @throws ScraperException
     * @throws ProfileNotFoundException
     * @throws InvalidArgumentException
     */
    private function httpCachedWrapper(string $key, string $url, array $params, ?string $requiredField = null): array
    {
        $errorCacheKey = $this->errorCacheKeyPrefix . __CLASS__ . $key;

        if ($cachedData = Cache::get($key)) {
            return $cachedData;
        }

        if ($errorMessage = Cache::get($errorCacheKey)) {
            throw new ProfileNotFoundException($errorMessage, previous: null, fromCache: true);
        }

        try {
            $response = Http::retry(2, 500)
                ->timeout(self::TIMEOUT)
                ->withHeaders($this->headers())
                ->withOptions(['stream' => true])
                ->get($url, $params)
                ->throw();

            $data = json_decode($response->body(), true);

            if ($data === null || ($requiredField !== null && !data_get($data, $requiredField))) {
                Log::warning("Required field missing for endpoint: $key");
                throw new RequestException($response);
            }

            if (Arr::get($data, 'data.user.is_private')) {
                return $data;
            }

            Cache::put($key, $data, $this->ttl);
        } catch (\Throwable $e) {
            $this->handleHttpException($e, $url, $params);
        }

        return $data ?? [];
    }

    /**
     * Handles HTTP exceptions and logs errors.
     */
    private function handleHttpException(\Throwable $e, string $url, array $params): void
    {
        $msg = 'Bad response from InstagramFlashScraper';
        $statusCode = $e->getCode();
        $response = property_exists($e, 'response') ? $e->response : null;

        $limits = $this->extractRateLimitInfo($response);
        $responseToLog = $response ? $response->body() : describe_exception($e);

        if (data_get($response, 'message') === 'Page not found') {
            $msg = 'Instagram profile or media not found';
        } else {
            $this->saveScraperError($responseToLog, $statusCode, $url, json_encode($params), $limits);
        }

        $logData = "$url, " . json_encode($params) . " status code: $statusCode response: $responseToLog limits: $limits";
        $this->logScraperError($logData);

        if ($msg === 'Instagram profile or media not found') {
            Cache::set($this->errorCacheKeyPrefix . __CLASS__ . $url, $msg, $this->errorCacheTtl);
            throw new ProfileNotFoundException($msg);
        }

        throw new ScraperException($msg);
    }

    /**
     * Extracts rate limit information from the response headers.
     */
    private function extractRateLimitInfo($response): string
    {
        $headers = make_data_getter($response?->headers());
        $reset = $headers('X-RateLimit-All-Reset.0');
        $left = $headers('X-RateLimit-All-Remaining.0');
        $max = $headers('X-RateLimit-All-Limit.0');

        return ($reset && $left)
            ? "$left of $max until " . now(config('app.timezone'))->addSeconds($reset)->format('Y-m-d\ H:i:sP')
            : 'No limits data';
    }


    /**
     * Parse media details from response data.
     */
    private function parseMedia(array $data): array
    {
        $g = make_data_getter($data);

        return [
            'code' => $g('code'),
            'comments' => $g('comment_count'),
            'id' => $g('pk'),
            'img' => $g('image_versions2.candidates.0.url'),
            'likes' => $g('like_count'),
            'login' => $g('caption.user.username'),
            'owner_id' => $g('caption.user.id'),
            'views' => $g('ig_play_count'),
            'views_real' => null, // No data available
            'type' => $this->getMediaType($g('media_type')),
        ];
    }

    /**
     * Retrieve Instagram media data based on a shortcode.
     */
    public function media(string $code): array
    {
        $res = $this->httpCachedWrapper(
            key: __METHOD__ . $code,
            url: "https://{$this->host}/ig/post_info/",
            params: ['shortcode' => $code],
            requiredField: self::ITEMS
        );

        return $this->parseMedia(make_data_getter($res)(self::ITEMS)[0]);
    }

    /**
     * Retrieve Instagram profile data.
     */
    public function profile(string $login, bool $feed = false): array
    {
        $res = $this->httpCachedWrapper(
            key: __METHOD__ . $login,
            url: "https://{$this->host}/ig/web_profile_info/",
            params: ['user' => $login],
            requiredField: self::DATA_USER
        );

        return $this->parseProfile(make_data_getter($res)(self::DATA_USER), $feed);
    }

    /**
     * Parse profile details from response data.
     */
    private function parseProfile(array $data, bool $feed = false): array
    {
        $g = make_data_getter($data);

        $profileData = [
            'followers' => $g('edge_followed_by.count'),
            'id' => $g('id'),
            'img' => $g('profile_pic_url'),
            'login' => $g('username'),
            'nickname' => $g('full_name'),
            'posts' => $g(sprintf('%s.count', self::FEED_PATH)),
            'private' => $g('is_private'),
        ];

        if ($feed) {
            $profileData['page_info'] = $g(sprintf('%s.page_info', self::FEED_PATH));
            $profileData['posts_data'] = $g(sprintf('%s.edges', self::FEED_PATH));
        }

        return $profileData;
    }

    /**
     * Retrieve user feed with a specific number of posts.
     */
    public function feed(string $login, int $postsRequired = 12): array
    {
        $profile = $this->profile($login, true);
        if (($c = data_get($profile, 'posts', 0)) < $postsRequired) {
            throw new ScraperException("Not enough posts: $c < " . $postsRequired);
        }

        $posts = data_get($profile, 'posts_data', []) ?? [];
        if (count($posts) >= $postsRequired) {
            return array_map(fn ($post) => $this->parseFeed($post, $login), array_slice($posts, 0, $postsRequired));
        }

        $pageInfo = data_get($profile, 'page_info', null);
        $morePosts = $this->fetchFeed($login, $postsRequired - count($posts), $pageInfo);
        return array_map(fn ($post) => $this->parseFeed($post, $login), array_merge($posts, $morePosts));
    }

    /**
     * Fetch additional posts from the feed if necessary.
     */
    private function fetchFeed(string $login, int $postsRequired, ?array $pageInfo): array
    {
        $posts = [];
        $params = ['user' => $login];
        $hasNext = data_get($pageInfo, 'has_next_page', false);
        $endCursor = data_get($pageInfo, 'end_cursor', null);

        do {
            if ($hasNext && $endCursor) {
                $params['end_cursor'] = $endCursor;
            }
            $res = $this->httpCachedWrapper(
                key: __METHOD__ . $login . '_' . ($endCursor ?? 'start'),
                url: "https://{$this->host}/ig/posts_username/",
                params: $params,
                requiredField: self::ITEMS,
                timeout: 25
            );
            
            $g = make_data_getter($res);
            $posts = array_merge($posts, $g(self::ITEMS));
            $hasNext = $g('more_available');
            $endCursor = $g('next_max_id');
        } while ($postsRequired > count($posts) && $hasNext);

        return array_slice($posts, 0, $postsRequired);
    }
}
