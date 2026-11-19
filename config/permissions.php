<?php

/*
|--------------------------------------------------------------------------
| Section access
|--------------------------------------------------------------------------
|
| Every part of the admin area is listed here once. The list drives three
| things at the same time, so they can never drift apart:
|
|   1. The switches an admin sees when adding or editing a user
|   2. Which links appear in the sidebar
|   3. Which routes the CheckSectionAccess middleware lets through
|
| A user with no saved permissions falls back to the defaults for their role,
| which are the same sections that role could already reach. Saving the form
| stores an explicit list for that user, and from then on the switches decide.
|
| Adding a feature later means adding one entry here. It shows up as a switch
| and starts being enforced without touching the sidebar or the routes.
|
*/

return [

    /*
    | Routes every signed in user can reach, whatever their switches say. These
    | are the landing page, their own profile and password, the notification
    | bell, and the personal clock in / break actions the layout posts to. The
    | controllers behind them already check the user's own role.
    */
    'always_allowed' => [
        'admin.dashboard',
        'admin.my_profile',
        'admin.my_profile.update',
        'admin.password.update',
        // Setting up and proving a fingerprint is something everybody does for
        // their own account, like their profile and their password.
        'admin.devices',
        'admin.devices.*',
        'logout',
        'notifications.*',
        'admin.rush_hour.*',
        'admin.moments',
        'admin.moments.start',
        'admin.moments.stop',
        // Being asked for help finds you wherever you are; asking still needs
        // the section switch.
        'admin.huddle.mine',
        'admin.huddle.share',
        'admin.huddle.answer',
        'admin.huddle.control',
        'admin.huddle.end',
        'admin.huddle.signal',
        'admin.huddle.signals',
        'scoreboard.rush-hour.status',
        'admin.attendance.clock-in',
        'admin.attendance.clock-out',
        'admin.attendance.start-break',
        'admin.attendance.end-break',
        'admin.attendance.appeal-break',
        'admin.attendance.appeal-status',
        // Your own payslip is yours. The controller is what refuses somebody
        // else's, because this is about one person's pay and not about which
        // menu they were given.
        // The bar is on every screen, so the endpoint behind it has to be
        // reachable from every screen. What it returns is still decided person
        // by person inside the controller.
        'admin.search',
        'admin.payslip',
        'admin.impersonate',
        'admin.impersonate.leave',
        'signature.*',

        // Anyone may ask for leave and see their own record and their own targets.
        // The controllers check that they are only touching their own rows.
        'admin.hr.my_leaves',
        'admin.hr.leaves.store',
        'admin.hr.leaves.cancel',
        'admin.hr.my_performance',
        'admin.hr.my_attendance',
        // The dashboard board: everybody reads it, the controller decides who
        // may post to it.
        'admin.announcements.*',
    ],

    /*
    | The sections themselves. "routes" are route name patterns; * matches any
    | ending. "group" only affects how the switches are grouped on the form.
    */
    'sections' => [

        'overview' => [
            'label' => 'Overview',
            'help' => 'The main dashboard with the summary figures',
            'icon' => 'fa-grid-2',
            'group' => 'General',
            'routes' => [],
        ],

        'emails' => [
            'label' => 'Communications (Inbox)',
            'help' => 'Send email to clients and read the reply history',
            'icon' => 'fa-envelope',
            'group' => 'General',
            'routes' => ['admin.emails.*'],
        ],

        'email_templates' => [
            'label' => 'Email Templates',
            'help' => 'Create and edit the reusable email templates',
            'icon' => 'fa-file-alt',
            'group' => 'General',
            'routes' => ['admin.email_templates', 'admin.email_templates.*'],
        ],

        'viewrec' => [
            'label' => 'Viewrec Signups',
            'help' => 'The free signups the floor is asked for five of a day',
            'icon' => 'fa-user-check',
            'group' => 'Sales',
            'routes' => ['admin.viewrec', 'admin.viewrec.*'],
        ],

        'training' => [
            'label' => 'Training',
            'help' => 'Recordings, voice notes and written training for the team',
            'icon' => 'fa-graduation-cap',
            'group' => 'General',
            'routes' => ['admin.training', 'admin.training.*'],
        ],

        'wallet' => [
            'label' => 'My Wallet',
            'help' => 'What they have earned: salary, incentive, and what was held back',
            'icon' => 'fa-wallet',
            'group' => 'General',
            'routes' => ['admin.wallet'],
        ],

        'netflix' => [
            'label' => 'Netflix',
            'help' => 'Put something on during a break',
            'icon' => 'fa-film',
            'group' => 'General',
            'routes' => ['admin.netflix'],
        ],

        'games' => [
            'label' => 'Games',
            'help' => 'A few minutes of something that is not a call',
            'icon' => 'fa-gamepad',
            'group' => 'General',
            'routes' => ['admin.games', 'admin.games.*'],
        ],

        'huddle' => [
            'label' => 'Quick Huddle',
            'help' => 'Ask a colleague to show you their screen, and help them in it',
            'icon' => 'fa-display',
            'group' => 'General',
            'routes' => ['admin.huddle', 'admin.huddle.*'],
        ],

        'chat' => [
            'label' => 'Team Chat',
            'help' => 'Internal chat with colleagues',
            'icon' => 'fa-comments',
            'group' => 'General',
            'routes' => ['chat.*'],
        ],

        'deals' => [
            'label' => 'Deals & Activity',
            'help' => 'Log deals, edit them and send proposals',
            'icon' => 'fa-handshake',
            'group' => 'Sales',
            'routes' => ['admin.deals', 'admin.deals.*', 'admin.docusign.*'],
        ],

        'leads' => [
            'label' => 'Leads Management',
            'help' => 'The lead list and its records',
            'icon' => 'fa-user-plus',
            'group' => 'Sales',
            'routes' => ['admin.leads', 'admin.leads.*'],
        ],

        'transferred_leads' => [
            'label' => 'Transferred Leads',
            'help' => 'Leads handed from an agent to a closer, with reminders',
            'icon' => 'fa-exchange-alt',
            'group' => 'Sales',
            'routes' => ['admin.transferred_leads', 'admin.transferred_leads.*'],
        ],

        'sales_history' => [
            'label' => 'Sales History & Team',
            'help' => 'Past performance and the sales team members',
            'icon' => 'fa-chart-line',
            'group' => 'Sales',
            'routes' => ['admin.history', 'admin.history.*', 'admin.team', 'admin.performers.*'],
        ],

        'clients' => [
            'label' => 'Client Database',
            'help' => 'Client lists by status, and the sheet view',
            'icon' => 'fa-address-book',
            'group' => 'Support',
            'routes' => ['support.clients', 'support.client.profile', 'support.sheet', 'support.sheet.save'],
        ],

        'support' => [
            'label' => 'Support Dashboard',
            'help' => 'Support tickets and their notes',
            'icon' => 'fa-headset',
            'group' => 'Support',
            'routes' => ['support.dashboard', 'support.show', 'support.addNote', 'support.updateStatus', 'support.updateClient', 'support.updateDealStatus'],
        ],

        'attendance' => [
            'label' => 'Attendance (staff report)',
            'help' => 'Everyone\'s clock in records, exports and break controls. A user can always clock themselves in without this.',
            'icon' => 'fa-clock',
            'group' => 'Support',
            'routes' => ['admin.attendance', 'admin.attendance.export', 'admin.attendance.print', 'admin.attendance.salary-rules', 'admin.attendance.salary.*', 'admin.attendance.active-overtimes', 'admin.attendance.pending-appeals', 'admin.attendance.appeal.*', 'admin.attendance.force-*', 'admin.attendance.*-global-break', 'admin.attendance.global-breaks-control'],
        ],

        'hr_dashboard' => [
            'label' => 'HR Dashboard',
            'help' => 'Today at a glance: who is in, who is late, who is on leave, and what is waiting on a decision',
            'icon' => 'fa-gauge-high',
            'group' => 'HR',
            'routes' => ['admin.hr.dashboard'],
        ],

        'hr_employees' => [
            'label' => 'Employee Records (HR)',
            'help' => 'Personnel files: contact details, documents, probation and salary',
            'icon' => 'fa-id-card',
            'group' => 'HR',
            'routes' => ['admin.hr.employees', 'admin.hr.employees.*', 'admin.hr.employee', 'admin.hr.employee.*'],
        ],

        'hr_leaves' => [
            'label' => 'Leave Management (HR)',
            'help' => 'Approve leave, set leave types and quotas. Everyone can ask for leave without this.',
            'icon' => 'fa-calendar-check',
            'group' => 'HR',
            'routes' => ['admin.hr.leaves', 'admin.hr.leaves.decide', 'admin.hr.leaves.destroy', 'admin.hr.leave_types.*'],
        ],

        'hr_work_record' => [
            'label' => 'Team Work Record',
            'help' => 'Transfers given and taken, deals won and days in — per person and day by day',
            'icon' => 'fa-chart-column',
            'group' => 'HR',
            'routes' => ['admin.hr.work_record', 'admin.hr.work_record.*'],
        ],

        'hr_reports' => [
            'label' => 'Attendance Reports',
            'help' => 'The month as a sheet, a PDF or a CSV, with the columns chosen at download',
            'icon' => 'fa-file-lines',
            'group' => 'HR',
            'routes' => ['admin.hr.reports', 'admin.hr.reports.*'],
        ],

        'hr_settings' => [
            'label' => 'HR Settings',
            'help' => 'Shifts, working days, holidays, departments and the attendance rules everything is judged by',
            'icon' => 'fa-sliders',
            'group' => 'HR',
            'routes' => ['admin.hr.settings', 'admin.hr.settings.*'],
        ],

        'hr_performance' => [
            'label' => 'Job Descriptions & KPIs (HR)',
            'help' => 'What each person does and the targets they are measured on',
            'icon' => 'fa-bullseye',
            'group' => 'HR',
            'routes' => ['admin.hr.performance', 'admin.hr.jd.*', 'admin.hr.kpi.*'],
        ],

        'moments' => [
            'label' => 'Floor Decorum',
            'help' => 'Rush hour, breaks and prayer time — what they are called and how long they run',
            'icon' => 'fa-bullhorn',
            'group' => 'Scoreboard',
            'routes' => ['admin.moments.settings', 'admin.moments.store', 'admin.moments.update', 'admin.moments.destroy'],
        ],

        'scoreboard_settings' => [
            'label' => 'Scoreboard Content',
            'help' => 'News headlines, business categories and states shown on the public board',
            'icon' => 'fa-newspaper',
            'group' => 'Scoreboard',
            'routes' => ['admin.headlines', 'admin.headlines.*', 'admin.categories', 'admin.categories.*', 'admin.usa_states', 'admin.usa_states.*'],
        ],

        'scoreboard_display' => [
            'label' => 'Scoreboard Display & Branding',
            'help' => 'Targets, logo, colours and break times',
            'icon' => 'fa-tv',
            'group' => 'Scoreboard',
            'routes' => ['admin.settings', 'admin.settings.branding.update', 'admin.stats.update'],
        ],

        'users' => [
            'label' => 'User Management',
            'help' => 'Add staff, set their role and decide what they can reach',
            'icon' => 'fa-user-shield',
            'group' => 'Administration',
            // The bin belongs to whoever may delete: the two are the same
            // decision seen a minute apart.
            'routes' => ['admin.users', 'admin.users.*', 'admin.bin', 'admin.bin.*'],
        ],

        'companies' => [
            'label' => 'Companies',
            'help' => 'Other companies on the system. Owner level.',
            'icon' => 'fa-building',
            'group' => 'Administration',
            'routes' => ['admin.companies', 'admin.companies.*'],
        ],

        'system_logs' => [
            'label' => 'System Logs',
            'help' => 'Record of who changed what. Owner level.',
            'icon' => 'fa-list-alt',
            'group' => 'Administration',
            'routes' => ['admin.logs'],
        ],

        'global_settings' => [
            'label' => 'Global Settings',
            'help' => 'Maintenance mode and system wide branding. Owner level.',
            'icon' => 'fa-globe',
            'group' => 'Administration',
            'routes' => ['admin.settings.global', 'admin.settings.global.update'],
        ],
    ],

    /*
    | Sections only the owner account should ever hold. They stay off the form
    | for everyone else so a company admin cannot grant themselves the keys to
    | the whole system.
    */
    'superadmin_only' => ['companies', 'system_logs', 'global_settings'],

    /*
    | What each role can reach when nothing has been set for the user. These
    | match what the sidebar already showed each role before the switches
    | existed, so nobody's access changes until an admin edits it.
    */
    'role_defaults' => [

        'superadmin' => ['*'],

        'admin' => [
            'wallet', 'moments', 'netflix', 'games', 'huddle', 'viewrec', 'overview', 'emails', 'email_templates', 'chat', 'training', 'deals', 'leads',
            'transferred_leads', 'sales_history', 'clients', 'support',
            'attendance', 'hr_dashboard', 'hr_employees', 'hr_leaves', 'hr_performance', 'hr_reports', 'hr_work_record', 'hr_settings',
            'scoreboard_settings', 'scoreboard_display', 'users',
        ],

        // HR runs the personnel side and needs the attendance it is all based on,
        // but has no reason to see deals, leads or client money.
        'hr' => [
            'wallet', 'netflix', 'games', 'huddle', 'viewrec', 'overview', 'chat', 'training', 'emails', 'attendance',
            'hr_dashboard', 'hr_employees', 'hr_leaves', 'hr_performance', 'hr_reports', 'hr_work_record', 'hr_settings', 'users',
        ],

        // A team lead answers for their own department: they approve its leave at
        // the first step and can see its attendance, and nothing beyond that.
        'team_lead' => [
            'wallet', 'netflix', 'games', 'huddle', 'viewrec', 'overview', 'chat', 'training', 'emails', 'attendance', 'hr_dashboard', 'hr_leaves', 'hr_work_record',
        ],

        // floor_manager passes the isAdmin() check in this codebase, so it has
        // always reached the same screens as an admin. Kept as is; an admin can
        // now narrow it per person with the switches.
        'floor_manager' => [
            'wallet', 'moments', 'netflix', 'games', 'huddle', 'viewrec', 'overview', 'emails', 'email_templates', 'chat', 'training', 'deals', 'leads',
            'transferred_leads', 'sales_history', 'clients', 'support',
            'attendance', 'scoreboard_settings', 'scoreboard_display', 'users',
        ],

        'editor' => [
            'overview', 'emails', 'chat', 'training', 'deals', 'leads', 'sales_history',
            'scoreboard_settings',
        ],

        // An agent is the one making the calls, so the calling list is theirs to
        // keep: capture a business, record how the call went, and hand on the
        // ones worth handing on.
        'agent' => [
            'wallet', 'netflix', 'games', 'huddle', 'viewrec', 'overview', 'emails', 'chat', 'training', 'deals', 'leads', 'transferred_leads',
        ],

        'closer' => [
            'wallet', 'netflix', 'games', 'huddle', 'viewrec', 'overview', 'emails', 'chat', 'training', 'deals', 'transferred_leads',
        ],

        'support_manager' => [
            'wallet', 'netflix', 'games', 'huddle', 'emails', 'chat', 'clients', 'support',
        ],

        'support_agent' => [
            'wallet', 'netflix', 'games', 'huddle', 'emails', 'chat', 'support',
        ],

        'qa' => [
            'overview', 'chat', 'deals',
        ],
    ],
];
