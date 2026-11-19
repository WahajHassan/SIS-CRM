<?php

namespace App\Http\Controllers;

use App\Models\SalesPerformer;
use App\Models\Deal;
use App\Models\ScoreboardStat;
use App\Models\NewsHeadline;
use App\Models\BusinessCategory;

use Illuminate\Http\Request;

class SalesController extends Controller
{
    /**
     * The public scoreboard.
     *
     * Reached two ways: /{company_slug} for a named company, and / for the site
     * root, which shows whichever company comes first. The slug is optional so
     * that one action serves both, instead of the root route being a closure —
     * closures cannot be represented in a cached route table, and when
     * `route:cache` runs in production the root request ends up dispatched here
     * with no argument at all, which used to be a 500.
     */
    public function index($company_slug = null)
    {
        $company = $company_slug
            ? \App\Models\Company::where('slug', $company_slug)->firstOrFail()
            : \App\Models\Company::first();

        if (! $company) {
            return redirect()->route('login');
        }

        if (!$company->is_active) {
            return response()->view('disabled', ['message' => $company->disable_message]);
        }

        \App\Services\TenantManager::setTenant($company);

        $stats_row = ScoreboardStat::first();
        $all_deals = Deal::all();
        $recent_deals = Deal::latest()->take(5)->get();
        
        $names_map = SalesPerformer::pluck('sudo_name', 'name')->map(function($sudo, $real) {
            return $sudo ?: $real;
        })->toArray();

        // PENDING is a new agent/closer awaiting admin approval (see
        // AdminController@storeUser) — kept off the public board the same way DRAFT
        // and INACTIVE already are, until the admin approves them.
        $performers = SalesPerformer::whereNotIn('status', ['DRAFT', 'PENDING'])->orWhereNull('status')->get()->map(function($p) use ($all_deals, $names_map) {
            $p_deals = $all_deals->filter(function($deal) use ($p) {
                return $deal->closed_by === $p->name || $deal->closer_by === $p->name;
            });
            $p_won = $p_deals->where('status', 'WON')->count();
            $p_total = $p_deals->whereIn('status', ['WON', 'LOST'])->count();
            
            $p->revenue = $p_deals->where('status', 'WON')->sum('value');
            $p->conversion_rate = $p_total > 0 ? round(($p_won / $p_total) * 100, 1) : 0;

            if ($p->is_closer) {
                $closer_won_deals = $all_deals->where('closer_by', $p->name)->where('status', 'WON');
                $p->closed_deals_details = $closer_won_deals->map(function($deal) use ($names_map) {
                    $agent_display_name = $names_map[$deal->closed_by] ?? $deal->closed_by;
                    return [
                        'agent_name' => $agent_display_name,
                        'value' => $deal->value,
                        'client' => $deal->name
                    ];
                })->values()->toArray();
            } else {
                $p->closed_deals_details = [];
            }

            return $p;
        })->filter(function($p) {
            if ($p->status === 'INACTIVE') {
                return false;
            }
            return true;
        })->sortByDesc('revenue');

        // News Headlines
        $news_headlines = NewsHeadline::where('is_active', true)->pluck('headline')->toArray();

        // Active Closers (only performers flagged as closer from admin)
        $active_closers = $performers->filter(fn($p) => $p->is_closer)->values();

        // Dynamic Calculations
        $won_deals = $all_deals->where('status', 'WON');
        $lost_deals = $all_deals->where('status', 'LOST');
        $pending_deals = $all_deals->where('status', 'PENDING');

        $total_revenue = $won_deals->sum('value');
        $deals_closed = $won_deals->count();
        $avg_deal_size = $deals_closed > 0 ? $total_revenue / $deals_closed : 0;
        
        $won_count = $won_deals->count();
        $lost_count = $lost_deals->count();
        $total_attempts = $won_count + $lost_count;
        $conversion_rate = $total_attempts > 0 ? ($won_count / $total_attempts) * 100 : 0;

        // Trend Calculations (Current Month vs Last Month)
        $thisMonth = now()->month;
        $lastMonth = now()->subMonth()->month;
        $thisYear = now()->year;
        $lastMonthYear = now()->subMonth()->year;

        $thisMonthRev = Deal::where('status', 'WON')->whereYear('created_at', $thisYear)->whereMonth('created_at', $thisMonth)->sum('value');
        $lastMonthRev = Deal::where('status', 'WON')->whereYear('created_at', $lastMonthYear)->whereMonth('created_at', $lastMonth)->sum('value');
        
        $thisMonthDeals = Deal::where('status', 'WON')->whereYear('created_at', $thisYear)->whereMonth('created_at', $thisMonth)->count();
        $lastMonthDeals = Deal::where('status', 'WON')->whereYear('created_at', $lastMonthYear)->whereMonth('created_at', $lastMonth)->count();

        $thisMonthAvg = $thisMonthDeals > 0 ? $thisMonthRev / $thisMonthDeals : 0;
        $lastMonthAvg = $lastMonthDeals > 0 ? $lastMonthRev / $lastMonthDeals : 0;

        $rev_trend_val = $lastMonthRev > 0 ? (($thisMonthRev - $lastMonthRev) / $lastMonthRev) * 100 : ($thisMonthRev > 0 ? 100 : 0);
        $deals_trend_val = $lastMonthDeals > 0 ? (($thisMonthDeals - $lastMonthDeals) / $lastMonthDeals) * 100 : ($thisMonthDeals > 0 ? 100 : 0);
        $avg_trend_val = $lastMonthAvg > 0 ? (($thisMonthAvg - $lastMonthAvg) / $lastMonthAvg) * 100 : ($thisMonthAvg > 0 ? 100 : 0);

        $thisMonthLeads = \App\Models\Lead::whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])->count();
        $lastMonthLeads = \App\Models\Lead::whereBetween('created_at', [now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth()])->count();
        $leads_trend_val = $lastMonthLeads > 0 ? (($thisMonthLeads - $lastMonthLeads) / $lastMonthLeads) * 100 : ($thisMonthLeads > 0 ? 100 : 0);

        $stats = [
            'monthly_target' => $stats_row->monthly_target ?? 250000,
            'total_revenue' => $total_revenue,
            'deals_closed' => $deals_closed,
            'avg_deal_size' => $avg_deal_size,
            'active_leads' => \App\Models\Lead::where('status', 'ACTIVE')->count(),
            'revenue_trend' => ($rev_trend_val >= 0 ? '+' : '') . round($rev_trend_val, 1) . '%',
            'deals_trend' => ($deals_trend_val >= 0 ? '+' : '') . round($deals_trend_val, 1) . '%',
            'avg_deal_trend' => ($avg_trend_val >= 0 ? '+' : '') . round($avg_trend_val, 1) . '%',
            'leads_trend' => ($leads_trend_val >= 0 ? '+' : '') . round($leads_trend_val, 1) . '%',
            'achieved_prospects' => $stats_row->achieved_prospects ?? 0,
            'live_update' => $stats_row->live_update ?? '',
            'active_agents' => \App\Models\SalesPerformer::where('is_closer', false)->where('role', '!=', 'Closer')->where(function($q) { $q->whereNotIn('status', ['DRAFT', 'INACTIVE'])->orWhereNull('status'); })->count(),
            'won_count' => $won_count,
            'lost_count' => $lost_count,
            'pipeline_probability' => $pending_deals->sum('value') * 0.5,
            'pipeline_proposal' => $pending_deals->sum('value'),
            'conversion_rate' => round($conversion_rate, 1),
        ];

        // Monthly Growth Data. Grouped in PHP from the deals already loaded above:
        // MONTH() is MySQL only and would break on any other driver.
        $monthly_data = $won_deals
            ->groupBy(fn($deal) => $deal->created_at?->month)
            ->map(fn($deals) => $deals->sum('value'))
            ->toArray();

        $growth_data = [];
        for($i=1; $i<=12; $i++) {
            $growth_data[] = $monthly_data[$i] ?? 0;
        }

        // Live news. Cached so the board's auto-refresh does not re-fetch every feed
        // on every page load, which is what made the page slow to appear.
        $live_news = \Illuminate\Support\Facades\Cache::remember('scoreboard_live_news', now()->addMinutes(10), function () {
            $feeds = [
                ['url' => 'https://feeds.bbci.co.uk/news/world/rss.xml', 'source' => 'BBC'],
                ['url' => 'https://rss.nytimes.com/services/xml/rss/nyt/World.xml', 'source' => 'NYT'],
                ['url' => 'https://moxie.foxnews.com/google-publisher/world.xml', 'source' => 'FOX'],
                ['url' => 'https://www.theguardian.com/world/rss', 'source' => 'GDN'],
            ];

            $news = [];
            foreach ($feeds as $feed) {
                $ctx = stream_context_create(['http' => ['timeout' => 5, 'user_agent' => 'Mozilla/5.0']]);
                $xml = @file_get_contents($feed['url'], false, $ctx);
                if (!$xml) {
                    continue;
                }

                $rss = @simplexml_load_string($xml);
                if (!$rss || !isset($rss->channel->item)) {
                    continue;
                }

                $count = 0;
                foreach ($rss->channel->item as $item) {
                    if ($count >= 8) break;

                    try {
                        $published = \Carbon\Carbon::parse((string) $item->pubDate);
                    } catch (\Exception $e) {
                        continue;
                    }

                    // Skip stale items so a frozen feed cannot fill the ticker with old news
                    if ($published->lt(now()->subWeek())) {
                        continue;
                    }

                    $news[] = [
                        'title' => (string) $item->title,
                        'source' => $feed['source'],
                        'time' => $published->diffForHumans(),
                        'link' => (string) $item->link,
                    ];
                    $count++;
                }
            }

            shuffle($news);
            return array_slice($news, 0, 18);
        });

        // Real weather for the four office cities. Open-Meteo is free and needs no API key.
        $weather = \Illuminate\Support\Facades\Cache::remember('scoreboard_weather', now()->addMinutes(15), function () {
            $cities = [
                'ny'  => [40.7128, -74.0060],
                'chi' => [41.8781, -87.6298],
                'den' => [39.7392, -104.9903],
                'la'  => [34.0522, -118.2437],
            ];

            // WMO weather codes returned by Open-Meteo
            $codes = [
                0  => ['☀️', 'Clear'],          1  => ['🌤️', 'Mostly Clear'],
                2  => ['⛅', 'Partly Cloudy'],   3  => ['🌥️', 'Overcast'],
                45 => ['🌫️', 'Fog'],            48 => ['🌫️', 'Fog'],
                51 => ['🌦️', 'Light Drizzle'],  53 => ['🌦️', 'Drizzle'],      55 => ['🌦️', 'Drizzle'],
                61 => ['🌧️', 'Light Rain'],     63 => ['🌧️', 'Rain'],         65 => ['🌧️', 'Heavy Rain'],
                71 => ['🌨️', 'Light Snow'],     73 => ['🌨️', 'Snow'],         75 => ['❄️', 'Heavy Snow'],
                80 => ['🌦️', 'Showers'],        81 => ['🌦️', 'Showers'],      82 => ['⛈️', 'Heavy Showers'],
                95 => ['⛈️', 'Thunderstorm'],   96 => ['⛈️', 'Thunderstorm'], 99 => ['⛈️', 'Thunderstorm'],
            ];

            $out = [];
            foreach ($cities as $key => [$lat, $lon]) {
                $out[$key] = ['emoji' => '🌡️', 'temp' => null, 'cond' => 'Unavailable'];

                $url = "https://api.open-meteo.com/v1/forecast?latitude={$lat}&longitude={$lon}"
                     . "&current=temperature_2m,weather_code&temperature_unit=fahrenheit";
                $ctx = stream_context_create(['http' => ['timeout' => 5, 'user_agent' => 'Mozilla/5.0']]);
                $raw = @file_get_contents($url, false, $ctx);
                if (!$raw) {
                    continue;
                }

                $data = json_decode($raw, true);
                if (!isset($data['current']['temperature_2m'])) {
                    continue;
                }

                $match = $codes[(int) ($data['current']['weather_code'] ?? 0)] ?? ['🌡️', 'Unknown'];
                $out[$key] = [
                    'emoji' => $match[0],
                    'temp' => round($data['current']['temperature_2m']),
                    'cond' => $match[1],
                ];
            }

            return $out;
        });

        $business_categories = BusinessCategory::where('is_active', true)->pluck('name')->toArray();
        $usa_states = \App\Models\UsaState::where('is_active', true)->pluck('name')->toArray();

        return view('scoreboard', compact('performers', 'stats', 'recent_deals', 'stats_row', 'growth_data', 'news_headlines', 'active_closers', 'live_news', 'weather', 'business_categories', 'usa_states', 'company'));
    }

    private function seedDefaults()
    {
        SalesPerformer::create(['name' => 'Alex Johnson', 'role' => 'Senior Account Executive', 'revenue' => 45200, 'goal' => 50000, 'conversion_rate' => 24, 'avatar_initials' => 'AJ']);
        SalesPerformer::create(['name' => 'Sarah Miller', 'role' => 'Sales Representative', 'revenue' => 38150, 'goal' => 45000, 'conversion_rate' => 19, 'avatar_initials' => 'SM']);
        SalesPerformer::create(['name' => 'David Wilson', 'role' => 'Sales Associate', 'revenue' => 29400, 'goal' => 38000, 'conversion_rate' => 15, 'avatar_initials' => 'DW']);
    }
}
