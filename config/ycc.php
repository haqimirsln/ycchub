<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Activity status rules
    |--------------------------------------------------------------------------
    | Days are "counted days": calendar days minus any days that fall inside an
    | activity pause (semester break etc.) set by an admin.
    |
    |  new       joined within grace_days and has not attended anything yet
    |  active    last attendance <= active_days ago
    |  at_risk   last attendance between active_days+1 and at_risk_days ago
    |  inactive  last attendance > at_risk_days ago, or never attended after grace
    */
    'activity' => [
        'active_days' => (int) env('YCC_ACTIVE_DAYS', 60),
        'at_risk_days' => (int) env('YCC_AT_RISK_DAYS', 90),
        'grace_days' => (int) env('YCC_GRACE_DAYS', 60),
    ],

    /*
    | How long after a program starts excos can still change attendance.
    */
    'attendance_lock_days' => (int) env('YCC_ATTENDANCE_LOCK_DAYS', 14),

    'program_categories' => [
        'beach_cleanup' => 'Beach Cleanup',
        'turtle_hatchery' => 'Turtle & Hatchery',
        'coral_marine' => 'Coral & Marine Life',
        'restoration' => 'Habitat Restoration',
        'talk' => 'Talk / Sharing Session',
        'workshop' => 'Workshop',
        'outreach' => 'Outreach & Awareness',
        'meeting' => 'Club Meeting',
        'other' => 'Other',
    ],

    // Old faculty codes, kept so accounts saved before faculty became free text still show the full name.
    'faculties' => [
        'FSSM' => 'Faculty of Science and Marine Environment',
        'FTKK' => 'Faculty of Ocean Engineering Technology',
        'FSKM' => 'Faculty of Computer Science and Mathematics',
        'FSPA' => 'Faculty of Science Fisheries and Aquaculture',
        'FSMA' => 'Faculty of Food Science and Agrotechnology',
        'FBESD' => 'Faculty of Business, Economics and Social Development',
        'FPM' => 'Faculty of Maritime Studies',
    ],

    // Suggestions shown while typing a faculty. Members can type anything,
    // because UMT faculty names change over time.
    'faculty_suggestions' => [
        'Faculty of Science and Marine Environment',
        'Faculty of Ocean Engineering Technology',
        'Faculty of Computer Science and Mathematics',
        'Faculty of Science Fisheries and Aquaculture',
        'Faculty of Food Science and Agrotechnology',
        'Faculty of Business, Economics and Social Development',
        'Faculty of Maritime Studies',
        'STEM Foundation',
    ],

    // YCC's four teams. Members pick one when they register.
    'teams' => [
        'volunteerism' => 'Volunteerism Team',
        'entrepreneurship' => 'Entrepreneurship Team',
        'leadership' => 'Leadership Team',
        'multimedia' => 'Multimedia Team',
    ],

    // MT positions, in the order they're listed. 'team' links a Ketua to the team they lead;
    // 'contact' tells members what to message that person about.
    'positions' => [
        'presiden' => ['label' => 'Presiden', 'team' => null, 'contact' => 'Anything about YCC, partnerships and where the club is heading'],
        'timbalan_presiden' => ['label' => 'Timbalan Presiden', 'team' => null, 'contact' => 'Helps the Presiden run the club'],
        'naib_presiden' => ['label' => 'Naib Presiden', 'team' => null, 'contact' => 'Club activities and coordination'],
        'setiausaha' => ['label' => 'Setiausaha', 'team' => null, 'contact' => 'Letters, meetings, membership and records'],
        'timbalan_setiausaha' => ['label' => 'Timbalan Setiausaha', 'team' => null, 'contact' => 'Helps with letters, minutes and records'],
        'bendahari' => ['label' => 'Bendahari', 'team' => null, 'contact' => 'Payments, fees, claims and receipts'],
        'ketua_kepimpinan' => ['label' => 'Ketua Kepimpinan', 'team' => 'leadership', 'contact' => 'Leadership Team, running events and training'],
        'ketua_sukarelawan' => ['label' => 'Ketua Sukarelawan', 'team' => 'volunteerism', 'contact' => 'Volunteering, cleanups, hatcheries and fieldwork'],
        'ketua_multimedia' => ['label' => 'Ketua Multimedia', 'team' => 'multimedia', 'contact' => 'Photos, videos, posters and social media'],
        'ketua_keusahawanan' => ['label' => 'Ketua Keusahawanan', 'team' => 'entrepreneurship', 'contact' => 'Merch, fundraising and sponsors'],
    ],

    // One line on what each team does, shown when members pick a team.
    'team_blurbs' => [
        'volunteerism' => 'Cleanups, hatcheries and fieldwork',
        'entrepreneurship' => 'Fundraising, merch and partners',
        'leadership' => 'Running the club and its events',
        'multimedia' => 'Photos, video, design and socials',
    ],

    // Merit for normal members. Admins can change these in Settings; these are the starting values.
    'merit' => [
        'attend' => 5,            // each event attended
        'break_bonus' => 5,       // extra for an event held during a semester break
        'committee' => 5,         // serving as AJK for an event
        'no_show' => 0,           // demerit for registering and not turning up (0 = off)
        'milestones' => [5 => 10, 10 => 20, 20 => 30], // events attended => bonus
    ],


    'faculty_short' => [
        'FSSM' => 'Marine Science',
        'FTKK' => 'Ocean Engineering',
        'FSKM' => 'Computing & Maths',
        'FSPA' => 'Fisheries',
        'FSMA' => 'Food & Agrotech',
        'FBESD' => 'Business & Econ.',
        'FPM' => 'Maritime Studies',
    ],

    'social' => [
        'instagram_handle' => env('YCC_INSTAGRAM_HANDLE', 'young.conservationist'),
        'instagram_url' => env('YCC_INSTAGRAM_URL', 'https://www.instagram.com/young.conservationist/'),
        'linkedin_url' => env('YCC_LINKEDIN_URL', 'https://www.linkedin.com/company/young-conservationist-club/'),
    ],

    // Set in .env only while running the one-time web installer (/install), then remove it.
    'install_key' => env('INSTALL_KEY'),

    'instagram' => [
        // Long-lived token from the Instagram API with Instagram Login.
        // Admins can also paste/replace it from Admin > Settings (stored in the settings table, which wins).
        'access_token' => env('INSTAGRAM_ACCESS_TOKEN'),
        'graph_url' => env('INSTAGRAM_GRAPH_URL', 'https://graph.instagram.com'),
        'post_limit' => 12,
    ],
];
