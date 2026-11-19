<?php

/*
|--------------------------------------------------------------------------
| Sales incentives
|--------------------------------------------------------------------------
|
| What an agent is working towards, shown on their own Overview. The figures
| live here rather than in the page so changing a bounty is one edit in one
| place, and every screen that reads them changes together.
|
| Sales figures are in dollars, because that is what deals are logged in.
| Bounties are in rupees, because that is what they are paid in.
|
*/

return [

    // The monthly sales an agent is expected to reach.
    'monthly_target' => 500,

    /*
    | Bounties earned by passing a sales figure in a single month. Listed in
    | order; each one is shown as its own block on the dashboard.
    */
    'bounties' => [
        [
            'above' => 1000,
            'reward' => 10000,
            'label' => 'Sale above $1,000',
            'note' => 'PKR 10,000 bounty',
        ],
        [
            'above' => 2500,
            'reward' => 20000,
            'label' => 'Sale above $2,500',
            'note' => 'PKR 20,000 bounty',
        ],
    ],

    /*
    | Hitting the monthly target this many months running earns a salary rise
    | and a one-off bounty.
    */
    'consistency' => [
        'months' => 3,
        'increment' => 5000,          // dollars added to the monthly salary
        'reward' => 10000,            // rupees, paid once
        'label' => 'Target 3 months running',
        'note' => '$5,000 salary increment + PKR 10,000',
    ],

    /*
    | Paid for the month once the target is reached.
    */
    'fuel_allowance' => [
        'reward' => 10000,
        'label' => 'Fuel allowance',
        'note' => 'PKR 10,000 on hitting target',
    ],
];
