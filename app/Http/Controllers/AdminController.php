<?php

namespace App\Http\Controllers;

use App\Models\ScoreboardStat;
use App\Models\SalesPerformer;
use App\Models\Deal;
use App\Models\Lead;
use App\Models\NewsHeadline;
use App\Models\BusinessCategory;
use App\Models\EmployeeSpotlight;
use App\Models\TransferredLead;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    private function getCommonStats()
    {
        $isAgent = auth()->check() && auth()->user()->isAgentOrCloser();
        $agentName = $isAgent && auth()->user()->performer ? auth()->user()->performer->name : null;

        $dealQuery = Deal::query();
        if ($isAgent && $agentName) {
            $dealQuery->where(function($q) use ($agentName) {
                $q->where('closed_by', $agentName)
                  ->orWhere('closer_by', $agentName);
            });
        }

        $all_deals = $dealQuery->get();
        $won_deals = $all_deals->where('status', 'WON');
        $lost_deals = $all_deals->where('status', 'LOST');
        $pending_deals = $all_deals->where('status', 'PENDING');

        // Trend Calculations
        $thisMonth = now()->month;
        $lastMonth = now()->subMonth()->month;
        $thisYear = now()->year;
        $lastMonthYear = now()->subMonth()->year;

        $thisMonthRev = (clone $dealQuery)->where('status', 'WON')->whereYear('created_at', $thisYear)->whereMonth('created_at', $thisMonth)->sum('value');
        $lastMonthRev = (clone $dealQuery)->where('status', 'WON')->whereYear('created_at', $lastMonthYear)->whereMonth('created_at', $lastMonth)->sum('value');

        $thisMonthDeals = (clone $dealQuery)->where('status', 'WON')->whereYear('created_at', $thisYear)->whereMonth('created_at', $thisMonth)->count();
        $lastMonthDeals = (clone $dealQuery)->where('status', 'WON')->whereYear('created_at', $lastMonthYear)->whereMonth('created_at', $lastMonth)->count();

        $thisMonthAvg = $thisMonthDeals > 0 ? $thisMonthRev / $thisMonthDeals : 0;
        $lastMonthAvg = $lastMonthDeals > 0 ? $lastMonthRev / $lastMonthDeals : 0;

        $rev_trend_val = $lastMonthRev > 0 ? (($thisMonthRev - $lastMonthRev) / $lastMonthRev) * 100 : ($thisMonthRev > 0 ? 100 : 0);
        $deals_trend_val = $lastMonthDeals > 0 ? (($thisMonthDeals - $lastMonthDeals) / $lastMonthDeals) * 100 : ($thisMonthDeals > 0 ? 100 : 0);
        $avg_trend_val = $lastMonthAvg > 0 ? (($thisMonthAvg - $lastMonthAvg) / $lastMonthAvg) * 100 : ($thisMonthAvg > 0 ? 100 : 0);

        $all_leads = Lead::all();
        $thisMonthLeads = $all_leads->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])->count();
        $lastMonthLeads = $all_leads->whereBetween('created_at', [now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth()])->count();
        $leads_trend_val = $lastMonthLeads > 0 ? (($thisMonthLeads - $lastMonthLeads) / $lastMonthLeads) * 100 : ($thisMonthLeads > 0 ? 100 : 0);

        $stats_row = ScoreboardStat::firstOrCreate([], ['monthly_target' => 250000]);

        return (object) [
            'monthly_target' => $stats_row->monthly_target,
            'total_revenue' => $won_deals->sum('value'),
            'deals_closed' => $won_deals->count(),
            'avg_deal_size' => $won_deals->count() > 0 ? $won_deals->sum('value') / $won_deals->count() : 0,
            'won_count' => $won_deals->count(),
            'lost_count' => $lost_deals->count(),
            'conversion_rate' => ($won_deals->count() + $lost_deals->count()) > 0 ? ($won_deals->count() / ($won_deals->count() + $lost_deals->count())) * 100 : 0,
            'pipeline_value' => $pending_deals->sum('value'),
            'active_leads' => $all_leads->where('status', 'ACTIVE')->count(),
            'revenue_trend' => ($rev_trend_val >= 0 ? '+' : '') . round($rev_trend_val, 1) . '%',
            'deals_trend' => ($deals_trend_val >= 0 ? '+' : '') . round($deals_trend_val, 1) . '%',
            'avg_deal_trend' => ($avg_trend_val >= 0 ? '+' : '') . round($avg_trend_val, 1) . '%',
            'leads_trend' => ($leads_trend_val >= 0 ? '+' : '') . round($leads_trend_val, 1) . '%',
        ];
    }

    public function dashboard()
    {
        if (auth()->check() && auth()->user()->isSupport()) {
            return redirect()->route('support.dashboard');
        }

        $stats = $this->getCommonStats();
        
        $isAgent = auth()->user()->isAgentOrCloser();
        $agent_name = $isAgent && auth()->user()->performer ? auth()->user()->performer->name : null;

        $all_deals = $isAgent ? Deal::where(function($q) use ($agent_name) {
            $q->where('closed_by', $agent_name)
              ->orWhere('closer_by', $agent_name);
        })->get() : Deal::all();
        
        if ($isAgent) {
            $performers = auth()->user()->performer ? collect([auth()->user()->performer]) : collect([]);
        } else {
            $performers = SalesPerformer::where('status', '!=', 'DRAFT')->orWhereNull('status')->get();
        }
        
        $performers = $performers->map(function ($p) use ($all_deals) {
            $p_deals = $all_deals->filter(function($deal) use ($p) {
                return $deal->closed_by === $p->name || $deal->closer_by === $p->name;
            });
            $p->revenue = $p_deals->where('status', 'WON')->sum('value');
            $p->deals_won = $p_deals->where('status', 'WON')->count();
            return $p;
        })->sortByDesc('revenue');
        
        $deals_query = $isAgent ? Deal::where(function($q) use ($agent_name) {
            $q->where('closed_by', $agent_name)
              ->orWhere('closer_by', $agent_name);
        }) : Deal::query();
        $deals = $deals_query->latest()->take(5)->get();

        // Chart Data: Monthly Revenue (Last 6 Months)
        $monthly_revenue = [];
        $months_labels = [];
        for ($i = 5; $i >= 0; $i--) {
            $month = now()->subMonths($i);
            $rev_query = Deal::where('status', 'WON')
                ->whereYear('created_at', $month->year)
                ->whereMonth('created_at', $month->month);
            
            if ($isAgent) {
                $rev_query->where(function($q) use ($agent_name) {
                    $q->where('closed_by', $agent_name)
                      ->orWhere('closer_by', $agent_name);
                });
            }
            $rev = $rev_query->sum('value');

            $monthly_revenue[] = (float) $rev;
            $months_labels[] = $month->format('M');
        }

        // Conversion Data
        $won_query = Deal::where('status', 'WON');
        $lost_query = Deal::where('status', 'LOST');
        if ($isAgent) {
            $won_query->where(function($q) use ($agent_name) {
                $q->where('closed_by', $agent_name)
                  ->orWhere('closer_by', $agent_name);
            });
            $lost_query->where(function($q) use ($agent_name) {
                $q->where('closed_by', $agent_name)
                  ->orWhere('closer_by', $agent_name);
            });
        }
        $won_count = $won_query->count();
        $lost_count = $lost_query->count();

        $super_stats = null;
        if (auth()->user()->isSuperAdmin()) {
            $super_stats = (object) [
                'total_companies' => \App\Models\Company::count(),
                'active_companies' => \App\Models\Company::where('is_active', true)->count(),
                'total_users' => \App\Models\User::count(),
                'total_leads' => \App\Models\Lead::count(),
                'deals_by_company' => \App\Models\Company::withCount(['deals as won_deals_count' => function ($query) {
                    $query->where('status', 'WON');
                }])->get(),
            ];
        }

        return view('admin.index', compact('stats', 'performers', 'deals', 'monthly_revenue', 'months_labels', 'won_count', 'lost_count', 'super_stats'));
    }

    public function logs()
    {
        abort_if(!auth()->user()->isSuperAdmin(), 403, 'Unauthorized action.');
        $logs = \App\Models\SystemLog::with(['user', 'company'])->latest()->paginate(20);
        return view('admin.logs', compact('logs'));
    }

    public function globalSettings()
    {
        abort_if(!auth()->user()->isSuperAdmin(), 403, 'Unauthorized action.');
        $maintenance = \App\Models\SystemSetting::where('key', 'maintenance_mode')->first();
        $is_maintenance = $maintenance ? ($maintenance->value === '1') : false;
        
        return view('admin.settings.global', compact('is_maintenance'));
    }

    public function updateGlobalSettings(Request $request)
    {
        abort_if(!auth()->user()->isSuperAdmin(), 403, 'Unauthorized action.');
        
        $request->validate([
            'logo' => 'nullable|image|max:2048',
            'dark_logo' => 'nullable|image|max:2048',
            'favicon' => 'nullable|image|max:1024',
            'number_of_breaks' => 'nullable|integer|min:1|max:2',
            'break_1_name' => 'nullable|string|max:50',
            'break_1_start' => 'nullable|date_format:H:i',
            'break_1_end' => 'nullable|date_format:H:i',
            'break_2_name' => 'nullable|string|max:50',
            'break_2_start' => 'nullable|date_format:H:i',
            'break_2_end' => 'nullable|date_format:H:i',
        ]);

        $maintenance = $request->has('maintenance_mode') ? '1' : '0';
        \App\Models\SystemSetting::updateOrCreate(
            ['key' => 'maintenance_mode'],
            ['value' => $maintenance]
        );

        $company = auth()->user()->company;
        $updateData = [];

        if ($request->hasFile('logo')) {
            if ($company->logo && file_exists(public_path($company->logo))) {
                @unlink(public_path($company->logo));
            }
            $filename = time() . '_logo_' . $request->file('logo')->getClientOriginalName();
            $request->file('logo')->move(public_path('uploads/company_logos'), $filename);
            $updateData['logo'] = 'uploads/company_logos/' . $filename;
        }

        if ($request->hasFile('dark_logo')) {
            if (!\Illuminate\Support\Facades\Schema::hasColumn('companies', 'dark_logo')) {
                \Illuminate\Support\Facades\Schema::table('companies', function($table) {
                    $table->string('dark_logo')->nullable();
                });
            }
            if ($company->dark_logo && file_exists(public_path($company->dark_logo))) {
                @unlink(public_path($company->dark_logo));
            }
            $filename = time() . '_dark_logo_' . $request->file('dark_logo')->getClientOriginalName();
            $request->file('dark_logo')->move(public_path('uploads/company_logos'), $filename);
            $updateData['dark_logo'] = 'uploads/company_logos/' . $filename;
        }

        if ($request->hasFile('favicon')) {
            if ($company->favicon && file_exists(public_path($company->favicon))) {
                @unlink(public_path($company->favicon));
            }
            $filename = time() . '_favicon_' . $request->file('favicon')->getClientOriginalName();
            $request->file('favicon')->move(public_path('uploads/company_logos'), $filename);
            $updateData['favicon'] = 'uploads/company_logos/' . $filename;
        }
        
        if (!\Illuminate\Support\Facades\Schema::hasColumn('companies', 'number_of_breaks')) {
            \Illuminate\Support\Facades\Schema::table('companies', function($table) {
                $table->integer('number_of_breaks')->default(2);
                $table->string('break_1_name')->default('1st Half Break');
                $table->string('break_2_name')->default('2nd Half Break');
            });
        }
        
        if (!\Illuminate\Support\Facades\Schema::hasColumn('companies', 'break_1_start')) {
            \Illuminate\Support\Facades\Schema::table('companies', function($table) {
                $table->time('break_1_start')->nullable();
                $table->time('break_1_end')->nullable();
                $table->time('break_2_start')->nullable();
                $table->time('break_2_end')->nullable();
            });
        }
        
        if ($request->has('number_of_breaks')) {
            $updateData['number_of_breaks'] = $request->number_of_breaks;
            $updateData['break_1_name'] = $request->break_1_name ?: '1st Half Break';
            $updateData['break_1_start'] = $request->break_1_start;
            $updateData['break_1_end'] = $request->break_1_end;
            $updateData['break_2_name'] = $request->break_2_name ?: '2nd Half Break';
            $updateData['break_2_start'] = $request->break_2_start;
            $updateData['break_2_end'] = $request->break_2_end;
        }

        if (!empty($updateData)) {
            $company->update($updateData);
        }

        \App\Models\SystemLog::log('System Settings', "Maintenance mode set to " . ($maintenance === '1' ? 'ON' : 'OFF') . " and Super Admin branding updated.");

        return redirect()->back()->with('success', 'Global Settings & Branding updated successfully!');
    }

    public function team()
    {
        $this->authorizeSection();
        $search = request('search');
        
        $query = SalesPerformer::query();
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('sudo_name', 'like', "%{$search}%");
            });
        }
        
        $all_deals = Deal::all();
        $performers = $query->get()->map(function ($p) use ($all_deals) {
            $p_deals = $all_deals->filter(function($deal) use ($p) {
                return $deal->closed_by === $p->name || $deal->closer_by === $p->name;
            });
            $p_won = $p_deals->where('status', 'WON')->count();
            $p_total = $p_deals->whereIn('status', ['WON', 'LOST'])->count();
            $p->revenue = $p_deals->where('status', 'WON')->sum('value');
            $p->conversion_rate = $p_total > 0 ? round(($p_won / $p_total) * 100, 1) : 0;
            return $p;
        })->sortByDesc('revenue');

        $stats_row = ScoreboardStat::firstOrCreate([], ['monthly_target' => 250000]);
        return view('admin.team', compact('performers', 'stats_row'));
    }

    public function deals()
    {
        $search = request('search');
        $dealQuery = Deal::query();
        
        if ($search) {
            // Find performers whose real name or sudo name matches the search term
            $matchedPerformerNames = SalesPerformer::where('name', 'like', "%{$search}%")
                ->orWhere('sudo_name', 'like', "%{$search}%")
                ->pluck('name')
                ->toArray();

            $dealQuery->where(function($q) use ($search, $matchedPerformerNames) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('closed_by', 'like', "%{$search}%")
                  ->orWhere('closer_by', 'like', "%{$search}%")
                  ->orWhere('business_owner_name', 'like', "%{$search}%")
                  ->orWhere('business_number', 'like', "%{$search}%")
                  ->orWhere('business_email', 'like', "%{$search}%")
                  ->orWhere('services_offered', 'like', "%{$search}%");
                
                if (!empty($matchedPerformerNames)) {
                    $q->orWhereIn('closed_by', $matchedPerformerNames)
                      ->orWhereIn('closer_by', $matchedPerformerNames);
                }
            });
        }
        
        if (auth()->user()->isAgentOrCloser()) {
            $agent_name = auth()->user()->performer ? auth()->user()->performer->name : null;
            $userId = auth()->id();

            // A deal reaches the people who made it. It used to be matched on the
            // performer's name alone, so somebody with no performer linked — or
            // whose name was typed differently — could close a deal and then not
            // find it. The ids written when a transfer is converted settle it;
            // the names stay for everything created before that existed.
            $deals = $dealQuery->where(function ($q) use ($agent_name, $userId) {
                $q->where('agent_id', $userId)
                  ->orWhere('closer_user_id', $userId);

                if ($agent_name) {
                    $q->orWhere('closed_by', $agent_name)
                      ->orWhere('closer_by', $agent_name);
                }
            })->latest()->paginate(15);
            $performers = SalesPerformer::where('status', '!=', 'DRAFT')->orWhereNull('status')->get();
        } else {
            $deals = $dealQuery->latest()->paginate(15);
            $performers = SalesPerformer::where('status', '!=', 'DRAFT')->orWhereNull('status')->get();
        }
        
        if ($search) {
            $deals->appends(['search' => $search]);
        }
        
        return view('admin.deals', compact('deals', 'performers'));
    }

    public function leads(Request $request)
    {
        $this->authorizeSection();

        $query = Lead::with(['category', 'state', 'city', 'creator'])->latest();

        // The floor works a patch at a time: this trade, this state, this city.
        foreach (['business_category_id', 'usa_state_id', 'city_id', 'status'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->input($filter));
            }
        }

        if ($request->filled('search')) {
            $search = $request->input('search');

            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('business_name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('address', 'like', "%{$search}%");
            });
        }

        $leads = $query->paginate(15)->withQueryString();

        $categories = \App\Models\BusinessCategory::active()->orderBy('name')->get();
        $states = \App\Models\UsaState::active()->orderBy('name')->get();

        // Only the cities of whichever state is being filtered by; the rest are
        // fetched as the state is chosen.
        $cities = $request->filled('usa_state_id')
            ? \App\Models\City::active()->ofState($request->input('usa_state_id'))->get()
            : collect();

        return view('admin.leads', compact('leads', 'categories', 'states', 'cities'));
    }

    /** The cities of one state, for the dropdown that follows the state. */
    public function citiesOfState($stateId)
    {
        $cities = \App\Models\City::active()->ofState($stateId)->get(['id', 'name']);

        return response()->json(['cities' => $cities]);
    }

    public function history(Request $request)
    {
        $this->authorizeSection();
        $selected_month = $request->input('month', now()->format('Y-m'));
        [$year, $month] = explode('-', $selected_month);
        $search = $request->input('search');
        
        $all_deals = Deal::whereYear('created_at', $year)->whereMonth('created_at', $month)->get();
        
        $query = SalesPerformer::query();
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('sudo_name', 'like', "%{$search}%");
            });
        }
        
        $performers = $query->get()->map(function ($p) use ($all_deals, $month, $year) {
            $p_deals = $all_deals->filter(function($deal) use ($p) {
                return $deal->closed_by === $p->name || $deal->closer_by === $p->name;
            });
            $p_won = $p_deals->where('status', 'WON')->count();
            $p_total = $p_deals->whereIn('status', ['WON', 'LOST'])->count();
            $p->revenue = $p_deals->where('status', 'WON')->sum('value');
            $p->conversion_rate = $p_total > 0 ? round(($p_won / $p_total) * 100, 1) : 0;
            
            $stat = $p->monthlyStats()->where('month', $month)->where('year', $year)->first();
            $p->hist_goal = $stat ? $stat->goal : 0;
            $p->hist_transferred = $stat ? $stat->number_of_transferred : 0;
            $manual_sales = $stat ? $stat->number_of_sales : 0;
            $p->hist_sales = max($p_won, $manual_sales);
            
            // Transfer Leads KPI (Red Mark Logic)
            $userIds = \App\Models\User::where('sales_performer_id', $p->id)->pluck('id');
            $p_transfers = \App\Models\TransferredLead::whereIn('closer_id', $userIds)
                             ->whereMonth('scheduled_at', $month)
                             ->whereYear('scheduled_at', $year)
                             ->get();
            $p->total_transfers_count = $p_transfers->count();
            $p->converted_transfers_count = $p_transfers->where('is_converted', true)->count();
            $p->needs_red_mark = ($p->total_transfers_count >= 10 && $p->converted_transfers_count < 2);
            
            return $p;
        })->sortByDesc('revenue');
        
        return view('admin.history', compact('performers', 'selected_month'));
    }

    public function agentHistory(Request $request)
    {
        $this->authorizeSection();
        $performers = \App\Models\SalesPerformer::where('status', '!=', 'DRAFT')->orWhereNull('status')->get();
        $selected_agent_id = $request->input('agent_id', $performers->first() ? $performers->first()->id : null);
        $selected_year = $request->input('year', now()->year);
        
        $monthsData = [];
        $selected_agent = null;
 
        if ($selected_agent_id) {
            $selected_agent = \App\Models\SalesPerformer::find($selected_agent_id);
            if ($selected_agent) {
                $all_deals = \App\Models\Deal::where(function($q) use ($selected_agent) {
                        $q->where('closed_by', $selected_agent->name)
                          ->orWhere('closer_by', $selected_agent->name);
                    })
                    ->whereYear('created_at', $selected_year)
                    ->get();
                    
                $monthly_stats = $selected_agent->monthlyStats()
                    ->where('year', $selected_year)
                    ->get()
                    ->keyBy('month');
                    
                for ($m = 1; $m <= 12; $m++) {
                    $month_deals = $all_deals->filter(function($d) use ($m) { 
                        return $d->created_at->month == $m; 
                    });
                    
                    $m_won = $month_deals->where('status', 'WON')->count();
                    $m_total = $month_deals->whereIn('status', ['WON', 'LOST'])->count();
                    $revenue = $month_deals->where('status', 'WON')->sum('value');
                    $conversion_rate = $m_total > 0 ? round(($m_won / $m_total) * 100, 1) : 0;
                    
                    $stat = $monthly_stats->get($m);
                    $goal = $stat ? $stat->goal : 0;
                    $transferred = $stat ? $stat->number_of_transferred : 0;
                    $manual_sales = $stat ? $stat->number_of_sales : 0;
                    $sales = max($m_won, $manual_sales);
                    
                    $monthsData[] = (object)[
                        'month_num' => $m,
                        'month_name' => \Carbon\Carbon::create()->month($m)->format('F'),
                        'goal' => $goal,
                        'transferred' => $transferred,
                        'sales' => $sales,
                        'revenue' => $revenue,
                        'conversion_rate' => $conversion_rate,
                    ];
                }
            }
        }
        
        return view('admin.history_agent', compact('performers', 'selected_agent', 'selected_year', 'monthsData'));
    }

    // CRUD Methods
    public function updateStats(Request $request)
    {
        $this->authorizeSection();
        $stats = ScoreboardStat::first();
        if (!$stats) {
            $stats = ScoreboardStat::create($request->all());
        } else {
            $stats->update($request->all());
        }
        return redirect()->back()->with('success', 'Settings updated!');
    }

    public function storePerformer(Request $request)
    {
        $this->authorizeSection();
        
        $request->validate([
            'name' => 'required'
        ]);

        $data = $request->all();
        $data['is_closer'] = ($request->role === 'Closer');
        $data['goal'] = $request->goal ?? 0;

        $fullRules = [
            'name' => 'required',
            'role' => 'required',
            'goal' => 'required|numeric',
        ];

        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), $fullRules);
        
        if ($validator->fails()) {
            $data['status'] = 'DRAFT';
        } else {
            $data['status'] = 'ACTIVE';
        }

        if ($request->hasFile('contract_file')) {
            $data['contract_file'] = $request->file('contract_file')->store('contracts', 'public');
        }
        if ($request->hasFile('id_card_front')) {
            $data['id_card_front'] = $request->file('id_card_front')->store('documents', 'public');
        }
        if ($request->hasFile('id_card_back')) {
            $data['id_card_back'] = $request->file('id_card_back')->store('documents', 'public');
        }
        if ($request->hasFile('police_clearance_certificate')) {
            $data['police_clearance_certificate'] = $request->file('police_clearance_certificate')->store('documents', 'public');
        }
        $performer = SalesPerformer::create($data);
        
        \App\Models\MonthlyPerformerStat::updateOrCreate(
            ['sales_performer_id' => $performer->id, 'month' => now()->month, 'year' => now()->year],
            [
                'goal' => $request->goal ?? 0,
                'number_of_transferred' => $request->number_of_transferred ?? 0,
                'number_of_sales' => $request->number_of_sales ?? 0
            ]
        );

        $msg = $data['status'] == 'DRAFT' ? 'Member saved as Draft due to missing information.' : 'Member added to team!';
        return redirect()->back()->with('success', $msg);
    }

    public function deletePerformer($id)
    {
        abort_if(!auth()->user()->isAdmin(), 403, 'Unauthorized action.');
        $performer = SalesPerformer::findOrFail($id);
        
        if ($performer->status === 'INACTIVE') {
            $performer->status = 'ACTIVE';
            $msg = 'Member reactivated successfully!';
        } else {
            $performer->status = 'INACTIVE';
            $msg = 'Member deactivated! Their historical data remains, but they will not appear in new deal assignments.';
        }
        $performer->save();
        
        return redirect()->back()->with('success', $msg);
    }

    public function forceDeletePerformer($id)
    {
        abort_if(!in_array(auth()->user()->role, ['admin', 'superadmin']), 403, 'Unauthorized action.');
        $performer = SalesPerformer::findOrFail($id);
        
        // Remove performer reference from users table
        \App\Models\User::where('sales_performer_id', $id)->update(['sales_performer_id' => null]);
        
        $performer->delete();
        
        return redirect()->back()->with('success', 'Member permanently deleted!');
    }

    public function storeDeal(Request $request)
    {
        abort_if(auth()->user()->isAgent(), 403, 'Unauthorized action.');
        
        $request->validate([
            'name' => 'nullable'
        ]);

        $rules = [
            'name' => 'nullable',
            'value' => 'nullable|numeric',
            'charge_payment' => 'nullable|numeric',
            'remaining_payment' => 'nullable|numeric',
            'closed_by' => 'nullable',
            'closer_by' => 'nullable',
            'status' => 'nullable',
            'business_owner_name' => 'nullable',
            'business_number' => 'nullable',
            'business_email' => 'nullable|email',
            'business_address' => 'nullable',
            'services_offered' => 'nullable',
        ];

        if (auth()->user()->isAdmin() || auth()->user()->isCloser()) {
            $rules['card_number'] = 'nullable|string';
            $rules['card_name'] = 'nullable|string';
            $rules['card_expiry'] = 'nullable|string';
            $rules['card_cvv'] = 'nullable|string';
            $rules['card_address'] = 'nullable|string';
        }

        if ($request->status === 'WON') {
            $rules['docusign_file'] = 'nullable|file';
            $rules['proposal_pdf'] = 'nullable|file';
            $rules['payment_auth_file'] = 'nullable|file';
        } else {
            $rules['docusign_file'] = 'nullable|file';
            $rules['proposal_pdf'] = 'nullable|file';
            $rules['payment_auth_file'] = 'nullable|file';
        }

        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), $rules);

        $data = $request->all();

        // Default empty numeric fields to 0 so they don't break database insertion
        $data['value'] = $request->value ?? 0;
        $data['charge_payment'] = $request->charge_payment ?? 0;
        $data['remaining_payment'] = $request->remaining_payment ?? 0;

        // A deal can deliberately be saved incomplete as a draft, but name is NOT NULL,
        // so an untitled one needs a placeholder rather than a database error.
        $data['name'] = $request->name ?: 'Untitled Deal';

        // status is read again further down for the confirmation message, so it always
        // needs a value even when the form did not send one.
        $data['status'] = $request->status ?: 'PENDING';

        if ($validator->fails() || $request->status === 'DRAFT') {
            $data['status'] = 'DRAFT';
        }

        if (!auth()->user()->isAdmin() && !auth()->user()->isCloser()) {
            unset($data['card_number'], $data['card_name'], $data['card_expiry'], $data['card_cvv'], $data['card_address']);
        }

        if ($request->hasFile('docusign_file')) {
            $data['docusign_file'] = $request->file('docusign_file')->store('deals/documents', 'public');
        }
        if ($request->hasFile('proposal_pdf')) {
            $data['proposal_pdf'] = $request->file('proposal_pdf')->store('deals/documents', 'public');
        }
        if ($request->hasFile('payment_auth_file')) {
            $data['payment_auth_file'] = $request->file('payment_auth_file')->store('deals/documents', 'public');
        }

        $deal = Deal::create($data);

        if ($deal->status !== 'DRAFT') {
            \App\Support\AdminNotifier::notifyCompanyAdmins($deal->company_id, [
                'title' => 'New Sale Logged',
                'message' => auth()->user()->display_name . ' logged a new deal: ' . $deal->name . ($deal->value ? ' ($' . number_format($deal->value, 2) . ')' : ''),
                'icon' => 'fas fa-handshake',
                'type' => 'success',
            ], [], auth()->id());
        }

        // Create Support Ticket if Deal is WON
        if ($deal->status === 'WON') {
            \App\Models\SupportTicket::create([
                'company_id' => $deal->company_id,
                'deal_id' => $deal->id,
                'subject' => 'Onboarding: ' . $deal->name,
                'description' => 'New deal won. Needs onboarding and verification.',
                'status' => 'pending_verification',
                'priority' => 'high'
            ]);

            \App\Support\AdminNotifier::notifyCompanyAdmins($deal->company_id, [
                'title' => 'Deal Won',
                'message' => $deal->name . ' marked WON' . ($deal->value ? ' — $' . number_format($deal->value, 2) : ''),
                'icon' => 'fas fa-trophy',
                'type' => 'success',
            ], [], auth()->id());
        }
        // Auto-increment transfers for Agent and Closer
        if (!empty($data['closed_by'])) {
            $agent = \App\Models\SalesPerformer::where('name', $data['closed_by'])->first();
            if ($agent) {
                $stat = $agent->currentStat();
                $stat ? $stat->increment('number_of_transferred') : $agent->increment('number_of_transferred');
            }
        }
        
        // Only increment for closer if they are a different person and it's not a self-generated sale
        if (!empty($data['closer_by']) && $data['closer_by'] !== ($data['closed_by'] ?? '') && ($data['closed_by'] ?? '') !== 'Self-Generated by Closer') {
            $closer = \App\Models\SalesPerformer::where('name', $data['closer_by'])->first();
            if ($closer) {
                $stat = $closer->currentStat();
                $stat ? $stat->increment('number_of_transferred') : $closer->increment('number_of_transferred');
            }
        }

        $msg = $data['status'] == 'DRAFT' ? 'Deal saved as Draft due to incomplete information.' : 'Deal logged successfully!';
        return redirect()->back()->with('success', $msg);
    }

    public function deleteDeal($id)
    {
        abort_if(!auth()->user()->isAdmin(), 403, 'Unauthorized action.');
        $deal = Deal::findOrFail($id);
        
        // Auto-decrement transfers for Agent and Closer
        if (!empty($deal->closed_by)) {
            $agent = \App\Models\SalesPerformer::where('name', $deal->closed_by)->first();
            if ($agent) {
                $stat = $agent->currentStat();
                $stat ? $stat->decrement('number_of_transferred') : $agent->decrement('number_of_transferred');
            }
        }
        
        if (!empty($deal->closer_by) && $deal->closed_by !== 'Self-Generated by Closer') {
            $closer = \App\Models\SalesPerformer::where('name', $deal->closer_by)->first();
            if ($closer && $closer->name !== ($deal->closed_by ?? '')) {
                $stat = $closer->currentStat();
                $stat ? $stat->decrement('number_of_transferred') : $closer->decrement('number_of_transferred');
            }
        }
        
        \App\Models\SystemLog::log('Deleted Deal', "Removed deal #{$deal->id}: {$deal->name} (\${$deal->value})");

        $deal->delete();
        return redirect()->back()->with('success', 'Deal record deleted!');
    }

    public function storeLead(Request $request)
    {
        $this->authorizeSection();

        // name and source are NOT NULL on the table, so they have to be checked here
        // or the insert fails with a database error instead of a form message.
        $data = $request->validate($this->leadRules());
        $data['created_by'] = auth()->id();

        Lead::create($data);

        return redirect()->back()->with('success', 'Lead captured!');
    }

    public function storeTransferredLead(Request $request)
    {
        $this->authorizeSection();
        
        $request->validate([
            'customer_name' => 'required',
            'scheduled_at' => 'required|date',
            'agent_id' => 'nullable|exists:users,id',
            'closer_id' => 'nullable|exists:users,id',
            'lead_id' => 'nullable|exists:leads,id',
            'business_category_id' => 'nullable|exists:business_categories,id',
            'usa_state_id' => 'nullable|exists:usa_states,id',
            'city_id' => 'nullable|exists:cities,id',
        ]);

        $data = $request->all();
        $data['agent_id'] = auth()->user()->isCloser() && $request->agent_id ? $request->agent_id : auth()->id();

        // Into the pool unless a closer was named: the pipeline decides the
        // stage, writes the history and tells whoever needs telling.
        $lead = $request->filled('lead_id') ? \App\Models\Lead::find($request->input('lead_id')) : null;

        (new \App\Services\LeadPipeline())->transfer($data, auth()->user(), $lead);

        // The handover is counted on the scoreboard by the pipeline itself, so
        // that a lead handed over any other way is counted too.

        return redirect()->back()->with('success', 'Lead transferred successfully!');
    }

    public function transferredLeads(Request $request)
    {
        $this->authorizeSection();
        
        $query = TransferredLead::with(['agent', 'closer'])->latest();
        
        if (auth()->user()->isAgent()) {
            $query->where('agent_id', auth()->id());
        } elseif (auth()->user()->isCloser()) {
            if ($request->has('filter_agent_id') && $request->filter_agent_id != '') {
                $query->where('agent_id', $request->filter_agent_id);
            }
        }
        
        if ($request->has('month') && $request->month != '') {
            $query->whereMonth('scheduled_at', \Carbon\Carbon::parse($request->month)->month)
                  ->whereYear('scheduled_at', \Carbon\Carbon::parse($request->month)->year);
        }

        if ($request->filled('stage')) {
            $query->where('stage', $request->input('stage'));
        }

        // The figures below describe everything the filters left, so they are
        // worked out before the list is cut into pages — otherwise "won" would
        // mean "won on this page", which is a different and useless number.
        $all = (clone $query)->with('deal:id,value,status')->get();

        $transferred_leads = $query->with(['category', 'state', 'city', 'deal'])
            ->latest()
            ->paginate(50)
            ->withQueryString();

        $active_count = $all->where('status', 'Active')->count();
        $missed_count = $all->where('status', 'Missed')->count();

        // What the handovers came to.
        //
        // Counted over whatever the filters left, so the figures answer the
        // question actually on screen rather than a different one about the
        // whole table. The rate is out of the ones that were settled: a lead
        // still being worked has not been lost, and counting it as one would
        // make every closer look worse the harder they are working.
        $won = $all->where('stage', 'won');
        $won_count = $won->count();
        $settled_count = $won_count + $all->where('stage', 'lost')->count();

        $conversion_rate = $settled_count > 0
            ? round($won_count / $settled_count * 100)
            : 0;

        // The money lives on the deal each win became; a win whose deal has not
        // had its figure put in yet contributes nothing, and is counted
        // separately so nobody reads the total as final.
        $won_value = $won->sum(fn ($t) => (float) ($t->deal?->value ?? 0));
        $awaiting_figure = $won->filter(fn ($t) => !$t->deal || (float) $t->deal->value <= 0)->count();

        $agents = \App\Models\User::where('role', 'agent')->get();
        $closers = \App\Models\User::where('role', 'closer')->orderBy('name')->get();

        // The pool is what a closer actually works from: what is waiting, and
        // what they have already picked up.
        $board = (new \App\Services\LeadPipeline())->board(auth()->user());

        $categories = \App\Models\BusinessCategory::active()->orderBy('name')->get();
        $states = \App\Models\UsaState::active()->orderBy('name')->get();

        return view('admin.transferred_leads', compact(
            'transferred_leads', 'active_count', 'missed_count',
            'won_count', 'won_value', 'settled_count', 'conversion_rate', 'awaiting_figure',
            'agents', 'closers', 'board', 'categories', 'states'
        ));
    }

    public function updateTransferredLead(Request $request, $id)
    {
        $this->authorizeSection();

        $lead = TransferredLead::findOrFail($id);

        $request->validate([
            'customer_name' => 'required',
            'scheduled_at' => 'nullable|date',
            'status' => 'nullable|in:Active,Missed,Completed'
        ]);

        $lead->update($request->all());

        return redirect()->back()->with('success', 'Transferred lead updated successfully!');
    }

    public function checkReminders()
    {
        if (!auth()->check()) return response()->json(['reminders' => []]);

        $query = TransferredLead::where('status', 'Active')->whereNotNull('scheduled_at');
        
        if (auth()->user()->isAgent()) {
            $query->where('agent_id', auth()->id());
        }

        $leads = $query->get();
        $reminders = [];
        $now = now();

        foreach ($leads as $lead) {
            $scheduledAt = \Carbon\Carbon::parse($lead->scheduled_at);
            $diffMinutes = $now->diffInMinutes($scheduledAt, false);
            
            // Only care about future times within ~35 mins
            if ($diffMinutes >= 0 && $diffMinutes <= 35) {
                $sent = $lead->reminders_sent ?? [];
                
                if ($diffMinutes <= 30 && $diffMinutes > 15 && !in_array('30m', $sent)) {
                    $reminders[] = ['id' => $lead->id, 'type' => '30m', 'message' => "Reminder: Follow up for {$lead->customer_name} is scheduled in 30 minutes!"];
                    $sent[] = '30m';
                } elseif ($diffMinutes <= 15 && $diffMinutes > 2 && !in_array('15m', $sent)) {
                    $reminders[] = ['id' => $lead->id, 'type' => '15m', 'customer_name' => $lead->customer_name, 'message' => "We are scheduled next 15 minutes. Did you send the proposal already?"];
                    $sent[] = '15m';
                } elseif ($diffMinutes <= 2 && !in_array('2m', $sent)) {
                    $reminders[] = ['id' => $lead->id, 'type' => '2m', 'message' => "URGENT: Follow up for {$lead->customer_name} is starting in 2 minutes!"];
                    $sent[] = '2m';
                }
                
                if ($lead->reminders_sent !== $sent) {
                    $lead->reminders_sent = $sent;
                    $lead->save();
                }
            }
        }
        return response()->json(['reminders' => $reminders]);
    }

    public function markProposalSent($id)
    {
        $lead = TransferredLead::findOrFail($id);
        $lead->is_proposal_sent = true;
        $reminders = $lead->reminders_sent ?? [];
        if (!in_array('15m_dismissed', $reminders)) {
            $reminders[] = '15m_dismissed';
            $lead->reminders_sent = $reminders;
        }
        $lead->save();
        return response()->json(['success' => true]);
    }

    public function dismiss15m($id)
    {
        $lead = TransferredLead::findOrFail($id);
        $reminders = $lead->reminders_sent ?? [];
        if (!in_array('15m_dismissed', $reminders)) {
            $reminders[] = '15m_dismissed';
            $lead->reminders_sent = $reminders;
            $lead->save();
        }
        return response()->json(['success' => true]);
    }

    public function toggleConversion($id)
    {
        $lead = TransferredLead::findOrFail($id);
        // Only closers should ideally mark as converted, but we let them do it
        $lead->is_converted = !$lead->is_converted;
        if ($lead->is_converted && auth()->user()->isCloser()) {
            $lead->closer_id = auth()->id();
        }
        $lead->save();
        return redirect()->back()->with('success', 'Conversion status updated!');
    }

    /**
     * A closer takes a lead out of the pool.
     *
     * First come, first served — so the answer when somebody is a second too
     * late has to say so plainly rather than quietly handing them a lead that
     * is already being worked.
     */
    public function claimLead($id)
    {
        $this->authorizeSection();

        $transfer = TransferredLead::findOrFail($id);
        $taken = (new \App\Services\LeadPipeline())->claim($transfer, auth()->user());

        return redirect()->back()->with(
            $taken ? 'success' : 'error',
            $taken
                ? "{$transfer->displayName()} is yours. It is on your desk now."
                : 'Somebody else got to that one first.'
        );
    }

    /** Put one back for somebody else. */
    public function releaseLead(Request $request, $id)
    {
        $this->authorizeSection();

        $transfer = TransferredLead::findOrFail($id);

        if ($transfer->closer_id !== auth()->id() && !auth()->user()->isAdmin()) {
            abort(403, 'That lead is not yours to put back.');
        }

        (new \App\Services\LeadPipeline())->release($transfer, auth()->user(), $request->input('note'));

        return redirect()->back()->with('success', 'Back in the pool for somebody else.');
    }

    /** Move a lead along the pipeline. */
    public function moveLeadStage(Request $request, $id)
    {
        $this->authorizeSection();

        $request->validate([
            'stage' => 'required|in:' . implode(',', array_keys(TransferredLead::STAGES)),
            'note' => 'nullable|string|max:255',
            // What it was won for. Asked at the moment of winning rather than
            // left for later: a deal created at zero is a deal somebody has to
            // remember to go back to, and the figure is never more certain than
            // it is right now.
            'value' => 'nullable|numeric|min:0|max:99999999',
        ]);

        $transfer = TransferredLead::findOrFail($id);
        $pipeline = new \App\Services\LeadPipeline();

        // Winning goes the long way round, because a win creates the deal.
        if ($request->input('stage') === 'won') {
            $worth = $request->filled('value') ? (float) $request->input('value') : null;

            $deal = $pipeline->convert($transfer, auth()->user(), $worth !== null ? ['value' => $worth] : []);

            // An amount that arrived after the deal already existed still
            // belongs on it.
            if ($worth !== null && (float) $deal->value !== $worth) {
                $deal->update(['value' => $worth]);
            }

            return redirect()->back()->with(
                'success',
                $worth !== null
                    ? 'Won for $' . number_format($worth, 2) . '. Deal #' . $deal->id . ' is open — add its documents to mark it WON.'
                    : 'Won. Deal #' . $deal->id . ' is open and waiting for its figures.'
            );
        }

        $pipeline->moveTo($transfer, $request->input('stage'), auth()->user(), $request->input('note'));

        return redirect()->back()->with('success', 'Moved to ' . (TransferredLead::STAGES[$request->input('stage')] ?? $request->input('stage')) . '.');
    }

    public function deleteTransferredLead($id)
    {
        $this->authorizeSection();

        $lead = TransferredLead::findOrFail($id);

        \App\Models\SystemLog::log('Deleted Transfer', "Removed the handover of {$lead->displayName()}");

        $lead->delete();

        // Optional: Decrement the monthly stat
        if (auth()->user()->performer) {
            $stat = \App\Models\MonthlyPerformerStat::where([
                'sales_performer_id' => auth()->user()->performer->id,
                'month' => now()->month,
                'year' => now()->year
            ])->first();
            
            if ($stat && $stat->number_of_transferred > 0) {
                $stat->decrement('number_of_transferred');
            }
        }

        return redirect()->back()->with('success', 'Transferred lead deleted successfully!');
    }

    public function settings()
    {
        $this->authorizeSection();
        $stats_row = ScoreboardStat::firstOrCreate([], ['monthly_target' => 250000]);
        return view('admin.settings', compact('stats_row'));
    }

    public function updatePerformer(Request $request, $id)
    {
        $this->authorizeSection();
        $request->validate([
            'name' => 'required',
        ]);
        
        $fullRules = [
            'name' => 'required',
            'role' => 'required',
            'goal' => 'required|numeric',
        ];

        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), $fullRules);
        
        $data = $request->all();
        $data['goal'] = $request->goal ?? 0;
        
        if ($validator->fails() || $request->status === 'DRAFT') {
            $data['status'] = 'DRAFT';
        } else {
            $data['status'] = 'ACTIVE';
        }
        $data['is_closer'] = ($request->role === 'Closer');
        if ($request->hasFile('contract_file')) {
            $data['contract_file'] = $request->file('contract_file')->store('contracts', 'public');
        }
        if ($request->hasFile('id_card_front')) {
            $data['id_card_front'] = $request->file('id_card_front')->store('documents', 'public');
        }
        if ($request->hasFile('id_card_back')) {
            $data['id_card_back'] = $request->file('id_card_back')->store('documents', 'public');
        }
        if ($request->hasFile('police_clearance_certificate')) {
            $data['police_clearance_certificate'] = $request->file('police_clearance_certificate')->store('documents', 'public');
        }
        $performer = SalesPerformer::findOrFail($id);
        $performer->update($data);

        \App\Models\MonthlyPerformerStat::updateOrCreate(
            ['sales_performer_id' => $performer->id, 'month' => now()->month, 'year' => now()->year],
            [
                'goal' => $request->goal ?? 0,
                'number_of_transferred' => $request->number_of_transferred ?? 0,
                'number_of_sales' => $request->number_of_sales ?? 0
            ]
        );

        return redirect()->back()->with('success', 'Member updated!');
    }

    public function toggleCloser($id)
    {
        $this->authorizeSection();
        $performer = SalesPerformer::findOrFail($id);
        $performer->update(['is_closer' => !$performer->is_closer]);
        return redirect()->back()->with('success', $performer->name . ($performer->is_closer ? ' marked as Closer!' : ' removed from Closers!'));
    }

    public function updateDeal(Request $request, $id)
    {
        abort_if(auth()->user()->isAgent(), 403, 'Unauthorized action.');
        
        $deal = Deal::findOrFail($id);

        $rules = [
            'name' => 'nullable',
            'value' => 'nullable|numeric',
            'charge_payment' => 'nullable|numeric',
            'remaining_payment' => 'nullable|numeric',
            'closed_by' => 'nullable',
            'closer_by' => 'nullable',
            'status' => 'nullable',
            'business_owner_name' => 'nullable',
            'business_number' => 'nullable',
            'business_email' => 'nullable|email',
            'business_address' => 'nullable',
            'services_offered' => 'nullable',
        ];

        if (auth()->user()->isAdmin() || auth()->user()->isCloser()) {
            $rules['card_number'] = 'nullable|string';
            $rules['card_name'] = 'nullable|string';
            $rules['card_expiry'] = 'nullable|string';
            $rules['card_cvv'] = 'nullable|string';
            $rules['card_address'] = 'nullable|string';
        }

        if ($request->status === 'WON') {
            $rules['docusign_file'] = 'nullable|file';
            $rules['proposal_pdf'] = 'nullable|file';
            $rules['payment_auth_file'] = 'nullable|file';
        } else {
            $rules['docusign_file'] = 'nullable|file';
            $rules['proposal_pdf'] = 'nullable|file';
            $rules['payment_auth_file'] = 'nullable|file';
        }

        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), $rules);

        $data = $request->all();

        // Default empty numeric fields to 0
        $data['value'] = $request->value ?? 0;
        $data['charge_payment'] = $request->charge_payment ?? 0;
        $data['remaining_payment'] = $request->remaining_payment ?? 0;

        // name is NOT NULL, so an emptied field keeps the name the deal already has
        // rather than failing the update with a database error.
        $data['name'] = $request->name ?: $deal->name;

        if ($validator->fails() || $request->status === 'DRAFT') {
            $data['status'] = 'DRAFT';
        }

        if (!auth()->user()->isAdmin() && !auth()->user()->isCloser()) {
            unset($data['card_number'], $data['card_name'], $data['card_expiry'], $data['card_cvv'], $data['card_address']);
        }

        if ($request->hasFile('docusign_file')) {
            if ($deal->docusign_file) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($deal->docusign_file);
            }
            $data['docusign_file'] = $request->file('docusign_file')->store('deals/documents', 'public');
        }
        if ($request->hasFile('proposal_pdf')) {
            if ($deal->proposal_pdf) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($deal->proposal_pdf);
            }
            $data['proposal_pdf'] = $request->file('proposal_pdf')->store('deals/documents', 'public');
        }
        if ($request->hasFile('payment_auth_file')) {
            if ($deal->payment_auth_file) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($deal->payment_auth_file);
            }
            $data['payment_auth_file'] = $request->file('payment_auth_file')->store('deals/documents', 'public');
        }

        $oldAgent = $deal->closed_by;
        $oldCloser = $deal->closer_by;
        $oldStatus = $deal->status;

        $deal->update($data);

        // Create Support Ticket if Deal is WON and it wasn't WON before
        if ($oldStatus !== 'WON' && $deal->status === 'WON') {
            // Check if ticket already exists just in case
            $ticketExists = \App\Models\SupportTicket::where('deal_id', $deal->id)->exists();
            if (!$ticketExists) {
                \App\Models\SupportTicket::create([
                    'company_id' => $deal->company_id,
                    'deal_id' => $deal->id,
                    'subject' => 'Onboarding: ' . $deal->name,
                    'description' => 'Deal status updated to WON. Needs onboarding and verification.',
                    'status' => 'pending_verification',
                    'priority' => 'high'
                ]);
            }
        }

        if ($oldStatus !== $deal->status && in_array($deal->status, ['WON', 'LOST'])) {
            \App\Support\AdminNotifier::notifyCompanyAdmins($deal->company_id, [
                'title' => $deal->status === 'WON' ? 'Deal Won' : 'Deal Lost',
                'message' => $deal->name . ' marked ' . $deal->status . ($deal->value ? ' — $' . number_format($deal->value, 2) : ''),
                'icon' => $deal->status === 'WON' ? 'fas fa-trophy' : 'fas fa-times-circle',
                'type' => $deal->status === 'WON' ? 'success' : 'error',
            ], [], auth()->id());
        }

        // Auto-adjust transfers if Agent changed
        if ($oldAgent !== $deal->closed_by) {
            if (!empty($oldAgent)) {
                $agent = \App\Models\SalesPerformer::where('name', $oldAgent)->first();
                if ($agent) {
                    $stat = $agent->currentStat();
                    $stat ? $stat->decrement('number_of_transferred') : $agent->decrement('number_of_transferred');
                }
            }
            if (!empty($deal->closed_by)) {
                $agent = \App\Models\SalesPerformer::where('name', $deal->closed_by)->first();
                if ($agent) {
                    $stat = $agent->currentStat();
                    $stat ? $stat->increment('number_of_transferred') : $agent->increment('number_of_transferred');
                }
            }
            
            // Adjust closer's transfer count if deal changed between transferred and self-generated
            if ($oldCloser === $deal->closer_by && !empty($deal->closer_by)) {
                $closer = \App\Models\SalesPerformer::where('name', $deal->closer_by)->first();
                if ($closer) {
                    $wasSelfGen = ($oldAgent === 'Self-Generated by Closer');
                    $isSelfGen = ($deal->closed_by === 'Self-Generated by Closer');
                    if (!$wasSelfGen && $isSelfGen) {
                        $stat = $closer->currentStat();
                        $stat ? $stat->decrement('number_of_transferred') : $closer->decrement('number_of_transferred');
                    } elseif ($wasSelfGen && !$isSelfGen) {
                        $stat = $closer->currentStat();
                        $stat ? $stat->increment('number_of_transferred') : $closer->increment('number_of_transferred');
                    }
                }
            }
        }
        
        // Auto-adjust transfers if Closer changed
        if ($oldCloser !== $deal->closer_by) {
            if (!empty($oldCloser) && $oldCloser !== $oldAgent && $oldAgent !== 'Self-Generated by Closer') {
                $closer = \App\Models\SalesPerformer::where('name', $oldCloser)->first();
                if ($closer) {
                    $stat = $closer->currentStat();
                    $stat ? $stat->decrement('number_of_transferred') : $closer->decrement('number_of_transferred');
                }
            }
            if (!empty($deal->closer_by) && $deal->closer_by !== $deal->closed_by && $deal->closed_by !== 'Self-Generated by Closer') {
                $closer = \App\Models\SalesPerformer::where('name', $deal->closer_by)->first();
                if ($closer) {
                    $stat = $closer->currentStat();
                    $stat ? $stat->increment('number_of_transferred') : $closer->increment('number_of_transferred');
                }
            }
        }

        return redirect()->back()->with('success', 'Deal updated!');
    }

    public function updateLead(Request $request, $id)
    {
        $this->authorizeSection();
        Lead::findOrFail($id)->update($request->validate($this->leadRules()));

        return redirect()->back()->with('success', 'Lead updated!');
    }

    /**
     * What a lead must say, in one place so capturing one and correcting one
     * cannot drift apart.
     *
     * name and source are NOT NULL on the table, so they are checked here or the
     * insert fails with a database error instead of a form message. The trade and
     * the place are optional: a lead taken down mid-call should not be refused
     * because the city has not been asked for yet.
     */
    private function leadRules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'business_name' => 'nullable|string|max:255',
            'source' => 'required|string|max:255',
            'status' => 'required|string|max:255',
            'phone' => 'nullable|string|max:60',
            'email' => 'nullable|email|max:255',
            'business_category_id' => 'nullable|exists:business_categories,id',
            'usa_state_id' => 'nullable|exists:usa_states,id',
            'city_id' => 'nullable|exists:cities,id',
            'address' => 'nullable|string|max:255',
            'website' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ];
    }

    public function updateBranding(Request $request)
    {
        // Branding belongs to a company, and the owner account has no company of its
        // own, so it stays with the company admins whatever their switches say.
        $this->authorizeSection();
        abort_if(auth()->user()->isSuperAdmin(), 403, 'Unauthorized action.');
        
        $request->validate([
            'logo' => 'nullable|image|max:2048',
            'dark_logo' => 'nullable|image|max:2048',
            'favicon' => 'nullable|image|max:1024',
            'theme_color' => 'nullable|string|max:7',
            'number_of_breaks' => 'nullable|integer|min:1|max:2',
            'break_1_name' => 'nullable|string|max:50',
            'break_1_start' => 'nullable|date_format:H:i',
            'break_1_end' => 'nullable|date_format:H:i',
            'break_2_name' => 'nullable|string|max:50',
            'break_2_start' => 'nullable|date_format:H:i',
            'break_2_end' => 'nullable|date_format:H:i',
        ]);

        $company = auth()->user()->company;
        $updateData = [];

        if ($request->has('theme_color')) {
            $updateData['theme_color'] = $request->theme_color;
        }

        if ($request->hasFile('logo')) {
            if ($company->logo && file_exists(public_path($company->logo))) {
                @unlink(public_path($company->logo));
            }
            $filename = time() . '_logo_' . $request->file('logo')->getClientOriginalName();
            $request->file('logo')->move(public_path('uploads/company_logos'), $filename);
            $updateData['logo'] = 'uploads/company_logos/' . $filename;
        }

        if ($request->hasFile('dark_logo')) {
            if (!\Illuminate\Support\Facades\Schema::hasColumn('companies', 'dark_logo')) {
                \Illuminate\Support\Facades\Schema::table('companies', function($table) {
                    $table->string('dark_logo')->nullable();
                });
            }
            if ($company->dark_logo && file_exists(public_path($company->dark_logo))) {
                @unlink(public_path($company->dark_logo));
            }
            $filename = time() . '_dark_logo_' . $request->file('dark_logo')->getClientOriginalName();
            $request->file('dark_logo')->move(public_path('uploads/company_logos'), $filename);
            $updateData['dark_logo'] = 'uploads/company_logos/' . $filename;
        }

        if ($request->hasFile('favicon')) {
            if ($company->favicon && file_exists(public_path($company->favicon))) {
                @unlink(public_path($company->favicon));
            }
            $filename = time() . '_favicon_' . $request->file('favicon')->getClientOriginalName();
            $request->file('favicon')->move(public_path('uploads/company_logos'), $filename);
            $updateData['favicon'] = 'uploads/company_logos/' . $filename;
        }

        if (!\Illuminate\Support\Facades\Schema::hasColumn('companies', 'number_of_breaks')) {
            \Illuminate\Support\Facades\Schema::table('companies', function($table) {
                $table->integer('number_of_breaks')->default(2);
                $table->string('break_1_name')->default('1st Half Break');
                $table->string('break_2_name')->default('2nd Half Break');
            });
        }
        
        if (!\Illuminate\Support\Facades\Schema::hasColumn('companies', 'break_1_start')) {
            \Illuminate\Support\Facades\Schema::table('companies', function($table) {
                $table->time('break_1_start')->nullable();
                $table->time('break_1_end')->nullable();
                $table->time('break_2_start')->nullable();
                $table->time('break_2_end')->nullable();
            });
        }
        
        if ($request->has('number_of_breaks')) {
            $updateData['number_of_breaks'] = $request->number_of_breaks;
            $updateData['break_1_name'] = $request->break_1_name ?: '1st Half Break';
            $updateData['break_1_start'] = $request->break_1_start;
            $updateData['break_1_end'] = $request->break_1_end;
            $updateData['break_2_name'] = $request->break_2_name ?: '2nd Half Break';
            $updateData['break_2_start'] = $request->break_2_start;
            $updateData['break_2_end'] = $request->break_2_end;
        }

        $company->update($updateData);

        \App\Models\SystemLog::log('Updated Branding', "Company admin updated branding settings.");

        return back()->with('success', 'Company branding updated successfully!');
    }

    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'password' => 'required|min:8|confirmed',
        ]);

        $user = auth()->user();

        if (!\Illuminate\Support\Facades\Hash::check($request->current_password, $user->password)) {
            return back()->withErrors(['current_password' => 'The provided password does not match your current password.']);
        }

        $user->update([
            'password' => \Illuminate\Support\Facades\Hash::make($request->password),
        ]);

        return back()->with('success', 'Password updated successfully!');
    }

    /**
     * Bring a list of businesses in from a spreadsheet.
     *
     * The whole file is checked before anything is written, and the result is
     * reported plainly — how many went in, how many were already on the list,
     * and which lines had something wrong with them. An import that says only
     * "done" leaves somebody wondering what happened to the other forty rows.
     */
    public function importLeads(Request $request)
    {
        abort_unless(auth()->user()->canAccess('leads'), 403, 'Unauthorized action.');

        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:10240',
        ]);

        $result = (new \App\Services\LeadImport(auth()->user()))
            ->run($request->file('file')->getRealPath());

        \App\Models\SystemLog::log(
            'Imported Leads',
            "Added {$result['added']}, skipped {$result['skipped']} already on the list"
        );

        $said = $result['added'] === 0 && $result['skipped'] === 0
            ? 'Nothing was imported.'
            : "{$result['added']} lead(s) added"
                . ($result['skipped'] ? ", {$result['skipped']} already on the list and left alone" : '')
                . '.';

        return redirect()->back()
            ->with($result['added'] > 0 ? 'success' : 'error', $said)
            ->with('import_problems', array_slice($result['problems'], 0, 25));
    }

    public function deleteLead($id)
    {
        abort_if(!auth()->user()->isAdmin(), 403, 'Unauthorized action.');

        $lead = Lead::findOrFail($id);

        \App\Models\SystemLog::log('Deleted Lead', "Removed lead: {$lead->name}");

        $lead->delete();

        return redirect()->back()->with('success', 'Lead removed. It is in the Recycle Bin if you need it back.');
    }

    // News Headlines CRUD
    public function headlines()
    {
        $this->authorizeSection();
        $headlines = NewsHeadline::latest()->paginate(15);
        return view('admin.headlines', compact('headlines'));
    }

    public function storeHeadline(Request $request)
    {
        $this->authorizeSection();
        $request->validate(['headline' => 'required|string|max:255']);
        NewsHeadline::create($request->all());
        return redirect()->back()->with('success', 'Headline added!');
    }

    public function updateHeadline(Request $request, $id)
    {
        $this->authorizeSection();
        $request->validate(['headline' => 'required|string|max:255']);
        NewsHeadline::findOrFail($id)->update($request->all());
        return redirect()->back()->with('success', 'Headline updated!');
    }

    public function toggleHeadline($id)
    {
        $this->authorizeSection();
        $headline = NewsHeadline::findOrFail($id);
        $headline->update(['is_active' => !$headline->is_active]);
        return redirect()->back()->with('success', 'Headline status toggled!');
    }

    public function deleteHeadline($id)
    {
        abort_if(!auth()->user()->isAdmin(), 403, 'Unauthorized action.');
        NewsHeadline::destroy($id);
        return redirect()->back()->with('success', 'Headline removed!');
    }

    // Business Categories CRUD
    public function categories()
    {
        $this->authorizeSection();
        $categories = BusinessCategory::latest()->paginate(20);
        return view('admin.categories', compact('categories'));
    }

    public function storeCategory(Request $request)
    {
        $this->authorizeSection();
        $request->validate(['name' => 'required|string|max:255']);
        BusinessCategory::create($request->all());
        return redirect()->back()->with('success', 'Category added!');
    }

    public function updateCategory(Request $request, $id)
    {
        $this->authorizeSection();
        $request->validate(['name' => 'required|string|max:255']);
        BusinessCategory::findOrFail($id)->update($request->all());
        return redirect()->back()->with('success', 'Category updated!');
    }

    public function toggleCategory($id)
    {
        $this->authorizeSection();
        $category = BusinessCategory::findOrFail($id);
        $category->update(['is_active' => !$category->is_active]);
        return redirect()->back()->with('success', 'Category status toggled!');
    }

    public function deleteCategory($id)
    {
        abort_if(!auth()->user()->isAdmin(), 403, 'Unauthorized action.');
        BusinessCategory::destroy($id);
        return redirect()->back()->with('success', 'Category removed!');
    }

    // USA States Methods
    public function usaStates()
    {
        $this->authorizeSection();
        $states = \App\Models\UsaState::latest()->paginate(20);
        return view('admin.usa_states', compact('states'));
    }

    public function storeUsaState(Request $request)
    {
        $this->authorizeSection();
        $request->validate(['name' => 'required|string|max:255']);

        // The table is unique on (company_id, name). The model is company scoped, so
        // this only looks at the current company's list.
        if (\App\Models\UsaState::where('name', $request->name)->exists()) {
            return redirect()->back()->withInput()->withErrors(['name' => 'This state is already on your list.']);
        }

        \App\Models\UsaState::create($request->all());
        return redirect()->back()->with('success', 'USA State added!');
    }

    public function updateUsaState(Request $request, $id)
    {
        $this->authorizeSection();
        $request->validate(['name' => 'required|string|max:255']);

        if (\App\Models\UsaState::where('name', $request->name)->where('id', '!=', $id)->exists()) {
            return redirect()->back()->withInput()->withErrors(['name' => 'This state is already on your list.']);
        }

        \App\Models\UsaState::findOrFail($id)->update($request->all());
        return redirect()->back()->with('success', 'USA State updated!');
    }

    public function toggleUsaState($id)
    {
        $this->authorizeSection();
        $state = \App\Models\UsaState::findOrFail($id);
        $state->update(['is_active' => !$state->is_active]);
        return redirect()->back()->with('success', 'USA State status toggled!');
    }

    public function deleteUsaState($id)
    {
        abort_if(!auth()->user()->isAdmin(), 403, 'Unauthorized action.');
        \App\Models\UsaState::destroy($id);
        return redirect()->back()->with('success', 'USA State removed!');
    }

    /**
     * The access switches as submitted on the user form.
     *
     * Returns null when the form did not carry them, which leaves the user falling
     * back to the defaults for their role. An empty array is a real answer: it means
     * every switch was turned off.
     *
     * Owner level sections are always dropped for a non superadmin target, whoever is
     * saving the form. canAccess() would refuse them anyway, so keeping them out of
     * storage is only about not leaving misleading data behind — a company admin's
     * own limited view already never offers these switches in the first place.
     */
    private function submittedPermissions(Request $request, ?string $role): ?array
    {
        if (!$request->boolean('permissions_submitted')) {
            return null;
        }

        // A superadmin reaches everything regardless, so storing a list would only
        // be misleading.
        if ($role === 'superadmin') {
            return null;
        }

        $known = array_keys(config('permissions.sections', []));
        $chosen = array_values(array_intersect((array) $request->input('permissions', []), $known));
        $chosen = array_values(array_diff($chosen, config('permissions.superadmin_only', [])));

        return $chosen;
    }

    // User Management Methods
    public function users()
    {
        $this->authorizeSection();

        $me = auth()->user();

        // Who this person is allowed to see at all. Unchanged from before — an
        // owner sees everybody, a floor manager sees the floor, anybody else
        // sees everybody but the owner.
        $query = match (true) {
            $me->isSuperAdmin() => \App\Models\User::query(),
            $me->isFloorManager() => \App\Models\User::whereNotIn('role', ['superadmin', 'admin']),
            default => \App\Models\User::where('role', '!=', 'superadmin'),
        };

        // Narrowing by name, which the global search bar links into. The list
        // is already past forty rows on a small floor and only grows.
        $search = request('search');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('username', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $users = $query->get();
        $performers = SalesPerformer::where('status', '!=', 'DRAFT')->orWhereNull('status')->get();

        if ($me->isSuperAdmin()) {
            $companies = \App\Models\Company::all();

            return view('admin.users', compact('users', 'performers', 'companies', 'search'));
        }

        return view('admin.users', compact('users', 'performers', 'search'));
    }

    public function storeUser(Request $request)
    {
        abort_if(!auth()->user()->managesStaff(), 403, 'Unauthorized action.');
        // Both the add and edit forms offer QA as a role unconditionally, but it was
        // missing from every one of these lists. Picking it failed validation with
        // "The selected role is invalid" and the change was silently dropped.
        if (auth()->user()->isSuperAdmin()) {
            $allowed_roles = 'superadmin,admin,editor,floor_manager,agent,closer,support_manager,support_agent,qa,hr,team_lead';
        } elseif (auth()->user()->isFloorManager()) {
            $allowed_roles = 'agent,closer,qa';
        } else {
            $allowed_roles = 'editor,floor_manager,agent,closer,support_manager,support_agent,qa,hr,team_lead';
        }
        $rules = [
            'name' => 'required|string|max:255',
            'username' => 'required|string|max:255|unique:users,username',
            'email' => 'required|string|unique:users,email',
            'password' => 'required|min:8',
            'role' => 'required|in:' . $allowed_roles,
            'sales_performer_id' => 'nullable|exists:sales_performers,id',
            'permissions' => 'nullable|array',
            'permissions.*' => 'string',
        ];

        if (auth()->user()->isSuperAdmin() && $request->role !== 'superadmin') {
            $rules['company_id'] = 'required|exists:companies,id';
        }

        $request->validate($rules);

        if (in_array($request->role, ['agent', 'closer', 'support_manager', 'support_agent', 'floor_manager']) && $request->sales_performer_id) {
            $performer = SalesPerformer::find($request->sales_performer_id);
            if ($performer) {
                if ($request->role === 'agent' && $performer->is_closer) {
                    return redirect()->back()->withInput()->withErrors(['sales_performer_id' => 'The selected performer is a Closer, but the role is Agent.']);
                }
                if ($request->role === 'closer' && !$performer->is_closer) {
                    return redirect()->back()->withInput()->withErrors(['sales_performer_id' => 'The selected performer is an Agent, but the role is Closer.']);
                }
            }
        }

        $company_id = auth()->user()->isSuperAdmin() ? ($request->role === 'superadmin' ? null : $request->company_id) : auth()->user()->company_id;

        $sales_performer_id = in_array($request->role, ['agent', 'closer', 'support_manager', 'support_agent', 'floor_manager']) ? $request->sales_performer_id : null;

        // Every operational account starts pending rather than live: it used to go
        // straight onto the scoreboard and get full dashboard access the instant it
        // was created, with no review step at all. Admin/superadmin creation is left
        // out — one admin adding another is already a deliberate, trusted action, and
        // gating that too would just be friction with no one left to click Approve.
        $needsApproval = !in_array($request->role, ['admin', 'superadmin']);

        // Agents and closers belong on the scoreboard. If no existing performer was
        // picked, create one so the new user shows up there once approved. It starts
        // PENDING rather than ACTIVE, which — like DRAFT and INACTIVE — is filtered
        // out of the public scoreboard in SalesController@index, so an unapproved
        // agent is invisible there until the admin approves them.
        if (in_array($request->role, ['agent', 'closer']) && empty($sales_performer_id)) {
            $performer = SalesPerformer::create([
                'company_id' => $company_id,
                'name' => $request->name,
                'role' => $request->role === 'closer' ? 'Closer' : 'Sales Agent',
                'is_closer' => $request->role === 'closer',
                'goal' => 0,
                'status' => $needsApproval ? 'PENDING' : 'ACTIVE',
                'avatar_initials' => strtoupper(mb_substr($request->name, 0, 2)),
            ]);
            $sales_performer_id = $performer->id;
        }

        \App\Models\User::create([
            'name' => $request->name,
            'username' => $request->username,
            'email' => $request->email,
            'password' => \Illuminate\Support\Facades\Hash::make($request->password),
            'role' => $request->role,
            'approval_status' => $needsApproval ? 'pending' : 'approved',
            'permissions' => $this->submittedPermissions($request, $request->role),
            'sales_performer_id' => $sales_performer_id,
            'company_id' => $company_id,
        ]);

        \App\Models\SystemLog::log('Created User', "Created new user: {$request->name} ({$request->email}) with role {$request->role}");

        return redirect()->back()->with('success', $needsApproval
            ? 'User created — pending your approval before they can sign in.'
            : 'User created successfully!');
    }

    public function updateUserRole(Request $request, $id)
    {
        abort_if(!auth()->user()->managesStaff(), 403, 'Unauthorized action.');
        $target_user = \App\Models\User::findOrFail($id);
        
        if (!auth()->user()->isSuperAdmin() && in_array($target_user->role, ['superadmin', 'admin'])) {
            abort(403, 'You cannot manage admin users.');
        }
        if (auth()->user()->isFloorManager() && !in_array($target_user->role, ['agent', 'closer'])) {
            abort(403, 'You can only manage agents and closers.');
        }

        // Both the add and edit forms offer QA as a role unconditionally, but it was
        // missing from every one of these lists. Picking it failed validation with
        // "The selected role is invalid" and the change was silently dropped.
        if (auth()->user()->isSuperAdmin()) {
            $allowed_roles = 'superadmin,admin,editor,floor_manager,agent,closer,support_manager,support_agent,qa,hr,team_lead';
        } elseif (auth()->user()->isFloorManager()) {
            $allowed_roles = 'agent,closer,qa';
        } else {
            $allowed_roles = 'editor,floor_manager,agent,closer,support_manager,support_agent,qa,hr,team_lead';
        }
        $rules = [
            'username' => 'required|string|max:255|unique:users,username,' . $id,
            'role' => 'required|in:' . $allowed_roles,
            'sales_performer_id' => 'nullable|exists:sales_performers,id',
            'permissions' => 'nullable|array',
            'permissions.*' => 'string',
            'monthly_salary' => 'nullable|numeric|min:0|max:999999999',
        ];

        if (auth()->user()->isSuperAdmin() && $request->role !== 'superadmin') {
            $rules['company_id'] = 'required|exists:companies,id';
        }

        $request->validate($rules);

        if (in_array($request->role, ['agent', 'closer', 'support_manager', 'support_agent', 'floor_manager']) && $request->sales_performer_id) {
            $performer = SalesPerformer::find($request->sales_performer_id);
            if ($performer) {
                if ($request->role === 'agent' && $performer->is_closer) {
                    return redirect()->back()->withInput()->withErrors(['sales_performer_id' => 'The selected performer is a Closer, but the role is Agent.']);
                }
                if ($request->role === 'closer' && !$performer->is_closer) {
                    return redirect()->back()->withInput()->withErrors(['sales_performer_id' => 'The selected performer is an Agent, but the role is Closer.']);
                }
            }
        }

        if ($id == auth()->id()) {
            return redirect()->back()->with('error', 'Cannot change your own role.');
        }

        $company_id = auth()->user()->isSuperAdmin() ? ($request->role === 'superadmin' ? null : $request->company_id) : $target_user->company_id;

        $updateData = [
            'username' => $request->username,
            'role' => $request->role,
            'sales_performer_id' => in_array($request->role, ['agent', 'closer', 'support_manager', 'support_agent', 'floor_manager']) ? $request->sales_performer_id : null,
            'company_id' => $company_id,
        ];

        // Left alone when the form did not carry the field, so an update posted from
        // elsewhere cannot silently zero somebody's pay.
        if ($request->filled('monthly_salary') || $request->input('monthly_salary') === '0') {
            $updateData['monthly_salary'] = (float) $request->input('monthly_salary');
        }

        // Only touched when the form carried the switches, so an update posted from
        // somewhere else cannot silently wipe someone's access.
        $permissions = $this->submittedPermissions($request, $request->role);

        if ($permissions !== null || $request->boolean('permissions_submitted')) {
            $updateData['permissions'] = $permissions;
        }

        if (auth()->user()->isSuperAdmin()) {
            $updateData['is_active'] = $request->has('is_active') ? $request->is_active : true;
            $updateData['disable_message'] = $request->disable_message;
        }

        $target_user->update($updateData);

        // Automatically toggle the company status and all its users if this user is the company admin
        if (auth()->user()->isSuperAdmin() && $target_user->role === 'admin' && $target_user->company_id) {
            $company = \App\Models\Company::find($target_user->company_id);
            if ($company) {
                $company->update([
                    'is_active' => $target_user->is_active,
                    'disable_message' => $target_user->disable_message
                ]);
            }

            // Also toggle all other users in this company so their status explicitly shows as disabled/active in the UI
            \App\Models\User::where('company_id', $target_user->company_id)
                ->where('id', '!=', $target_user->id)
                ->update([
                    'is_active' => $target_user->is_active,
                    'disable_message' => $target_user->disable_message
                ]);
        }
        return redirect()->back()->with('success', 'User role updated!');
    }

    public function updateUserPassword(Request $request, $id)
    {
        abort_if(!auth()->user()->managesStaff(), 403, 'Unauthorized action.');
        $target_user = \App\Models\User::findOrFail($id);
        
        if (!auth()->user()->isSuperAdmin() && in_array($target_user->role, ['superadmin', 'admin'])) {
            abort(403, 'You cannot manage admin users.');
        }
        if (auth()->user()->isFloorManager() && !in_array($target_user->role, ['agent', 'closer'])) {
            abort(403, 'You can only manage passwords for agents and closers.');
        }
        $request->validate(['password' => 'required|min:8']);
        $target_user->update([
            'password' => \Illuminate\Support\Facades\Hash::make($request->password),
        ]);
        return redirect()->back()->with('success', 'User password updated!');
    }

    public function deleteUser($id)
    {
        abort_if(!auth()->user()->managesStaff(), 403, 'Unauthorized action.');
        $target_user = \App\Models\User::findOrFail($id);
        
        if (!auth()->user()->isSuperAdmin() && in_array($target_user->role, ['superadmin', 'admin'])) {
            abort(403, 'You cannot delete admin users.');
        }
        if (auth()->user()->isFloorManager() && !in_array($target_user->role, ['agent', 'closer'])) {
            abort(403, 'You can only delete agents and closers.');
        }
        if ($id == auth()->id()) {
            return redirect()->back()->with('error', 'Cannot delete yourself.');
        }

        // Soft now: the row is marked rather than removed, so none of the
        // twenty-four tables hanging off a user — their attendance, their
        // leave, their employee file, the leads they handed over — goes with
        // them. The Recycle Bin puts them back.
        \App\Models\SystemLog::log(
            'Deleted User',
            "Removed {$target_user->name} ({$target_user->username}). Recoverable from the Recycle Bin."
        );

        $target_user->delete();

        return redirect()->back()->with('success', $target_user->name . ' was removed. You can put them back from the Recycle Bin.');
    }

    /**
     * Approves a pending account: unlocks the dashboard, and — if the user has a
     * linked sales performer sitting in the PENDING state a new agent/closer starts
     * in — brings that live on the public scoreboard too.
     */
    public function approveUser($id)
    {
        abort_if(!auth()->user()->managesStaff(), 403, 'Unauthorized action.');
        $target_user = \App\Models\User::findOrFail($id);

        if (!auth()->user()->isSuperAdmin() && in_array($target_user->role, ['superadmin', 'admin'])) {
            abort(403, 'You cannot manage admin users.');
        }
        if (auth()->user()->isFloorManager() && !in_array($target_user->role, ['agent', 'closer'])) {
            abort(403, 'You can only manage agents and closers.');
        }

        $target_user->update(['approval_status' => 'approved']);

        if ($target_user->performer && $target_user->performer->status === 'PENDING') {
            $target_user->performer->update(['status' => 'ACTIVE']);
        }

        \App\Models\SystemLog::log('Approved User', "Approved user: {$target_user->name} ({$target_user->email})");

        return redirect()->back()->with('success', "{$target_user->display_name} is approved and now live.");
    }

    public function myProfile()
    {
        $user = auth()->user();
        $performer = $user->performer;
        return view('admin.my_profile', compact('user', 'performer'));
    }

    public function updateMyProfile(Request $request)
    {
        $user = auth()->user();
        
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,'.$user->id,
            // A face, not a wallpaper: anything bigger than this is somebody
            // uploading a photograph straight off a phone.
            'avatar' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:4096',
        ]);

        $user->update([
            'name' => $request->name,
            'email' => $request->email,
            // An unticked box sends nothing at all, so its absence is the answer.
            'email_notifications' => $request->boolean('email_notifications'),
        ]);

        // Their picture, if they chose one. The old file goes, or a year of
        // changed minds sits in storage forever.
        if ($request->hasFile('avatar')) {
            $previous = $user->avatar;

            $user->update([
                'avatar' => $request->file('avatar')->store('avatars', 'public'),
            ]);

            if ($previous) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($previous);
            }
        }

        // Taking it off again goes back to the letter on their own colour.
        if ($request->boolean('remove_avatar') && $user->avatar) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($user->avatar);
            $user->update(['avatar' => null]);
        }

        // The performer row is reached through users.sales_performer_id. There is no
        // user_id column on sales_performers, so it cannot be looked up by user.
        $performer = $user->performer;

        // Agents and closers belong on the scoreboard, so give them a performer row
        // if they do not have one yet. Everyone else keeps whatever they are linked to.
        if (!$performer && in_array($user->role, ['agent', 'closer'])) {
            $performer = SalesPerformer::create([
                'company_id' => $user->company_id,
                'name' => $request->name,
                'role' => $user->role === 'closer' ? 'Closer' : 'Sales Agent',
                'is_closer' => $user->role === 'closer',
                'goal' => 0,
                'status' => 'ACTIVE',
                'avatar_initials' => strtoupper(mb_substr($request->name, 0, 2)),
            ]);
            $user->update(['sales_performer_id' => $performer->id]);
        }

        // Admins and superadmins are not on the scoreboard and the profile form shows
        // them no performer fields, so there is nothing further to save for them.
        if ($performer) {
            $data = $request->only(['sudo_name']);
            if ($user->isAdmin() || $user->role == 'superadmin') {
                if ($request->has('employee_id_number')) {
                    $data['employee_id_number'] = $request->employee_id_number;
                }
            }
            // The performer name is deliberately left alone. Deals link to a performer
            // by that name (deals.closed_by / closer_by), so renaming it here would
            // detach every past deal and zero the performer's revenue. The scoreboard
            // shows sudo_name when it is set, which is what this form edits.

            // Uploads are stored only once there is a row to attach them to, so a
            // failed save cannot leave orphaned files behind.
            if ($request->hasFile('contract_file')) {
                $data['contract_file'] = $request->file('contract_file')->store('contracts', 'public');
            }
            if ($request->hasFile('id_card_front')) {
                $data['id_card_front'] = $request->file('id_card_front')->store('documents', 'public');
            }
            if ($request->hasFile('id_card_back')) {
                $data['id_card_back'] = $request->file('id_card_back')->store('documents', 'public');
            }
            if ($request->hasFile('police_clearance_certificate')) {
                $data['police_clearance_certificate'] = $request->file('police_clearance_certificate')->store('documents', 'public');
            }

            $performer->update($data);
        }

        return redirect()->back()->with('success', 'Profile updated successfully!');
    }

    public function startRushHour(\Illuminate\Http\Request $request)
    {
        abort_if(!auth()->check() || (!auth()->user()->isAdmin() && !auth()->user()->isCloser()), 403, 'Unauthorized action.');
        
        $companyId = auth()->user()->company_id;
        if (!$companyId) {
            return response()->json(['error' => 'Superadmin cannot start rush hour.'], 403);
        }

        // Set rush hour to end 1 hour from now
        $endTime = now()->addHour();
        $message = $request->input('message') ?: 'ALL HANDS ON DECK';
        
        $data = [
            'end_time' => $endTime,
            'message' => $message
        ];
        
        \Illuminate\Support\Facades\Cache::put("rush_hour_data_{$companyId}", $data, 3600); // cache for 1 hour

        return response()->json(['success' => true, 'end_time' => $endTime->toIso8601String(), 'message' => $message]);
    }

    public function stopRushHour()
    {
        abort_if(!auth()->check() || (!auth()->user()->isAdmin() && !auth()->user()->isCloser()), 403, 'Unauthorized action.');
        
        $companyId = auth()->user()->company_id;
        if (!$companyId) {
            return response()->json(['error' => 'Superadmin cannot stop rush hour.'], 403);
        }

        \Illuminate\Support\Facades\Cache::forget("rush_hour_data_{$companyId}");
        \Illuminate\Support\Facades\Cache::forget("rush_hour_end_{$companyId}");

        return response()->json(['success' => true]);
    }

    public function rushHourStatus($company_slug)
    {
        $company = \App\Models\Company::where('slug', $company_slug)->firstOrFail();
        $companyId = $company->id;
        
        $data = \Illuminate\Support\Facades\Cache::get("rush_hour_data_{$companyId}");
        
        if (!$data) {
            $endTime = \Illuminate\Support\Facades\Cache::get("rush_hour_end_{$companyId}");
            if ($endTime) {
                $data = ['end_time' => $endTime, 'message' => 'ALL HANDS ON DECK'];
            }
        }

        if ($data && isset($data['end_time']) && now()->lessThan($data['end_time'])) {
            return response()->json([
                'active' => true,
                'end_time' => \Carbon\Carbon::parse($data['end_time'])->toIso8601String(),
                'message' => $data['message'] ?? 'ALL HANDS ON DECK'
            ]);
        }

        return response()->json(['active' => false]);
    }

    public function sendNotification(\Illuminate\Http\Request $request)
    {
        abort_if(!auth()->check() || !auth()->user()->isAdmin(), 403, 'Unauthorized action.');

        $request->validate([
            'title' => 'required|string|max:255',
            'message' => 'required|string',
            'type' => 'nullable|string',
            'icon' => 'nullable|string'
        ]);

        $users = \App\Models\User::all();
        
        $data = [
            'title' => $request->title,
            'message' => $request->message,
            'type' => $request->type ?? 'info',
            'icon' => $request->icon ?? 'fas fa-bullhorn'
        ];

        \Illuminate\Support\Facades\Notification::send($users, new \App\Notifications\SystemAnnouncement($data));

        return redirect()->back()->with('success', 'Notification sent to all users successfully!');
    }

    // Companies Management (Superadmin Only)
    public function companies()
    {
        abort_if(!auth()->user()->isSuperAdmin(), 403, 'Unauthorized action.');
        
        $companies = \App\Models\Company::with(['users' => function($query) {
            $query->where('role', 'admin');
        }])->latest()->paginate(20);

        return view('admin.companies', compact('companies'));
    }

    public function storeCompany(Request $request)
    {
        abort_if(!auth()->user()->isSuperAdmin(), 403, 'Unauthorized action.');

        $request->validate([
            'name' => 'required|string|max:255|unique:companies,name',
            'slug' => 'nullable|string|max:255|unique:companies,slug',
            'admin_name' => 'required|string|max:255',
            'admin_email' => 'required|string|email|max:255|unique:users,email',
            'admin_password' => 'required|string|min:8',
        ]);

        $slug = $request->slug ?: \Illuminate\Support\Str::slug($request->name);

        // Make sure slug is unique
        $originalSlug = $slug;
        $count = 1;
        while (\App\Models\Company::where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $count;
            $count++;
        }

        \Illuminate\Support\Facades\DB::transaction(function() use ($request, $slug) {
            $company = \App\Models\Company::create([
                'name' => $request->name,
                'slug' => $slug,
            ]);

            \App\Models\User::create([
                'name' => $request->admin_name,
                'email' => $request->admin_email,
                'password' => \Illuminate\Support\Facades\Hash::make($request->admin_password),
                'role' => 'admin',
                'company_id' => $company->id,
            ]);
        });

        \App\Models\SystemLog::log('Created Company', "Created new company: {$request->name}");

        return redirect()->back()->with('success', 'Company and its Admin created successfully!');
    }

    public function updateCompany(Request $request, $id)
    {
        abort_if(!auth()->user()->isSuperAdmin(), 403, 'Unauthorized action.');

        $company = \App\Models\Company::findOrFail($id);
        
        $request->validate([
            'name' => 'required|string|max:255|unique:companies,name,' . $company->id,
            'slug' => 'nullable|string|max:255|unique:companies,slug,' . $company->id,
            'is_active' => 'nullable|boolean',
            'disable_message' => 'nullable|string',
            'logo' => 'nullable|image|max:2048',
            'dark_logo' => 'nullable|image|max:2048',
            'favicon' => 'nullable|image|max:1024',
            'theme_color' => 'nullable|string|max:7',
            'number_of_breaks' => 'nullable|integer|min:1|max:2',
            'break_1_name' => 'nullable|string|max:50',
            'break_1_start' => 'nullable|date_format:H:i',
            'break_1_end' => 'nullable|date_format:H:i',
            'break_2_name' => 'nullable|string|max:50',
            'break_2_start' => 'nullable|date_format:H:i',
            'break_2_end' => 'nullable|date_format:H:i',
        ]);

        $slug = $request->slug ?: \Illuminate\Support\Str::slug($request->name);

        if ($slug !== $company->slug) {
            $originalSlug = $slug;
            $count = 1;
            while (\App\Models\Company::where('slug', $slug)->where('id', '!=', $company->id)->exists()) {
                $slug = $originalSlug . '-' . $count;
                $count++;
            }
        }

        $updateData = [
            'name' => $request->name,
            'slug' => $slug,
            'is_active' => $request->is_active ?? true,
            'disable_message' => $request->disable_message,
            'theme_color' => $request->theme_color,
        ];

        if ($request->hasFile('logo')) {
            if ($company->logo && file_exists(public_path($company->logo))) {
                @unlink(public_path($company->logo));
            }
            $filename = time() . '_logo_' . $request->file('logo')->getClientOriginalName();
            $request->file('logo')->move(public_path('uploads/company_logos'), $filename);
            $updateData['logo'] = 'uploads/company_logos/' . $filename;
        }

        if ($request->hasFile('dark_logo')) {
            if (!\Illuminate\Support\Facades\Schema::hasColumn('companies', 'dark_logo')) {
                \Illuminate\Support\Facades\Schema::table('companies', function($table) {
                    $table->string('dark_logo')->nullable();
                });
            }
            if ($company->dark_logo && file_exists(public_path($company->dark_logo))) {
                @unlink(public_path($company->dark_logo));
            }
            $filename = time() . '_dark_logo_' . $request->file('dark_logo')->getClientOriginalName();
            $request->file('dark_logo')->move(public_path('uploads/company_logos'), $filename);
            $updateData['dark_logo'] = 'uploads/company_logos/' . $filename;
        }

        if ($request->hasFile('favicon')) {
            if ($company->favicon && file_exists(public_path($company->favicon))) {
                @unlink(public_path($company->favicon));
            }
            $filename = time() . '_favicon_' . $request->file('favicon')->getClientOriginalName();
            $request->file('favicon')->move(public_path('uploads/company_logos'), $filename);
            $updateData['favicon'] = 'uploads/company_logos/' . $filename;
        }

        if (!\Illuminate\Support\Facades\Schema::hasColumn('companies', 'number_of_breaks')) {
            \Illuminate\Support\Facades\Schema::table('companies', function($table) {
                $table->integer('number_of_breaks')->default(2);
                $table->string('break_1_name')->default('1st Half Break');
                $table->string('break_2_name')->default('2nd Half Break');
            });
        }
        
        if (!\Illuminate\Support\Facades\Schema::hasColumn('companies', 'break_1_start')) {
            \Illuminate\Support\Facades\Schema::table('companies', function($table) {
                $table->time('break_1_start')->nullable();
                $table->time('break_1_end')->nullable();
                $table->time('break_2_start')->nullable();
                $table->time('break_2_end')->nullable();
            });
        }
        
        if ($request->has('number_of_breaks')) {
            $updateData['number_of_breaks'] = $request->number_of_breaks;
            $updateData['break_1_name'] = $request->break_1_name ?: '1st Half Break';
            $updateData['break_1_start'] = $request->break_1_start;
            $updateData['break_1_end'] = $request->break_1_end;
            $updateData['break_2_name'] = $request->break_2_name ?: '2nd Half Break';
            $updateData['break_2_start'] = $request->break_2_start;
            $updateData['break_2_end'] = $request->break_2_end;
        }

        $company->update($updateData);

        \App\Models\SystemLog::log('Updated Company', "Updated company: {$company->name}");

        return redirect()->back()->with('success', 'Company updated successfully!');
    }

    public function deleteCompany($id)
    {
        abort_if(!auth()->user()->isSuperAdmin(), 403, 'Unauthorized action.');

        $company = \App\Models\Company::findOrFail($id);
        
        \App\Models\SystemLog::log('Deleted Company', "Deleted company: {$company->name}");
        
        $company->delete();

        return redirect()->back()->with('success', 'Company deleted successfully!');
    }

    /**
     * Put the tick on somebody, or take it off.
     *
     * One route for both, because it is one decision seen from two sides, and
     * because a page that has to work out which of two buttons to draw is a
     * page that can draw the wrong one.
     */
    public function toggleVerified(Request $request, $id)
    {
        $me = auth()->user();
        $user = \App\Models\User::findOrFail($id);

        abort_unless($me->mayVerify($user), 403, 'Unauthorized action.');

        if ($user->isVerified()) {
            $me->unverify($user);
            \App\Models\SystemLog::log('Removed Verification', "Removed the verified mark from {$user->name}");

            return back()->with('success', $user->name . ' is no longer marked verified.');
        }

        $me->verify($user, $request->input('note'));
        \App\Models\SystemLog::log('Verified User', "Marked {$user->name} as verified");

        return back()->with('success', $user->name . ' is now marked verified.');
    }

    public function impersonate($id)
    {
        $user = \App\Models\User::findOrFail($id);

        // The same rule the user list draws the button from, asked again here:
        // a link that is not on the page is still a URL somebody can type.
        if (!auth()->user()->mayImpersonate($user)) {
            abort_if(!auth()->user()->canAccess('users'), 403, 'Unauthorized action.');

            return redirect()->back()->with('error', "You cannot view that account's dashboard.");
        }

        session()->put('impersonate_original_id', auth()->id());
        
        $name = \Illuminate\Support\Facades\Auth::getName();
        session()->put($name, $id);
        session()->put($name.'_hash', $user->getAuthPassword());

        return redirect()->route('admin.dashboard')->with('success', 'You are now impersonating ' . $user->name);
    }

    public function leaveImpersonate()
    {
        if (session()->has('impersonate_original_id')) {
            $originalId = session()->get('impersonate_original_id');
            session()->forget('impersonate_original_id');
            
            $user = \App\Models\User::find($originalId);
            if ($user) {
                $name = \Illuminate\Support\Facades\Auth::getName();
                session()->put($name, $originalId);
                session()->put($name.'_hash', $user->getAuthPassword());
            }
            
            return redirect()->route('admin.users')->with('success', 'You have left impersonation mode.');
        }

        return redirect()->route('admin.dashboard');
    }

    public function deleteLoginHistory($id)
    {
        abort_if(!auth()->user()->isAdmin(), 403, 'Unauthorized action.');
        
        $user = \App\Models\User::findOrFail($id);
        $user->loginLogs()->delete();

        return redirect()->back()->with('success', 'Login history cleared successfully.');
    }
}
