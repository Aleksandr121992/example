<?php

namespace App\Http\Controllers;

use App\Documentor\Documentor as D;
use App\Documentor\Endpoint;
use App\Documentor\Group;
use App\Documentor\Param;
use App\Documentor\Text;
use App\Documentor\Verbs;
use App\Parsers\TelegramLinkParser;
use App\Scraper\Models\IgMedia;
use App\Scraper\Models\IgUser;
use App\Scraper\PInstagramScraper;
use App\Scraper\Simple\KirtanTiktokScraper;
use App\Scraper\Simple\PremiumIgScraper;
use App\Scraper\Simple\Instagram39Scraper;
use App\Scraper\Simple\Instagram28Scraper;
use App\Scraper\Simple\InstagramFlashScraper;
use App\Scraper\TiktokScraper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class ScraperController extends Controller
{
    #[Group('scraper')]
    #[Endpoint('scrape/instagram/profile/micro')]
    #[Verbs(D::GET)]
    #[Text('Retrieve data from the Instagram scraper')]
    #[Param('login', true, D::URL)]
    public function microIgProfile(Request $request): array
    {
        $request->validate([
            'login' => 'required',
        ]);

        $scraper = App::make(PInstagramScraper::class);
        $user = IgUser::fromLogin($request->login, $scraper);

        return [
            'user' => $user,
            'scraper' => get_class($scraper),
        ];
    }

    #[Group('scraper')]
    #[Endpoint('scrape/instagram/media/micro')]
    #[Verbs(D::GET)]
    #[Text('Retrieve Instagram media data from the scraper')]
    #[Param('link', true, D::URL)]
    public function microIgMedia(Request $request): array
    {
        $request->validate([
            'link' => 'required',
        ]);

        $scraper = App::make(PInstagramScraper::class);
        $media = IgMedia::fromUrl($request->link, $scraper);

        return [
            'media' => $media,
            'scraper' => get_class($scraper),
        ];
    }

    #[Group('scraper')]
    #[Endpoint('scrape/instagram/feed/micro')]
    #[Verbs(D::GET)]
    #[Text('Retrieve Instagram feed data from the scraper')]
    #[Param('login', true, D::STRING, 'Can be a username or a page link')]
    #[Param('count', true, D::INT)]
    public function microIgFeed(Request $request): array
    {
        $request->validate([
            'login' => 'required',
            'count' => 'required|integer|min:1|max:100',
        ]);

        $scraper = App::make(PInstagramScraper::class);
        $posts = IgMedia::fromLogin($request->login, $request->count, $scraper);

        return [
            'posts' => $posts,
            'urls' => array_map(fn($p) => $p->link, $posts),
            'total' => count($posts),
            'scraper' => get_class($scraper),
        ];
    }

    // --------------- https://rapidapi.com/premium-apis-premium-apis-default/api/instagram85/ --------------
    #[Group('scraper')]
    #[Endpoint('scrape/instagram/profile/rapid85')]
    #[Verbs(D::GET)]
    #[Text('Retrieve Instagram profile data')]
    #[Param('login', true, D::STRING, 'Username', 'putin.life')]
    public function rapid85IgProfile(Request $request): array
    {
        $val = $request->validate(['login' => 'required']);
        return [
            'sdata' => App::make(PremiumIgScraper::class)->profile($val['login']),
        ];
    }

    #[Group('scraper')]
    #[Endpoint('scrape/instagram/media/rapid85')]
    #[Verbs(D::GET)]
    #[Text('Retrieve Instagram media (photo/video) data')]
    #[Param('url', true, D::URL, 'Photo link', 'https://www.instagram.com/p/CXJR5eOMXFV/')]
    public function rapid85IgMedia(Request $request): array
    {
        $val = $request->validate(['url' => 'required|url']);
        return [
            'sdata' => App::make(PremiumIgScraper::class)->media($val['url']),
        ];
    }

    #[Group('scraper')]
    #[Endpoint('scrape/instagram/feed/rapid85')]
    #[Verbs(D::GET)]
    #[Text('Retrieve Instagram user feed')]
    #[Param('login', true, D::STRING, 'Username')]
    #[Param('posts', false, D::INT, 'Number of posts, defaults to 12 if not set')]
    public function rapid85IgFeed(Request $request): array
    {
        $val = $request->validate([
            'login' => 'required',
            'posts' => 'integer|min:1|max:100',
        ]);
        $data = App::make(PremiumIgScraper::class)->feed($val['login'], $val['posts'] ?? 12);
        return [
            'sdata' => $data,
            'count' => count($data),
        ];
    }

    // --------------- https://rapidapi.com/socialminer/api/instagram39/ --------------
    #[Group('scraper')]
    #[Endpoint('scrape/instagram/profile/rapid39')]
    #[Verbs(D::GET)]
    #[Text('Retrieve Instagram profile data')]
    #[Param('login', true, D::STRING, 'Username', 'putin.life')]
    public function rapid39IgProfile(Request $request): array
    {
        $val = $request->validate(['login' => 'required']);
        return [
            'sdata' => App::make(Instagram39Scraper::class)->profile($val['login']),
        ];
    }

    #[Group('scraper')]
    #[Endpoint('scrape/instagram/media/rapid39')]
    #[Verbs(D::GET)]
    #[Text('Retrieve Instagram media (photo/video) data')]
    #[Param('code', true, D::STRING, 'Post hash code', 'CXJR5eOMXFV')]
    public function rapid39IgMedia(Request $request): array
    {
        $val = $request->validate(['code' => 'required']);

        return [
            'sdata' => App::make(Instagram39Scraper::class)->media($val['code']),
        ];
    }

    #[Group('scraper')]
    #[Endpoint('scrape/instagram/feed/rapid39')]
    #[Verbs(D::GET)]
    #[Text('Retrieve Instagram user feed')]
    #[Param('login', true, D::STRING, 'Username')]
    #[Param('posts', false, D::INT, 'Number of posts, defaults to 12 if not set')]
    public function rapid39IgFeed(Request $request): array
    {
        $val = $request->validate([
            'login' => 'required',
            'posts' => 'integer|min:1|max:100',
        ]);
        $data = App::make(Instagram39Scraper::class)->feed($val['login'], $val['posts'] ?? 12);
        return [
            'sdata' => $data,
            'count' => count($data),
        ];
    }

    // --------------- https://rapidapi.com/yuananf/api/instagram28/ --------------
    #[Group('scraper')]
    #[Endpoint('scrape/instagram/profile/rapid28')]
    #[Verbs(D::GET)]
    #[Text('Retrieve Instagram profile data')]
    #[Param('login', true, D::STRING, 'Username', 'putin.life')]
    public function rapid28IgProfile(Request $request): array
    {
        $val = $request->validate(['login' => 'required']);
        return [
            'sdata' => App::make(Instagram28Scraper::class)->profile($val['login']),
        ];
    }

    #[Group('scraper')]
    #[Endpoint('scrape/instagram/media/rapid28')]
    #[Verbs(D::GET)]
    #[Text('Retrieve Instagram media (photo/video) data')]
    #[Param('code', true, D::STRING, 'Post hash code', 'CXJR5eOMXFV')]
    public function rapid28IgMedia(Request $request): array
    {
        $val = $request->validate(['code' => 'required']);

        return [
            'sdata' => App::make(Instagram28Scraper::class)->media($val['code']),
        ];
    }

    #[Group('scraper')]
    #[Endpoint('scrape/instagram/feed/rapid28')]
    #[Verbs(D::GET)]
    #[Text('Retrieve Instagram user feed')]
    #[Param('login', true, D::STRING, 'Username')]
    #[Param('posts', false, D::INT, 'Number of posts, defaults to 12 if not set')]
    public function rapid28IgFeed(Request $request): array
    {
        $val = $request->validate([
            'login' => 'required',
            'posts' => 'integer|min:1|max:100',
        ]);
        $data = App::make(Instagram28Scraper::class)->feed($val['login'], $val['posts'] ?? 12);
        return [
            'sdata' => $data,
            'count' => count($data),
        ];
    }


    // --------------- https://rapidapi.com/api/instagram-flash1api --------------
    #[Group('scraper')]
    #[Endpoint('scrape/instagram/feed/flash')]
    #[Verbs(D::GET)]
    #[Text('Retrieve Instagram user feed from Flash Scraper')]
    #[Param('login', true, D::STRING, 'Username')]
    #[Param('posts', false, D::INT, 'Number of posts, defaults to 12 if not set')]
    public function rapidFlashIgFeed(Request $request): array
    {
        $val = $request->validate([
            'login' => 'required',
            'posts' => 'integer|min:1|max:100',
        ]);
        $data = App::make(InstagramFlashScraper::class)->feed($val['login'], $val['posts'] ?? 12);
        return [
            'sdata' => $data,
            'count' => count($data),
        ];
    }

    #[Group('scraper')]
    #[Endpoint('scrape/instagram/profile/flash')]
    #[Verbs(D::GET)]
    #[Text('Retrieve Instagram profile data from Flash Scraper')]
    #[Param('login', true, D::STRING, 'Username')]
    public function rapidFlashIgProfile(Request $request): array
    {
        $val = $request->validate(['login' => 'required']);
        return [
            'sdata' => App::make(InstagramFlashScraper::class)->profile($val['login']),
        ];
    }

    #[Group('scraper')]
    #[Endpoint('scrape/instagram/media/flash')]
    #[Verbs(D::GET)]
    #[Text('Retrieve Instagram media (photo/video) data from Flash Scraper')]
    #[Param('code', true, D::STRING, 'Post hash code')]
    public function rapidFlashIgMedia(Request $request): array
    {
        $val = $request->validate(['code' => 'required']);

        return [
            'sdata' => App::make(InstagramFlashScraper::class)->media($val['code']),
        ];
    }
  
    // --------------- https://rapidapi.com/api/tiktok-kirtan --------------
    #[Group('scraper')]
    #[Endpoint('scrape/tiktok/profile/kirtan')]
    #[Verbs(D::GET)]
    #[Text('Retrieve TikTok profile data from Kirtan Scraper')]
    #[Param('link', true, D::URL)]
    public function kirtanTtProfile(Request $request): array
    {
        $scraper = App::make(KirtanTiktokScraper::class);
        $data = $scraper->profile($request->user);
        return [
            'sdata' => $data,
        ];
    }

    #[Group('scraper')]
    #[Endpoint('scrape/tiktok/video/kirtan')]
    #[Verbs(D::GET)]
    #[Text('Retrieve TikTok video data from Kirtan Scraper')]
    #[Param('url', true, D::URL)]
    public function kirtanTtVideo(Request $request): array
    {
        $request->validate(['url' => 'required']);

        $scraper = App::make(KirtanTiktokScraper::class);
        $data = $scraper->video($request->url);
        return [
            'sdata' => $data,
        ];
    }

    #[Group('scraper')]
    #[Endpoint('scrape/tiktok/feed/kirtan')]
    #[Verbs(D::GET)]
    #[Text('Retrieve TikTok user feed from Kirtan Scraper')]
    #[Param('user', true, D::STRING)]
    public function kirtanTtFeed(Request $request): array
    {
        $scraper = App::make(KirtanTiktokScraper::class);
        $data = $scraper->feed($request->user);
        return [
            'sdata' => $data,
        ];
    }
}
