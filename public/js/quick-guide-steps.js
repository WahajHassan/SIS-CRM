/*
 * What each Quick Guide says.
 *
 * Pure data — the engine in quick-guide.js does the work. Adding a guide for a
 * new section is one more entry here and one more case in the layout's section
 * mapping; no new component, no new markup.
 *
 * Every `sel` points at something the page already renders. `text` narrows a
 * selector to the element carrying that label, which is how buttons are targeted
 * without adding ids to the Blade templates. Steps whose element is absent are
 * dropped before the guide opens, so a guide automatically shrinks to whatever
 * the current user's role actually shows them — and never describes a control
 * they do not have.
 */
(function (window) {
    'use strict';

    if (!window.QuickGuide) return;

    var G = window.QuickGuide;

    // The sidebar and header exist on every admin page, so these steps are
    // shared rather than repeated per section.
    var NAV_STEP = {
        sel: '.sidebar .nav-links',
        title: 'Your menu',
        text: 'Everything you have access to lives here. Drag any item up or down to put the sections you use most at the top — the order is remembered.',
        radius: 18
    };

    G.register('overview', {
        steps: [
            {
                sel: '.page-title h1',
                title: 'Welcome to your Overview',
                text: 'This is the home screen: a running summary of how the business is doing right now.'
            },
            {
                // Agents only; for everybody else the step is dropped because
                // the row is not on their page.
                sel: '.inc-row',
                title: 'What you are working towards',
                text: 'Your target for the month, the two sales bounties, the three-month streak and the fuel allowance. Each one fills up as your sales land.',
                radius: 18
            },
            {
                sel: '.stat-card-grid',
                title: 'Your key numbers',
                text: 'Revenue, deals, leads and conversion at a glance. Drag any card to a new position — your arrangement is saved for next time.',
                radius: 20
            },
            {
                sel: '#salesGrowthChart',
                title: 'Trends over time',
                text: 'The charts show how sales are moving month to month, and how much of your pipeline is converting.'
            },
            {
                sel: '#toggleRushHourBtn',
                title: 'Rush Hour',
                text: 'Start a rush hour to push a live message to the team scoreboard. Type a custom note beside it first if you want.'
            },
            NAV_STEP
        ]
    });

    G.register('rush-hours', {
        steps: [
            {
                sel: '#rushHourMsg',
                title: 'Set the message',
                text: 'Optional. Whatever you type here is what the team sees on the scoreboard during the rush hour.'
            },
            {
                sel: '#toggleRushHourBtn',
                title: 'Start and stop',
                text: 'One click starts the rush hour; the same button ends it. The scoreboard updates for everyone straight away.'
            },
            {
                sel: '.btn',
                match: 'Announcement',
                title: 'Announcements',
                text: 'Send a one-off announcement to the whole team without starting a rush hour.'
            },
            {
                sel: '.notif-bell, #notifBtn',
                title: 'Keeping track',
                text: 'Notifications for rush hours, break requests and approvals arrive here.'
            }
        ]
    });

    /*
     * Talk with Agent. Not a page, so it is not in the layout's section map —
     * it opens the first time someone actually opens the panel (see the layout's
     * quick-guide wiring), which is the only moment these elements exist on
     * screen to point at.
     *
     * The wording says what the panel does today: it is a UI shell with no
     * backend behind it, and a guide that promised answers it cannot give would
     * be worse than no guide.
     */
    G.register('assistant', {
        steps: [
            {
                sel: '.ai-panel-header',
                title: 'Talk with Agent',
                text: 'Your assistant panel. Open it any time from the coloured orb in the bottom-right corner of any page.',
                radius: 12
            },
            {
                sel: '.ai-panel-stage',
                title: 'The assistant',
                text: 'This is where replies will appear. As soon as you send something the orb steps aside and the panel becomes a normal conversation.',
                radius: 16
            },
            {
                sel: '#aiChatInput',
                title: 'Ask something',
                text: 'Type here and press Enter. Note that the assistant is not connected to a backend yet, so your message is shown but nothing answers it.',
                radius: 12
            },
            {
                sel: '#aiChatSend',
                title: 'Send',
                text: 'Send with this button or the Enter key. Close the panel with the × in its header when you are done.',
                radius: 12
            }
        ]
    });

    G.register('sales', {
        steps: [
            {
                sel: '.page-title h1',
                title: 'Welcome to Sales',
                text: 'Your sales team and their results live in this section.'
            },
            {
                sel: 'table',
                title: 'The team and their numbers',
                text: 'Each row is a team member with their goal, transfers, sales, revenue and conversion rate for the month.',
                radius: 12
            },
            {
                sel: '.btn',
                match: 'Add New Member',
                title: 'Adding someone',
                text: 'Add a team member here. You can set their monthly target, role and documents at the same time.'
            },
            {
                sel: '.fa-edit',
                title: 'Editing a member',
                text: 'The pencil opens that person\'s details right under their row, so you can see who you are editing.',
                radius: 10
            },
            NAV_STEP
        ]
    });

    G.register('users', {
        steps: [
            {
                sel: '.page-title h1',
                title: 'User Management',
                text: 'Accounts, roles and what each person is allowed to open.'
            },
            {
                sel: 'table',
                title: 'Everyone with an account',
                text: 'Each row is a login. The role decides what that person sees by default.',
                radius: 12
            },
            {
                sel: '.btn',
                match: 'Add User',
                title: 'Creating an account',
                text: 'Add a user, pick a role, and the sections that role normally needs are ticked for you.'
            },
            {
                sel: 'table',
                title: 'Fine-grained access',
                text: 'Editing a user opens "What can this person open?" — a switch per section. Off means the menu item is hidden and the address is refused, not just hidden.',
                radius: 12
            },
            NAV_STEP
        ]
    });

    G.register('communications', {
        steps: [
            {
                sel: '.page-title h1',
                title: 'Welcome to Communications',
                text: 'Email to and from your clients, kept alongside the rest of the CRM.'
            },
            {
                sel: 'table, .card',
                title: 'Your messages',
                text: 'Incoming and sent mail appears here, tied to the client it belongs to.',
                radius: 16
            },
            {
                sel: '.btn',
                match: 'Compose',
                title: 'Writing a message',
                text: 'Compose opens a rich text editor with your saved templates ready to drop in.'
            },
            NAV_STEP
        ]
    });

    G.register('queries', {
        steps: [
            {
                sel: '.page-title h1',
                title: "Client's Queries",
                text: 'Your client list, split by where each one currently stands.'
            },
            {
                sel: 'table',
                title: 'The client list',
                text: 'Active, on hold, charge backs, case studies, deactivated and win-backs each have their own view in the menu.',
                radius: 12
            },
            {
                sel: 'input[type="search"], input[placeholder*="Search" i]',
                title: 'Finding someone',
                text: 'Search by name to jump straight to a client.'
            },
            NAV_STEP
        ]
    });

    G.register('leads', {
        steps: [
            {
                sel: '.page-title h1',
                title: 'Leads Management',
                text: 'Everything that has come in but has not yet become a deal.'
            },
            {
                sel: 'table',
                title: 'Your leads',
                text: 'Each row is a lead with its source and current status. Work down the list and update as you go.',
                radius: 12
            },
            {
                sel: '.btn',
                match: 'Add',
                title: 'Adding a lead',
                text: 'Add leads by hand here when they arrive outside the usual channels.'
            },
            NAV_STEP
        ]
    });

    G.register('deals', {
        steps: [
            {
                sel: '.page-title h1',
                title: 'Deals & Activity',
                text: 'Closed and in-progress deals, with the activity attached to each one.'
            },
            {
                sel: 'table',
                title: 'The deal list',
                text: 'Each row carries the client, its value and whether it is won, lost or still open.',
                radius: 12
            },
            {
                sel: '.btn',
                match: 'Add Deal',
                title: 'Logging a deal',
                text: 'Record a new deal along with its documents and payment details.'
            },
            NAV_STEP
        ]
    });

    G.register('attendance', {
        steps: [
            {
                sel: '.page-title h1',
                title: 'Attendance',
                text: 'Who clocked in, when they took breaks, and who is working right now.'
            },
            {
                sel: 'table',
                title: 'The daily record',
                text: 'Clock-ins, breaks and clock-outs per person per day. Anyone who forgets to clock out is closed off automatically.',
                radius: 12
            },
            {
                sel: 'input[type="date"], select',
                title: 'Changing the view',
                text: 'Pick a date or a person to narrow the record down.'
            },
            {
                sel: '#salarySection',
                title: 'Salary for the month',
                text: 'Days present, days off and lateness from the record above, turned into what each person is owed. Type a monthly salary in the table to set it.',
                radius: 14
            },
            {
                sel: '#salarySection .pay-tools',
                title: 'Month, export and the rules',
                text: 'Switch months, download the sheet for whoever pays, or set the shift time, grace period and weekly offs the figures are counted by.',
                radius: 12
            },
            NAV_STEP
        ]
    });

    G.register('hr-dashboard', {
        steps: [
            {
                sel: '.page-title h1',
                title: 'HR Dashboard',
                text: 'Today at a glance. Every figure here is read off the attendance already recorded, so it always agrees with what is on an employee file.'
            },
            {
                sel: '.hd-cards',
                title: 'Who is in',
                text: 'Present, late, absent, on leave, early off, and who is simply off today.',
                radius: 16
            },
            {
                sel: '.hd-filters',
                title: 'Narrow it down',
                text: 'Any day, one department, one shift, or one person. The leave figures below take their own date range.',
                radius: 12
            },
            {
                sel: '.hd-split',
                title: 'Department and shift',
                text: 'The same day split two ways, so you can see whether it is one team or one shift that is short.',
                radius: 14
            },
            {
                sel: 'table',
                title: 'Decide without leaving',
                text: 'Leave waiting on you is listed here with Approve and Reject on the row.',
                radius: 12
            },
            NAV_STEP
        ]
    });

    G.register('my-attendance', {
        steps: [
            {
                sel: '.ma-cards',
                title: 'Your month',
                text: 'Days present, absences, leave and hours — and how many lates you have this month.',
                radius: 16
            },
            {
                sel: '.ma-grid',
                title: 'Day by day',
                text: 'Green is a day worked, amber late or short, red an absence, blue an approved leave, grey a day off. A small L marks a late arrival.',
                radius: 14
            },
            {
                sel: '.ma-quota',
                title: 'What is left',
                text: 'Your allowance for each kind of leave, and how much of it you have spent.',
                radius: 14
            },
            NAV_STEP
        ]
    });

    G.register('hr-employees', {
        steps: [
            {
                sel: '.page-title h1',
                title: 'Employee Records',
                text: 'Every person on the team, with the personnel file behind their CRM login. Adding a login in User Management puts them here automatically.'
            },
            {
                sel: '.hr-stats',
                title: 'Where the team stands',
                text: 'How many are permanent, how many are still on probation, and how many files are still missing details.',
                radius: 16
            },
            {
                sel: '.hr-filters',
                title: 'Find someone',
                text: 'Search by name, email, designation or ID card number, or narrow the list to one department.',
                radius: 12
            },
            {
                sel: 'table tbody tr',
                title: 'The file bar',
                text: 'The bar on each row shows how much of that person\'s file is filled in. Open the file to complete it.',
                radius: 10
            },
            {
                sel: 'button',
                match: 'Onboard new employee',
                title: 'Hiring somebody',
                text: 'This makes their login, their personnel file and the starting targets for their role in one go — no separate trip to User Management.',
                radius: 10
            },
            {
                sel: 'a',
                match: 'Open file',
                title: 'One person, one page',
                text: 'Their details and documents, this month\'s attendance and salary, their leave, and what they are measured on — all in one place.',
                radius: 10
            },
            NAV_STEP
        ]
    });

    G.register('hr-employee-file', {
        steps: [
            {
                sel: '.ep-cards',
                title: 'Their month so far',
                text: 'Days present, absent, on leave and late, hours on the clock, and what that adds up to in pay. Change the month with the box above.',
                radius: 16
            },
            {
                sel: '.cal-grid',
                title: 'Every day of the month',
                text: 'Green is a day worked, amber a half or short day, red an absence, blue an approved leave, grey a weekly off. A small L marks a late arrival.',
                radius: 14
            },
            {
                sel: '.cal-day',
                title: 'Open any day',
                text: 'Click a day for the detail: the exact clock in and out, and every break with the minute they left and came back. Missed clock-outs can be corrected there too, and the salary follows immediately.',
                radius: 10
            },
            {
                sel: '.card',
                match: 'Last 12 months',
                title: 'The year behind them',
                text: 'A row per month with days worked, missed and paid. Open any month to see that month\'s calendar.',
                radius: 16
            },
            {
                sel: '.ep-files',
                title: 'Their papers',
                text: 'CNIC, photo and CV sit here; degrees and anything else go in the documents list below.',
                radius: 14
            },
            NAV_STEP
        ]
    });

    G.register('hr-settings', {
        steps: [
            {
                sel: '.page-title h1',
                title: 'HR Settings',
                text: 'How this office works. Nothing here is fixed in the software — change a number and every attendance figure follows it.'
            },
            {
                sel: '.hs-tabs',
                title: 'Five questions',
                text: 'The rules, the shifts, who works which shift, the working days and holidays, and the departments.',
                radius: 999
            },
            {
                sel: '.hs-days',
                title: 'The working week',
                text: 'Tick the days nobody is expected in. A one-off exception for one person is set under Working Days & Holidays.',
                radius: 12
            },
            {
                sel: '#earlyCheckin',
                title: 'The night-shift rule',
                text: 'Clocking in this long before a shift starts still counts as that shift, which is what keeps a 7 PM start from being read as the day before.',
                radius: 10
            },
            {
                sel: '#latesPerOff',
                title: 'Lates',
                text: 'How many lates cost one off, and whether that off comes out of Casual Leave or is simply unpaid. Counted per month.',
                radius: 10
            },
            NAV_STEP
        ]
    });

    G.register('hr-leaves', {
        steps: [
            {
                sel: '.page-title h1',
                title: 'Leave Management',
                text: 'Leave lives here rather than in a notebook, because approved leave changes what someone is paid.'
            },
            {
                sel: '.lv-stats',
                title: 'What needs you',
                text: 'Requests waiting on a decision are counted first — that is the only part of this page holding somebody up.',
                radius: 16
            },
            {
                sel: '.lv-form',
                title: 'Record a leave',
                text: 'File one for anybody. Tick "approve straight away" when you are recording leave that was already agreed. Weekly offs inside the dates are not charged to their quota.',
                radius: 12
            },
            {
                sel: 'table',
                title: 'Approve or reject',
                text: 'Approve and reject sit on the row itself. An approved paid leave stops counting as absence on the salary sheet; an unpaid one is treated like an absence.',
                radius: 12
            },
            {
                sel: '#ltName',
                title: 'Your kinds of leave',
                text: 'Set the yearly quota for each kind, and whether it is paid. That quota is what everyone sees on their own leave page.',
                radius: 10
            },
            NAV_STEP
        ]
    });

    G.register('hr-performance', {
        steps: [
            {
                sel: '.page-title h1',
                title: 'Job Descriptions & KPIs',
                text: 'What each person is here to do, and the numbers they are measured on this month.'
            },
            {
                sel: '.kp-stats',
                title: 'Coverage at a glance',
                text: 'Who has a written job description, who has targets this month, and who is falling behind them.',
                radius: 16
            },
            {
                sel: 'table tbody tr',
                title: 'Edit in the row',
                text: 'Open a person and the panel appears in place, below their row. The job description is on the left, their targets on the right.',
                radius: 10
            },
            {
                sel: 'select[name="month"]',
                title: 'Which month',
                text: 'Targets belong to a month. Switch months to set next month\'s numbers or look back at what was achieved.',
                radius: 10
            },
            NAV_STEP
        ]
    });

    G.register('support', {
        steps: [
            {
                sel: '.page-title h1',
                title: 'Support Dashboard',
                text: 'Where client issues are picked up and worked through.'
            },
            {
                sel: '.card',
                title: 'What needs attention',
                text: 'Open items are summarised here so nothing sits unanswered.',
                radius: 20
            },
            {
                sel: 'table',
                title: 'The queue',
                text: 'Open a row to see the full history for that client and log what you did.',
                radius: 12
            },
            NAV_STEP
        ]
    });
})(window);
